<?php

declare(strict_types=1);

/**
 * Controlador Config - Configuración del sistema
 * Permisos por nivel: 1=Usuario, 2=Admin, 3=SuperAdmin
 */

namespace App\controllers;

use App\core\Controller;
use App\models\ConfiguracionOpcion;
use App\models\Usuario as ModelUsuario;
use App\Services\modulos\AsientosTipoService;

class ConfigController extends Controller
{
    public function modulo(): void
    {
        (new ModuloController())->index();
    }

    public function asignarEmpresas(): void
    {
        $sub = $_GET['action'] ?? $_POST['action'] ?? 'index';
        $c = new AsignarEmpresasController();
        $method = match ($sub) {
            'empresasUsuario' => 'empresasUsuarioJson',
            'empresasDisponibles' => 'empresasDisponiblesJson',
            'asignar' => 'asignar',
            'quitar' => 'quitar',
            default => 'index',
        };
        if (method_exists($c, $method)) {
            $c->$method();
        } else {
            $c->index();
        }
    }

    public function permisosModulos(): void
    {
        $sub = $_GET['action'] ?? $_POST['action'] ?? 'index';
        $c = new PermisosModulosController();
        $method = match ($sub) {
            'guardar' => 'guardar',
            'guardarUno' => 'guardarUno',
            'copiarPermisos' => 'copiarPermisos',
            'usuariosJson' => 'usuariosJson',
            'empresasJson' => 'empresasJson',
            default => 'index',
        };
        if (method_exists($c, $method)) {
            $c->$method();
        } else {
            $c->index();
        }
    }

    public function asignacionSubmodulos(): void
    {
        $sub = $_GET['action'] ?? $_POST['action'] ?? 'index';
        $c = new AsignacionSubmodulosController();
        $method = match ($sub) {
            'usuarios'      => 'usuariosJson',
            'previsualizar' => 'previsualizarAjax',
            'aplicar'       => 'aplicarAjax',
            default         => 'index',
        };
        if (method_exists($c, $method)) {
            $c->$method();
        } else {
            $c->index();
        }
    }

    public function combosSubmodulos(): void
    {
        $sub = $_GET['action'] ?? $_POST['action'] ?? 'index';
        $c = new CombosSubmodulosController();
        $method = match ($sub) {
            'store'    => 'store',
            'update'   => 'update',
            'eliminar' => 'eliminar',
            'aplicar'  => 'aplicar',
            default    => 'index',
        };
        if (method_exists($c, $method)) {
            $c->$method();
        } else {
            $c->index();
        }
    }

    public function moduloStoreModulo(): void { (new ModuloController())->storeModulo(); }
    public function moduloUpdateModulo(): void { (new ModuloController())->updateModulo(); }

    public function usuarioResponsablesTraslado(): void
    {
        $sub = $_GET['action'] ?? $_POST['action'] ?? 'listar';
        $c = new UsuarioResponsableTrasladoController();
        $method = match ($sub) {
            'empresasUsuario' => 'empresasUsuarioJson',
            'listar' => 'listarJson',
            'disponibles' => 'disponiblesJson',
            'vincular' => 'vincular',
            'desvincular' => 'desvincular',
            default => 'listarJson',
        };
        if (method_exists($c, $method)) {
            $c->$method();
        }
    }
    public function moduloDeleteModulo(): void { (new ModuloController())->deleteModulo(); }
    public function moduloStoreSubmodulo(): void { (new ModuloController())->storeSubmodulo(); }
    public function moduloUpdateSubmodulo(): void { (new ModuloController())->updateSubmodulo(); }
    public function moduloDeleteSubmodulo(): void { (new ModuloController())->deleteSubmodulo(); }
    public function moduloToggleSubmoduloStatus(): void { (new ModuloController())->toggleSubmoduloStatus(); }
    public function moduloStoreIcono(): void { (new ModuloController())->storeIcono(); }
    public function moduloUpdateIcono(): void { (new ModuloController())->updateIcono(); }

    public function retencionesSri(): void
    {
        (new RetencionesSriController())->index();
    }

    public function retencionesSriUpdate(): void
    {
        (new RetencionesSriController())->update();
    }

    public function retencionesSriStore(): void
    {
        (new RetencionesSriController())->store();
    }

    public function bancosEcuador(): void
    {
        (new BancosEcuadorController())->index();
    }

    public function bancosEcuadorUpdate(): void
    {
        (new BancosEcuadorController())->update();
    }

