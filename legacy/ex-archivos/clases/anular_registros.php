<?php
class anular_registros
{

	//para anular un asiento contable cuando se anula una factura, retencion, compras...
	public function anular_asiento_contable($con, $numero_asiento)
	{
		ini_set('date.timezone', 'America/Guayaquil');
		$fecha_registro = date("Y-m-d H:i:s");
		
		$select_diario = mysqli_query($con, "SELECT codigo_unico, codigo_unico_bloque, tipo, ruc_empresa FROM encabezado_diario WHERE id_diario = '" . $numero_asiento . "' ");
		$row_encabezado = mysqli_fetch_array($select_diario);
		$codigo_unico = isset($row_encabezado['codigo_unico']) ? $row_encabezado['codigo_unico'] : "";
		$codigo_unico_bloque = isset($row_encabezado['codigo_unico_bloque']);
		if (!empty($codigo_unico)) {
			$delete_detalle_diario = mysqli_query($con, "DELETE FROM detalle_diario_contable 
			WHERE codigo_unico = '" . $codigo_unico . "' and codigo_unico_bloque = '" . $codigo_unico_bloque . "'  ");
		}
		$update_encabezado = mysqli_query($con, "UPDATE encabezado_diario SET codigo_unico='', estado='Anulado', fecha_registro='" . $fecha_registro . "' WHERE id_diario='" . $numero_asiento . "' ");
		if ($update_encabezado) {
			return true;
		} else {
			$query_insert  = mysqli_query($con, "INSERT INTO asientos_contables_no_anulados(id_asiento, tipo, ruc_empresa) VALUES('" . $numero_asiento . "', '" . $row_encabezado['tipo'] . "', '" . $row_encabezado['ruc_empresa'] . "')");
			return false;
		}
	}
}
