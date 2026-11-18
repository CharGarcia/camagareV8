<?php

/** @var string $titulo */
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var string $buscar */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var array $bancos */

$base = BASE_URL;
$urlBaseEmp = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .emp-scroll {
        max-height: calc(100dvh - 250px);
        overflow-y: auto;
    }

    .emp-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #f8f9fa;
    }

    .empleado-row {
        cursor: pointer;
    }

    .empleado-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }

    .scrollbar-hidden::-webkit-scrollbar {
        display: none;
    }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-people-fill me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalCrear()">
            <i class="bi bi-plus-lg me-1"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar (FiltrosModal), igual que Proveedores: texto libre sobre las
            // columnas del listado + botón embudo con todos los filtros + chips de los
            // activos. Las claves (key) deben existir en los mapas de
            // EmpleadoRepository::getListado().
            $opcFiltro   = $opcionesFiltro ?? [];
            $opcIdNombre = fn(string $k) => array_map(fn($x) => ['v' => (string) $x['id'], 'l' => (string) $x['nombre']], $opcFiltro[$k] ?? []);
            $opcTexto    = fn(string $k) => array_map(fn($x) => ['v' => (string) $x, 'l' => (string) $x], $opcFiltro[$k] ?? []);
            $opcSiNo     = fn(string $si, string $no) => [['v' => 'si', 'l' => $si], ['v' => 'no', 'l' => $no]];
            $opcDecimo   = [['v' => 'acumula', 'l' => 'Acumula'], ['v' => 'mensualiza', 'l' => 'Mensualiza'], ['v' => 'no_recibe', 'l' => 'No recibe']];
            $tE = 'Empleado';
            // Filas de 12 columnas:
            //   Identificación:  [Tipo 3][Identificación 3][Nombre 3][Estado 3]
            //   Datos personales:[Sexo 3][Fecha de nacimiento 3][Discapacidad 3][Cargas 3]
            //   Contacto:        [Correo 3][Con correo 3][Teléfono 3][Dirección 3]
            //   Trabajo:         [Cargo 3][Departamento 3][Lugar 3][Región 3]
            //   Remuneración:    [Sueldo 4][Semanal 4][Quincena 4]
            //   Beneficios:      [Aporta IESS 4][Utilidades 4][Fondos de reserva 4][Décimo 3.º 6][Décimo 4.º 6]
            //   Pago:            [Banco 4][Tipo de cuenta 4][Cuenta registrada 4]
            //   Registro:        [Fecha de registro 6][Usuario 6]
            $filtrosEmpleados = [
                // ── Identificación ──
                ['tab' => $tE, 'key' => 'tipo', 'label' => 'Tipo identificación', 'icon' => 'bi-credit-card', 'type' => 'select', 'grupo' => 'Identificación', 'col' => 3, 'options' => [
                    ['v' => 'cedula',    'l' => 'Cédula'],
                    ['v' => 'pasaporte', 'l' => 'Pasaporte'],
                ]],
                ['tab' => $tE, 'key' => 'identificacion', 'label' => 'Identificación',     'icon' => 'bi-card-text', 'type' => 'text', 'grupo' => 'Identificación', 'col' => 3],
                ['tab' => $tE, 'key' => 'nombre',         'label' => 'Nombres y apellidos', 'icon' => 'bi-person',    'type' => 'text', 'grupo' => 'Identificación', 'col' => 3],
                ['tab' => $tE, 'key' => 'estado',         'label' => 'Estado',             'icon' => 'bi-flag',      'type' => 'select', 'grupo' => 'Identificación', 'col' => 3, 'options' => [
                    ['v' => 'activo',   'l' => 'Activo'],
                    ['v' => 'inactivo', 'l' => 'Inactivo'],
                ]],
                // ── Datos personales ──
                ['tab' => $tE, 'key' => 'sexo', 'label' => 'Sexo', 'icon' => 'bi-gender-ambiguous', 'type' => 'select', 'grupo' => 'Datos personales', 'col' => 3, 'options' => [
                    ['v' => 'M', 'l' => 'Masculino'],
                    ['v' => 'F', 'l' => 'Femenino'],
                    ['v' => 'O', 'l' => 'Otro'],
                ]],
                ['tab' => $tE, 'key' => 'nacimiento',   'label' => 'Fecha de nacimiento', 'icon' => 'bi-cake2',         'type' => 'date_range',   'grupo' => 'Datos personales', 'col' => 3],
                ['tab' => $tE, 'key' => 'discapacidad', 'label' => 'Discapacidad',        'icon' => 'bi-universal-access', 'type' => 'select',    'grupo' => 'Datos personales', 'col' => 3, 'options' => $opcSiNo('Con discapacidad', 'Sin discapacidad')],
                ['tab' => $tE, 'key' => 'cargas',       'label' => 'Cargas familiares',   'icon' => 'bi-people',        'type' => 'number_range', 'grupo' => 'Datos personales', 'col' => 3],
                // ── Contacto ──
                ['tab' => $tE, 'key' => 'email',     'label' => 'Correo',            'icon' => 'bi-envelope',       'type' => 'text',   'grupo' => 'Contacto', 'col' => 3],
                ['tab' => $tE, 'key' => 'con_email', 'label' => 'Correo registrado', 'icon' => 'bi-envelope-check', 'type' => 'select', 'grupo' => 'Contacto', 'col' => 3, 'options' => $opcSiNo('Con correo', 'Sin correo')],
                ['tab' => $tE, 'key' => 'telefono',  'label' => 'Teléfono',          'icon' => 'bi-telephone',      'type' => 'text',   'grupo' => 'Contacto', 'col' => 3],
                ['tab' => $tE, 'key' => 'direccion', 'label' => 'Dirección',         'icon' => 'bi-geo',            'type' => 'text',   'grupo' => 'Contacto', 'col' => 3],
                // ── Trabajo ──
                ['tab' => $tE, 'key' => 'cargo_sel', 'label' => 'Cargo',            'icon' => 'bi-briefcase',   'type' => 'select', 'grupo' => 'Trabajo', 'col' => 3, 'options' => $opcTexto('cargos')],
                ['tab' => $tE, 'key' => 'depto_sel', 'label' => 'Departamento',     'icon' => 'bi-diagram-3',   'type' => 'select', 'grupo' => 'Trabajo', 'col' => 3, 'options' => $opcTexto('departamentos')],
                ['tab' => $tE, 'key' => 'lugar_sel', 'label' => 'Lugar de trabajo', 'icon' => 'bi-building',    'type' => 'select', 'grupo' => 'Trabajo', 'col' => 3, 'options' => $opcTexto('lugares')],
                ['tab' => $tE, 'key' => 'region',    'label' => 'Región',           'icon' => 'bi-map',         'type' => 'select', 'grupo' => 'Trabajo', 'col' => 3, 'options' => [
                    ['v' => 'costa',   'l' => 'Costa'],
                    ['v' => 'sierra',  'l' => 'Sierra'],
                    ['v' => 'oriente', 'l' => 'Oriente'],
                    ['v' => 'insular', 'l' => 'Insular'],
                ]],
                // ── Remuneración ──
                ['tab' => $tE, 'key' => 'sueldo',   'label' => 'Sueldo base',     'icon' => 'bi-cash-stack', 'type' => 'number_range', 'grupo' => 'Remuneración', 'col' => 4],
                ['tab' => $tE, 'key' => 'semanal',  'label' => 'Valor semanal',   'icon' => 'bi-cash',       'type' => 'number_range', 'grupo' => 'Remuneración', 'col' => 4],
                ['tab' => $tE, 'key' => 'quincena', 'label' => 'Valor quincena',  'icon' => 'bi-cash',       'type' => 'number_range', 'grupo' => 'Remuneración', 'col' => 4],
                // ── Beneficios ──
                ['tab' => $tE, 'key' => 'aporta_iess', 'label' => 'Aporta al IESS',        'icon' => 'bi-shield-check', 'type' => 'select', 'grupo' => 'Beneficios', 'col' => 4, 'options' => $opcSiNo('Sí aporta', 'No aporta')],
                ['tab' => $tE, 'key' => 'utilidades',  'label' => 'Participa en utilidades', 'icon' => 'bi-pie-chart', 'type' => 'select', 'grupo' => 'Beneficios', 'col' => 4, 'options' => $opcSiNo('Sí participa', 'No participa')],
                ['tab' => $tE, 'key' => 'fondos',      'label' => 'Fondos de reserva',     'icon' => 'bi-piggy-bank',   'type' => 'select', 'grupo' => 'Beneficios', 'col' => 4, 'options' => [
                    ['v' => 'no_se_paga', 'l' => 'No se paga'],
                    ['v' => 'rol',        'l' => 'En rol mensual'],
                    ['v' => 'desde_anio', 'l' => 'A partir del año'],
                    ['v' => 'planilla',   'l' => 'Planilla IESS'],
                ]],
                ['tab' => $tE, 'key' => 'decimo_tercero', 'label' => 'Décimo tercero', 'icon' => 'bi-gift', 'type' => 'select', 'grupo' => 'Beneficios', 'col' => 6, 'options' => $opcDecimo],
                ['tab' => $tE, 'key' => 'decimo_cuarto',  'label' => 'Décimo cuarto',  'icon' => 'bi-gift', 'type' => 'select', 'grupo' => 'Beneficios', 'col' => 6, 'options' => $opcDecimo],
                // ── Pago ──
                ['tab' => $tE, 'key' => 'id_banco',    'label' => 'Banco',          'icon' => 'bi-bank',        'type' => 'select', 'grupo' => 'Pago', 'col' => 4, 'options' => $opcIdNombre('bancos')],
                ['tab' => $tE, 'key' => 'tipo_cuenta', 'label' => 'Tipo de cuenta', 'icon' => 'bi-wallet2',     'type' => 'select', 'grupo' => 'Pago', 'col' => 4, 'options' => [
                    ['v' => 'ahorros',   'l' => 'Ahorros'],
                    ['v' => 'corriente', 'l' => 'Corriente'],
                    ['v' => 'virtual',   'l' => 'Virtual'],
                ]],
                ['tab' => $tE, 'key' => 'con_cuenta',  'label' => 'Cuenta bancaria', 'icon' => 'bi-credit-card-2-front', 'type' => 'select', 'grupo' => 'Pago', 'col' => 4, 'options' => $opcSiNo('Con cuenta', 'Sin cuenta')],
                // ── Registro ──
                ['tab' => $tE, 'key' => 'registro', 'label' => 'Fecha de registro',    'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Registro', 'col' => 6, 'atajos' => true],
                ['tab' => $tE, 'key' => 'usuario',  'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',    'type' => 'select',     'grupo' => 'Registro', 'col' => 6, 'options' => $opcIdNombre('usuarios')],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorEMP"></div>
            <input type="hidden" id="inputBuscar" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorEMP',
                        hiddenInputId: 'inputBuscar',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de empleados',
                        inputWidth: 420,
                        extraId: 'fmExtraEMP',   // columnas + PDF + Excel + Importar, pegados al final del grupo
                        fields: <?= json_encode($filtrosEmpleados, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyEmpleados',   // se atenúa mientras se busca
                        onApply: () => window.cambiarPaginaAjax && window.cambiarPaginaAjax(1),
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraEMP" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'identificacion'    => 'Identificación',
                    'nombres_apellidos' => 'Nombre',
                    'email'             => 'Correo',
                    'telefono'          => 'Teléfono',
                    'sexo'              => 'Sexo',
                    'fecha_nacimiento'  => 'F. Nac.',
                    'direccion'         => 'Dirección',
                    'cargo'             => 'Cargo',
                    'departamento'      => 'Departamento',
                    'sueldo_base'       => 'Sueldo Base',
                    'valor_semanal'     => 'V. Semanal',
                    'valor_quincena'    => 'V. Quincena',
                    'region'            => 'Región',
                    'nombre_banco'      => 'Banco',
                    'tipo_cuenta'       => 'T. Cuenta',
                    'numero_cuenta'     => 'N. Cuenta',
                    'tipo_id'           => 'Tipo ID',
                    'estado'            => 'Estado'
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="btnExportPdf" href="<?= $urlBaseEmp ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-danger" title="Descargar PDF"><i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span></a>
                <a id="btnExportExcel" href="<?= $urlBaseEmp ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-success" title="Descargar Excel"><i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span></a>
                <?php if (!empty($perm['crear'])): ?>
                    <button type="button" class="btn btn-outline-primary" title="Cargar empleados desde Excel" onclick="window.abrirImportEmp()"><i class="bi bi-upload"></i><span class="d-none d-md-inline"> Importar</span></button>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?> / <?= $total ?></span>
            <div id="wrapper-pagination" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="emp-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="identificacion" role="button" data-col="identificacion">ID <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nombres_apellidos" role="button" data-col="nombres_apellidos">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="email" role="button" data-col="email">Correo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="telefono" role="button" data-col="telefono">Teléfono <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="sexo" role="button" data-col="sexo">Sexo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="fecha_nacimiento" role="button" data-col="fecha_nacimiento">F. Nac. <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="direccion" role="button" data-col="direccion">Dirección <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="cargo" role="button" data-col="cargo">Cargo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="departamento" role="button" data-col="departamento">Depto <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="sueldo_base" role="button" data-col="sueldo_base">Sueldo Base <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="valor_semanal" role="button" data-col="valor_semanal">V. Semanal <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="valor_quincena" role="button" data-col="valor_quincena">V. Quincena <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="region" role="button" data-col="region">Región <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nombre_banco" role="button" data-col="nombre_banco">Banco <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="tipo_cuenta" role="button" data-col="tipo_cuenta">T. Cuenta <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="numero_cuenta" role="button" data-col="numero_cuenta">N. Cuenta <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="tipo_id" role="button" data-col="tipo_id">Tipo ID <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="estado" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody id="tbodyEmpleados">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="19" class="text-center py-5 text-muted">No se encontraron empleados registrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr class="empleado-row" onclick="abrirModalEditar(this)" data-row='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'>
                                <td class="ps-3" data-col="identificacion"><code class="text-secondary"><?= htmlspecialchars((string)($row['identificacion'] ?? '')) ?></code></td>
                                <td class="fw-medium" data-col="nombres_apellidos"><?= htmlspecialchars((string)($row['nombres_apellidos'] ?? '')) ?></td>
                                <td data-col="email"><?= htmlspecialchars((string)($row['email'] ?? '-')) ?></td>
                                <td data-col="telefono"><?= htmlspecialchars((string)($row['telefono'] ?? '-')) ?></td>
                                <td class="text-center" data-col="sexo"><?= htmlspecialchars((string)($row['sexo'] ?? '-')) ?></td>
                                <td data-col="fecha_nacimiento"><?= htmlspecialchars((string)($row['fecha_nacimiento'] ?? '-')) ?></td>
                                <td data-col="direccion" class="small text-muted"><?= htmlspecialchars((string)($row['direccion'] ?? '-')) ?></td>
                                <td data-col="cargo"><?= htmlspecialchars((string)($row['cargo'] ?? '-')) ?></td>
                                <td data-col="departamento"><?= htmlspecialchars((string)($row['departamento'] ?? '-')) ?></td>
                                <td data-col="sueldo_base" class="text-end fw-bold">$<?= number_format((float)($row['sueldo_base'] ?? 0), 2) ?></td>
                                <td data-col="valor_semanal" class="text-end">$<?= number_format((float)($row['valor_semanal'] ?? 0), 2) ?></td>
                                <td data-col="valor_quincena" class="text-end">$<?= number_format((float)($row['valor_quincena'] ?? 0), 2) ?></td>
                                <td data-col="region" class="text-capitalize"><?= htmlspecialchars((string)($row['region'] ?? '-')) ?></td>
                                <td data-col="nombre_banco"><?= htmlspecialchars((string)($row['nombre_banco'] ?? '-')) ?></td>
                                <td data-col="tipo_cuenta" class="text-capitalize"><?= htmlspecialchars((string)($row['tipo_cuenta'] ?? '-')) ?></td>
                                <td data-col="numero_cuenta"><?= htmlspecialchars((string)($row['numero_cuenta'] ?? '-')) ?></td>
                                <td class="text-center" data-col="tipo_id"><span class="small text-muted"><?= htmlspecialchars((string)($row['tipo_id'] ?? '')) ?></span></td>
                                <td class="text-center" data-col="estado">
                                    <span class="badge bg-<?= ($row['estado'] ?? 'activo') === 'activo' ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= ($row['estado'] ?? 'activo') === 'activo' ? 'success' : 'secondary' ?> border border-<?= ($row['estado'] ?? 'activo') === 'activo' ? 'success' : 'secondary' ?> border-opacity-25">
                                        <?= ucfirst($row['estado'] ?? 'activo') ?>
                                    </span>
                                </td>
                                <td class="text-center pe-3" onclick="event.stopPropagation()">
                                    <button class="btn btn-outline-danger btn-xs border-0 px-2" onclick="eliminarRegistro(<?= $row['id'] ?>)" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    window.BASE_URL = '<?= $base ?>';
</script>
<?php include 'modal_empleado.php'; ?>
<script src="<?= $base ?>/js/modulos/empleados_modal.js?v=<?= asset_ver('/js/modulos/empleados_modal.js') ?>"></script>

<script>
    (function() {
        'use strict';
        const urlBase = '<?= $urlBaseEmp ?>';
        const inputB = document.getElementById('inputBuscar');
        let currentSort = '<?= $ordenCol ?>';
        let currentDir = '<?= $ordenDir ?>';
        let timer;

        window.cambiarPaginaAjax = (p) => cargarListado(p);

        async function cargarListado(page = 1) {
            const b = inputB ? inputB.value.trim() : '';
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${currentSort}&dir=${currentDir}`;
            // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa mientras
            // carga, también al paginar u ordenar.
            const tbody = document.getElementById('tbodyEmpleados');
            if (tbody) tbody.classList.add('fm-cargando-target');
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyEmpleados').innerHTML = data.rows;
                    document.getElementById('wrapper-pagination').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.getElementById('btnExportPdf').href = data.pdf_url;
                    document.getElementById('btnExportExcel').href = data.excel_url;

                    document.querySelectorAll('.sortable-header').forEach(th => {
                        const icon = th.querySelector('i');
                        if (!icon) return;
                        if (th.dataset.sort === currentSort) {
                            icon.className = (currentDir.toLowerCase() === 'asc') ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1';
                        } else icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                    });
                }
            } catch (e) {
                console.error('Error en búsqueda de empleados:', e);
            } finally {
                if (tbody) tbody.classList.remove('fm-cargando-target');
            }
        }

        window.CMG_initSort('empleados', (col, dir) => {
            currentSort = col;
            currentDir = dir;
            cargarListado(1);
        }, { col: currentSort, dir: currentDir });

        if (inputB) inputB.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => cargarListado(1), 400);
        });
    })();
</script>

<!-- Modal Importar Empleados -->
<div class="modal fade" id="modalImportEmp" tabindex="-1" aria-hidden="true" style="z-index:1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-upload me-2 text-primary"></i>Importar Empleados desde Excel</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small py-2 mb-3">
                    Descarga la <a href="<?= $urlBaseEmp ?>/plantilla-excel" class="fw-bold">plantilla</a>, complétala y súbela.
                    Solo <b>IDENTIFICACION</b> y <b>NOMBRES_APELLIDOS</b> son obligatorios; el resto es opcional
                    (ver hoja «Referencia» para los valores válidos). Los aportes IESS se asignan automáticamente.
                </div>
                <input type="file" id="emp_import_file" class="form-control form-control-sm" accept=".xlsx,.xls">
                <div id="emp_import_result" class="mt-3"></div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <a href="<?= $urlBaseEmp ?>/plantilla-excel" class="btn btn-outline-secondary btn-sm me-auto"><i class="bi bi-download me-1"></i>Plantilla</a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnImportarEmp" onclick="window.importarEmp()"><i class="bi bi-upload me-1"></i> Importar</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        'use strict';
        const urlImp = '<?= $urlBaseEmp ?>';
        let modalImp = null;
        const esc = (s) => (s == null ? '' : String(s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])));

        window.abrirImportEmp = function () {
            document.getElementById('emp_import_file').value = '';
            document.getElementById('emp_import_result').innerHTML = '';
            if (!modalImp && typeof bootstrap !== 'undefined') modalImp = new bootstrap.Modal(document.getElementById('modalImportEmp'));
            modalImp?.show();
        };

        window.importarEmp = async function () {
            const fileInput = document.getElementById('emp_import_file');
            if (!fileInput.files.length) { Swal.fire({ icon: 'info', title: 'Seleccione un archivo', timer: 1500, showConfirmButton: false }); return; }
            const btn = document.getElementById('btnImportarEmp');
            btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Importando...';
            const cont = document.getElementById('emp_import_result');
            cont.innerHTML = '';
            try {
                const fd = new FormData(); fd.append('archivo', fileInput.files[0]);
                const resp = await fetch(`${urlImp}/importar-excel`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (!json.ok) {
                    cont.innerHTML = `<div class="alert alert-danger small py-2 mb-0">${esc(json.error)}</div>`;
                } else {
                    let html = `<div class="alert alert-success small py-2 mb-2"><b>${json.creadas}</b> empleado(s) importado(s) de ${json.total}.</div>`;
                    if (json.errores && json.errores.length) {
                        html += `<div class="alert alert-warning small py-2 mb-0" style="max-height:220px;overflow:auto;"><b>${json.errores.length} fila(s) con error:</b><ul class="mb-0 mt-1 ps-3">`;
                        json.errores.forEach(e => { html += `<li>Fila ${e.fila}: ${esc(e.error)}</li>`; });
                        html += '</ul></div>';
                    }
                    cont.innerHTML = html;
                    if (json.creadas > 0 && typeof window.cambiarPaginaAjax === 'function') window.cambiarPaginaAjax(window.currentPage || 1);
                }
            } catch (e) {
                cont.innerHTML = '<div class="alert alert-danger small py-2 mb-0">Error de red al importar.</div>';
            }
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-upload me-1"></i> Importar';
        };
    })();
</script>