    public function bancosEcuadorStore(): void
    {
        (new BancosEcuadorController())->store();
    }

    public function iaAgentes(): void
    {
        (new IaAgentesController())->index();
    }

    public function iaAgentesStore(): void
    {
        (new IaAgentesController())->store();
    }

    public function iaAgentesUpdate(): void
    {
        (new IaAgentesController())->update();
    }

    public function iaAgentesDelete(): void
    {
        (new IaAgentesController())->delete();
    }

    public function tarifaIva(): void
    {
        (new TarifaIvaController())->index();
    }

    public function tarifaIvaStore(): void
    {
        (new TarifaIvaController())->store();
    }

    public function tarifaIvaUpdate(): void
    {
        (new TarifaIvaController())->update();
    }

    public function comprobantesAutorizados(): void
    {
        (new ComprobantesAutorizadosController())->index();
    }

    public function comprobantesAutorizadosStore(): void
    {
        (new ComprobantesAutorizadosController())->store();
    }

    public function comprobantesAutorizadosUpdate(): void
    {
        (new ComprobantesAutorizadosController())->update();
    }

    public function comprobantesAutorizadosDelete(): void
    {
        (new ComprobantesAutorizadosController())->delete();
    }

    public function formasPagoSri(): void
    {
        (new FormasPagoSriController())->index();
    }

    public function formasPagoSriStore(): void
    {
        (new FormasPagoSriController())->store();
    }

    public function formasPagoSriUpdate(): void
    {
        (new FormasPagoSriController())->update();
    }

    public function formasPagoSriDelete(): void
    {
        (new FormasPagoSriController())->delete();
    }

    public function sustentoTributario(): void
    {
        (new SustentoTributarioController())->index();
    }

    public function sustentoTributarioStore(): void
    {
        (new SustentoTributarioController())->store();
    }

    public function sustentoTributarioUpdate(): void
    {
        (new SustentoTributarioController())->update();
    }

    public function sustentoTributarioDelete(): void
    {
        (new SustentoTributarioController())->delete();
    }

    public function tiposEmpresa(): void
    {
        (new TiposEmpresaController())->index();
    }

    public function tiposEmpresaStore(): void
    {
        (new TiposEmpresaController())->store();
    }

    public function tiposEmpresaUpdate(): void
    {
        (new TiposEmpresaController())->update();
    }

    public function tiposEmpresaDelete(): void
    {
        (new TiposEmpresaController())->delete();
    }

    public function tiposRegimen(): void
    {
        (new TiposRegimenController())->index();
    }

    public function tiposRegimenStore(): void
    {
        (new TiposRegimenController())->store();
    }

    public function tiposRegimenUpdate(): void
    {
        (new TiposRegimenController())->update();
    }

    public function tiposRegimenDelete(): void
    {
        (new TiposRegimenController())->delete();
    }

    public function unidadesMedida(): void
    {
        (new UnidadesMedidaController())->index();
    }

    public function unidadesMedidaTipoStore(): void
    {
        (new UnidadesMedidaController())->tipoStore();
    }

    public function unidadesMedidaTipoUpdate(): void
    {
        (new UnidadesMedidaController())->tipoUpdate();
    }

    public function unidadesMedidaUnidadStore(): void
    {
        (new UnidadesMedidaController())->unidadStore();
    }

    public function unidadesMedidaUnidadUpdate(): void
    {
        (new UnidadesMedidaController())->unidadUpdate();
    }

    public function impuestosVentas(): void
    {
        (new ImpuestosVentasController())->index();
    }

    public function impuestosVentasStore(): void
    {
        (new ImpuestosVentasController())->store();
    }

    public function impuestosVentasUpdate(): void
    {
        (new ImpuestosVentasController())->update();
    }

    public function impuestosVentasDelete(): void
    {
        (new ImpuestosVentasController())->delete();
    }

    public function identificadoresCompradorVendedor(): void
    {
        (new IdentificadoresCompradorVendedorController())->index();
    }

    public function identificadoresCompradorVendedorStore(): void
    {
        (new IdentificadoresCompradorVendedorController())->store();
    }

    public function identificadoresCompradorVendedorUpdate(): void
    {
        (new IdentificadoresCompradorVendedorController())->update();
    }

    public function identificadoresCompradorVendedorDelete(): void
    {
        (new IdentificadoresCompradorVendedorController())->delete();
    }

    public function supercias(): void
    {
        (new SuperciasEstructurasController())->index();
    }

    public function salarios(): void
    {
        (new SalariosController())->index();
    }

