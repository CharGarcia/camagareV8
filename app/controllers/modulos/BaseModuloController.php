<?php
/**
 * BaseModuloController - Clase base para todos los controladores bajo modulos/.
 *
 * Convención de uso:
 *   1. Extender esta clase en lugar de Controller directamente.
 *   2. Implementar getRutaModulo() retornando la ruta MVC sin slash inicial (ej: 'modulos/clientes').
 *   3. La ruta MVC debe estar registrada en config/modulos_mvc.php con sus legacy_rutas.
 *   4. El submodulo correspondiente en submodulos_menu debe tener esa misma ruta en el campo 'ruta'.
 *
 * Permisos automáticos (tabla modulos_asignados):
 *   - index()  → llamar requireLeer()
 *   - store()  → llamar requireCrear()
 *   - update() → llamar requireActualizar()
 *   - delete() → llamar requireEliminar()
 *
 * Nivel 3 (super admin): siempre tiene acceso total, sin registro en modulos_asignados.
 */

declare(strict_types=1);

namespace App\controllers\modulos;

use App\core\Controller;
use App\Traits\PermisoModuloTrait;

abstract class BaseModuloController extends Controller
{
    use PermisoModuloTrait;

    /**
     * Ruta MVC del módulo, ej: 'modulos/clientes'.
     * Debe coincidir con la clave en config/modulos_mvc.php
     * y con submodulos_menu.ruta del submodulo correspondiente.
     */
    abstract protected function getRutaModulo(): string;

    /**
     * Verifica sesión + permiso de lectura (r=1).
     * Usar en index() y en las acciones que muestran datos.
     */
    protected function requireLeer(): void
    {
        $this->requireEmpresaSesion();
        $this->requirePermisoVerModulo($this->getRutaModulo());
    }

    /**
     * Verifica sesión + permiso de creación (w=1).
     * Usar en store().
     * Si es petición AJAX, responde JSON 403 en lugar de redirigir.
     */
    protected function requireCrear(): void
    {
        $this->requireEmpresaSesion();
        $this->requirePermisoModulo($this->getRutaModulo(), 'w');
    }

    /**
     * Verifica sesión + permiso de actualización (u=1).
     * Usar en update().
     */
    protected function requireActualizar(): void
    {
        $this->requireEmpresaSesion();
        $this->requirePermisoModulo($this->getRutaModulo(), 'u');
    }

    /**
     * Verifica sesión + permiso de eliminación (d=1).
     * Usar en delete().
     */
    protected function requireEliminar(): void
    {
        $this->requireEmpresaSesion();
        $this->requirePermisoModulo($this->getRutaModulo(), 'd');
    }

    /**
     * Retorna el array de permisos del módulo para pasar a la vista.
     * Equivalente a permisosModuloPorRuta() pero usando la ruta del módulo actual.
     * @return array{ver:bool,crear:bool,actualizar:bool,eliminar:bool,id_submodulo:?int}
     */
    protected function getPermisos(): array
    {
        return $this->permisosModuloPorRuta($this->getRutaModulo());
    }

    /**
     * Registros propios: si el usuario NO tiene acceso total ('t') en este
     * módulo, solo puede operar sobre los documentos que él creó.
     *
     * Los listados ya lo resuelven con $idUsuarioFiltro / getBaseWhere(); este
     * guard cubre las acciones que reciben un id suelto (abrir, PDF, Excel,
     * correo, cambiar estado, duplicar, eliminar…), que sin él permiten llegar
     * por id a un documento que el listado sí oculta.
     *
     * Se le pasa la cabecera YA leída (debe traer created_by) para no repetir la
     * consulta. Si es null no hace nada: el llamador responde su propio "no
     * encontrado". Nivel 3 y quien tenga 't' pasan siempre.
     */
    protected function requireRegistroPropio(?array $registro, string $campo = 'created_by'): void
    {
        if ($registro === null) {
            return;
        }
        if ((int) ($_SESSION['nivel'] ?? 1) >= 3) {
            return;
        }
        $perm = $this->getPermisos();
        if (!empty($perm['todo'])) {
            return;
        }

        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $creador   = (int) ($registro[$campo] ?? 0);
        if ($idUsuario > 0 && $creador === $idUsuario) {
            return;
        }

        $msg = 'No tiene permiso sobre este registro: lo creó otro usuario.';
        if ($this->esAjaxRequest()) {
            $this->json(['ok' => false, 'error' => $msg], 403);
        }
        http_response_code(403);
        echo $msg;
        exit;
    }

