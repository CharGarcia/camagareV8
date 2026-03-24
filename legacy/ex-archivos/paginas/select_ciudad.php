<?php
/**
 * Devuelve opciones HTML de ciudades filtradas por provincia.
 * Relación: provincia.codigo = ciudad.cod_prov
 * POST: codigo_provincia
 */
include("../conexiones/conectalogin.php");
$codigo_provincia = isset($_POST['codigo_provincia']) ? trim($_POST['codigo_provincia']) : '';
$conexion = conenta_login();

echo '<option value="">Seleccione ciudad</option>';
if (!empty($codigo_provincia)) {
	$codigo_provincia = mysqli_real_escape_string($conexion, $codigo_provincia);
	// Normalizar a 2 dígitos (Ecuador: 01, 02, ...) por si provincia envía "1" en vez de "01"
	$cod_pad = str_pad($codigo_provincia, 2, '0', STR_PAD_LEFT);
	$res = mysqli_query($conexion, "SELECT codigo, nombre FROM ciudad WHERE cod_prov = '$cod_pad' ORDER BY nombre ASC");
	if ($res) {
		while ($p = mysqli_fetch_assoc($res)) {
			echo '<option value="' . htmlspecialchars($p['codigo']) . '">' . htmlspecialchars($p['nombre']) . '</option>';
		}
	}
}
?>