    public function salariosStore(): void
    {
        (new SalariosController())->store();
    }

    public function salariosUpdate(): void
    {
        (new SalariosController())->update();
    }

    public function salariosDelete(): void
    {
        (new SalariosController())->delete();
    }

    public function correosConfig(): void
    {
        (new CorreosConfigController())->index();
    }

    public function correosConfigStore(): void
    {
        (new CorreosConfigController())->store();
    }

    public function correosConfigUpdate(): void
    {
        (new CorreosConfigController())->update();
    }

    public function correosConfigDelete(): void
    {
        (new CorreosConfigController())->delete();
    }

    public function tiposNovedadesNomina(): void
    {
        (new TiposNovedadesNominaController())->index();
    }

    public function tiposNovedadesNominaStore(): void
    {
        (new TiposNovedadesNominaController())->store();
    }

    public function tiposNovedadesNominaUpdate(): void
    {
        (new TiposNovedadesNominaController())->update();
    }

    public function tiposNovedadesNominaDelete(): void
    {
        (new TiposNovedadesNominaController())->delete();
    }

    public function iconosFontawesome(): void
    {
        (new IconosFontawesomeController())->index();
    }

    public function iconosFontawesomeUpdate(): void
    {
        (new IconosFontawesomeController())->update();
    }

    public function iconosFontawesomeStore(): void
    {
        (new IconosFontawesomeController())->store();
    }

    public function iconosFontawesomeDelete(): void
    {
        (new IconosFontawesomeController())->delete();
    }

    public function usuariosSistema(): void
    {
        (new UsuariosSistemaController())->index();
    }

    public function usuariosSistemaUpdate(): void
    {
        (new UsuariosSistemaController())->update();
    }

    public function usuariosSistemaEliminar(): void
    {
        (new UsuariosSistemaController())->eliminar();
    }

    public function usuariosSistemaReenviarInvitacion(): void
    {
        (new UsuariosSistemaController())->reenviarInvitacion();
    }

    public function empresasSistema(): void
    {
        $sub = $_GET['action'] ?? $_POST['action'] ?? 'index';
        $c = new EmpresasSistemaController();
        $method = match ($sub) {
            'usuariosEmpresa' => 'usuariosEmpresaJson',
            'establecimientosEmpresa' => 'establecimientosEmpresaJson',
            'updateEstablecimiento' => 'updateEstablecimiento',
            'documentosEmpresa' => 'documentosEmpresaJson',
            'usuariosDisponiblesEmpresa' => 'usuariosDisponiblesEmpresaJson',
            'uploadDocumento' => 'uploadDocumento',
            'deleteDocumento' => 'deleteDocumento',
            'descargarDocumento' => 'descargarDocumento',
            'provincias' => 'provinciasJson',
            'ciudades' => 'ciudadesJson',
            'sriIdentificacion' => 'sriIdentificacionJson',
            'buscarEmpresas' => 'buscarEmpresasJson',
            'buscarClientes' => 'buscarClientesJson',
            'enviarDocumentosLegales' => 'enviarDocumentosLegales',
            'historialDocumentosLegales' => 'historialDocumentosLegalesJson',
            'descargarDocumentoLegal' => 'descargarDocumentoLegal',
            'delete' => 'delete',
            default => 'index',
        };
        if (method_exists($c, $method)) {
            $c->$method();
        } else {
            $c->index();
        }
    }

    public function empresasSistemaStore(): void
    {
        (new EmpresasSistemaController())->store();
    }

    // ─── Documentos legales (acuerdo de datos + contrato de uso) ────────────

    public function documentosLegales(): void
    {
        (new DocumentosLegalesController())->index();
    }

    public function documentosLegalesGuardar(): void
    {
        (new DocumentosLegalesController())->guardar();
    }

    public function documentosLegalesPrevisualizar(): void
    {
        (new DocumentosLegalesController())->previsualizar();
    }

    public function empresasSistemaUpdate(): void
    {
        (new EmpresasSistemaController())->update();
    }

    public function empresasSistemaDelete(): void
    {
        (new EmpresasSistemaController())->delete();
    }

    public function provinciaCiudad(): void
    {
        (new ProvinciaCiudadController())->index();
    }

    public function importadorExcel(): void
    {
        $sub = $_GET['action'] ?? $_POST['action'] ?? 'index';
        $c = new ImportadorExcelController();
        $method = match ($sub) {
            'descargarPlantillaAjax' => 'descargarPlantillaAjax',
            'procesarImportacionAjax' => 'procesarImportacionAjax',
            default => 'index',
        };
        if (method_exists($c, $method)) {
            $c->$method();
        } else {
            $c->index();
        }
    }

