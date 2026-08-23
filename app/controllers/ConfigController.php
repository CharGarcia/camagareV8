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
            'search' => 'searchAjax',
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
            'asignarEmpresa' => 'asignarEmpresa',
            'empresasAsignablesJson' => 'empresasAsignablesJson',
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
            'search'   => 'searchAjax',
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

    public function retencionesSriSearch(): void
    {
        (new RetencionesSriController())->searchAjax();
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

    public function bancosEcuadorSearch(): void
    {
        (new BancosEcuadorController())->searchAjax();
    }

    public function bancosEcuadorUpdate(): void
    {
        (new BancosEcuadorController())->update();
    }

    public function bancosEcuadorStore(): void
    {
        (new BancosEcuadorController())->store();
    }

    public function transferenciaFormatos(): void
    {
        (new TransferenciaFormatoController())->index();
    }

    public function transferenciaFormatosSearch(): void
    {
        (new TransferenciaFormatoController())->searchAjax();
    }

    public function transferenciaFormatosStore(): void
    {
        (new TransferenciaFormatoController())->store();
    }

    public function transferenciaFormatosUpdate(): void
    {
        (new TransferenciaFormatoController())->update();
    }

    public function transferenciaFormatosDelete(): void
    {
        (new TransferenciaFormatoController())->delete();
    }

    public function transferenciaFormatosActivar(): void
    {
        (new TransferenciaFormatoController())->activar();
    }

    public function transferenciaFormatosDesactivar(): void
    {
        (new TransferenciaFormatoController())->desactivar();
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

    public function tarifaIvaSearch(): void
    {
        (new TarifaIvaController())->searchAjax();
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

    public function comprobantesAutorizadosSearch(): void
    {
        (new ComprobantesAutorizadosController())->searchAjax();
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

    public function formasPagoSriSearch(): void
    {
        (new FormasPagoSriController())->searchAjax();
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

    public function sustentoTributarioSearch(): void
    {
        (new SustentoTributarioController())->searchAjax();
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

    public function tiposEmpresaSearch(): void
    {
        (new TiposEmpresaController())->searchAjax();
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

    public function tiposRegimenSearch(): void
    {
        (new TiposRegimenController())->searchAjax();
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

    public function unidadesMedidaTiposSearch(): void
    {
        (new UnidadesMedidaController())->tiposSearchAjax();
    }

    public function unidadesMedidaUnidadesSearch(): void
    {
        (new UnidadesMedidaController())->unidadesSearchAjax();
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

    public function impuestosVentasSearch(): void
    {
        (new ImpuestosVentasController())->searchAjax();
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

    public function identificadoresCompradorVendedorSearch(): void
    {
        (new IdentificadoresCompradorVendedorController())->searchAjax();
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

    public function salariosSearch(): void
    {
        (new SalariosController())->searchAjax();
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

    public function correosConfigSearch(): void
    {
        (new CorreosConfigController())->searchAjax();
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

    public function tiposNovedadesNominaSearch(): void
    {
        (new TiposNovedadesNominaController())->searchAjax();
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

    public function iconosFontawesomeSearch(): void
    {
        (new IconosFontawesomeController())->searchAjax();
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

    public function usuariosSistemaSearch(): void
    {
        (new UsuariosSistemaController())->searchAjax();
    }

    public function empresasSistema(): void
    {
        $sub = $_GET['action'] ?? $_POST['action'] ?? 'index';
        $c = new EmpresasSistemaController();
        $method = match ($sub) {
            'search' => 'searchAjax',
            'usuariosEmpresa' => 'usuariosEmpresaJson',
            'establecimientosEmpresa' => 'establecimientosEmpresaJson',
            'updateEstablecimiento' => 'updateEstablecimiento',
            'deleteEstablecimiento' => 'deleteEstablecimiento',
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
            'suscripcionesCliente' => 'suscripcionesClienteJson',
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

    public function provinciaCiudadProvinciasSearch(): void
    {
        (new ProvinciaCiudadController())->provinciasSearchAjax();
    }

    public function provinciaCiudadCiudadesSearch(): void
    {
        (new ProvinciaCiudadController())->ciudadesSearchAjax();
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

    public function sriCasillerosEtiquetasSearch(): void
    {
        (new SriCasillerosEtiquetasController())->searchAjax();
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
            'eliminar-preview'   => 'eliminarPreviewAjax',
            'eliminar'           => 'eliminarAjax',
            'verificar-existentes' => 'verificarExistentesAjax',
            'verificar-ruc-migrado' => 'verificarRucMigradoAjax',
            'usuarios-por-migrar' => 'usuariosPorMigrarAjax',
            'migrar-usuarios'    => 'migrarUsuariosAjax',
            'empresas-por-migrar' => 'empresasPorMigrarAjax',
            'migrar-empresas'    => 'migrarEmpresasAjax',
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
            'intentos'       => 'intentosAjax',
            default          => 'index',
        };
        if (method_exists($c, $method)) {
            $c->$method();
        } else {
            $c->index();
        }
    }

    /** Despachador de la tarjeta "Errores del sistema" (/config/errores-sistema). */
    /**
     * Videollamadas: servidores STUN/TURN globales y límites de la empresa.
     * Es configuración de plataforma, no una función del módulo de reuniones.
     */
    public function videollamadas(): void
    {
        $sub = $_GET['action'] ?? $_POST['action'] ?? 'index';
        $c = new VideollamadasConfigController();
        $method = match ($sub) {
            'guardarGlobal' => 'guardarGlobalAjax',
            'probar'        => 'probarAjax',
            default         => 'index',
        };
        if (method_exists($c, $method)) {
            $c->$method();
        } else {
            $c->index();
        }
    }

    public function erroresSistema(): void
    {
        $sub = $_GET['action'] ?? $_POST['action'] ?? 'index';
        $c = new ErroresSistemaConsultaController();
        $method = match ($sub) {
            'listar'  => 'listarAjax',
            'detalle' => 'detalleAjax',
            default   => 'index',
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
        $esAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

        if ($nivel < 2) {
            if ($esAjax) {
                $this->json(['ok' => false, 'msg' => 'No tiene permisos.']);
            }
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

            $fallar = function (string $msg) use ($esAjax, $msgKey, $targetUrl): never {
                if ($esAjax) {
                    $this->json(['ok' => false, 'msg' => $msg]);
                }
                $_SESSION[$msgKey] = ['danger', $msg];
                $this->redirect($targetUrl);
            };

            if ($correo === '') {
                $fallar('El correo es obligatorio.');
            }
            // El nombre es opcional: el modal de Permisos de módulos solo pide correo y
            // empresa. Se guarda un nombre provisional tomado del correo; el usuario
            // escribe su nombre real al completar el registro.
            if ($nombre === '') {
                $nombre = trim((string) strstr($correo, '@', true));
                if ($nombre === '') $nombre = $correo;
            }

            // Empresas a asignar al nuevo usuario (solo cuando el formulario las envía,
            // p. ej. modal de config/usuarios-sistema). Nivel 3: cualquier empresa activa.
            // Nivel 2: únicamente las que él mismo tiene asignadas (empresa_asignada).
            $idsEmpresasPost = array_map('intval', (array) ($_POST['empresas'] ?? []));
            $idsEmpresasPost = array_values(array_unique(array_filter($idsEmpresasPost, fn($v) => $v > 0)));
            $idsEmpresasValidas = [];
            if (!empty($idsEmpresasPost)) {
                $modelAsignadaEmp = new \App\models\EmpresaAsignada();
                if ($nivel >= 3) {
                    // Cualquier empresa activa. Se comprueba una por una para no depender
                    // del límite con que se lista el catálogo de empresas.
                    $idsEmpresasValidas = array_values(array_filter(
                        $idsEmpresasPost,
                        fn($id) => $modelAsignadaEmp->getEmpresaActivaPorId((int) $id) !== null
                    ));
                } else {
                    $permitidas = array_map('intval', array_column($modelAsignadaEmp->getEmpresasDeUsuario($idAdmin), 'id_empresa'));
                    $idsEmpresasValidas = array_values(array_intersect($idsEmpresasPost, $permitidas));
                }
                if (empty($idsEmpresasValidas)) {
                    $fallar('Las empresas seleccionadas no son válidas para su usuario.');
                }
            }

            // Formularios que exigen empresa (modal de Permisos de módulos): sin empresa
            // el usuario quedaría creado pero sin poder entrar a ningún lado.
            if (!empty($_POST['empresa_requerida']) && empty($idsEmpresasValidas)) {
                $fallar('Seleccione la empresa que se le asignará al usuario.');
            }

            // Validar límite de usuarios por empresa para admins (nivel < 3). Se valida
            // la empresa que se va a asignar (el formulario ya permite elegirla); si el
            // formulario no manda ninguna, se cae a la empresa activa como antes.
            if ($nivel < 3) {
                $modelAsignada = new \App\models\EmpresaAsignada();
                $empresasLimite = !empty($idsEmpresasValidas)
                    ? $idsEmpresasValidas
                    : array_filter([(int) ($_SESSION['id_empresa'] ?? 0)]);
                // Si el correo es de un usuario que ya existe, las empresas que ya tiene
                // no suman un cupo nuevo y no deben bloquear por límite.
                $idUsuarioExistente = 0;
                if (!empty($_POST['asignar_si_existe'])) {
                    $yaRegistrado = (new ModelUsuario())->getUsuarioActivoPorCorreo($correo);
                    $idUsuarioExistente = (int) ($yaRegistrado['id'] ?? 0);
                }
                foreach ($empresasLimite as $idEmpLimite) {
                    if ($idUsuarioExistente > 0
                        && $modelAsignada->estaEmpresaAsignada((int) $idEmpLimite, $idUsuarioExistente)) {
                        continue;
                    }
                    $limite = $modelAsignada->getLimiteUsuariosEmpresa((int) $idEmpLimite);
                    if ($limite['actual'] >= $limite['max']) {
                        $emp = $modelAsignada->getEmpresaActivaPorId((int) $idEmpLimite);
                        $nombreEmp = $emp['nombre_comercial'] ?? ('la empresa #' . (int) $idEmpLimite);
                        $fallar("{$nombreEmp} alcanzó el límite de {$limite['max']} usuario(s). Contacte al super administrador para ampliar el límite.");
                    }
                }
            }

            // Correo ya registrado: no se crea nada ni se reenvía la invitación, solo se
            // asigna la empresa al usuario que ya existe para poder darle permisos.
            // Solo cuando el formulario lo pide (asignar_si_existe), para no cambiar el
            // comportamiento de las demás pantallas que crean usuarios.
            if (!empty($_POST['asignar_si_existe'])) {
                $resultadoExistente = $this->asignarUsuarioExistente(
                    $correo,
                    $idsEmpresasValidas,
                    $idAdmin,
                    $nivel
                );
                if ($resultadoExistente !== null) {
                    if (!empty($resultadoExistente['error'])) {
                        $fallar($resultadoExistente['error']);
                    }
                    if ($esAjax) {
                        $this->json($resultadoExistente);
                    }
                    $_SESSION[$msgKey] = ['success', $resultadoExistente['msg']];
                    $this->redirect($targetUrl);
                }
            }

            try {
                $model = new ModelUsuario();
                $resultado = $model->crearPorCorreo($nombre, $correo, $idAdmin);
                $idNuevo = $resultado['id'];
                $token = $resultado['token'];

                if (!empty($idsEmpresasValidas)) {
                    $modelAsignadaEmp = $modelAsignadaEmp ?? new \App\models\EmpresaAsignada();
                    foreach ($idsEmpresasValidas as $idEmp) {
                        $modelAsignadaEmp->asignar($idEmp, $idNuevo, $idAdmin);
                    }
                }

                if ($token !== '') {
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $urlEmail = urlencode($correo);
                    $urlInvite = $scheme . '://' . $host . rtrim(BASE_URL, '/') . '/registro/index/' . $urlEmail . '/' . $token;

                    require_once MVC_APP . '/helpers/mail.php';
                    enviar_correo_nuevo_usuario($nombre, $correo, $urlInvite);
                }

                $msgExito = 'Usuario creado. Se ha enviado un correo a ' . $correo . ' para que complete su registro.';
                if ($esAjax) {
                    $this->json([
                        'ok'         => true,
                        'msg'        => $msgExito,
                        'id_usuario' => (int) $idNuevo,
                        'id_empresa' => (int) ($idsEmpresasValidas[0] ?? 0),
                        'ya_existia' => false,
                    ]);
                }
                $_SESSION[$msgKey] = ['success', $msgExito];
                $this->redirect($targetUrl);
            } catch (\InvalidArgumentException $e) {
                $fallar($e->getMessage());
            } catch (\Throwable $e) {
                $fallar('Error al crear usuario: ' . $e->getMessage());
            }
        }

        $this->redirect(BASE_URL . '/config/permisos-modulos');
    }

    /**
     * Correo ya registrado en el sistema: no se crea otro usuario ni se reenvía la
     * invitación; solo se le asigna la empresa para poder darle permisos ahí mismo.
     *
     * Devuelve null si el correo no corresponde a ningún usuario (sigue el alta
     * normal), o un arreglo con el resultado listo para responder.
     */
    private function asignarUsuarioExistente(string $correo, array $idsEmpresas, int $idAdmin, int $nivel): ?array
    {
        $modelUsuario = new ModelUsuario();
        $existente = $modelUsuario->getUsuarioActivoPorCorreo($correo);

        if (!$existente) {
            // Puede existir pero inactivo o eliminado: no se puede continuar, pero
            // tampoco crear otro usuario con el mismo correo.
            if ($modelUsuario->existePorCorreo($correo)) {
                return ['ok' => false, 'error' => 'Ya existe un usuario con ese correo, pero está inactivo. Actívelo desde Usuarios del sistema.'];
            }
            return null;
        }

        $idExistente = (int) ($existente['id'] ?? 0);
        $nivelExistente = (int) ($existente['nivel'] ?? 1);

        if ($idExistente <= 0) {
            return null;
        }
        if ($nivelExistente >= 3) {
            return ['ok' => false, 'error' => 'Ese correo es de un superadministrador: ya accede a todas las empresas, no necesita asignación.'];
        }
        if (empty($idsEmpresas)) {
            return ['ok' => false, 'error' => 'Ya existe un usuario con ese correo. Seleccione la empresa que desea asignarle.'];
        }

        $modelEmpresa = new \App\models\EmpresaAsignada();
        $asignadas = [];
        $yaTenia = [];

        foreach ($idsEmpresas as $idEmp) {
            $idEmp = (int) $idEmp;
            if ($idEmp <= 0) continue;
            if ($modelEmpresa->estaEmpresaAsignada($idEmp, $idExistente)) {
                $yaTenia[] = $idEmp;
                continue;
            }
            if ($modelEmpresa->asignar($idEmp, $idExistente, $idAdmin)) {
                $asignadas[] = $idEmp;
                try {
                    (new \App\Services\LogSistemaService())->registrar(
                        $idAdmin,
                        $idEmp,
                        'asignar_empresa_usuario',
                        'empresa_asignada',
                        null,
                        null,
                        [
                            'id_usuario' => $idExistente,
                            'id_empresa' => $idEmp,
                            'origen'     => 'crear-usuario-correo-existente',
                        ]
                    );
                } catch (\Throwable $e) {
                    // La auditoría no debe bloquear la operación.
                }
            }
        }

        // El administrador debe poder gestionar después a ese usuario.
        $modelUsuario->agregarAUsuarioAsignado($idExistente, $idAdmin);

        $idEmpresaFoco = (int) ($asignadas[0] ?? $yaTenia[0] ?? 0);
        $empresaFoco = $idEmpresaFoco > 0 ? $modelEmpresa->getEmpresaActivaPorId($idEmpresaFoco) : null;
        $nombreEmpresa = $empresaFoco['nombre_comercial'] ?? 'la empresa seleccionada';

        $msg = empty($asignadas)
            ? 'El usuario ya existe en el sistema y ya tenía asignada ' . $nombreEmpresa . '. Asígnele los módulos y permisos.'
            : 'El usuario ya existe en el sistema y fue asignado a ' . $nombreEmpresa . '. Ahora asígnele los módulos y permisos.';

        return [
            'ok'         => true,
            'msg'        => $msg,
            'id_usuario' => $idExistente,
            'id_empresa' => $idEmpresaFoco,
            'ya_existia' => true,
        ];
    }
    /**
     * RUC del proveedor del sistema de facturación (Res. NAC-DGERCGC26-00000027).
     * Config GLOBAL (tabla configuracion_sistema), solo nivel 3. GET muestra la
     * pantalla; POST guarda el nuevo RUC con auditoría en log_sistema.
     */
    public function sriProveedor(): void
    {
        $this->requireAuth();
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        if ($nivel < 3) {
            $_SESSION['config_msg'] = ['danger', 'Solo el super administrador puede configurar el RUC del proveedor.'];
            $this->redirect(BASE_URL . '/config');
            return;
        }

        $db = \App\core\Database::getConnection();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ruc = preg_replace('/\D/', '', (string) ($_POST['ruc_proveedor'] ?? ''));
            if ($ruc !== '' && strlen($ruc) !== 13) {
                $_SESSION['config_msg'] = ['danger', 'El RUC debe tener exactamente 13 dígitos (o dejarse vacío para desactivar el campo).'];
                $this->redirect(BASE_URL . '/config/sri-proveedor');
                return;
            }

            $st = $db->prepare("SELECT id, valor FROM configuracion_sistema WHERE clave = 'sri_ruc_proveedor_sistema' AND eliminado = false LIMIT 1");
            $st->execute();
            $fila = $st->fetch(\PDO::FETCH_ASSOC);

            $idUsuario = (int) $_SESSION['id_usuario'];
            if ($fila) {
                $up = $db->prepare("UPDATE configuracion_sistema SET valor = :valor, updated_at = NOW(), updated_by = :usr WHERE id = :id");
                $up->execute([':valor' => $ruc, ':usr' => $idUsuario, ':id' => (int) $fila['id']]);
            } else {
                $ins = $db->prepare("INSERT INTO configuracion_sistema (clave, valor, descripcion, created_by, updated_by)
                                     VALUES ('sri_ruc_proveedor_sistema', :valor, 'RUC del proveedor del sistema de facturación electrónica (Res. NAC-DGERCGC26-00000027).', :usr, :usr2)");
                $ins->execute([':valor' => $ruc, ':usr' => $idUsuario, ':usr2' => $idUsuario]);
            }

            (new \App\Services\LogSistemaService())->registrar(
                $idUsuario,
                null, // configuración global: sin empresa
                'actualizar',
                'configuracion_sistema',
                $fila ? (int) $fila['id'] : null,
                ['sri_ruc_proveedor_sistema' => $fila['valor'] ?? null],
                ['sri_ruc_proveedor_sistema' => $ruc]
            );

            $_SESSION['config_msg'] = ['success', 'RUC del proveedor guardado. Se incluirá como "RUC Proveedor" en la información adicional de los comprobantes que se emitan desde ahora.'];
            $this->redirect(BASE_URL . '/config/sri-proveedor');
            return;
        }

        $rucActual = '';
        try {
            $st = $db->prepare("SELECT valor FROM configuracion_sistema WHERE clave = 'sri_ruc_proveedor_sistema' AND eliminado = false LIMIT 1");
            $st->execute();
            $rucActual = trim((string) ($st->fetchColumn() ?: ''));
        } catch (\Throwable $e) {
            // Tabla aún no migrada: mostrar el respaldo de config/app.php.
        }
        if ($rucActual === '') {
            $rucActual = \App\Helpers\SriProveedorHelper::rucProveedor();
        }

        $this->viewWithLayout('layouts.main', 'config.sri_proveedor', [
            'titulo'     => 'RUC Proveedor (SRI)',
            'rucActual'  => $rucActual,
            'nombreCampo' => \App\Helpers\SriProveedorHelper::CAMPO_NOMBRE,
        ]);
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
