<?php
$empresas = $empresas ?? [];
$nombre = $nombre ?? '';
$base = BASE_URL;
$idEmpresaSel = (int) ($_SESSION['id_empresa'] ?? 0);
$empresaSel = null;
foreach ($empresas as $e) {
    if ((int)($e['id_empresa'] ?? 0) === $idEmpresaSel) {
        $empresaSel = $e;
        break;
    }
}
$valorInicial = $empresaSel ? ($empresaSel['nombre_comercial'] ?? $empresaSel['ruc'] ?? '') : '';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary cmg-navbar-compact">
    <div class="container-fluid gap-2 align-items-center py-0">
        <!-- Brand CaMaGaRe -->
        <a class="navbar-brand text-white fw-bold text-decoration-none py-0" href="<?= $base ?>/home/index">CaMaGaRe</a>

        <!-- Toggler para móvil -->
        <button class="navbar-toggler cmg-navbar-toggler border-0 py-1 px-2" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Abrir menú">
            <i class="bi bi-list text-white"></i>
        </button>

        <!-- Contenido colapsable -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center gap-2 gap-lg-0 w-100 py-2 py-lg-0">
                <!-- Select empresas -->
                <form id="form-cambiar-empresa" method="POST" action="<?= $base ?>/empresa/setEmpresa" class="cmg-empresas-form cmg-empresas-dropdown-wrap order-1 order-lg-2">
                    <input type="hidden" name="id_usuario" value="<?= (int) ($_SESSION['id_usuario'] ?? 0) ?>">
                    <input type="hidden" name="id_empresa" id="input-id-empresa" value="<?= (int) ($_SESSION['id_empresa'] ?? 0) ?>">
                    <input type="hidden" name="ruc_empresa" id="input-ruc-empresa" value="<?= htmlspecialchars($_SESSION['ruc_empresa'] ?? '') ?>">
                    <input type="text" id="input-empresas" class="form-control cmg-empresas-input" autocomplete="off" 
                           placeholder="Seleccionar empresa..." 
                           value="<?= htmlspecialchars($valorInicial) ?>"
                           data-options="<?= htmlspecialchars(json_encode(array_map(function($e) {
                               return ['id' => (int)$e['id_empresa'], 'text' => $e['nombre_comercial'] ?? $e['ruc'] ?? '', 'ruc' => $e['ruc'] ?? ''];
                           }, $empresas))) ?>">
                    <div class="cmg-empresas-dropdown" id="dropdown-empresas" role="listbox">
                        <?php foreach ($empresas as $emp): ?>
                        <div class="cmg-empresas-dropdown-item" role="option" data-id="<?= (int)($emp['id_empresa'] ?? 0) ?>" data-text="<?= htmlspecialchars($emp['nombre_comercial'] ?? $emp['ruc'] ?? '') ?>" data-ruc="<?= htmlspecialchars($emp['ruc'] ?? '') ?>">
                            <?= htmlspecialchars($emp['nombre_comercial'] ?? $emp['ruc'] ?? '') ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </form>

                <!-- Label mensajes -->
                <span class="navbar-text text-white-50 text-center text-lg-center small order-2 order-lg-3 flex-grow-1" id="navbar-mensajes">&nbsp;</span>

                <!-- Usuario, config, logout -->
                <div class="d-flex align-items-center justify-content-center justify-content-lg-end gap-2 order-3 order-lg-4">
                    <a href="<?= $base ?>/perfil" class="text-white text-decoration-none" style="font-size:0.8rem" title="Mi perfil"><i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($nombre) ?></a>
                    <a href="<?= $base ?>/config" class="btn btn-outline-light btn-sm cmg-navbar-btn" title="Configuración">
                        <i class="bi bi-gear-fill"></i>
                    </a>
                    <a href="/sistema/legacy/includes/logout.php" class="btn btn-outline-light btn-sm cmg-navbar-btn" title="Cerrar sesión">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>