    public function provinciaCiudadProvinciaStore(): void
    {
        (new ProvinciaCiudadController())->provinciaStore();
    }

    public function provinciaCiudadProvinciaUpdate(): void
    {
        (new ProvinciaCiudadController())->provinciaUpdate();
    }

    public function provinciaCiudadCiudadStore(): void
    {
        (new ProvinciaCiudadController())->ciudadStore();
    }

    public function provinciaCiudadCiudadUpdate(): void
    {
        (new ProvinciaCiudadController())->ciudadUpdate();
    }

    public function sriCasillerosEtiquetas(): void
    {
        (new SriCasillerosEtiquetasController())->index();
    }

    public function sriCasillerosEtiquetasStore(): void
    {
        (new SriCasillerosEtiquetasController())->store();
    }

    public function sriCasillerosEtiquetasUpdate(): void
    {
        (new SriCasillerosEtiquetasController())->update();
    }

    public function sriCasillerosEtiquetasDelete(): void
    {
        (new SriCasillerosEtiquetasController())->delete();
    }

    public function impuestoRentaTramos(): void
    {
        (new ImpuestoRentaTramosController())->index();
    }

    public function impuestoRentaTramosStore(): void
    {
        (new ImpuestoRentaTramosController())->store();
    }

    public function impuestoRentaTramosDelete(): void
    {
        (new ImpuestoRentaTramosController())->delete();
    }

    public function impuestoRentaTramosGuardarParametros(): void
    {
        (new ImpuestoRentaTramosController())->guardarParametros();
    }

    public function importarAntiguo(): void
    {
        $sub = $_GET['action'] ?? $_POST['action'] ?? 'index';
        $c = new ImportarAntiguoController();
        $method = match ($sub) {
            'escanear' => 'escanearAjax',
            'importar' => 'importarAjax',
            'lotes'    => 'lotesAjax',
            'anular'   => 'anularLoteAjax',
            default    => 'index',
        };
        if (method_exists($c, $method)) {
            $c->$method();
        } else {
            $c->index();
        }
    }

    public function migrarMysql(): void
    {
        $sub = $_GET['action'] ?? $_POST['action'] ?? 'index';
        $c = new MigrarMysqlController();
        $method = match ($sub) {
            'analizar'           => 'analizarAjax',
            'probar'             => 'probarAjax',
            'migrar'             => 'migrarAjax',
            'progreso'           => 'progresoAjax',
            'verificar-anuladas' => 'verificarAnuladasAjax',
            'config-preview'     => 'configPreviewAjax',
            'config-aplicar'     => 'configAplicarAjax',
            default              => 'index',
        };
        if (method_exists($c, $method)) {
            $c->$method();
        } else {
            $c->index();
        }
    }

    public function logSistema(): void
    {
        $sub = $_GET['action'] ?? $_POST['action'] ?? 'index';
        $c = new LogSistemaConsultaController();
        $method = match ($sub) {
            'listar'         => 'listarAjax',
            'detalle'        => 'detalleAjax',
            'exportarExcel'  => 'exportarExcel',
            'exportarPdf'    => 'exportarPdf',
            default          => 'index',
        };
        if (method_exists($c, $method)) {
            $c->$method();
        } else {
            $c->index();
        }
    }

    public function index(): void
    {
        $this->requireAuth();
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $model = new ConfiguracionOpcion();
        $model->asegurarOpcionesBase();
        $opciones = $model->getOpcionesConEnlaces($nivel);

        $puedeCrear = $nivel >= 3; // Solo Super Admin

        $this->viewWithLayout('layouts.main', 'config.index', [
            'titulo' => 'Configuración',
            'opciones' => $opciones,
            'nivel' => $nivel,
            'puedeCrear' => $puedeCrear,
        ]);
    }

