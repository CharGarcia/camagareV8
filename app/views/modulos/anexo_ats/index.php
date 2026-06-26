<?php
/** @var string $titulo */
/** @var array  $perm */
/** @var array  $empresa */
/** @var int    $anioActual */
/** @var string $rutaModulo */

$base    = BASE_URL;
$urlBase = $base . '/' . $rutaModulo;

$meses = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
];
$mesActual = date('m');
?>
<div class="container-fluid py-3">
  <div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex align-items-center">
      <i class="fas fa-file-code text-primary me-2"></i>
      <h5 class="mb-0"><?= htmlspecialchars($titulo) ?></h5>
    </div>
    <div class="card-body">

      <p class="text-muted small mb-3">
        Genera el archivo <code>ATmmaaaa.xml</code> del período a partir de las
        <strong>compras</strong>, <strong>liquidaciones de compra</strong> y sus
        <strong>retenciones</strong> registradas. El comprobante se reporta en el
        período de su fecha de registro contable.
      </p>

      <form id="form-ats" class="row g-3 align-items-end">
        <div class="col-12 col-md-3">
          <label class="form-label fw-semibold">Mes</label>
          <select class="form-select" name="mes" id="ats-mes">
            <?php foreach ($meses as $cod => $nom): ?>
              <option value="<?= $cod ?>" <?= $cod === $mesActual ? 'selected' : '' ?>><?= $nom ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label fw-semibold">Año</label>
          <select class="form-select" name="anio" id="ats-anio">
            <?php for ($a = $anioActual; $a >= $anioActual - 5; $a--): ?>
              <option value="<?= $a ?>"><?= $a ?></option>
            <?php endfor; ?>
          </select>
        </div>

        <div class="col-12 col-md-4" id="ats-semestral-wrap" style="display:none;">
          <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" value="1" id="ats-semestral" name="semestral">
            <label class="form-check-label" for="ats-semestral">
              Declarar semestral (régimen RIMPE)
            </label>
          </div>
        </div>

        <div class="col-12 col-md-2">
          <button type="submit" class="btn btn-primary w-100" id="ats-generar">
            <i class="fas fa-cogs me-1"></i> Generar
          </button>
        </div>
      </form>

      <div id="ats-resultado" class="mt-4"></div>

    </div>
  </div>
</div>

<script>
  window.BASE_URL   = '<?= $base ?>';
  window.R_MODULO   = '<?= $rutaModulo ?>';
  window.ID_EMPRESA = <?= (int) ($_SESSION['id_empresa'] ?? 0) ?>;
</script>
<script src="<?= $base ?>/js/modulos/anexo_ats.js?v=<?= time() ?>"></script>
