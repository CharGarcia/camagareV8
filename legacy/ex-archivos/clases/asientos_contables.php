<?php

class asientos_contables
{

	public function guarda_asiento($con, $fecha_asiento, $concepto_asiento, $tipo_asiento, $id_documento, $ruc_empresa, $id_usuario, $id_cli_pro)
	{
		$codigo_unico = codigo_unico(20);
		ini_set('date.timezone', 'America/Guayaquil');
		$fecha_registro = date("Y-m-d H:i:s");

		switch ($tipo_asiento) {
			case 'DIARIO':
				$codigo_unico = $codigo_unico;
				$id_documento = $id_documento;
				break;
			case 'VENTAS':
				$codigo_unico = "FAC" . $id_documento;
				$id_documento = $codigo_unico;
				break;
			case 'COMPRAS_SERVICIOS':
				$codigo_unico = "COM" . $id_documento;
				$id_documento = $codigo_unico;
				break;
			case 'RETENCIONES_VENTAS':
				$codigo_unico = "RETVEN" . $id_documento;
				$id_documento = $codigo_unico;
				break;
			case 'RETENCIONES_COMPRAS':
				$codigo_unico = "RETCOM" . $id_documento;
				$id_documento = $codigo_unico;
				break;
			case 'NC_VENTAS':
				$codigo_unico = "NCV" . $id_documento;
				$id_documento = $codigo_unico;
				break;
			case 'INGRESOS':
				$codigo_unico = "ING" . $id_documento;
				$id_documento = $codigo_unico;
				break;
			case 'EGRESOS':
				$codigo_unico = "EGR" . $id_documento;
				$id_documento = $codigo_unico;
				break;
			case 'RECIBOS':
				$codigo_unico = "REC" . $id_documento;
				$id_documento = $codigo_unico;
				break;
			case 'BALANCE_INICIAL':
				$codigo_unico = $codigo_unico;
				$id_documento = $id_documento;
				break;
			case 'ROL_PAGOS':
				$codigo_unico = "ROL_PAGOS" . $id_documento;
				$id_documento = $codigo_unico;
				break;
			default:
				$codigo_unico = $codigo_unico;
				$id_documento = $id_documento;
				break;
		}
		$encabezado_repetido = $this->encabezadoRepetido($con, $ruc_empresa, $fecha_asiento, $concepto_asiento, $tipo_asiento);
		$detalle_repetido = 0;

		if ($encabezado_repetido > 0) {
			return "2";
		} else if ($detalle_repetido > 0) {
			return "3";
		} else {
			/* $encabezado = mysqli_query($con, "INSERT INTO encabezado_diario VALUES (null,'" . $ruc_empresa . "','" . $codigo_unico . "','" . $fecha_asiento . "','" . $concepto_asiento . "','ok','" . $id_usuario . "','" . $fecha_registro . "','" . $tipo_asiento . "', '" . $id_documento . "','" . $codigo_unico . "')");
			$detalle_diario_contable = mysqli_query($con, "INSERT INTO detalle_diario_contable (id_detalle_cuenta, ruc_empresa, codigo_unico, id_cuenta, debe, haber, detalle_item, codigo_unico_bloque, id_cli_pro)
			SELECT null, '" . $ruc_empresa . "', '" . $codigo_unico . "', id_cuenta, debe, haber, detalle_item, '" . $codigo_unico . "', '" . $id_cli_pro . "'  FROM detalle_diario_tmp where id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "' and (debe + haber) !=0");
			$delete_tmp = mysqli_query($con, "DELETE FROM detalle_diario_tmp WHERE id_usuario='" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "' ");
 */
			// --- CONTROL: TODO O NADA ---
			mysqli_autocommit($con, false);

			try {
				// 0) Cuántas líneas válidas hay en TMP (deben insertarse y luego borrarse)
				$sqlCnt = mysqli_query($con, "
        SELECT COUNT(*) AS n
        FROM detalle_diario_tmp
        WHERE id_usuario = '" . $id_usuario . "'
          AND ruc_empresa = '" . $ruc_empresa . "'
          AND (debe + haber) <> 0
    ");
				if (!$sqlCnt) {
					throw new Exception('Error contando TMP: ' . mysqli_error($con));
				}
				$rowCnt = mysqli_fetch_assoc($sqlCnt);
				$nTmp   = isset($rowCnt['n']) ? (int)$rowCnt['n'] : 0;
				if ($nTmp <= 0) {
					throw new Exception('No hay detalle válido en TMP.');
				}

				// 1) Encabezado
				$encabezado = mysqli_query(
					$con,
					"INSERT INTO encabezado_diario
         VALUES (null,'" . $ruc_empresa . "','" . $codigo_unico . "','" . $fecha_asiento . "','" . $concepto_asiento . "','ok','" . $id_usuario . "','" . $fecha_registro . "','" . $tipo_asiento . "', '" . $id_documento . "','" . $codigo_unico . "')"
				);
				if (!$encabezado) {
					throw new Exception('Error insertando encabezado: ' . mysqli_error($con));
				}
				$insEnc = mysqli_affected_rows($con);
				if ($insEnc !== 1) {
					throw new Exception('Encabezado no insertado exactamente 1 vez.');
				}

				// 2) Detalle desde TMP
				$detalle_diario_contable = mysqli_query(
					$con,
					"INSERT INTO detalle_diario_contable
            (id_detalle_cuenta, ruc_empresa, codigo_unico, id_cuenta, debe, haber, detalle_item, codigo_unico_bloque, id_cli_pro)
         SELECT null, '" . $ruc_empresa . "', '" . $codigo_unico . "', id_cuenta, debe, haber, detalle_item, '" . $codigo_unico . "', '" . $id_cli_pro . "'
         FROM detalle_diario_tmp
         WHERE id_usuario = '" . $id_usuario . "'
           AND ruc_empresa = '" . $ruc_empresa . "'
           AND (debe + haber) <> 0"
				);
				if (!$detalle_diario_contable) {
					throw new Exception('Error insertando detalle: ' . mysqli_error($con));
				}
				$insDet = mysqli_affected_rows($con);
				if ($insDet !== $nTmp) {
					throw new Exception('Detalle incompleto: esperadas ' . $nTmp . ' filas, insertadas ' . $insDet . '.');
				}

				// 3) Limpieza de TMP (debe borrar exactamente lo insertado)
				$delete_tmp = mysqli_query(
					$con,
					"DELETE FROM detalle_diario_tmp
         WHERE id_usuario = '" . $id_usuario . "'
           AND ruc_empresa = '" . $ruc_empresa . "'
           AND (debe + haber) <> 0"
				);
				if (!$delete_tmp) {
					throw new Exception('Error limpiando TMP: ' . mysqli_error($con));
				}
				$delTmp = mysqli_affected_rows($con);
				if ($delTmp !== $nTmp) {
					throw new Exception('TMP no limpiada completamente: esperadas ' . $nTmp . ' filas, borradas ' . $delTmp . '.');
				}

				// 4) Commit si todo OK
				mysqli_commit($con);
				mysqli_autocommit($con, true);
				// éxito
			} catch (Exception $e) {
				// Rollback ante cualquier fallo
				mysqli_rollback($con);
				mysqli_autocommit($con, true);
				// Aquí puedes loguear o propagar el error
				// error_log('[guarda_asiento] ' . $e->getMessage());
				throw $e; // o retorna código de error si prefieres
			}

			return "1";
		}
	}

	public function edita_asiento($con, $fecha_asiento, $concepto_asiento, $ruc_empresa, $id_usuario, $codigo_unico)
	{
		ini_set('date.timezone', 'America/Guayaquil');
		$fecha_registro    = date("Y-m-d H:i:s");
		$codigo_unico_nuevo = codigo_unico(20);

		// Inicia transacción
		mysqli_begin_transaction($con);
		try {
			// 1) Actualiza encabezado (marca bloque nuevo y estado)
			$q1 = "
            UPDATE encabezado_diario 
               SET fecha_asiento      = '" . mysqli_real_escape_string($con, $fecha_asiento) . "',
                   concepto_general   = '" . mysqli_real_escape_string($con, $concepto_asiento) . "',
                   estado             = 'Editado',
                   id_usuario         = '" . mysqli_real_escape_string($con, $id_usuario) . "',
                   fecha_registro     = '" . mysqli_real_escape_string($con, $fecha_registro) . "',
                   codigo_unico_bloque= '" . mysqli_real_escape_string($con, $codigo_unico_nuevo) . "'
             WHERE codigo_unico       = '" . mysqli_real_escape_string($con, $codigo_unico) . "'
               AND ruc_empresa        = '" . mysqli_real_escape_string($con, $ruc_empresa) . "'";
			if (!mysqli_query($con, $q1)) {
				throw new Exception('Error actualizando encabezado');
			}

			// 2) Elimina detalle previo del asiento (evita duplicados)
			$qDel = "
            DELETE FROM detalle_diario_contable
             WHERE codigo_unico = '" . mysqli_real_escape_string($con, $codigo_unico) . "'
               AND ruc_empresa  = '" . mysqli_real_escape_string($con, $ruc_empresa) . "'";
			if (!mysqli_query($con, $qDel)) {
				throw new Exception('Error eliminando detalle previo');
			}

			// 3) Inserta el nuevo detalle desde la TMP (solo líneas con valor)
			$qIns = "
            INSERT INTO detalle_diario_contable
                (id_detalle_cuenta, ruc_empresa, codigo_unico, id_cuenta, debe, haber, detalle_item, codigo_unico_bloque)
            SELECT NULL,
                   '" . mysqli_real_escape_string($con, $ruc_empresa) . "',
                   '" . mysqli_real_escape_string($con, $codigo_unico) . "',
                   id_cuenta, debe, haber, detalle_item,
                   '" . mysqli_real_escape_string($con, $codigo_unico_nuevo) . "'
              FROM detalle_diario_tmp
             WHERE id_usuario  = '" . mysqli_real_escape_string($con, $id_usuario) . "'
               AND ruc_empresa = '" . mysqli_real_escape_string($con, $ruc_empresa) . "'
               AND (debe + haber) <> 0";
			if (!mysqli_query($con, $qIns)) {
				throw new Exception('Error insertando detalle nuevo');
			}

			// 4) Limpia la TMP del usuario/empresa
			$qTmp = "
            DELETE FROM detalle_diario_tmp 
             WHERE id_usuario  = '" . mysqli_real_escape_string($con, $id_usuario) . "'
               AND ruc_empresa = '" . mysqli_real_escape_string($con, $ruc_empresa) . "'";
			if (!mysqli_query($con, $qTmp)) {
				throw new Exception('Error limpiando TMP');
			}

			// 5) Setea id_cli_pro según el tipo de asiento (solo para el bloque recién insertado)
			$baseFiltro = " det.codigo_unico = '" . mysqli_real_escape_string($con, $codigo_unico) . "' 
                        AND det.codigo_unico_bloque = '" . mysqli_real_escape_string($con, $codigo_unico_nuevo) . "' 
                        AND enc.ruc_empresa = '" . mysqli_real_escape_string($con, $ruc_empresa) . "'";

			$updates = [
				"UPDATE detalle_diario_contable det 
              INNER JOIN encabezado_diario enc ON enc.codigo_unico = det.codigo_unico
                SET det.id_cli_pro = (SELECT id_proveedor FROM encabezado_compra WHERE CONCAT('COM', id_encabezado_compra) = enc.id_documento)
              WHERE $baseFiltro AND enc.tipo = 'COMPRAS_SERVICIOS'",

				"UPDATE detalle_diario_contable det 
              INNER JOIN encabezado_diario enc ON enc.codigo_unico = det.codigo_unico
                SET det.id_cli_pro = (SELECT id_cliente FROM encabezado_factura WHERE CONCAT('FAC', id_encabezado_factura) = enc.id_documento)
              WHERE $baseFiltro AND enc.tipo = 'VENTAS'",

				"UPDATE detalle_diario_contable det 
              INNER JOIN encabezado_diario enc ON enc.codigo_unico = det.codigo_unico
                SET det.id_cli_pro = (SELECT id_cliente FROM encabezado_retencion_venta WHERE CONCAT('RETVEN', id_encabezado_retencion) = enc.id_documento)
              WHERE $baseFiltro AND enc.tipo = 'RETENCIONES_VENTAS'",

				"UPDATE detalle_diario_contable det 
              INNER JOIN encabezado_diario enc ON enc.codigo_unico = det.codigo_unico
                SET det.id_cli_pro = (SELECT id_proveedor FROM encabezado_retencion WHERE CONCAT('RETCOM', id_encabezado_retencion) = enc.id_documento)
              WHERE $baseFiltro AND enc.tipo = 'RETENCIONES_COMPRAS'",

				"UPDATE detalle_diario_contable det 
              INNER JOIN encabezado_diario enc ON enc.codigo_unico = det.codigo_unico
                SET det.id_cli_pro = (SELECT id_cliente FROM encabezado_nc WHERE CONCAT('NCV', id_encabezado_nc) = enc.id_documento)
              WHERE $baseFiltro AND enc.tipo = 'NC_VENTAS'",

				"UPDATE detalle_diario_contable det 
              INNER JOIN encabezado_diario enc ON enc.codigo_unico = det.codigo_unico
                SET det.id_cli_pro = (SELECT id_cli_pro FROM ingresos_egresos WHERE CONCAT('ING', id_ing_egr) = enc.id_documento)
              WHERE $baseFiltro AND enc.tipo = 'INGRESOS'",

				"UPDATE detalle_diario_contable det 
              INNER JOIN encabezado_diario enc ON enc.codigo_unico = det.codigo_unico
                SET det.id_cli_pro = (SELECT id_cli_pro FROM ingresos_egresos WHERE CONCAT('EGR', id_ing_egr) = enc.id_documento)
              WHERE $baseFiltro AND enc.tipo = 'EGRESOS'",

				"UPDATE detalle_diario_contable det 
              INNER JOIN encabezado_diario enc ON enc.codigo_unico = det.codigo_unico
                SET det.id_cli_pro = (SELECT id_cliente FROM encabezado_recibo WHERE CONCAT('REC', id_encabezado_recibo) = enc.id_documento)
              WHERE $baseFiltro AND enc.tipo = 'RECIBOS'",

				"UPDATE detalle_diario_contable det 
              INNER JOIN encabezado_diario enc ON enc.codigo_unico = det.codigo_unico
                SET det.id_cli_pro = (SELECT id_empleado FROM detalle_rolespago WHERE CONCAT('ROL_PAGOS', id) = enc.id_documento)
              WHERE $baseFiltro AND enc.tipo = 'ROL_PAGOS'",
			];

			foreach ($updates as $q) {
				if (!mysqli_query($con, $q)) {
					throw new Exception('Error actualizando id_cli_pro');
				}
			}

			// 6) Commit si todo salió bien
			mysqli_commit($con);
			return "1";
		} catch (Exception $e) {
			// Rollback ante error
			mysqli_rollback($con);
			// Puedes loguear $e->getMessage() si quieres más detalle
			return "2";
		}
	}


	//detalle asiento repetido
	public function asientoRepetido($con, $ruc_empresa, $id_cuenta, $debe, $haber, $detalle, $id_usuario)
	{
		$sql_repetido = mysqli_query($con, "SELECT count(*) as numrows FROM detalle_diario_tmp 
	WHERE ruc_empresa = '" . $ruc_empresa . "' and id_cuenta='" . $id_cuenta . "' 
	and debe='" . $debe . "' and haber = '" . $haber . "' and detalle_item= '" . $detalle . "' and id_usuario='" . $id_usuario . "'");
		$row_repetido = mysqli_fetch_array($sql_repetido);
		$repetidos = $row_repetido['numrows'];
		return $repetidos;
	}


	//detalle asiento repetido
	public function encabezadoRepetido($con, $ruc_empresa, $fecha, $concepto, $tipo)
	{
		$sql_repetido = mysqli_query($con, "SELECT count(*) as numrows FROM encabezado_diario 
	WHERE ruc_empresa = '" . $ruc_empresa . "' and fecha_asiento='" . $fecha . "' 
	and concepto_general='" . $concepto . "' and tipo = '" . $tipo . "' and estado !='Anulado'");
		$row_repetido = mysqli_fetch_array($sql_repetido);
		$repetidos = $row_repetido['numrows'];
		return $repetidos;
	}
}