    public function storeOption(): void
    {
        $this->requireAuth();
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        if ($nivel < 3) {
            $_SESSION['config_msg'] = ['danger', 'Solo el super administrador puede crear estas tarjetas.'];
            $this->redirect(BASE_URL . '/config');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . '/config');
        }

        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') {
            $_SESSION['config_msg'] = ['danger', 'El nombre es obligatorio.'];
            $this->redirect(BASE_URL . '/config');
        }

        $model = new ConfiguracionOpcion();
        $data = [
            'nombre' => $nombre,
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'icono' => trim($_POST['icono'] ?? 'gear'),
            'clase_color' => trim($_POST['clase_color'] ?? 'primary'),
            'nivel_minimo' => (int) ($_POST['nivel_minimo'] ?? 1),
            'orden' => (int) ($_POST['orden'] ?? 0),
            'activo' => isset($_POST['activo']) ? 1 : 1,
        ];

        try {
            $idOpcion = $model->crearOpcion($data);

            // Enlaces enviados como arrays: enlace_etiqueta[], enlace_ruta[], enlace_clase_btn[]
            $etiquetas = $_POST['enlace_etiqueta'] ?? [];
            $rutas = $_POST['enlace_ruta'] ?? [];
            $clases = $_POST['enlace_clase_btn'] ?? [];
            $ordenEnlace = 0;
            foreach ($etiquetas as $i => $et) {
                $et = trim($et);
                $ruta = trim($rutas[$i] ?? '');
                if ($et !== '' && $ruta !== '') {
                    $model->crearEnlace($idOpcion, [
                        'etiqueta' => $et,
                        'ruta' => $ruta,
                        'clase_btn' => trim($clases[$i] ?? 'outline-primary'),
                        'orden' => $ordenEnlace++,
                    ]);
                }
            }

            $_SESSION['config_msg'] = ['success', 'Tarjeta creada correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['config_msg'] = ['danger', 'Error al guardar: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . '/config');
    }

    public function updateOption(): void
    {
        $this->requireAuth();
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        if ($nivel < 3) {
            $_SESSION['config_msg'] = ['danger', 'Solo el super administrador puede modificar tarjetas.'];
            $this->redirect(BASE_URL . '/config');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . '/config');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['config_msg'] = ['danger', 'ID de tarjeta inválido.'];
            $this->redirect(BASE_URL . '/config');
        }

        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') {
            $_SESSION['config_msg'] = ['danger', 'El nombre es obligatorio.'];
            $this->redirect(BASE_URL . '/config');
        }

        $model = new ConfiguracionOpcion();
        if ($model->getOpcionPorId($id) === null) {
            $_SESSION['config_msg'] = ['danger', 'Tarjeta no encontrada.'];
            $this->redirect(BASE_URL . '/config');
        }

        $data = [
            'nombre' => $nombre,
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'icono' => trim($_POST['icono'] ?? 'gear'),
            'clase_color' => trim($_POST['clase_color'] ?? 'primary'),
            'nivel_minimo' => (int) ($_POST['nivel_minimo'] ?? 1),
            'orden' => (int) ($_POST['orden'] ?? 0),
            'activo' => isset($_POST['activo']) ? 1 : 0,
        ];

        try {
            $model->actualizarOpcion($id, $data);
            $model->eliminarEnlacesPorOpcion($id);

            $etiquetas = $_POST['enlace_etiqueta'] ?? [];
            $rutas = $_POST['enlace_ruta'] ?? [];
            $clases = $_POST['enlace_clase_btn'] ?? [];
            $ordenEnlace = 0;
            foreach ($etiquetas as $i => $et) {
                $et = trim($et);
                $ruta = trim($rutas[$i] ?? '');
                if ($et !== '' && $ruta !== '') {
                    $model->crearEnlace($id, [
                        'etiqueta' => $et,
                        'ruta' => $ruta,
                        'clase_btn' => trim($clases[$i] ?? 'outline-primary'),
                        'orden' => $ordenEnlace++,
                    ]);
                }
            }

            $_SESSION['config_msg'] = ['success', 'Tarjeta actualizada correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['config_msg'] = ['danger', 'Error al actualizar: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . '/config');
    }

    public function deleteOption(): void
    {
        $this->requireAuth();
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        if ($nivel < 3) {
            $_SESSION['config_msg'] = ['danger', 'Solo el super administrador puede eliminar tarjetas.'];
            $this->redirect(BASE_URL . '/config');
        }

        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['config_msg'] = ['danger', 'ID de tarjeta inválido.'];
            $this->redirect(BASE_URL . '/config');
        }

        $model = new ConfiguracionOpcion();
        if ($model->getOpcionPorId($id) === null) {
            $_SESSION['config_msg'] = ['danger', 'Tarjeta no encontrada.'];
            $this->redirect(BASE_URL . '/config');
        }

        try {
            $model->eliminarOpcion($id);
            $_SESSION['config_msg'] = ['success', 'Tarjeta eliminada correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['config_msg'] = ['danger', 'Error al eliminar: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . '/config');
    }

    public function reordenarOpciones(): void
    {
        $this->requireAuth();
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        if ($nivel < 3) {
            $this->json(['ok' => false, 'msg' => 'Sin permisos']);
        }

        $ordenIds = $_POST['orden'] ?? [];
        if (!is_array($ordenIds) || empty($ordenIds)) {
            $this->json(['ok' => false, 'msg' => 'Orden no válido']);
        }

        try {
            $model = new ConfiguracionOpcion();
            $model->reordenarOpciones($ordenIds);
            $this->json(['ok' => true, 'msg' => 'Orden guardado']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function crearUsuario(): void
    {
        $this->requireAuth();
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        if ($nivel < 2) {
            $_SESSION['config_msg'] = ['danger', 'No tiene permisos.'];
            $this->redirect(BASE_URL . '/config');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre = trim($_POST['nombre'] ?? '');
            $correo = trim($_POST['correo'] ?? '');
            $idAdmin = (int) ($_SESSION['id_usuario'] ?? 0);
            $redirectTo = trim($_POST['redirect'] ?? '');
            if (!in_array($redirectTo, ['asignar-empresas', 'permisos-modulos', 'usuarios-sistema'], true)) {
                $redirectTo = 'permisos-modulos';
            }
            $msgKey = match ($redirectTo) {
                'asignar-empresas' => 'asignar_msg',
                'usuarios-sistema' => 'usuarios_msg',
                default => 'permisos_msg',
            };
            $targetUrl = BASE_URL . '/config/' . $redirectTo;

            if ($nombre === '' || $correo === '') {
                $_SESSION[$msgKey] = ['danger', 'Nombre y correo son obligatorios.'];
                $this->redirect($targetUrl);
            }

            // Validar límite de usuarios por empresa para admins (nivel < 3)
            if ($nivel < 3) {
                $idEmpresaActual = (int) ($_SESSION['id_empresa'] ?? 0);
                if ($idEmpresaActual > 0) {
                    $modelAsignada = new \App\models\EmpresaAsignada();
                    $limite = $modelAsignada->getLimiteUsuariosEmpresa($idEmpresaActual);
                    if ($limite['actual'] >= $limite['max']) {
                        $_SESSION[$msgKey] = ['danger', "Ha alcanzado el límite de {$limite['max']} usuario(s) permitidos para esta empresa. Contacte al super administrador para ampliar el límite."];
                        $this->redirect($targetUrl);
                    }
                }
            }

            try {
                $model = new ModelUsuario();
                $resultado = $model->crearPorCorreo($nombre, $correo, $idAdmin);
                $idNuevo = $resultado['id'];
                $token = $resultado['token'];

                if ($token !== '') {
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $urlEmail = urlencode($correo);
                    $urlInvite = $scheme . '://' . $host . rtrim(BASE_URL, '/') . '/registro/index/' . $urlEmail . '/' . $token;

                    require_once MVC_APP . '/helpers/mail.php';
                    enviar_correo_nuevo_usuario($nombre, $correo, $urlInvite);
                }

                $_SESSION[$msgKey] = ['success', 'Usuario creado. Se ha enviado un correo a ' . $correo . ' para que complete su registro.'];
                $this->redirect($targetUrl);
            } catch (\InvalidArgumentException $e) {
                $_SESSION[$msgKey] = ['danger', $e->getMessage()];
                $this->redirect($targetUrl);
            } catch (\Throwable $e) {
                $_SESSION[$msgKey] = ['danger', 'Error al crear usuario: ' . $e->getMessage()];
                $this->redirect($targetUrl);
            }
        }

        $this->redirect(BASE_URL . '/config/permisos-modulos');
    }

    public function appearance(): void
    {
        $this->requireAuth();
        $theme = getThemeConfig();
        $this->viewWithLayout('layouts.main', 'config.appearance', [
            'titulo' => 'Apariencia',
            'theme' => $theme,
        ]);
    }

    public function saveAppearance(): void
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . '/config/appearance');
        }

        $data = [
            'body' => [
                'gradient_start' => trim($_POST['gradient_start'] ?? ''),
                'gradient_end' => trim($_POST['gradient_end'] ?? ''),
                'gradient_angle' => trim($_POST['gradient_angle'] ?? ''),
            ],
            'primary' => [
                'main' => trim($_POST['primary_main'] ?? ''),
                'hover' => trim($_POST['primary_hover'] ?? ''),
                'text' => trim($_POST['primary_text'] ?? ''),
            ],
            'links' => [
                'color' => trim($_POST['links_color'] ?? ''),
                'hover' => trim($_POST['links_hover'] ?? ''),
            ],
            'typography' => [
                'font_size_base' => trim($_POST['font_size_base'] ?? ''),
                'font_family' => trim($_POST['font_family'] ?? ''),
            ],
            'borders' => [
                'radius' => trim($_POST['radius'] ?? ''),
                'radius_sm' => trim($_POST['radius_sm'] ?? ''),
                'radius_lg' => trim($_POST['radius_lg'] ?? ''),
            ],
        ];

        if (saveThemeConfig($data)) {
            $_SESSION['config_msg'] = ['success', 'Colores guardados correctamente.'];
        } else {
            $_SESSION['config_msg'] = ['danger', 'Error al guardar.'];
        }

        $this->redirect(BASE_URL . '/config/appearance');
    }

    public function restoreTheme(): void
    {
        $this->requireAuth();
        $file = MVC_ROOT . '/storage/theme.json';
        if (file_exists($file)) {
            unlink($file);
        }
        $_SESSION['config_msg'] = ['success', 'Colores restaurados a los valores por defecto.'];
        $this->redirect(BASE_URL . '/config/appearance');
    }

    // ─── Tareas y Obligaciones ────────────────────────────────

    public function tareasObligaciones(): void
    {
        $sub = $_GET['action'] ?? $_POST['action'] ?? 'index';
        $c   = new TareasObligacionesController();
        $method = match ($sub) {
            // Obligaciones
            'obligaciones-search-ajax' => 'obligacionesSearchAjax',
            'obligaciones-store'       => 'obligacionesStore',
            'obligaciones-update'      => 'obligacionesUpdate',
            'obligaciones-delete'      => 'obligacionesDelete',
            // Tareas
            'tareas-search-ajax'       => 'tareasSearchAjax',
            'tareas-store'             => 'tareasStore',
            'tareas-update'            => 'tareasUpdate',
            'tareas-delete'            => 'tareasDelete',
            'tareas-get-detalle'       => 'tareasGetDetalle',
            // Adjuntos
            'tareas-upload-adjunto'    => 'tareasUploadAdjunto',
            'tareas-delete-adjunto'    => 'tareasDeleteAdjunto',
            // Búsquedas y SRI
            'buscar-clientes'          => 'buscarClientes',
            'buscar-usuarios'          => 'buscarUsuarios',
            'correos-cliente'          => 'getCorreosCliente',
            'consultar-sri'            => 'consultarSri',
            'crear-cliente-tarea'      => 'crearClienteTarea',
            'crear-responsable-tarea'  => 'crearResponsableTarea',
            'tareas-alertas-count'     => 'tareasAlertasCountAjax',
            // Clientes / duplicar combo
            'clientes-search-ajax'     => 'clientesSearchAjax',
            'cliente-combo-ajax'       => 'clienteComboAjax',
            'tareas-copiar-combo'      => 'tareasCopiarComboAjax',
            default                    => 'index',
        };
        if (method_exists($c, $method)) {
            $c->$method();
        } else {
            $c->index();
        }
    }

    // ==========================================
    // SECCIÓN DE CONFIGURACIÓN DE ASIENTOS TIPO
    // ==========================================

    public function asientosTipo(): void
    {
        $this->requireAuth();
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        if ($nivel < 2) {
            $_SESSION['config_msg'] = ['danger', 'No tiene permisos para acceder a esta configuración.'];
            $this->redirect(BASE_URL . '/config');
        }

        $this->viewWithLayout('layouts.main', 'config.asientos_tipo', [
            'titulo' => 'Modelos de Asientos Tipo',
            'nivel' => $nivel
        ]);
    }

    public function asientosTipoListAjax(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'id');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'ASC'));
        $perPage   = 10;

        $service = new AsientosTipoService();
        $result = $service->getListado($buscar, $page, $perPage, $ordenCol, $ordenDir);
        $rows   = $result['rows'];
        $total  = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        $tiposTextos = [
            'ventas_factura' => 'Ventas con Factura',
            'recibos_venta' => 'Ventas con Recibo',
            'adquisiciones_compras' => 'Adquisiciones de Compras/Servicios',
            'retenciones_venta' => 'Retenciones en Venta',
            'retenciones_compra' => 'Retenciones en Compra',
            'ingresos_egresos' => 'Ingresos y Egresos',
            'cobros_pagos' => 'Cobros y Pagos',
            'nomina' => 'Nómina'
        ];

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron asientos tipo.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $tipoText = $tiposTextos[$r['tipo_asiento']] ?? ucwords(str_replace('_', ' ', $r['tipo_asiento']));
                
                $parts = array_map('trim', explode(',', strtolower($r['tipo_cuenta'] ?? '')));
                $badgeHtml = '';
                foreach ($parts as $p) {
                    if (!empty($p)) {
                        $label = ucfirst($p);
                        $badgeHtml .= '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 py-1 px-2 m-1 small">' . $label . '</span>';
                    }
                }
                if (empty($badgeHtml)) {
                    $badgeHtml = '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 py-1 px-2 m-1 small">Todos</span>';
                }

                $debeHaberBadge = (strtolower($r['debe_haber'] ?? 'debe') === 'debe')
                    ? '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 py-1 px-2 fw-bold small">DEBE</span>'
                    : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 py-1 px-2 fw-bold small">HABER</span>';

                echo '<tr class="asiento-tipo-row align-middle" role="button" onclick="ASIENTOTIPO_editar(' . $r['id'] . ')">
                        <td class="ps-3 fw-bold text-primary">' . htmlspecialchars($r['codigo']) . '</td>
                        <td class="fw-medium">' . htmlspecialchars($tipoText) . '</td>
                        <td>' . htmlspecialchars($r['referencia']) . '</td>
                        <td class="text-center">' . $debeHaberBadge . '</td>
                        <td>' . $badgeHtml . '</td>
                        <td class="small text-muted text-truncate" style="max-width: 300px;" title="' . htmlspecialchars($r['detalle'] ?? '') . '">' . htmlspecialchars($r['detalle'] ?? '') . '</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-link text-danger p-0 border-0" onclick="event.stopPropagation(); ASIENTOTIPO_eliminar(' . $r['id'] . ')" title="Eliminar">
                                <i class="bi bi-trash fs-5"></i>
                            </button>
                        </td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="ASIENTOTIPO_cambiarPagina(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="ASIENTOTIPO_cambiarPagina(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total"
        ]);
        exit;
    }

    public function asientosTipoGetDetailAjax(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $id = (int)($_GET['id'] ?? 0);
        $service = new AsientosTipoService();
        $data = $service->findById($id);

        if (!$data) {
            echo json_encode(['ok' => false, 'error' => 'No se encontró el asiento tipo.']);
        } else {
            $repo = new \App\repositories\modulos\AsientosTipoRepository();
            $data['en_uso'] = $repo->estaEnUso($id);
            echo json_encode(['ok' => true, 'data' => $data]);
        }
        exit;
    }

    public function asientosTipoStore(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $data = [
            'tipo_asiento' => trim($_POST['tipo_asiento'] ?? ''),
            'referencia'   => trim($_POST['referencia'] ?? ''),
            'detalle'      => trim($_POST['detalle'] ?? ''),
            'codigo'       => trim($_POST['codigo'] ?? ''),
            'tipo_cuenta'  => trim($_POST['tipo_cuenta'] ?? ''),
            'debe_haber'   => trim($_POST['debe_haber'] ?? 'debe'),
            'id_usuario'   => (int)$_SESSION['id_usuario']
        ];

        try {
            $service = new AsientosTipoService();
            $id = $service->crear($data);
            echo json_encode(['ok' => true, 'msg' => 'Asiento tipo registrado correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function asientosTipoUpdate(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'tipo_asiento' => trim($_POST['tipo_asiento'] ?? ''),
            'referencia'   => trim($_POST['referencia'] ?? ''),
            'detalle'      => trim($_POST['detalle'] ?? ''),
            'codigo'       => trim($_POST['codigo'] ?? ''),
            'tipo_cuenta'  => trim($_POST['tipo_cuenta'] ?? ''),
            'debe_haber'   => trim($_POST['debe_haber'] ?? 'debe'),
            'id_usuario'   => (int)$_SESSION['id_usuario']
        ];

        try {
            if ($id <= 0) {
                throw new \Exception('ID de asiento tipo inválido.');
            }
            $service = new AsientosTipoService();
            $service->actualizar($id, $data);
            echo json_encode(['ok' => true, 'msg' => 'Asiento tipo actualizado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function asientosTipoDelete(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $idUsuario = (int)$_SESSION['id_usuario'];

        try {
            if ($id <= 0) {
                throw new \Exception('ID de asiento tipo inválido.');
            }
            $service = new AsientosTipoService();
            $service->eliminar($id, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Asiento tipo de modelo predefinido eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
