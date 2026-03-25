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

    public function moduloStoreModulo(): void { (new ModuloController())->storeModulo(); }
    public function moduloUpdateModulo(): void { (new ModuloController())->updateModulo(); }
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

    public function planCuentasModelo(): void
    {
        (new PlanCuentasModeloController())->index();
    }

    public function planCuentasModeloStore(): void
    {
        (new PlanCuentasModeloController())->store();
    }

    public function planCuentasModeloUpdate(): void
    {
        (new PlanCuentasModeloController())->update();
    }

    public function planCuentasModeloDelete(): void
    {
        (new PlanCuentasModeloController())->delete();
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

    public function empresasSistema(): void
    {
        $sub = $_GET['action'] ?? $_POST['action'] ?? 'index';
        $c = new EmpresasSistemaController();
        $method = match ($sub) {
            'usuariosEmpresa' => 'usuariosEmpresaJson',
            'documentosEmpresa' => 'documentosEmpresaJson',
            'usuariosDisponiblesEmpresa' => 'usuariosDisponiblesEmpresaJson',
            'uploadDocumento' => 'uploadDocumento',
            'deleteDocumento' => 'deleteDocumento',
            'descargarDocumento' => 'descargarDocumento',
            'provincias' => 'provinciasJson',
            'ciudades' => 'ciudadesJson',
            'sriIdentificacion' => 'sriIdentificacionJson',
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

    public function empresasSistemaUpdate(): void
    {
        (new EmpresasSistemaController())->update();
    }

    public function provinciaCiudad(): void
    {
        (new ProvinciaCiudadController())->index();
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

            try {
                $model = new ModelUsuario();
                $model->crearPorCorreo($nombre, $correo, $idAdmin);

                $_SESSION[$msgKey] = ['success', 'Usuario creado. Se enviará un correo para que complete su registro. Asigne empresas en "Asignar empresas".'];
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
}
