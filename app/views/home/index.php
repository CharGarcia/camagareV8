<?php
/** @var string $titulo */
/** @var bool $sinEmpresaSuperAdmin */
/** @var array $accesos  AccesosFrecuentesService::obtener() */
/** @var array|null $accesoDashboard  AccesosFrecuentesService::accesoDashboard(): va primero */
$sinEmpresaSuperAdmin = $sinEmpresaSuperAdmin ?? false;
$accesos = $accesos ?? [];
$accesoDashboard = $accesoDashboard ?? null;
$tarjetas = $accesoDashboard ? array_merge([$accesoDashboard], $accesos) : $accesos;
$base = rtrim(BASE_URL ?? '', '/');
?>

<?php if (!empty($tarjetas)): ?>
<style>
    .inicio-acceso {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: .45rem; height: 100%; min-height: 128px; padding: 1rem .5rem;
        background: #fff; border: 1px solid #e9ecef; border-radius: 12px;
        color: #495057; text-decoration: none; text-align: center;
        transition: border-color .15s, box-shadow .15s, transform .15s;
    }
    .inicio-acceso:hover, .inicio-acceso:focus-visible {
        border-color: var(--cmg-primary, #0d6efd); color: var(--cmg-primary, #0d6efd);
        box-shadow: 0 4px 14px rgba(13, 110, 253, .12); transform: translateY(-2px); outline: none;
    }
    .inicio-acceso__icono {
        display: flex; align-items: center; justify-content: center;
        width: 52px; height: 52px; border-radius: 14px; font-size: 1.5rem;
        background: rgba(13, 110, 253, .08); color: var(--cmg-primary, #0d6efd);
    }
    .inicio-acceso__nombre { font-size: .88rem; font-weight: 600; line-height: 1.2; }
    .inicio-acceso__modulo { font-size: .72rem; color: #6c757d; }
    .inicio-acceso--dashboard { border-color: rgba(13, 110, 253, .35); }
    .inicio-acceso--dashboard .inicio-acceso__icono { background: var(--cmg-primary, #0d6efd); color: #fff; }
    /* Encabezado compacto: logo + CaMaGaRe ERP + acrónimo, saludo a la derecha */
    .inicio-encabezado {
        display: flex; flex-wrap: wrap; align-items: center; gap: .75rem 1.25rem;
        padding: .75rem 1rem; margin-bottom: 1.25rem;
        background: #fff; border: 1px solid #e9ecef; border-radius: 12px;
    }
    .inicio-encabezado__logo { height: 64px; width: auto; flex-shrink: 0; }
    .inicio-encabezado__marca { flex: 1 1 320px; min-width: 0; }
    .inicio-encabezado__titulo { font-size: 1.8rem; line-height: 1.1; margin: 0 0 .2rem; }
    .inicio-encabezado__acronimo { font-size: .8rem; font-style: italic; color: #6c757d; margin: 0; }
    .inicio-encabezado__saludo { font-size: 1rem; font-weight: 600; margin: 0; white-space: nowrap; }
    @media (max-width: 575.98px) {
        .inicio-encabezado__logo { height: 48px; }
        .inicio-encabezado__titulo { font-size: 1.45rem; }
        .inicio-encabezado__saludo { flex-basis: 100%; white-space: normal; }
    }
</style>
<div class="inicio-encabezado">
    <img src="<?= $base ?>/image/logofinal.png" alt="CaMaGaRe ERP" class="inicio-encabezado__logo">
    <div class="inicio-encabezado__marca">
        <p class="inicio-encabezado__titulo"><span class="fw-bold text-primary">CaMaGaRe</span> <span class="text-muted">ERP</span></p>
        <p class="inicio-encabezado__acronimo">
            <span class="fw-bold text-dark">C</span>ontrol <span class="fw-bold text-dark">A</span>dministrativo ·
            <span class="fw-bold text-dark">M</span>anejo <span class="fw-bold text-dark">A</span>utomatizado ·
            <span class="fw-bold text-dark">G</span>estión <span class="fw-bold text-dark">A</span>nalítica ·
            <span class="fw-bold text-dark">R</span>esultados <span class="fw-bold text-dark">E</span>mpresariales
        </p>
    </div>
    <p class="inicio-encabezado__saludo"><i class="bi bi-person-circle text-primary me-1"></i>Bienvenid@<?= !empty($_SESSION['nombre']) ? ', ' . htmlspecialchars($_SESSION['nombre']) : '' ?></p>
</div>
<div class="text-muted small mb-2"><i class="bi bi-lightning-charge me-1"></i>Tus accesos frecuentes: las pantallas que más has usado últimamente en esta empresa.</div>
<div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-xl-6 g-3">
    <?php foreach ($tarjetas as $i => $a): ?>
    <div class="col">
        <a class="inicio-acceso<?= ($accesoDashboard && $i === 0) ? ' inicio-acceso--dashboard' : '' ?>" href="<?= htmlspecialchars($a['href']) ?>"<?= $a['nueva_pestana'] ? ' target="_blank" rel="noopener"' : '' ?>
           title="<?= htmlspecialchars($a['modulo'] . ' › ' . $a['nombre']) ?>">
            <span class="inicio-acceso__icono"><i class="<?= htmlspecialchars(iconoClase($a['icono'])) ?>"></i></span>
            <span class="inicio-acceso__nombre"><?= htmlspecialchars($a['nombre']) ?></span>
            <span class="inicio-acceso__modulo"><?= htmlspecialchars($a['modulo']) ?></span>
        </a>
    </div>
    <?php endforeach; ?>
</div>
<p class="text-muted small mt-3 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    <?= empty($accesos)
        ? 'Aquí aparecerán las pantallas que más uses: la lista se arma sola a medida que trabajas.'
        : 'La lista se arma sola según tu uso.' ?>
    <?php if ($accesoDashboard): ?>
    Para entrar directo al dashboard al ingresar, usa el botón
    <i class="bi bi-sliders2"></i> <strong>Personalizar menú</strong> → <em>Al ingresar mostrar</em>.
    <?php endif; ?>
</p>
<?php else: ?>

<?php if ($sinEmpresaSuperAdmin): ?>
<div class="alert alert-info border-0 shadow-sm rounded-3 mb-3 d-flex align-items-center">
    <i class="bi bi-info-circle-fill fs-4 me-3"></i>
    <div>
        <strong class="d-block mb-1">Super administrador sin empresa activa.</strong>
        Cree la primera empresa en <a href="<?= $base ?>/config/empresas-sistema" class="alert-link fw-semibold">Empresas del sistema</a>.
    </div>
</div>
<?php endif; ?>

<div class="d-flex align-items-center justify-content-center" style="min-height:60vh">
    <div class="text-center">
        <img src="<?= $base ?>/image/logofinal.png" alt="CaMaGaRe ERP" class="mb-3 d-block mx-auto" style="max-height:140px;width:auto">
        <h4 class="fw-bold mb-3">Bienvenid@<?= !empty($_SESSION['nombre']) ? ', ' . htmlspecialchars($_SESSION['nombre']) : '' ?></h4>
        <p class="mb-3" style="font-size:5rem;line-height:1.1"><span class="fw-bold text-primary">CaMaGaRe</span> <span class="text-muted">ERP</span></p>
        <p class="fst-italic text-muted mb-0">
            <span class="fw-bold text-dark">C</span>ontrol <span class="fw-bold text-dark">A</span>dministrativo &nbsp;--&nbsp;
            <span class="fw-bold text-dark">M</span>anejo <span class="fw-bold text-dark">A</span>utomatizado &nbsp;--&nbsp;
            <span class="fw-bold text-dark">G</span>estión <span class="fw-bold text-dark">A</span>nalítica &nbsp;--&nbsp;
            <span class="fw-bold text-dark">R</span>esultados <span class="fw-bold text-dark">E</span>mpresariales
        </p>
    </div>
</div>
<?php endif; ?>
