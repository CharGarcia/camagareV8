<?php
/**
 * Barra de búsqueda y filtros reutilizable para módulos
 * 
 * Uso:
 *   $search_placeholder = 'Nombre, dirección, correo...';
 *   $excel_url = $base . '/excel/clientes.php';
 *   $pdf_url = $base . '/pdf/clientes.php';
 *   $load_func = 'load';
 *   $hidden_inputs = ['ordenado' => 'cli.nombre', 'por' => 'asc'];
 *   $filters_html = ''; // Ej: '<select class="form-select form-select-sm" onchange="load(1)">...</select>'
 *   $search_id = 'q'; // id del input (opcional)
 *   include ROOT_PATH . '/includes/cmg-search-bar.php';
 */
$search_placeholder = $search_placeholder ?? 'Buscar...';
$excel_url = $excel_url ?? '';
$pdf_url = $pdf_url ?? '';
$load_func = $load_func ?? 'load';
$hidden_inputs = $hidden_inputs ?? [];
$filters_html = $filters_html ?? '';
$search_id = $search_id ?? 'q';
?>
<div class="cmg-search-bar">
	<form class="cmg-search-form" method="POST" onsubmit="return false;">
		<?php foreach ($hidden_inputs as $name => $value): ?>
		<input type="hidden" id="<?= htmlspecialchars($name) ?>" name="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars($value) ?>">
		<?php endforeach; ?>
		<div class="cmg-search-row">
			<div class="cmg-search-input-wrap">
				<input type="text" class="form-control cmg-search-input" id="<?= htmlspecialchars($search_id) ?>" 
					placeholder="<?= htmlspecialchars($search_placeholder) ?>" 
					onkeyup="typeof loadDebounced === 'function' ? loadDebounced() : <?= htmlspecialchars($load_func) ?>(1);" 
					autocomplete="off">
				<button type="button" class="btn btn-outline-secondary cmg-search-btn" onclick="<?= htmlspecialchars($load_func) ?>(1);" title="Buscar">
					<i class="fas fa-search"></i>
				</button>
			</div>
			<?php if (!empty($filters_html)): ?>
			<div class="cmg-search-filters">
				<?= $filters_html ?>
			</div>
			<?php endif; ?>
			<div class="cmg-search-actions">
				<?php if (!empty($excel_url)): ?>
				<a href="<?= htmlspecialchars($excel_url) ?>" class="btn btn-success btn-sm cmg-export-btn" title="Descargar Excel" target="_blank">
					<i class="fas fa-file-excel"></i>
				</a>
				<?php endif; ?>
				<?php if (!empty($pdf_url)): ?>
				<a href="<?= htmlspecialchars($pdf_url) ?>" class="btn btn-danger btn-sm cmg-export-btn" title="Descargar PDF" target="_blank">
					<i class="fas fa-file-pdf"></i>
				</a>
				<?php endif; ?>
			</div>
			<span class="cmg-search-loader" id="loader"></span>
			<div id="cmg-pagination-placeholder" class="cmg-pagination-inline"></div>
		</div>
	</form>
</div>
