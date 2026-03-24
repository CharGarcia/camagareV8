<?php
function paginate($reload, $page, $tpages, $adjacents, $numrows = null, $per_page = 20) {
	$total_pages = max(1, (int)$tpages);
	$current = max(1, min($page, $total_pages));
	$prev = $current - 1;
	$next = $current + 1;

	// Contador: 1-20 / 100 (rango de registros / total)
	if ($numrows !== null && $numrows > 0) {
		$first = ($current - 1) * $per_page + 1;
		$last = min($current * $per_page, $numrows);
		$info = $first . '-' . $last . ' / ' . $numrows;
	} else {
		$info = $current . '/' . $total_pages;
	}

	$out = '<nav aria-label="Paginación" class="cmg-pagination-simple">';
	$out .= '<span class="cmg-pagination-info">' . $info . '</span>';
	$out .= '<div class="btn-group btn-group-sm" role="group">';
	// Anterior
	if ($current <= 1) {
		$out .= '<button type="button" class="btn btn-outline-secondary" disabled title="Anterior"><i class="fas fa-chevron-left"></i></button>';
	} else {
		$out .= '<button type="button" class="btn btn-outline-secondary" onclick="load(' . $prev . ')" title="Anterior"><i class="fas fa-chevron-left"></i></button>';
	}
	// Siguiente
	if ($current >= $total_pages) {
		$out .= '<button type="button" class="btn btn-outline-secondary" disabled title="Siguiente"><i class="fas fa-chevron-right"></i></button>';
	} else {
		$out .= '<button type="button" class="btn btn-outline-secondary" onclick="load(' . $next . ')" title="Siguiente"><i class="fas fa-chevron-right"></i></button>';
	}
	$out .= '</div>';
	$out .= '</nav>';
	return $out;
}
?>