    // ─── Replicación de registros entre empresas del mismo usuario ────────────
    // Compartido por los módulos que ofrecen "aplicar también en otras empresas"
    // (Clientes, Productos, …). La lógica de qué copiar/omitir es específica de
    // cada Service; esto solo resuelve QUÉ empresas son destinos válidos.

    /**
     * Empresas del usuario actual donde podría replicar un registro de este
     * módulo (todas las asignadas, o todas las activas si es nivel 3),
     * EXCLUYENDO la empresa activa.
     */
    protected function empresasCandidatasReplicacion(): array
    {
        $idUsuario = (int) $_SESSION['id_usuario'];
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $idEmpresaActual = (int) $_SESSION['id_empresa'];

        $model = new \App\models\Empresa();
        $todas = $nivel >= 3 ? $model->getTodasActivas() : $model->getEmpresasAsignadas($idUsuario);

        return array_values(array_filter($todas, fn($e) => (int) $e['id_empresa'] !== $idEmpresaActual));
    }

    /**
     * De la lista de empresas destino que pidió el usuario, filtra a las que
     * realmente son candidatas (asignadas al usuario / nivel 3) Y donde tiene
     * permiso de crear en la ruta MVC de este módulo. Las que no cumplen se
     * reportan como 'sin_permiso' en vez de simplemente ignorarse, para que la
     * UI pueda avisar.
     *
     * @return array{permitidas:int[], resultado:array<int,array{estado:string}>}
     */
    protected function filtrarEmpresasDestinoPermitidas(array $idsSolicitadas): array
    {
        $idUsuario = (int) $_SESSION['id_usuario'];
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $candidatasIds = array_map(fn($e) => (int) $e['id_empresa'], $this->empresasCandidatasReplicacion());

        $permitidas = [];
        $resultado = [];
        foreach (array_unique(array_map('intval', $idsSolicitadas)) as $idEmp) {
            if ($idEmp <= 0 || !in_array($idEmp, $candidatasIds, true)) {
                $resultado[$idEmp] = ['estado' => 'sin_permiso'];
                continue;
            }
            $permiso = \App\Helpers\Permisos::porRutaEnEmpresa($this->getRutaModulo(), $idEmp, $idUsuario, $nivel);
            if (empty($permiso['crear'])) {
                $resultado[$idEmp] = ['estado' => 'sin_permiso'];
                continue;
            }
            $permitidas[] = $idEmp;
        }

        return ['permitidas' => $permitidas, 'resultado' => $resultado];
    }

    /**
     * Responde JSON con las empresas candidatas para el selector de replicación
     * (checkboxes del modal / selector del botón masivo). Uso típico:
     *   public function empresasDestinoAjax(): void {
     *       $this->requireCrear();
     *       $this->empresasDestinoAjaxResponse();
     *   }
     */
    protected function empresasDestinoAjaxResponse(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $data = array_map(fn($e) => [
                'id_empresa' => (int) $e['id_empresa'],
                'texto'      => ($e['establecimiento'] ?? '001') . ' - ' . (!empty($e['nombre_comercial']) ? $e['nombre_comercial'] : $e['nombre']),
            ], $this->empresasCandidatasReplicacion());
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'empresasDestinoAjax']);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── Comprobación con Contabilidad ────────────────────────────────────────
    // Compartido por los módulos con saldo propio que debe cuadrar con sus cuentas
    // contables (Reporte de Inventarios, Cuentas por Cobrar, Cuentas por Pagar). La
    // definición de qué se compara la arma el repositorio del módulo; el cálculo,
    // App\Services\ComprobacionContableService.

    /** Módulo que da acceso a la comprobación: quien ve la contabilidad de la empresa. */
    private const RUTA_ACCESO_COMPROBACION = 'modulos/estados-financieros';

    /**
     * ¿Puede usar la Comprobación con Contabilidad? Solo quien tiene acceso a la
     * contabilidad (módulo Estados Financieros) en la empresa activa: la comprobación
     * muestra saldos contables y los totales de toda la empresa.
     */
    protected function puedeComprobarContabilidad(): bool
    {
        return \App\Helpers\Permisos::puedeVer(self::RUTA_ACCESO_COMPROBACION);
    }

    /**
     * Responde el JSON de la comprobación del período ?fecha_inicio=&fecha_fin= para la
     * empresa activa. El llamador ya validó el permiso de ver el módulo.
     */
    protected function responderComprobacionContable(array $definicion): void
    {
        $this->responderJsonComprobacion(fn (int $idEmpresa, string $desde, string $hasta) =>
            (new \App\Services\ComprobacionContableService())->comprobar($definicion, $idEmpresa, $desde, $hasta));
    }

    /**
     * Envoltorio común de las respuestas de comprobación: exige el acceso a la contabilidad,
     * lee el período (?fecha_inicio=&fecha_fin=) y traduce los errores. $calculo recibe
     * (idEmpresa, desde, hasta) y devuelve los datos.
     */
    protected function responderJsonComprobacion(callable $calculo): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            if (!$this->puedeComprobarContabilidad()) {
                http_response_code(403);
                throw new \DomainException('La comprobación con contabilidad requiere acceso al módulo Estados Financieros.');
            }
            $data = $calculo(
                (int) $_SESSION['id_empresa'],
                trim((string) ($_GET['fecha_inicio'] ?? '')),
                trim((string) ($_GET['fecha_fin'] ?? ''))
            );
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\InvalidArgumentException | \DomainException $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'comprobacionContableAjax']);
            echo json_encode(['ok' => false, 'error' => 'No se pudo hacer la comprobación con la contabilidad.']);
        }
        exit;
    }

    /**
     * AJAX: Obtiene el historial de cambios de un registro.
     */
    public function getHistorialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        
        $id = (int) ($_GET['id'] ?? 0);
        $tabla = $_GET['tabla'] ?? ''; 
        
        if (!$id || !$tabla) {
            echo json_encode(['ok' => false, 'error' => 'Parámetros insuficientes']);
            exit;
        }
        
        // El id_empresa es obligatorio por reglas de seguridad de modulos operativos
        $idEmpresa = (int)($_SESSION['id_empresa'] ?? 0);
        
        $logService = new \App\Services\LogSistemaService();
        $historial = $logService->getHistorial($tabla, $id, $idEmpresa);
        
        echo json_encode(['ok' => true, 'data' => $historial]);
        exit;
    }

    /**
     * AJAX: guarda en sesión el estado del listado (búsqueda, página y orden) para que la
     * vista navegue luego a la URL limpia (`modulos/x`, sin `?b=&page=&sort=&dir=`).
     * El index() lo recupera con leerEstadoListado(). Se conserva al recargar la página
     * (p. ej. tras guardar en el modal), por eso no es de un solo uso.
     */
    public function estadoListadoAjax(): void
    {
        $this->requireLeer();

        $dir = strtoupper(trim((string) ($_POST['dir'] ?? '')));
        $_SESSION['listado_estado'][$this->getRutaModulo()] = [
            'b'    => mb_substr(trim((string) ($_POST['b'] ?? '')), 0, 200),
            'page' => max(1, (int) ($_POST['page'] ?? 1)),
            // Solo identificadores: la whitelist real de columnas vive en el repository.
            'sort' => preg_replace('/[^a-z0-9_]/i', '', (string) ($_POST['sort'] ?? '')),
            'dir'  => in_array($dir, ['ASC', 'DESC'], true) ? $dir : '',
        ];

        $this->json(['ok' => true]);
    }

    /**
     * Estado del listado guardado por estadoListadoAjax(): ['b','page','sort','dir'].
     * Las claves vacías significan "usar el valor por defecto / las preferencias".
     */
    protected function leerEstadoListado(): array
    {
        $estado = $_SESSION['listado_estado'][$this->getRutaModulo()] ?? [];
        return [
            'b'    => (string) ($estado['b'] ?? ''),
            'page' => max(1, (int) ($estado['page'] ?? 1)),
            'sort' => (string) ($estado['sort'] ?? ''),
            'dir'  => (string) ($estado['dir'] ?? ''),
        ];
    }
}
