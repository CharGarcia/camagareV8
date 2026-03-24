<?php
include __DIR__ . "/../clases/control_caracteres_especiales.php";
include __DIR__ . "/consulta.comprobantes.sri.class.php";
include __DIR__ . "/../clases/contabilizacion.php";

class rides_sri
{
	public function lee_archivo_xml_notaria($object_xml, $ruc_empresa, $id_usuario, $con)
	{
		ini_set('date.timezone', 'America/Guayaquil');

		// Helpers (compatibles con PHP 5.6)
		$safeStr = function ($v) {
			return trim((string)$v);
		};
		$ruc10   = function ($r) {
			return substr(preg_replace('~\D~', '', (string)$r), 0, 10);
		};
		$ruc13   = function ($r) {
			$digits = preg_replace('~\D~', '', (string)$r);
			if (strlen($digits) === 13) return $digits;
			$base10 = substr($digits, 0, 10);
			return (strlen($base10) === 10) ? $base10 . '001' : $digits;
		};

		if (!is_object($object_xml)) {
			return array('documento' => 'XML', 'estado' => '0', 'nombre' => '', 'numero' => '', 'mensaje' => 'Archivo no válido');
		}

		// Nodos mínimos requeridos
		if (!isset($object_xml->infoTributaria) || !isset($object_xml->infoTributaria->codDoc)) {
			return array('documento' => 'XML', 'estado' => '0', 'nombre' => '', 'numero' => '', 'mensaje' => 'Faltan datos de infoTributaria');
		}

		$tipo_documento = $this->toString($object_xml->infoTributaria->codDoc); // si tienes helper toString, si no:
		if ($tipo_documento === '') $tipo_documento = (string)$object_xml->infoTributaria->codDoc;

		// Solo facturas (01) para notaría
		if ($tipo_documento !== "01") {
			return array('documento' => 'Factura', 'estado' => '0', 'nombre' => '', 'numero' => '', 'mensaje' => 'Tipo de documento no soportado en esta rutina');
		}

		// Armar claves
		$estab      = $this->toString($object_xml->infoTributaria->estab);
		if ($estab === '')      $estab      = (string)$object_xml->infoTributaria->estab;
		$ptoEmi     = $this->toString($object_xml->infoTributaria->ptoEmi);
		if ($ptoEmi === '')     $ptoEmi     = (string)$object_xml->infoTributaria->ptoEmi;
		$secuencial = $this->toString($object_xml->infoTributaria->secuencial);
		if ($secuencial === '') $secuencial = (string)$object_xml->infoTributaria->secuencial;
		$aut_sri    = $this->toString($object_xml->infoTributaria->claveAcceso);
		if ($aut_sri === '')    $aut_sri    = (string)$object_xml->infoTributaria->claveAcceso;

		$serie           = $estab . "-" . $ptoEmi;
		$secuencial_int  = (int)$secuencial;
		$numero_factura  = $serie . "-" . $secuencial;

		// Datos emisor (tu empresa) y comprador
		if (!isset($object_xml->infoFactura)) {
			return array('documento' => 'Factura', 'estado' => '0', 'nombre' => '', 'numero' => $numero_factura, 'mensaje' => 'Faltan datos de infoFactura');
		}

		$ruc_proveedor_emisor = $this->toString($object_xml->infoTributaria->ruc); // emisor del XML
		if ($ruc_proveedor_emisor === '') $ruc_proveedor_emisor = (string)$object_xml->infoTributaria->ruc;

		$idCompradorRaw = isset($object_xml->infoFactura->identificacionComprador)
			? (string)$object_xml->infoFactura->identificacionComprador : '';
		$ruc_comprador  = $ruc13($idCompradorRaw);

		$razon_social_comprador = isset($object_xml->infoFactura->razonSocialComprador)
			? (string)$object_xml->infoFactura->razonSocialComprador : '';

		// Validaciones básicas
		if ($estab === '' || $ptoEmi === '' || $secuencial === '') {
			return array('documento' => 'Factura', 'estado' => '0', 'nombre' => $razon_social_comprador, 'numero' => $numero_factura, 'mensaje' => 'Serie o secuencial incompletos');
		}
		if ($aut_sri === '') {
			return array('documento' => 'Factura', 'estado' => '0', 'nombre' => $razon_social_comprador, 'numero' => $numero_factura, 'mensaje' => 'Clave de acceso faltante');
		}

		// Solo registrar como FACTURA DE VENTA cuando el emisor (ruc_proveedor_emisor) coincide con mi empresa
		if ($ruc10($ruc_proveedor_emisor) !== $ruc10($ruc_empresa)) {
			return array('documento' => 'Factura', 'estado' => '2', 'nombre' => $razon_social_comprador, 'numero' => $numero_factura, 'mensaje' => 'Emitida para otro contribuyente');
		}

		// Asegurar/crear cliente a partir del comprador
		$id_cliente = $this->proveedor_cliente("cliente", $con, $ruc_empresa, $object_xml, $ruc_comprador);

		// ¿Ya registrada?
		$ya_reg = comprueba_registrados($con, $ruc_empresa, 'factura_venta', $serie, $secuencial_int, $id_cliente, $aut_sri);
		if ((int)$ya_reg > 0) {
			return array('documento' => 'Factura', 'estado' => '3', 'nombre' => $razon_social_comprador, 'numero' => $numero_factura, 'mensaje' => 'Registrada con anterioridad');
		}

		// Guardar como factura de venta notarial
		$ok = $this->guarda_factura_venta_notaria($con, $ruc_empresa, $object_xml, $id_usuario, $id_cliente);
		if ($ok) {
			return array('documento' => 'Factura', 'estado' => '1', 'nombre' => $razon_social_comprador, 'numero' => $numero_factura, 'mensaje' => 'Registrada');
		} else {
			return array('documento' => 'Factura', 'estado' => '0', 'nombre' => $razon_social_comprador, 'numero' => $numero_factura, 'mensaje' => 'No guardada');
		}
	}

	/**
	 * Si no tienes un helper toString en tu clase, puedes incluirlo así:
	 */
	private function toString($v)
	{
		return isset($v) ? trim((string)$v) : '';
	}

	public function lee_archivo_xml($object_xml, $ruc_empresa, $id_usuario, $con)
	{
		ini_set('date.timezone', 'America/Guayaquil');

		if (!is_object($object_xml)) {
			return array('documento' => 'Desconocido', 'estado' => '0', 'nombre' => '', 'numero' => '', 'mensaje' => 'XML no válido');
		}

		// Utilidades locales
		$tipo_documento = (string)$object_xml->infoTributaria->codDoc;
		$serie          = (string)$object_xml->infoTributaria->estab . "-" . (string)$object_xml->infoTributaria->ptoEmi;
		$secuencial     = (string)$object_xml->infoTributaria->secuencial;
		$aut_sri        = (string)$object_xml->infoTributaria->claveAcceso;

		// ---------- 03: Liquidación de compras/servicios ----------
		if ($tipo_documento === "03") {
			$ruc_comprador      = substr((string)$object_xml->infoTributaria->ruc, 0, 10) . "001"; // Emisor de la LC (quien compra)
			$numero_lcs         = $serie . "-" . $secuencial;
			$ruc_proveedor      = (string)$object_xml->infoLiquidacionCompra->identificacionProveedor;
			$nombre_proveedor   = (string)$object_xml->infoLiquidacionCompra->razonSocialProveedor;

			if (substr($ruc_comprador, 0, 10) !== substr($ruc_empresa, 0, 10)) {
				return array('documento' => 'Liquidación de compras/servicios', 'estado' => '2', 'nombre' => $nombre_proveedor, 'numero' => $numero_lcs, 'mensaje' => 'Emitida para otro contribuyente');
			}

			// Registrar proveedor (si no existe)
			$id_proveedor = $this->proveedor_cliente("proveedor", $con, $ruc_empresa, $object_xml, $ruc_proveedor);

			// 1) Intento como "liquidación" (módulo de L/C)
			$reg_lc_existe = comprueba_registrados($con, $ruc_empresa, 'liquidacion', $serie, $secuencial, $id_proveedor, $aut_sri);
			$ok_lc = false;
			$ya_lc = false;

			if ((int)$reg_lc_existe === 0) {
				$ok_lc = (bool)$this->guarda_lcs($con, $ruc_empresa, $object_xml, $id_usuario, $id_proveedor);
				if (!$ok_lc) {
					return array('documento' => 'Liquidación de compras/servicios', 'estado' => '0', 'nombre' => $nombre_proveedor, 'numero' => $numero_lcs, 'mensaje' => 'No guardada');
				}
			} else {
				$ya_lc = true;
			}

			// 2) (Opcional) Intento como "compra" reflejada desde una LC
			//   Nota: si no quieres duplicar como compra, comenta este bloque.
			$reg_compra_existe = comprueba_registrados($con, $ruc_empresa, 'lc_compra', $serie, $secuencial, $id_proveedor, $aut_sri);
			if ((int)$reg_compra_existe === 0) {
				$ok_compra = (bool)$this->guarda_lcs_compras($con, $ruc_empresa, $object_xml, $id_usuario, $id_proveedor);
				if (!$ok_compra) {
					// Si falla el reflejo como compra, pero LC ya fue ok/ya estaba, devolvemos ok por LC.
					return array('documento' => 'Liquidación de compras/servicios', 'estado' => ($ok_lc || $ya_lc ? '1' : '0'), 'nombre' => $nombre_proveedor, 'numero' => $numero_lcs, 'mensaje' => ($ok_lc || $ya_lc ? 'Registrada' : 'No guardada'));
				}
			}

			// Resumen final para LC
			if ($ya_lc) {
				return array('documento' => 'Liquidación de compras/servicios', 'estado' => '3', 'nombre' => $nombre_proveedor, 'numero' => $numero_lcs, 'mensaje' => 'Registrada con anterioridad');
			}
			return array('documento' => 'Liquidación de compras/servicios', 'estado' => '1', 'nombre' => $nombre_proveedor, 'numero' => $numero_lcs, 'mensaje' => 'Registrada');
		}

		// ---------- 01: Factura ----------
		if ($tipo_documento === "01") {
			$ruc_comprador   = substr((string)$object_xml->infoFactura->identificacionComprador, 0, 10) . "001";
			$numero_factura  = $serie . "-" . $secuencial;
			$razon_social    = (string)$object_xml->infoFactura->razonSocialComprador;
			$ruc_proveedor   = (string)$object_xml->infoTributaria->ruc;
			$nombre_proveedor = (string)$object_xml->infoTributaria->razonSocial;

			// Como compra
			if (substr($ruc_comprador, 0, 10) === substr($ruc_empresa, 0, 10)) {
				$id_proveedor     = $this->proveedor_cliente("proveedor", $con, $ruc_empresa, $object_xml, $ruc_proveedor);
				$reg_compra_existe = comprueba_registrados($con, $ruc_empresa, 'factura_compra', $serie, $secuencial, $id_proveedor, $aut_sri);
				if ((int)$reg_compra_existe === 0) {
					$ok = (bool)$this->guarda_factura_compra($con, $ruc_empresa, $object_xml, $id_usuario, $id_proveedor);
					return array('documento' => 'Factura', 'estado' => ($ok ? '1' : '0'), 'nombre' => $nombre_proveedor, 'numero' => $numero_factura, 'mensaje' => ($ok ? 'Registrada' : 'No guardada'));
				} else {
					return array('documento' => 'Factura', 'estado' => '3', 'nombre' => $nombre_proveedor, 'numero' => $numero_factura, 'mensaje' => 'Registrada con anterioridad');
				}
			}

			// Como venta
			if (substr($ruc_proveedor, 0, 10) === substr($ruc_empresa, 0, 10)) {
				$id_cliente      = $this->proveedor_cliente("cliente", $con, $ruc_empresa, $object_xml, $ruc_comprador);
				$reg_venta_existe = comprueba_registrados($con, $ruc_empresa, 'factura_venta', $serie, $secuencial, $id_cliente, $aut_sri);
				if ((int)$reg_venta_existe === 0) {
					$ok = (bool)$this->guarda_factura_venta($con, $ruc_empresa, $object_xml, $id_usuario, $id_cliente);
					return array('documento' => 'Factura', 'estado' => ($ok ? '1' : '0'), 'nombre' => $razon_social, 'numero' => $numero_factura, 'mensaje' => ($ok ? 'Registrada' : 'No guardada'));
				} else {
					return array('documento' => 'Factura', 'estado' => '3', 'nombre' => $razon_social, 'numero' => $numero_factura, 'mensaje' => 'Registrada con anterioridad');
				}
			}

			// Ni compra ni venta para esta empresa
			if (substr($ruc_comprador, 0, 10) !== substr($ruc_empresa, 0, 10)) {
				return array('documento' => 'Factura', 'estado' => '2', 'nombre' => $nombre_proveedor, 'numero' => $numero_factura, 'mensaje' => 'Emitida para otro contribuyente');
			}
			if (substr($ruc_proveedor, 0, 10) !== substr($ruc_empresa, 0, 10)) {
				return array('documento' => 'Factura', 'estado' => '2', 'nombre' => $razon_social, 'numero' => $numero_factura, 'mensaje' => 'Emitida para otro contribuyente');
			}
		}

		// ---------- 04: Nota de crédito (compras) ----------
		if ($tipo_documento === "04") {
			$ruc_comprador    = substr((string)$object_xml->infoNotaCredito->identificacionComprador, 0, 10) . "001";
			$numero_nc        = $serie . "-" . $secuencial;
			$ruc_proveedor    = (string)$object_xml->infoTributaria->ruc;
			$nombre_proveedor = (string)$object_xml->infoTributaria->razonSocial;

			if (substr($ruc_comprador, 0, 10) !== substr($ruc_empresa, 0, 10)) {
				return array('documento' => 'Nota de crédito', 'estado' => '2', 'nombre' => $nombre_proveedor, 'numero' => $numero_nc, 'mensaje' => 'Emitida para otro contribuyente');
			}

			$id_proveedor = $this->proveedor_cliente("proveedor", $con, $ruc_empresa, $object_xml, $ruc_proveedor);
			$reg_existe   = comprueba_registrados($con, $ruc_empresa, 'nc_compra', $serie, $secuencial, $id_proveedor, $aut_sri);

			if ((int)$reg_existe === 0) {
				$ok = (bool)$this->guarda_nc($con, $ruc_empresa, $object_xml, $id_usuario, $id_proveedor);
				return array('documento' => 'Nota de crédito', 'estado' => ($ok ? '1' : '0'), 'nombre' => $nombre_proveedor, 'numero' => $numero_nc, 'mensaje' => ($ok ? 'Registrada' : 'No guardada'));
			}
			return array('documento' => 'Nota de crédito', 'estado' => '3', 'nombre' => $nombre_proveedor, 'numero' => $numero_nc, 'mensaje' => 'Registrada con anterioridad');
		}

		// ---------- 05: Nota de débito (compras) ----------
		if ($tipo_documento === "05") {
			$ruc_comprador    = substr((string)$object_xml->infoNotaDebito->identificacionComprador, 0, 10) . "001";
			$numero_nd        = $serie . "-" . $secuencial;
			$ruc_proveedor    = (string)$object_xml->infoTributaria->ruc;
			$nombre_proveedor = (string)$object_xml->infoTributaria->razonSocial;

			if (substr($ruc_comprador, 0, 10) !== substr($ruc_empresa, 0, 10)) {
				return array('documento' => 'Nota de débito', 'estado' => '2', 'nombre' => $nombre_proveedor, 'numero' => $numero_nd, 'mensaje' => 'Emitida para otro contribuyente');
			}

			$id_proveedor = $this->proveedor_cliente("proveedor", $con, $ruc_empresa, $object_xml, $ruc_proveedor);
			$reg_existe   = comprueba_registrados($con, $ruc_empresa, 'nd_compra', $serie, $secuencial, $id_proveedor, $aut_sri);

			if ((int)$reg_existe === 0) {
				$ok = (bool)$this->guarda_nd($con, $ruc_empresa, $object_xml, $id_usuario, $id_proveedor);
				return array('documento' => 'Nota de débito', 'estado' => ($ok ? '1' : '0'), 'nombre' => $nombre_proveedor, 'numero' => $numero_nd, 'mensaje' => ($ok ? 'Registrada' : 'No guardada'));
			}
			return array('documento' => 'Nota de débito', 'estado' => '3', 'nombre' => $nombre_proveedor, 'numero' => $numero_nd, 'mensaje' => 'Registrada con anterioridad');
		}

		// ---------- 07: Comprobante de retención ----------
		if ($tipo_documento === "07") {
			$ruc_retenido     = substr((string)$object_xml->infoCompRetencion->identificacionSujetoRetenido, 0, 10) . "001";
			$numero_retencion = $serie . "-" . $secuencial;
			$ruc_emisor       = (string)$object_xml->infoTributaria->ruc;          // quien emite la retención
			$nombre_emisor    = (string)$object_xml->infoTributaria->razonSocial;  // cliente si es retención de ventas
			$nombre_retenido  = (string)$object_xml->infoCompRetencion->razonSocialSujetoRetenido; // proveedor si es retención de compras

			// Retención de ventas (me retienen a mí): el retenido soy yo (mi empresa)
			if (substr($ruc_retenido, 0, 10) === substr($ruc_empresa, 0, 10)) {
				$id_cliente  = $this->proveedor_cliente("cliente", $con, $ruc_empresa, $object_xml, $ruc_emisor);
				$reg_existe  = comprueba_registrados($con, $ruc_empresa, 'retencion_venta', $serie, $secuencial, $id_cliente, $aut_sri);

				if ((int)$reg_existe === 0) {
					$ok = (bool)$this->guarda_retencion_venta($con, $ruc_empresa, $object_xml, $id_usuario, $id_cliente);
					return array('documento' => 'Retención en ventas', 'estado' => ($ok ? '1' : '0'), 'nombre' => $nombre_emisor, 'numero' => $numero_retencion, 'mensaje' => ($ok ? 'Registrada' : 'No guardada'));
				}
				return array('documento' => 'Retención en ventas', 'estado' => '3', 'nombre' => $nombre_emisor, 'numero' => $numero_retencion, 'mensaje' => 'Registrada con anterioridad');
			}

			// Retención de compras (yo retuve a mi proveedor): el emisor soy yo
			if (substr($ruc_emisor, 0, 10) === substr($ruc_empresa, 0, 10)) {
				$id_proveedor = $this->proveedor_cliente("proveedor", $con, $ruc_empresa, $object_xml, $ruc_retenido);
				$reg_existe   = comprueba_registrados($con, $ruc_empresa, 'retencion_compra', $serie, $secuencial, $id_proveedor, $aut_sri);

				if ((int)$reg_existe === 0) {
					$ok = (bool)$this->guarda_retencion_compra($con, $ruc_empresa, $object_xml, $id_usuario, $id_proveedor);
					return array('documento' => 'Retención en compras', 'estado' => ($ok ? '1' : '0'), 'nombre' => $nombre_retenido, 'numero' => $numero_retencion, 'mensaje' => ($ok ? 'Registrada' : 'No guardada'));
				}
				return array('documento' => 'Retención en compras', 'estado' => '3', 'nombre' => $nombre_retenido, 'numero' => $numero_retencion, 'mensaje' => 'Registrada con anterioridad');
			}

			// Emitida para otro contribuyente
			if (substr($ruc_retenido, 0, 10) !== substr($ruc_empresa, 0, 10)) {
				return array('documento' => 'Retención en ventas', 'estado' => '2', 'nombre' => $nombre_emisor, 'numero' => $numero_retencion, 'mensaje' => 'Emitida para otro contribuyente');
			}
			if (substr($ruc_emisor, 0, 10) !== substr($ruc_empresa, 0, 10)) {
				return array('documento' => 'Retención en compras', 'estado' => '2', 'nombre' => $nombre_retenido, 'numero' => $numero_retencion, 'mensaje' => 'Emitida para otro contribuyente');
			}
		}

		// Tipo no soportado
		return array('documento' => 'No soportado', 'estado' => '0', 'nombre' => '', 'numero' => '', 'mensaje' => 'Tipo de documento no implementado: ' . $tipo_documento);
	}

	//para guardar una liquidacion de compra hecha en otro sistema y la quiero registrar en mi sistema
	public function guarda_lcs($con, $ruc_empresa, $object_xml, $id_usuario, $id_proveedor)
	{
		// Helpers locales (compatibles con PHP 5.6)
		$toDecimal = function ($val, $scale = 2) {
			$s = trim((string)$val);
			// limpia espacios, incluyendo NBSP
			$s = str_replace(array("\xC2\xA0", " ", "\t"), "", $s);
			if ($s === '' || $s === '+' || $s === '-') {
				return number_format(0, $scale, '.', '');
			}

			$hasDot   = strpos($s, '.') !== false;
			$hasComma = strpos($s, ',') !== false;

			if ($hasDot && $hasComma) {
				// Si hay ambos, el ÚLTIMO símbolo suele ser el separador decimal
				$lastDot   = strrpos($s, '.');
				$lastComma = strrpos($s, ',');
				if ($lastComma > $lastDot) {
					// coma decimal, punto miles -> quita puntos y cambia coma por punto
					$s = str_replace('.', '', $s);
					$s = str_replace(',', '.', $s);
				} else {
					// punto decimal, coma miles -> quita comas
					$s = str_replace(',', '', $s);
					// deja el punto decimal
				}
			} elseif ($hasComma) {
				// Solo comas: ¿miles o decimal?
				if (preg_match('/^\d{1,3}(,\d{3})+$/', $s)) {
					// "1,234,567" -> miles
					$s = str_replace(',', '', $s);
				} else {
					// "1234,56" / "1,000000" -> coma decimal
					$s = str_replace(',', '.', $s);
				}
			} elseif ($hasDot) {
				// Solo puntos: ¿miles o decimal?
				if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
					// "1.234.567" -> miles
					$s = str_replace('.', '', $s);
				} // si es "1.000000" -> punto decimal (no tocar)
			}

			if (!is_numeric($s)) $s = '0';
			// Devuelve string con punto y $scale decimales (como venías usando)
			return number_format((float)$s, $scale, '.', '');
		};
		$strCleanLocal = function ($s) {
			// Usa tu strClean si existe; si no, sanea básico
			if (function_exists('strClean')) {
				return strClean($s);
			}
			$s = strip_tags((string)$s);
			$s = preg_replace('/\s+/', ' ', $s);
			return trim($s);
		};
		$parseFechaEmision = function ($raw) {
			$raw = trim((string)$raw);
			// SRI suele ser dd/mm/yyyy
			if (preg_match('~^\d{2}/\d{2}/\d{4}$~', $raw)) {
				$dt = DateTime::createFromFormat('d/m/Y', $raw);
				if ($dt) return $dt->format('Y-m-d');
			}
			// fallback: yyyy-mm-dd
			if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $raw)) {
				return $raw;
			}
			// último recurso: intenta parsear “a lo bestia”
			$ts = strtotime($raw);
			return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
		};
		// ---------------------------------------------------------------------
		$fecha_registro   = date("Y-m-d H:i:s");
		$fecha_emision    = $parseFechaEmision($object_xml->infoLiquidacionCompra->fechaEmision);
		$serie_lcs        = (string)$object_xml->infoTributaria->estab . "-" . (string)$object_xml->infoTributaria->ptoEmi;
		$secuencial_lcs   = (string)$object_xml->infoTributaria->secuencial;
		$codigo_documento = codigo_aleatorio(20);
		$aut_sri          = (string)$object_xml->infoTributaria->claveAcceso;
		$total_lcs        = $toDecimal($object_xml->infoLiquidacionCompra->importeTotal, 2);

		// Para seguridad básica al interpolar
		$ruc_empresa_sql   = mysqli_real_escape_string($con, $ruc_empresa);
		$fecha_emision_sql = mysqli_real_escape_string($con, $fecha_emision);
		$serie_sql         = mysqli_real_escape_string($con, $serie_lcs);
		$secuencial_sql    = mysqli_real_escape_string($con, $secuencial_lcs);
		$id_proveedor_sql  = (int)$id_proveedor;
		$fecha_registro_sql = mysqli_real_escape_string($con, $fecha_registro);
		$total_lcs_sql     = mysqli_real_escape_string($con, $total_lcs);
		$id_usuario_sql    = (int)$id_usuario;
		$aut_sri_sql       = mysqli_real_escape_string($con, $aut_sri);
		$codigo_doc_sql    = mysqli_real_escape_string($con, $codigo_documento);

		// Inicia transacción
		mysqli_begin_transaction($con);

		// Nota: Si conoces tu esquema, especifica columnas. Mantengo tu orden original para no romper.
		$sql_enc = "
        INSERT INTO encabezado_liquidacion
        VALUES (NULL, '{$ruc_empresa_sql}', '{$fecha_emision_sql}', '{$serie_sql}', '{$secuencial_sql}',
                '{$id_proveedor_sql}', '{$fecha_registro_sql}', 'AUTORIZADO', '{$total_lcs_sql}',
                '{$id_usuario_sql}', '2', '0', '{$aut_sri_sql}', 'ENVIADO', '{$codigo_doc_sql}')
    ";
		$ok_enc = mysqli_query($con, $sql_enc);
		if (!$ok_enc) {
			mysqli_rollback($con);
			return false;
		}

		// ---------- Detalles ----------
		if (isset($object_xml->detalles)) {
			foreach ($object_xml->detalles->detalle as $detalle) {
				$codigo_detalle     = $strCleanLocal($detalle->codigoPrincipal);
				$descripcion_detalle = $strCleanLocal($detalle->descripcion);
				$cantidad_detalle   = $detalle->cantidad;
				$precio_detalle     = $detalle->precioUnitario;
				$descuento_detalle  = $detalle->descuento;

				// Si no hay impuestos, al menos guarda el renglón con base 0 y tarifa 0
				if (!isset($detalle->impuestos) || !isset($detalle->impuestos->impuesto)) {
					$sql_det = "
                    INSERT INTO cuerpo_liquidacion
                    VALUES (NULL, '{$ruc_empresa_sql}', '{$serie_sql}', '{$secuencial_sql}',
                            '{$cantidad_detalle}', '{$precio_detalle}', '0.00',
                            '0', '{$descuento_detalle}', '" . mysqli_real_escape_string($con, $codigo_detalle) . "',
                            '" . mysqli_real_escape_string($con, $descripcion_detalle) . "', '{$codigo_doc_sql}')
                ";
					if (!mysqli_query($con, $sql_det)) {
						mysqli_rollback($con);
						return false;
					}
				} else {
					foreach ($detalle->impuestos->impuesto as $imp) {
						$tarifa          = isset($imp->tarifa) ? (string)$imp->tarifa : '0';
						$base_imponible  = $toDecimal((isset($imp->baseImponible) ? $imp->baseImponible : 0), 2);

						$sql_det = "
                        INSERT INTO cuerpo_liquidacion
                        VALUES (NULL, '{$ruc_empresa_sql}', '{$serie_sql}', '{$secuencial_sql}',
                                '{$cantidad_detalle}', '{$precio_detalle}', '{$base_imponible}',
                                '" . mysqli_real_escape_string($con, $tarifa) . "', '{$descuento_detalle}', '" . mysqli_real_escape_string($con, $codigo_detalle) . "',
                                '" . mysqli_real_escape_string($con, $descripcion_detalle) . "', '{$codigo_doc_sql}')
                    ";
						if (!mysqli_query($con, $sql_det)) {
							mysqli_rollback($con);
							return false;
						}
					}
				}
			}
		}

		// ---------- Info adicional ----------
		if (isset($object_xml->infoAdicional) && isset($object_xml->infoAdicional->campoAdicional)) {
			foreach ($object_xml->infoAdicional->campoAdicional as $campo) {
				// En SRI, <campoAdicional nombre="...">valor</campoAdicional>
				$nombre = isset($campo['nombre']) ? (string)$campo['nombre'] : '';
				$valor  = (string)$campo;

				$nombre_sql = mysqli_real_escape_string($con, $strCleanLocal($nombre));
				$valor_sql  = mysqli_real_escape_string($con, $strCleanLocal($valor));

				$sql_ad = "
                INSERT INTO detalle_adicional_liquidacion
                VALUES (NULL, '{$ruc_empresa_sql}', '{$serie_sql}', '{$secuencial_sql}',
                        '{$nombre_sql}', '{$valor_sql}', '{$codigo_doc_sql}')
            ";
				if (!mysqli_query($con, $sql_ad)) {
					mysqli_rollback($con);
					return false;
				}
			}
		}

		// Todo OK
		mysqli_commit($con);
		return true;
	}


	//guardar liquidacion de compra en compras
	public function guarda_lcs_compras($con, $ruc_empresa, $object_xml, $id_usuario, $id_proveedor)
	{
		// Helpers (compatibles con PHP 5.6)
		$toDecimal = function ($val, $scale = 2) {
			$s = trim((string)$val);
			// limpia espacios, incluyendo NBSP
			$s = str_replace(array("\xC2\xA0", " ", "\t"), "", $s);
			if ($s === '' || $s === '+' || $s === '-') {
				return number_format(0, $scale, '.', '');
			}

			$hasDot   = strpos($s, '.') !== false;
			$hasComma = strpos($s, ',') !== false;

			if ($hasDot && $hasComma) {
				// Si hay ambos, el ÚLTIMO símbolo suele ser el separador decimal
				$lastDot   = strrpos($s, '.');
				$lastComma = strrpos($s, ',');
				if ($lastComma > $lastDot) {
					// coma decimal, punto miles -> quita puntos y cambia coma por punto
					$s = str_replace('.', '', $s);
					$s = str_replace(',', '.', $s);
				} else {
					// punto decimal, coma miles -> quita comas
					$s = str_replace(',', '', $s);
					// deja el punto decimal
				}
			} elseif ($hasComma) {
				// Solo comas: ¿miles o decimal?
				if (preg_match('/^\d{1,3}(,\d{3})+$/', $s)) {
					// "1,234,567" -> miles
					$s = str_replace(',', '', $s);
				} else {
					// "1234,56" / "1,000000" -> coma decimal
					$s = str_replace(',', '.', $s);
				}
			} elseif ($hasDot) {
				// Solo puntos: ¿miles o decimal?
				if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
					// "1.234.567" -> miles
					$s = str_replace('.', '', $s);
				} // si es "1.000000" -> punto decimal (no tocar)
			}

			if (!is_numeric($s)) $s = '0';
			// Devuelve string con punto y $scale decimales (como venías usando)
			return number_format((float)$s, $scale, '.', '');
		};


		$strCleanLocal = function ($s) {
			if (function_exists('strClean')) {
				return strClean($s);
			}
			$s = strip_tags((string)$s);
			$s = preg_replace('/\s+/', ' ', $s);
			return trim($s);
		};

		$parseFechaEmision = function ($raw) {
			$raw = trim((string)$raw);
			if (preg_match('~^\d{2}/\d{2}/\d{4}$~', $raw)) {
				$dt = DateTime::createFromFormat('d/m/Y', $raw);
				if ($dt) return $dt->format('Y-m-d');
			}
			if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $raw)) {
				return $raw;
			}
			$ts = strtotime($raw);
			return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
		};

		// Instancias opcionales (sin romper si no existen)
		$contabilizacion = new contabilizacion();
		$sanitize = class_exists('sanitize') ? new sanitize() : null;

		// ---------------------------------------------------------------------

		$fecha_registro   = date("Y-m-d H:i:s");
		$fecha_emision    = $parseFechaEmision($object_xml->infoLiquidacionCompra->fechaEmision);
		$estab            = (string)$object_xml->infoTributaria->estab;
		$ptoEmi           = (string)$object_xml->infoTributaria->ptoEmi;
		$secuencial_lcs   = (string)$object_xml->infoTributaria->secuencial;
		$numero_lcs       = $estab . "-" . $ptoEmi . "-" . $secuencial_lcs;
		$codigo_documento = codigo_aleatorio(20);
		$id_documento     = (string)$object_xml->infoTributaria->codDoc;         // 03
		$aut_sri          = (string)$object_xml->infoTributaria->claveAcceso;
		$total_lcs        = $toDecimal($object_xml->infoLiquidacionCompra->importeTotal, 2);
		$tipoIdComprador  = isset($object_xml->infoLiquidacionCompra->tipoIdentificacionComprador)
			? (string)$object_xml->infoLiquidacionCompra->tipoIdentificacionComprador
			: '';

		// Escapes
		$ruc_empresa_sql    = mysqli_real_escape_string($con, $ruc_empresa);
		$fecha_emision_sql  = mysqli_real_escape_string($con, $fecha_emision);
		$numero_lcs_sql     = mysqli_real_escape_string($con, $numero_lcs);
		$codigo_doc_sql     = mysqli_real_escape_string($con, $codigo_documento);
		$id_proveedor_sql   = (int)$id_proveedor;
		$id_documento_sql   = mysqli_real_escape_string($con, $id_documento);
		$aut_sri_sql        = mysqli_real_escape_string($con, $aut_sri);
		$secuencial_sql     = mysqli_real_escape_string($con, $secuencial_lcs);
		$fecha_registro_sql = mysqli_real_escape_string($con, $fecha_registro);
		$id_usuario_sql     = (int)$id_usuario;
		$total_lcs_sql      = mysqli_real_escape_string($con, $total_lcs);
		$tipoId_sql         = mysqli_real_escape_string($con, $tipoIdComprador);

		// Transacción
		mysqli_begin_transaction($con);

		// ⚠️ Sugerido: especifica columnas explícitas. Mantengo tu orden original para no romper tu esquema.
		$sql_enc = "
        INSERT INTO encabezado_compra
        VALUES (
            NULL,
            '{$fecha_emision_sql}',
            '{$ruc_empresa_sql}',
            '{$numero_lcs_sql}',
            '{$codigo_doc_sql}',
            '{$id_proveedor_sql}',
            '{$id_documento_sql}',
            '1',
            '{$aut_sri_sql}',
            '{$fecha_emision_sql}',
            '{$secuencial_sql}',
            '{$secuencial_sql}',
            '{$fecha_registro_sql}',
            '{$id_usuario_sql}',
            '{$total_lcs_sql}',
            '',
            '0',
            'ELECTRÓNICA',
            '{$tipoId_sql}',
            '0',
            '0',
            '0'
        )
    ";
		if (!mysqli_query($con, $sql_enc)) {
			mysqli_rollback($con);
			return false;
		}

		// -------- Detalles --------
		if (isset($object_xml->detalles) && isset($object_xml->detalles->detalle)) {
			foreach ($object_xml->detalles->detalle as $detalle) {
				$codigo_detalle      = $strCleanLocal($detalle->codigoPrincipal);
				$descripcion_detalle = $strCleanLocal($detalle->descripcion);
				$cantidad_detalle    = $detalle->cantidad;
				$precio_detalle      = $detalle->precioUnitario;
				$descuento_detalle   = $detalle->descuento;

				$codigo_detalle_sql      = mysqli_real_escape_string($con, $codigo_detalle);
				$descripcion_detalle_sql = mysqli_real_escape_string($con, $descripcion_detalle);

				// Si no hay impuestos, guarda una línea base con IVA/porcentaje 0 y base 0
				$tieneImpuestos = (isset($detalle->impuestos) && isset($detalle->impuestos->impuesto));

				if (!$tieneImpuestos) {
					$sql_det = "
                    INSERT INTO cuerpo_compra
                    VALUES (
                        NULL,
                        '{$ruc_empresa_sql}',
                        '{$codigo_doc_sql}',
                        '{$codigo_detalle_sql}',
                        '{$descripcion_detalle_sql}',
                        '{$cantidad_detalle}',
                        '{$precio_detalle}',
                        '{$descuento_detalle}',
                        '0',    -- codigo impuesto
                        '0',    -- codigo porcentaje
                        '0.00', -- base imponible
                        0
                    )
                ";
					if (!mysqli_query($con, $sql_det)) {
						mysqli_rollback($con);
						return false;
					}
				} else {
					foreach ($detalle->impuestos->impuesto as $imp) {
						$codigo_impuesto    = isset($imp->codigo) ? (string)$imp->codigo : '0';
						$codigo_porcentaje  = isset($imp->codigoPorcentaje) ? (string)$imp->codigoPorcentaje : '0';
						$base_imponible     = $toDecimal(isset($imp->baseImponible) ? $imp->baseImponible : 0, 2);

						$codigo_impuesto_sql   = mysqli_real_escape_string($con, $codigo_impuesto);
						$codigo_porcentaje_sql = mysqli_real_escape_string($con, $codigo_porcentaje);
						$base_imponible_sql    = mysqli_real_escape_string($con, $base_imponible);

						$sql_det = "
                        INSERT INTO cuerpo_compra
                        VALUES (
                            NULL,
                            '{$ruc_empresa_sql}',
                            '{$codigo_doc_sql}',
                            '{$codigo_detalle_sql}',
                            '{$descripcion_detalle_sql}',
                            '{$cantidad_detalle}',
                            '{$precio_detalle}',
                            '{$descuento_detalle}',
                            '{$codigo_impuesto_sql}',
                            '{$codigo_porcentaje_sql}',
                            '{$base_imponible_sql}',
                            0
                        )
                    ";
						if (!mysqli_query($con, $sql_det)) {
							mysqli_rollback($con);
							return false;
						}
					}
				}
			}
		}

		// -------- Info adicional --------
		if (isset($object_xml->infoAdicional) && isset($object_xml->infoAdicional->campoAdicional)) {
			foreach ($object_xml->infoAdicional->campoAdicional as $campo) {
				// <campoAdicional nombre="...">valor</campoAdicional>
				$nombre = isset($campo['nombre']) ? (string)$campo['nombre'] : '';
				$valor  = (string)$campo;

				// Usa sanitize si está disponible; si no, limpieza básica
				if ($sanitize) {
					$nombre = $sanitize->string_sanitize($nombre, $force_lowercase = false, $anal = false);
					$valor  = $sanitize->string_sanitize($valor,  $force_lowercase = false, $anal = false);
				} else {
					$nombre = $strCleanLocal($nombre);
					$valor  = $strCleanLocal($valor);
				}

				$nombre_sql = mysqli_real_escape_string($con, $nombre);
				$valor_sql  = mysqli_real_escape_string($con, $valor);

				$sql_ad = "
                INSERT INTO detalle_adicional_compra
                VALUES (NULL, '{$ruc_empresa_sql}', '{$codigo_doc_sql}', '{$nombre_sql}', '{$valor_sql}')
            ";
				if (!mysqli_query($con, $sql_ad)) {
					mysqli_rollback($con);
					return false;
				}
			}
		}

		// -------- Forma de pago (crédito 20) --------
		$sql_pago = "
        INSERT INTO formas_pago_compras
        VALUES (NULL, '{$ruc_empresa_sql}', '{$codigo_doc_sql}', '20', '{$total_lcs_sql}', '1', 'dias')
    ";
		if (!mysqli_query($con, $sql_pago)) {
			mysqli_rollback($con);
			return false;
		}

		mysqli_commit($con);
		// -------- Asientos contables --------
		// Si no tienes la clase/instancias, no falles
		if ($contabilizacion) {
			$emi_iso = formatea_fecha_emision($fecha_emision);
			$desde = $emi_iso . ' 00:00:00';
			$hasta = $emi_iso . ' 23:59:59';
			$contabilizacion->documentosAdquisiciones($con, $ruc_empresa, $desde, $hasta);
			$contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'compras_servicios');
		}

		// Todo ok
		return true;
	}

	public function guarda_factura_venta_notaria($con, $ruc_empresa, $object_xml, $id_usuario, $id_cliente)
	{
		// --- Helpers compatibles con PHP 5.6 ---
		$toDecimal = function ($val, $scale = 2) {
			$s = trim((string)$val);
			// limpia espacios, incluyendo NBSP
			$s = str_replace(array("\xC2\xA0", " ", "\t"), "", $s);
			if ($s === '' || $s === '+' || $s === '-') {
				return number_format(0, $scale, '.', '');
			}

			$hasDot   = strpos($s, '.') !== false;
			$hasComma = strpos($s, ',') !== false;

			if ($hasDot && $hasComma) {
				// Si hay ambos, el ÚLTIMO símbolo suele ser el separador decimal
				$lastDot   = strrpos($s, '.');
				$lastComma = strrpos($s, ',');
				if ($lastComma > $lastDot) {
					// coma decimal, punto miles -> quita puntos y cambia coma por punto
					$s = str_replace('.', '', $s);
					$s = str_replace(',', '.', $s);
				} else {
					// punto decimal, coma miles -> quita comas
					$s = str_replace(',', '', $s);
					// deja el punto decimal
				}
			} elseif ($hasComma) {
				// Solo comas: ¿miles o decimal?
				if (preg_match('/^\d{1,3}(,\d{3})+$/', $s)) {
					// "1,234,567" -> miles
					$s = str_replace(',', '', $s);
				} else {
					// "1234,56" / "1,000000" -> coma decimal
					$s = str_replace(',', '.', $s);
				}
			} elseif ($hasDot) {
				// Solo puntos: ¿miles o decimal?
				if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
					// "1.234.567" -> miles
					$s = str_replace('.', '', $s);
				} // si es "1.000000" -> punto decimal (no tocar)
			}

			if (!is_numeric($s)) $s = '0';
			// Devuelve string con punto y $scale decimales (como venías usando)
			return number_format((float)$s, $scale, '.', '');
		};

		$strCleanLocal = function ($s) {
			if (function_exists('strClean')) return strClean($s);
			$s = strip_tags((string)$s);
			$s = preg_replace('/\s+/', ' ', $s);
			return trim($s);
		};
		$parseFechaEmision = function ($raw) {
			$raw = trim((string)$raw);
			if (preg_match('~^\d{2}/\d{2}/\d{4}$~', $raw)) {
				$dt = DateTime::createFromFormat('d/m/Y', $raw);
				if ($dt) return $dt->format('Y-m-d');
			}
			if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $raw)) return $raw;
			$ts = strtotime($raw);
			return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
		};

		$contabilizacion = new contabilizacion();
		$sanitize        = class_exists('sanitize') ? new sanitize() : null;

		// --- Datos base ---
		$fecha_agregado   = date("Y-m-d H:i:s");
		$fecha_emision    = $parseFechaEmision($object_xml->infoFactura->fechaEmision);
		$estab            = (string)$object_xml->infoTributaria->estab;
		$ptoEmi           = (string)$object_xml->infoTributaria->ptoEmi;
		$secuencial_fact  = (string)$object_xml->infoTributaria->secuencial;
		$serie_factura    = $estab . "-" . $ptoEmi;
		$aut_sri          = (string)$object_xml->infoTributaria->claveAcceso;

		$total_factura    = $toDecimal(isset($object_xml->infoFactura->importeTotal) ? $object_xml->infoFactura->importeTotal : 0, 2);
		$propina          = $toDecimal(isset($object_xml->infoFactura->propina) ? $object_xml->infoFactura->propina : 0, 2);
		$otros_val        = 0;
		if (isset($object_xml->otrosRubrosTerceros) && isset($object_xml->otrosRubrosTerceros->rubro) && isset($object_xml->otrosRubrosTerceros->rubro->total)) {
			$otros_val = $toDecimal($object_xml->otrosRubrosTerceros->rubro->total, 2);
		}

		// --- Escapes (básicos) ---
		$ruc_empresa_sql   = mysqli_real_escape_string($con, $ruc_empresa);
		$fecha_emision_sql = mysqli_real_escape_string($con, $fecha_emision);
		$serie_sql         = mysqli_real_escape_string($con, $serie_factura);
		$secuencial_sql    = mysqli_real_escape_string($con, $secuencial_fact);
		$aut_sri_sql       = mysqli_real_escape_string($con, $aut_sri);
		$total_sql         = mysqli_real_escape_string($con, $total_factura);
		$propina_sql       = mysqli_real_escape_string($con, $propina);
		$otros_sql         = mysqli_real_escape_string($con, $otros_val);
		$fecha_agregado_sql = mysqli_real_escape_string($con, $fecha_agregado);
		$id_usuario_sql    = (int)$id_usuario;
		$id_cliente_sql    = (int)$id_cliente;

		// --- Transacción ---
		mysqli_begin_transaction($con);

		// ⚠️ Mantengo el orden de columnas de tu tabla (como en tu versión original)
		$sql_enc = "
        INSERT INTO encabezado_factura
        VALUES (
            NULL,
            '{$ruc_empresa_sql}',
            '{$fecha_emision_sql}',
            '{$serie_sql}',
            '{$secuencial_sql}',
            '{$id_cliente_sql}',
            'Cargada desde xml',
            '',
            '{$fecha_agregado_sql}',
            'ok',
            'ELECTRÓNICA',
            'PENDIENTE',
            '{$total_sql}',
            '{$id_usuario_sql}',
            '2',
            '0',
            '{$aut_sri_sql}',
            'PENDIENTE',
            '{$propina_sql}',
            '{$otros_sql}'
        )
    ";
		if (!mysqli_query($con, $sql_enc)) {
			mysqli_rollback($con);
			return false;
		}

		// --- Detalles ---
		if (isset($object_xml->detalles) && isset($object_xml->detalles->detalle)) {
			foreach ($object_xml->detalles->detalle as $detalle) {
				$codigo_detalle   = $strCleanLocal(isset($detalle->codigoPrincipal) ? $detalle->codigoPrincipal : '');
				$nombre_producto  = $strCleanLocal(isset($detalle->descripcion) ? $detalle->descripcion : '');
				$precio_producto  = $detalle->precioUnitario;
				$cantidad_detalle = $detalle->cantidad;
				$descuento_detalle = $detalle->descuento;
				// Calcula subtotal como número, luego formatea
				$subtotal_num     = (float)$cantidad_detalle * (float)$precio_producto;
				$subtotal         = number_format($subtotal_num, 2, '.', '');

				$codigo_detalle_sql  = mysqli_real_escape_string($con, $codigo_detalle);
				$nombre_producto_sql = mysqli_real_escape_string($con, $nombre_producto);

				// impuestos: pueden venir varios; si no vienen, guarda con IVA/ICE 0
				$lineas_impuesto = array();
				if (isset($detalle->impuestos) && isset($detalle->impuestos->impuesto)) {
					foreach ($detalle->impuestos->impuesto as $imp) {
						$codigo_imp   = isset($imp->codigo) ? (string)$imp->codigo : '0';
						$iva          = ($codigo_imp === "2" && isset($imp->codigoPorcentaje)) ? (string)$imp->codigoPorcentaje : "0";
						$ice          = ($codigo_imp === "3" && isset($imp->codigoPorcentaje)) ? (string)$imp->codigoPorcentaje : "0";
						$lineas_impuesto[] = array('iva' => $iva, 'ice' => $ice);
					}
				}
				if (empty($lineas_impuesto)) {
					$lineas_impuesto[] = array('iva' => '0', 'ice' => '0');
				}

				// Buscar/crear producto
				$tipo_produccion = "02"; // Servicio por defecto (ajústalo a tu codificación)
				$id_producto = 0;

				$busca_producto = mysqli_query($con, "
                SELECT id, codigo_producto, nombre_producto, tipo_producion, precio_producto
                FROM productos_servicios
                WHERE ruc_empresa = '{$ruc_empresa_sql}' AND codigo_producto = '{$codigo_detalle_sql}'
                LIMIT 1
            ");
				if ($busca_producto && mysqli_num_rows($busca_producto) > 0) {
					$row = mysqli_fetch_assoc($busca_producto);
					$id_producto      = (int)$row['id'];
					$tipo_produccion  = isset($row['tipo_producion']) ? $row['tipo_producion'] : "02";
					// (opcional) podrías actualizar nombre/precio si difieren
				} else {
					// Si no existe, lo creamos usando el primer par IVA/ICE encontrado
					$iva_ins = isset($lineas_impuesto[0]['iva']) ? $lineas_impuesto[0]['iva'] : '0';
					$ice_ins = isset($lineas_impuesto[0]['ice']) ? $lineas_impuesto[0]['ice'] : '0';
					$precio_sql = mysqli_real_escape_string($con, $precio_producto);

					$sql_ins_prod = "
                    INSERT INTO productos_servicios
                    VALUES (
                        NULL,
                        '{$ruc_empresa_sql}',
                        '{$codigo_detalle_sql}',
                        '{$nombre_producto_sql}',
                        '',
                        '{$precio_sql}',
                        '02',
                        '{$iva_ins}',
                        '{$ice_ins}',
                        '0',
                        '{$fecha_agregado_sql}',
                        '17',
                        '1',
                        '{$id_usuario_sql}'
                    )
                ";
					if (!mysqli_query($con, $sql_ins_prod)) {
						mysqli_rollback($con);
						return false;
					}
					// vuelve a leer para obtener id y tipo
					$busca_producto2 = mysqli_query($con, "
                    SELECT id, codigo_producto, tipo_producion
                    FROM productos_servicios
                    WHERE ruc_empresa = '{$ruc_empresa_sql}' AND codigo_producto = '{$codigo_detalle_sql}'
                    LIMIT 1
                ");
					if (!$busca_producto2 || mysqli_num_rows($busca_producto2) == 0) {
						mysqli_rollback($con);
						return false;
					}
					$row2 = mysqli_fetch_assoc($busca_producto2);
					$id_producto     = (int)$row2['id'];
					$tipo_produccion = isset($row2['tipo_producion']) ? $row2['tipo_producion'] : "02";
				}

				// Inserta una línea por combinación de impuestos (comportamiento original)
				foreach ($lineas_impuesto as $li) {
					$iva_sql = mysqli_real_escape_string($con, $li['iva']);
					$ice_sql = mysqli_real_escape_string($con, $li['ice']);

					$sql_det = "
                    INSERT INTO cuerpo_factura
                    VALUES (
                        NULL,
                        '{$ruc_empresa_sql}',
                        '{$serie_sql}',
                        '{$secuencial_sql}',
                        '{$id_producto}',
                        '{$cantidad_detalle}',
                        '{$precio_producto}',
                        '{$subtotal}',
                        '{$tipo_produccion}',
                        '{$iva_sql}',
                        '{$ice_sql}',
                        '0',
                        '{$descuento_detalle}',
                        '{$codigo_detalle_sql}',
                        '{$nombre_producto_sql}',
                        '0','0','0','0'
                    )
                ";
					if (!mysqli_query($con, $sql_det)) {
						mysqli_rollback($con);
						return false;
					}
				}
			}
		}

		// --- Info adicional ---
		if (isset($object_xml->infoAdicional) && isset($object_xml->infoAdicional->campoAdicional)) {
			foreach ($object_xml->infoAdicional->campoAdicional as $campo) {
				// <campoAdicional nombre="...">valor</campoAdicional>
				$nombre = isset($campo['nombre']) ? (string)$campo['nombre'] : '';
				$valor  = (string)$campo;

				if ($sanitize) {
					$nombre = $sanitize->string_sanitize($nombre, false, false);
					$valor  = $sanitize->string_sanitize($valor,  false, false);
				} else {
					$nombre = $strCleanLocal($nombre);
					$valor  = $strCleanLocal($valor);
				}

				$nombre_sql = mysqli_real_escape_string($con, $nombre);
				$valor_sql  = mysqli_real_escape_string($con, $valor);

				$sql_ad = "
                INSERT INTO detalle_adicional_factura
                VALUES (NULL, '{$ruc_empresa_sql}', '{$serie_sql}', '{$secuencial_sql}', '{$nombre_sql}', '{$valor_sql}')
            ";
				if (!mysqli_query($con, $sql_ad)) {
					mysqli_rollback($con);
					return false;
				}
			}
		}

		// --- Formas de pago ---
		if (isset($object_xml->infoFactura->pagos) && isset($object_xml->infoFactura->pagos->pago)) {
			foreach ($object_xml->infoFactura->pagos->pago as $pago) {
				$forma_pago  = isset($pago->formaPago) ? (string)$pago->formaPago : '';
				$total_pago  = $toDecimal(isset($pago->total) ? $pago->total : 0, 2);
				// En tu esquema original no guardas plazo/tiempo; lo dejo igual
				$forma_pago_sql = mysqli_real_escape_string($con, $forma_pago);
				$total_pago_sql = mysqli_real_escape_string($con, $total_pago);

				$sql_pago = "
                INSERT INTO formas_pago_ventas
                VALUES (NULL, '{$ruc_empresa_sql}', '{$serie_sql}', '{$secuencial_sql}', '{$forma_pago_sql}', '{$total_pago_sql}')
            ";
				if (!mysqli_query($con, $sql_pago)) {
					mysqli_rollback($con);
					return false;
				}
			}
		}

		mysqli_commit($con);
		// -------- Asientos contables --------
		if ($contabilizacion) {
			$emi_iso = formatea_fecha_emision($fecha_emision);
			$desde = $emi_iso . ' 00:00:00';
			$hasta = $emi_iso . ' 23:59:59';
			$contabilizacion->documentosVentasFacturas($con, $ruc_empresa, $desde, $hasta);
			$contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'ventas');
		}

		return true;
	}


	public function guarda_factura_venta($con, $ruc_empresa, $object_xml, $id_usuario, $id_cliente)
	{
		// --- Helpers (PHP 5.6+) ---
		$toDecimal = function ($val, $scale = 2) {
			$s = trim((string)$val);
			// limpia espacios, incluyendo NBSP
			$s = str_replace(array("\xC2\xA0", " ", "\t"), "", $s);
			if ($s === '' || $s === '+' || $s === '-') {
				return number_format(0, $scale, '.', '');
			}

			$hasDot   = strpos($s, '.') !== false;
			$hasComma = strpos($s, ',') !== false;

			if ($hasDot && $hasComma) {
				// Si hay ambos, el ÚLTIMO símbolo suele ser el separador decimal
				$lastDot   = strrpos($s, '.');
				$lastComma = strrpos($s, ',');
				if ($lastComma > $lastDot) {
					// coma decimal, punto miles -> quita puntos y cambia coma por punto
					$s = str_replace('.', '', $s);
					$s = str_replace(',', '.', $s);
				} else {
					// punto decimal, coma miles -> quita comas
					$s = str_replace(',', '', $s);
					// deja el punto decimal
				}
			} elseif ($hasComma) {
				// Solo comas: ¿miles o decimal?
				if (preg_match('/^\d{1,3}(,\d{3})+$/', $s)) {
					// "1,234,567" -> miles
					$s = str_replace(',', '', $s);
				} else {
					// "1234,56" / "1,000000" -> coma decimal
					$s = str_replace(',', '.', $s);
				}
			} elseif ($hasDot) {
				// Solo puntos: ¿miles o decimal?
				if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
					// "1.234.567" -> miles
					$s = str_replace('.', '', $s);
				} // si es "1.000000" -> punto decimal (no tocar)
			}

			if (!is_numeric($s)) $s = '0';
			// Devuelve string con punto y $scale decimales (como venías usando)
			return number_format((float)$s, $scale, '.', '');
		};

		$strCleanLocal = function ($s) {
			if (function_exists('strClean')) return strClean($s);
			$s = strip_tags((string)$s);
			$s = preg_replace('/\s+/', ' ', $s);
			return trim($s);
		};
		$parseFechaEmision = function ($raw) {
			$raw = trim((string)$raw);
			if (preg_match('~^\d{2}/\d{2}/\d{4}$~', $raw)) {
				$dt = DateTime::createFromFormat('d/m/Y', $raw);
				if ($dt) return $dt->format('Y-m-d');
			}
			if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $raw)) return $raw;
			$ts = strtotime($raw);
			return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
		};

		$contabilizacion = new contabilizacion();
		$sanitize        = class_exists('sanitize') ? new sanitize() : null;

		// --- Datos base ---
		$fecha_agregado   = date("Y-m-d H:i:s");
		$fecha_emision    = $parseFechaEmision($object_xml->infoFactura->fechaEmision);
		$estab            = (string)$object_xml->infoTributaria->estab;
		$ptoEmi           = (string)$object_xml->infoTributaria->ptoEmi;
		$secuencial_fact  = (string)$object_xml->infoTributaria->secuencial;
		$serie_factura    = $estab . "-" . $ptoEmi;
		$aut_sri          = (string)$object_xml->infoTributaria->claveAcceso;

		$total_factura    = $toDecimal(isset($object_xml->infoFactura->importeTotal) ? $object_xml->infoFactura->importeTotal : 0, 2);
		$propina          = $toDecimal(isset($object_xml->infoFactura->propina) ? $object_xml->infoFactura->propina : 0, 2);
		$otros_val        = 0;
		if (isset($object_xml->otrosRubrosTerceros) && isset($object_xml->otrosRubrosTerceros->rubro) && isset($object_xml->otrosRubrosTerceros->rubro->total)) {
			$otros_val = $toDecimal($object_xml->otrosRubrosTerceros->rubro->total, 2);
		}

		// --- Escapes ---
		$ruc_empresa_sql   = mysqli_real_escape_string($con, $ruc_empresa);
		$fecha_emision_sql = mysqli_real_escape_string($con, $fecha_emision);
		$serie_sql         = mysqli_real_escape_string($con, $serie_factura);
		$secuencial_sql    = mysqli_real_escape_string($con, $secuencial_fact);
		$aut_sri_sql       = mysqli_real_escape_string($con, $aut_sri);
		$total_sql         = mysqli_real_escape_string($con, $total_factura);
		$propina_sql       = mysqli_real_escape_string($con, $propina);
		$otros_sql         = mysqli_real_escape_string($con, $otros_val);
		$fecha_agregado_sql = mysqli_real_escape_string($con, $fecha_agregado);
		$id_usuario_sql    = (int)$id_usuario;
		$id_cliente_sql    = (int)$id_cliente;

		// --- Transacción ---
		mysqli_begin_transaction($con);

		// Encabezado (mantengo tu orden/estados: AUTORIZADO + ENVIADO)
		$sql_enc = "
        INSERT INTO encabezado_factura
        VALUES (
            NULL,
            '{$ruc_empresa_sql}',
            '{$fecha_emision_sql}',
            '{$serie_sql}',
            '{$secuencial_sql}',
            '{$id_cliente_sql}',
            'Cargada desde xml',
            '',
            '{$fecha_agregado_sql}',
            'ok',
            'ELECTRÓNICA',
            'AUTORIZADO',
            '{$total_sql}',
            '{$id_usuario_sql}',
            '2',
            '0',
            '{$aut_sri_sql}',
            'ENVIADO',
            '{$propina_sql}',
            '{$otros_sql}'
        )
    ";
		if (!mysqli_query($con, $sql_enc)) {
			mysqli_rollback($con);
			return false;
		}

		// --- Detalles ---
		if (isset($object_xml->detalles) && isset($object_xml->detalles->detalle)) {
			foreach ($object_xml->detalles->detalle as $detalle) {
				$codigo_detalle   = $strCleanLocal(isset($detalle->codigoPrincipal) ? $detalle->codigoPrincipal : '');
				$nombre_producto  = $strCleanLocal(isset($detalle->descripcion) ? $detalle->descripcion : '');
				$precio_producto  = $toDecimal(isset($detalle->precioUnitario) ? $detalle->precioUnitario : 0, 6);
				$cantidad_detalle = $detalle->cantidad;
				$descuento_detalle = $detalle->descuento;
				$subtotal         = number_format(((float)$cantidad_detalle * (float)$precio_producto), 2, '.', '');

				$codigo_detalle_sql  = mysqli_real_escape_string($con, $codigo_detalle);
				$nombre_producto_sql = mysqli_real_escape_string($con, $nombre_producto);

				// impuestos (puede haber 0..n). Si no hay, guardamos línea con IVA/ICE 0
				$lineas_impuesto = array();
				if (isset($detalle->impuestos) && isset($detalle->impuestos->impuesto)) {
					foreach ($detalle->impuestos->impuesto as $imp) {
						$codigo_imp   = isset($imp->codigo) ? (string)$imp->codigo : '0';
						$iva          = ($codigo_imp === "2" && isset($imp->codigoPorcentaje)) ? (string)$imp->codigoPorcentaje : "0";
						$ice          = ($codigo_imp === "3" && isset($imp->codigoPorcentaje)) ? (string)$imp->codigoPorcentaje : "0";
						$lineas_impuesto[] = array('iva' => $iva, 'ice' => $ice);
					}
				}
				if (empty($lineas_impuesto)) {
					$lineas_impuesto[] = array('iva' => '0', 'ice' => '0');
				}

				// Buscar/crear producto (servicio por defecto: "02")
				$tipo_produccion = "02";
				$id_producto = 0;

				$busca_producto = mysqli_query($con, "
                SELECT id, codigo_producto, nombre_producto, tipo_producion, precio_producto
                FROM productos_servicios
                WHERE ruc_empresa = '{$ruc_empresa_sql}' AND codigo_producto = '{$codigo_detalle_sql}'
                LIMIT 1
            ");
				if ($busca_producto && mysqli_num_rows($busca_producto) > 0) {
					$row = mysqli_fetch_assoc($busca_producto);
					$id_producto     = (int)$row['id'];
					$tipo_produccion = isset($row['tipo_producion']) ? $row['tipo_producion'] : "02";
				} else {
					$iva_ins = isset($lineas_impuesto[0]['iva']) ? $lineas_impuesto[0]['iva'] : '0';
					$ice_ins = isset($lineas_impuesto[0]['ice']) ? $lineas_impuesto[0]['ice'] : '0';
					$precio_sql = mysqli_real_escape_string($con, $precio_producto);

					$sql_ins_prod = "
                    INSERT INTO productos_servicios
                    VALUES (
                        NULL,
                        '{$ruc_empresa_sql}',
                        '{$codigo_detalle_sql}',
                        '{$nombre_producto_sql}',
                        '',
                        '{$precio_sql}',
                        '02',
                        '{$iva_ins}',
                        '{$ice_ins}',
                        '0',
                        '{$fecha_agregado_sql}',
                        '17',
                        '1',
                        '{$id_usuario_sql}'
                    )
                ";
					if (!mysqli_query($con, $sql_ins_prod)) {
						mysqli_rollback($con);
						return false;
					}
					$busca_producto2 = mysqli_query($con, "
                    SELECT id, codigo_producto, tipo_producion
                    FROM productos_servicios
                    WHERE ruc_empresa = '{$ruc_empresa_sql}' AND codigo_producto = '{$codigo_detalle_sql}'
                    LIMIT 1
                ");
					if (!$busca_producto2 || mysqli_num_rows($busca_producto2) == 0) {
						mysqli_rollback($con);
						return false;
					}
					$row2 = mysqli_fetch_assoc($busca_producto2);
					$id_producto     = (int)$row2['id'];
					$tipo_produccion = isset($row2['tipo_producion']) ? $row2['tipo_producion'] : "02";
				}

				// Inserta una línea por combinación de impuestos
				foreach ($lineas_impuesto as $li) {
					$iva_sql = mysqli_real_escape_string($con, $li['iva']);
					$ice_sql = mysqli_real_escape_string($con, $li['ice']);

					$sql_det = "
                    INSERT INTO cuerpo_factura
                    VALUES (
                        NULL,
                        '{$ruc_empresa_sql}',
                        '{$serie_sql}',
                        '{$secuencial_sql}',
                        '{$id_producto}',
                        '{$cantidad_detalle}',
                        '{$precio_producto}',
                        '{$subtotal}',
                        '{$tipo_produccion}',
                        '{$iva_sql}',
                        '{$ice_sql}',
                        '0',
                        '{$descuento_detalle}',
                        '{$codigo_detalle_sql}',
                        '{$nombre_producto_sql}',
                        '0','0','0','0'
                    )
                ";
					if (!mysqli_query($con, $sql_det)) {
						mysqli_rollback($con);
						return false;
					}
				}
			}
		}

		// --- Info adicional ---
		if (isset($object_xml->infoAdicional) && isset($object_xml->infoAdicional->campoAdicional)) {
			foreach ($object_xml->infoAdicional->campoAdicional as $campo) {
				// <campoAdicional nombre="...">valor</campoAdicional>
				$nombre = isset($campo['nombre']) ? (string)$campo['nombre'] : '';
				$valor  = (string)$campo;

				if ($sanitize) {
					$nombre = $sanitize->string_sanitize($nombre, false, false);
					$valor  = $sanitize->string_sanitize($valor,  false, false);
				} else {
					$nombre = $strCleanLocal($nombre);
					$valor  = $strCleanLocal($valor);
				}

				$nombre_sql = mysqli_real_escape_string($con, $nombre);
				$valor_sql  = mysqli_real_escape_string($con, $valor);

				$sql_ad = "
                INSERT INTO detalle_adicional_factura
                VALUES (NULL, '{$ruc_empresa_sql}', '{$serie_sql}', '{$secuencial_sql}', '{$nombre_sql}', '{$valor_sql}')
            ";
				if (!mysqli_query($con, $sql_ad)) {
					mysqli_rollback($con);
					return false;
				}
			}
		}

		// --- Formas de pago ---
		if (isset($object_xml->infoFactura->pagos) && isset($object_xml->infoFactura->pagos->pago)) {
			foreach ($object_xml->infoFactura->pagos->pago as $pago) {
				$forma_pago  = isset($pago->formaPago) ? (string)$pago->formaPago : '';
				$total_pago  = $toDecimal(isset($pago->total) ? $pago->total : 0, 2);
				$forma_pago_sql = mysqli_real_escape_string($con, $forma_pago);
				$total_pago_sql = mysqli_real_escape_string($con, $total_pago);

				$sql_pago = "
                INSERT INTO formas_pago_ventas
                VALUES (NULL, '{$ruc_empresa_sql}', '{$serie_sql}', '{$secuencial_sql}', '{$forma_pago_sql}', '{$total_pago_sql}')
            ";
				if (!mysqli_query($con, $sql_pago)) {
					mysqli_rollback($con);
					return false;
				}
			}
		}


		mysqli_commit($con);
		// -------- Asientos contables --------
		if ($contabilizacion) {
			$emi_iso = formatea_fecha_emision($fecha_emision);
			$desde = $emi_iso . ' 00:00:00';
			$hasta = $emi_iso . ' 23:59:59';
			$contabilizacion->documentosVentasFacturas($con, $ruc_empresa, $desde, $hasta);
			$contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'ventas');
		}
		return true;
	}


	public function guarda_factura_compra($con, $ruc_empresa, $object_xml, $id_usuario, $id_proveedor)
	{
		// --- Helpers compatibles con PHP 5.6 ---
$toDecimal = function ($val, $scale = 2) {
    // 1) Normaliza: a string, quita espacios (incluye NBSP) y símbolos no numéricos comunes
    $s = trim((string)$val);
    $s = str_replace(array("\xC2\xA0", " ", "\t"), "", $s);
    // Permitir solo dígitos, punto, coma, + y -
    $s = preg_replace('/[^0-9\-\+\,\.]/u', '', $s);

    if ($s === '' || $s === '+' || $s === '-') {
        return number_format(0, $scale, '.', '');
    }

    // 2) Conserva signo si existe
    $sign = '';
    if ($s[0] === '+' || $s[0] === '-') {
        $sign = $s[0];
        $s = substr($s, 1);
        if ($s === '') return number_format(0, $scale, '.', '');
    }

    $hasDot   = strpos($s, '.') !== false;
    $hasComma = strpos($s, ',') !== false;

    if ($hasDot && $hasComma) {
        // Ambos presentes: el último símbolo es decimal
        $lastDot   = strrpos($s, '.');
        $lastComma = strrpos($s, ',');
        if ($lastComma > $lastDot) {
            // coma decimal, punto miles
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            // punto decimal, coma miles
            $s = str_replace(',', '', $s);
            // deja el punto como decimal
        }
    } elseif ($hasComma) {
        // Solo comas
        if (preg_match('/^\d{1,3}(,\d{3})+$/', $s)) {
            // miles con múltiples grupos: "1,234,567"
            $s = str_replace(',', '', $s);
        } else {
            // decimal con coma: "1234,56"
            $s = str_replace(',', '.', $s);
        }
    } elseif ($hasDot) {
        // Solo puntos
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
            // miles con múltiples grupos: "1.234.567"
            $s = str_replace('.', '', $s);
        } else {
            // decimal con punto: "641.680" -> NO tocar
        }
    }

    // 3) Restaura signo
    if ($sign !== '') {
        $s = $sign . $s;
    }

    // 4) Validación final
    if (!is_numeric($s)) {
        $s = '0';
    }

    // 5) Salida formateada
    return number_format((float)$s, $scale, '.', '');
};


		$strCleanLocal = function ($s) {
			if (function_exists('strClean')) return strClean($s);
			$s = strip_tags((string)$s);
			$s = preg_replace('/\s+/', ' ', $s);
			return trim($s);
		};
		$parseFechaEmision = function ($raw) {
			$raw = trim((string)$raw);
			if (preg_match('~^\d{2}/\d{2}/\d{4}$~', $raw)) {
				$dt = DateTime::createFromFormat('d/m/Y', $raw);
				if ($dt) return $dt->format('Y-m-d');
			}
			if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $raw)) return $raw;
			$ts = strtotime($raw);
			return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
		};

		$contabilizacion = new contabilizacion();
		$sanitize        = class_exists('sanitize') ? new sanitize() : null;

		// --- Base ---
		$fecha_registro   = date("Y-m-d H:i:s");
		$fecha_emision    = $parseFechaEmision($object_xml->infoFactura->fechaEmision);
		$estab            = (string)$object_xml->infoTributaria->estab;
		$ptoEmi           = (string)$object_xml->infoTributaria->ptoEmi;
		$secuencial       = (string)$object_xml->infoTributaria->secuencial;
		$numero_factura   = $estab . "-" . $ptoEmi . "-" . $secuencial;
		$codigo_documento = codigo_aleatorio(20);
		$id_documento     = (string)$object_xml->infoTributaria->codDoc;          // 01
		$aut_sri          = (string)$object_xml->infoTributaria->claveAcceso;
		$total_factura    = $toDecimal($object_xml->infoFactura->importeTotal, 2);
		$propina          = $toDecimal(isset($object_xml->infoFactura->propina) ? $object_xml->infoFactura->propina : 0, 2);
		$otros_val_xml    = 0;
		if (isset($object_xml->otrosRubrosTerceros) && isset($object_xml->otrosRubrosTerceros->rubro) && isset($object_xml->otrosRubrosTerceros->rubro->total)) {
			$otros_val_xml = $toDecimal($object_xml->otrosRubrosTerceros->rubro->total, 2);
		}
		//$nombre_proveedor = (string)$object_xml->infoTributaria->razonSocial;
		$tipoIdCompXML    = isset($object_xml->infoFactura->tipoIdentificacionComprador) ? (string)$object_xml->infoFactura->tipoIdentificacionComprador : '';

		// --- Tipo empresa (ajuste deducibilidad para personas naturales) ---
		$tipoIdentificacionComprador = $tipoIdCompXML;
		$rs_emp = mysqli_query($con, "SELECT tipo FROM empresas WHERE ruc = '" . mysqli_real_escape_string($con, $ruc_empresa) . "' LIMIT 1");
		if ($rs_emp && ($row = mysqli_fetch_assoc($rs_emp))) {
			$tipo_emp = $row['tipo'];
			if (!($tipo_emp === '01' || $tipo_emp === '02')) { // Si NO es persona jurídica
				$tipoIdentificacionComprador = '04'; // cédula por defecto
			}
		}

		// --- Escapes ---
		$ruc_empresa_sql    = mysqli_real_escape_string($con, $ruc_empresa);
		$fecha_emision_sql  = mysqli_real_escape_string($con, $fecha_emision);
		$numero_factura_sql = mysqli_real_escape_string($con, $numero_factura);
		$codigo_doc_sql     = mysqli_real_escape_string($con, $codigo_documento);
		$id_proveedor_sql   = (int)$id_proveedor;
		$id_documento_sql   = mysqli_real_escape_string($con, $id_documento);
		$aut_sri_sql        = mysqli_real_escape_string($con, $aut_sri);
		$secuencial_sql     = mysqli_real_escape_string($con, $secuencial);
		$fecha_registro_sql = mysqli_real_escape_string($con, $fecha_registro);
		$id_usuario_sql     = (int)$id_usuario;
		$total_sql          = mysqli_real_escape_string($con, $total_factura);
		$propina_sql        = mysqli_real_escape_string($con, $propina);
		$otros_xml_sql      = mysqli_real_escape_string($con, $otros_val_xml);
		$tipoId_sql         = mysqli_real_escape_string($con, $tipoIdentificacionComprador);

		// --- Transacción ---
		mysqli_begin_transaction($con);

		// Encabezado (mantengo tu orden de columnas)
		$sql_enc = "
        INSERT INTO encabezado_compra
        VALUES (
            NULL,
            '{$fecha_emision_sql}',
            '{$ruc_empresa_sql}',
            '{$numero_factura_sql}',
            '{$codigo_doc_sql}',
            '{$id_proveedor_sql}',
            '{$id_documento_sql}',
            '1',
            '{$aut_sri_sql}',
            '{$fecha_emision_sql}',
            '{$secuencial_sql}',
            '{$secuencial_sql}',
            '{$fecha_registro_sql}',
            '{$id_usuario_sql}',
            '{$total_sql}',
            '',
            '0',
            'ELECTRÓNICA',
            '{$tipoId_sql}',
            '{$propina_sql}',
            '{$otros_xml_sql}',
            '0'
        )
    ";
		if (!mysqli_query($con, $sql_enc)) {
			mysqli_rollback($con);
			return false;
		}
		$id_compras = mysqli_insert_id($con);

		// --- Detalles ---
		if (isset($object_xml->detalles) && isset($object_xml->detalles->detalle)) {
			foreach ($object_xml->detalles->detalle as $detalle) {
				$codigo_detalle     = $strCleanLocal(isset($detalle->codigoPrincipal) ? $detalle->codigoPrincipal : '');
				$descripcion_detalle = $strCleanLocal(isset($detalle->descripcion) ? $detalle->descripcion : '');
				$cantidad_detalle = $detalle->cantidad ;
				$precio_detalle     = $detalle->precioUnitario;
				$descuento_detalle  = $detalle->descuento;

				$codigo_detalle_sql      = mysqli_real_escape_string($con, $codigo_detalle);
				$descripcion_detalle_sql = mysqli_real_escape_string($con, $descripcion_detalle);

				// Si no trae <impuestos>, inserta una línea base con IVA 0 (código 2, porcentaje 0) y base = cantidad*precio - descuento
				$tieneImpuestos = (isset($detalle->impuestos) && isset($detalle->impuestos->impuesto));

				if (!$tieneImpuestos) {
					$base_calc = (float)$cantidad_detalle * (float)$precio_detalle - (float)$descuento_detalle;
					$base_calc = number_format($base_calc < 0 ? 0 : $base_calc, 2, '.', '');
					$sql_det = "
                    INSERT INTO cuerpo_compra
                    VALUES (
                        NULL,
                        '{$ruc_empresa_sql}',
                        '{$codigo_doc_sql}',
                        '{$codigo_detalle_sql}',
                        '{$descripcion_detalle_sql}',
                        '{$cantidad_detalle}',
                        '{$precio_detalle}',
                        '{$descuento_detalle}',
                        '2',
                        '0',
                        '{$base_calc}',
                        0
                    )
                ";
					if (!mysqli_query($con, $sql_det)) {
						mysqli_rollback($con);
						return false;
					}
				} else {
					foreach ($detalle->impuestos->impuesto as $imp) {
						$codigo_impuesto   = isset($imp->codigo) ? (string)$imp->codigo : '0';
						$codigo_porcentaje = isset($imp->codigoPorcentaje) ? (string)$imp->codigoPorcentaje : '0';
						$base_imponible    = $toDecimal(isset($imp->baseImponible) ? $imp->baseImponible : 0, 2);
						$valor_impuesto    = $toDecimal(isset($imp->valor) ? $imp->valor : 0, 2);

						if ($codigo_impuesto === "2") { // IVA
							$sql_det = "
                            INSERT INTO cuerpo_compra
                            VALUES (
                                NULL,
                                '{$ruc_empresa_sql}',
                                '{$codigo_doc_sql}',
                                '{$codigo_detalle_sql}',
                                '{$descripcion_detalle_sql}',
                                '{$cantidad_detalle}',
                                '{$precio_detalle}',
                                '{$descuento_detalle}',
                                '{$codigo_impuesto}',
                                '{$codigo_porcentaje}',
                                '{$base_imponible}',
                                0
                            )
                        ";
							if (!mysqli_query($con, $sql_det)) {
								mysqli_rollback($con);
								return false;
							}
						} elseif ($codigo_impuesto === "3") { // ICE
							// Acumula ICE en otros_val
							$sql_upd_ice = "
                            UPDATE encabezado_compra
                            SET otros_val = (otros_val + '{$valor_impuesto}')
                            WHERE codigo_documento = '{$codigo_doc_sql}'
                            LIMIT 1
                        ";
							if (!mysqli_query($con, $sql_upd_ice)) {
								mysqli_rollback($con);
								return false;
							}
						}
						// Si necesitas soportar IRBPNR (código 5), se puede añadir aquí.
					}
				}
			}
		}

		// --- Info adicional ---
		if (isset($object_xml->infoAdicional) && isset($object_xml->infoAdicional->campoAdicional)) {
			foreach ($object_xml->infoAdicional->campoAdicional as $campo) {
				// <campoAdicional nombre="...">valor</campoAdicional>
				$nombre = isset($campo['nombre']) ? (string)$campo['nombre'] : '';
				$valor  = (string)$campo;

				if ($sanitize) {
					$nombre = $sanitize->string_sanitize($nombre, false, false);
					$valor  = $sanitize->string_sanitize($valor,  false, false);
				} else {
					$nombre = $strCleanLocal($nombre);
					$valor  = $strCleanLocal($valor);
				}

				$nombre_sql = mysqli_real_escape_string($con, $nombre);
				$valor_sql  = mysqli_real_escape_string($con, $valor);

				$sql_ad = "
                INSERT INTO detalle_adicional_compra
                VALUES (NULL, '{$ruc_empresa_sql}', '{$codigo_doc_sql}', '{$nombre_sql}', '{$valor_sql}')
            ";
				if (!mysqli_query($con, $sql_ad)) {
					mysqli_rollback($con);
					return false;
				}
			}
		}

		// --- Formas de pago ---
		if (isset($object_xml->infoFactura->pagos) && isset($object_xml->infoFactura->pagos->pago)) {
			foreach ($object_xml->infoFactura->pagos->pago as $p) {
				$forma_pago = isset($p->formaPago) ? (string)$p->formaPago : '20';
				$total_pago = $toDecimal(isset($p->total) ? $p->total : $total_factura, 2);
				$plazo_pago = isset($p->plazo) ? (string)$p->plazo : '1';
				$tiempo_pago = isset($p->unidadTiempo) ? (string)$p->unidadTiempo : 'Dias';

				$sql_pago = "
                INSERT INTO formas_pago_compras
                VALUES (NULL, '{$ruc_empresa_sql}', '{$codigo_doc_sql}', '" . mysqli_real_escape_string($con, $forma_pago) . "',
                        '" . mysqli_real_escape_string($con, $total_pago) . "',
                        '" . mysqli_real_escape_string($con, $plazo_pago) . "',
                        '" . mysqli_real_escape_string($con, $tiempo_pago) . "')
            ";
				if (!mysqli_query($con, $sql_pago)) {
					mysqli_rollback($con);
					return false;
				}
			}
		} else {
			// Fallback: crédito 1 día por total
			$sql_pago = "
            INSERT INTO formas_pago_compras
            VALUES (NULL, '{$ruc_empresa_sql}', '{$codigo_doc_sql}', '20', '{$total_sql}', '1', 'Dias')
        ";
			if (!mysqli_query($con, $sql_pago)) {
				mysqli_rollback($con);
				return false;
			}
		}

		// --- Vincula retenciones a la compra creada ---
		$sql_upd_ret = "
        UPDATE encabezado_retencion AS enc
        INNER JOIN encabezado_compra AS com
            ON com.numero_documento = enc.numero_comprobante
           AND com.id_proveedor     = enc.id_proveedor
           AND com.ruc_empresa      = enc.ruc_empresa
        SET enc.id_compras = com.id_encabezado_compra
        WHERE com.ruc_empresa   = '{$ruc_empresa_sql}'
          AND com.id_proveedor  = '{$id_proveedor_sql}'
          AND com.numero_documento = '{$numero_factura_sql}'
          AND enc.id_compras = '0'
    ";
		if (!mysqli_query($con, $sql_upd_ret)) {
			mysqli_rollback($con);
			return false;
		}

		mysqli_commit($con);
		// -------- Asientos contables --------
		if ($contabilizacion) {
			$emi_iso = formatea_fecha_emision($fecha_emision);
			$desde = $emi_iso . ' 00:00:00';
			$hasta = $emi_iso . ' 23:59:59';
			$contabilizacion->documentosAdquisiciones($con, $ruc_empresa, $desde, $hasta);
			$contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'compras_servicios');
		}
		return true;
	}

	public function guarda_retencion_compra($con, $ruc_empresa, $object_xml, $id_usuario, $id_proveedor)
	{
		// --- Helpers (PHP 5.6+) ---
		$toDecimal = function ($val, $scale = 2) {
			$s = trim((string)$val);
			// limpia espacios, incluyendo NBSP
			$s = str_replace(array("\xC2\xA0", " ", "\t"), "", $s);
			if ($s === '' || $s === '+' || $s === '-') {
				return number_format(0, $scale, '.', '');
			}

			$hasDot   = strpos($s, '.') !== false;
			$hasComma = strpos($s, ',') !== false;

			if ($hasDot && $hasComma) {
				// Si hay ambos, el ÚLTIMO símbolo suele ser el separador decimal
				$lastDot   = strrpos($s, '.');
				$lastComma = strrpos($s, ',');
				if ($lastComma > $lastDot) {
					// coma decimal, punto miles -> quita puntos y cambia coma por punto
					$s = str_replace('.', '', $s);
					$s = str_replace(',', '.', $s);
				} else {
					// punto decimal, coma miles -> quita comas
					$s = str_replace(',', '', $s);
					// deja el punto decimal
				}
			} elseif ($hasComma) {
				// Solo comas: ¿miles o decimal?
				if (preg_match('/^\d{1,3}(,\d{3})+$/', $s)) {
					// "1,234,567" -> miles
					$s = str_replace(',', '', $s);
				} else {
					// "1234,56" / "1,000000" -> coma decimal
					$s = str_replace(',', '.', $s);
				}
			} elseif ($hasDot) {
				// Solo puntos: ¿miles o decimal?
				if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
					// "1.234.567" -> miles
					$s = str_replace('.', '', $s);
				} // si es "1.000000" -> punto decimal (no tocar)
			}

			if (!is_numeric($s)) $s = '0';
			// Devuelve string con punto y $scale decimales (como venías usando)
			return number_format((float)$s, $scale, '.', '');
		};

		$strCleanLocal = function ($s) {
			if (function_exists('strClean')) return strClean($s);
			$s = strip_tags((string)$s);
			$s = preg_replace('/\s+/', ' ', $s);
			return trim($s);
		};
		$parseFecha = function ($raw) {
			$raw = trim((string)$raw);
			if ($raw === '') return date('Y-m-d');
			if (preg_match('~^\d{2}/\d{2}/\d{4}$~', $raw)) {
				$dt = DateTime::createFromFormat('d/m/Y', $raw);
				if ($dt) return $dt->format('Y-m-d');
			}
			if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $raw)) return $raw;
			$ts = strtotime($raw);
			return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
		};

		$contabilizacion = new contabilizacion();

		// --- Base de encabezado ---
		$fecha_emision   = $parseFecha($object_xml->infoCompRetencion->fechaEmision);
		$serie_retencion = (string)$object_xml->infoTributaria->estab . "-" . (string)$object_xml->infoTributaria->ptoEmi;
		$secuencial_ret  = (string)$object_xml->infoTributaria->secuencial;
		$aut_sri         = (string)$object_xml->infoTributaria->claveAcceso;
		$ejercicio_fiscal = (string)$object_xml->infoCompRetencion->periodoFiscal;
		$version_xml     = isset($object_xml['version']) ? (string)$object_xml['version'] : '1.0.0';

		// Datos del documento sustentos (se llenan con el primer doc encontrado)
		$tipo_comprobante   = '';
		$numero_comprobante = '';
		$fecha_documento    = $fecha_emision;

		// Calcula totales y arma detalles ANTES de insertar encabezado (para guardar total exacto)
		$detalles_sql = array();
		$total_retencion_num = 0.00;

		if ($version_xml === '1.0.0') {
			if (isset($object_xml->impuestos) && isset($object_xml->impuestos->impuesto)) {
				$first = true;
				foreach ($object_xml->impuestos->impuesto as $det) {
					if ($first) {
						$fecha_documento  = $parseFecha($det->fechaEmisionDocSustento);
						$tipo_comprobante = (string)$det->codDocSustento;
						// numDocSustento viene como 001001000000123 -> 001-001-000000123
						$numRaw           = preg_replace('~\D~', '', (string)$det->numDocSustento);
						if (strlen($numRaw) >= 9) {
							$numero_comprobante = substr($numRaw, 0, 3) . '-' . substr($numRaw, 3, 3) . '-' . substr($numRaw, 6);
						} else {
							$numero_comprobante = (string)$det->numDocSustento;
						}
						$first = false;
					}

					$codigo_imp = isset($det->codigo) ? (string)$det->codigo : '';
					if ($codigo_imp === '1') $impuesto_txt = 'RENTA';
					else if ($codigo_imp === '2') $impuesto_txt = 'IVA';
					else if ($codigo_imp === '6') $impuesto_txt = 'ISD';
					else $impuesto_txt = $codigo_imp;

					$codigo_retencion   = (string)$det->codigoRetencion;
					$base_imponible     = $toDecimal(isset($det->baseImponible) ? $det->baseImponible : 0, 2);
					$porcentaje_retener = (string)(isset($det->porcentajeRetener) ? $det->porcentajeRetener : '0');
					$valor_retenido     = $toDecimal(isset($det->valorRetenido) ? $det->valorRetenido : 0, 2);
					$total_retencion_num += (float)$valor_retenido;

					// Consulta concepto/id de retención
					$rs_ret = mysqli_query($con, "SELECT id_ret, concepto_ret FROM retenciones_sri WHERE codigo_ret = '" . mysqli_real_escape_string($con, $codigo_retencion) . "' LIMIT 1");
					$concepto_ret = '';
					$id_retencion = 0;
					if ($rs_ret && ($row = mysqli_fetch_assoc($rs_ret))) {
						$concepto_ret = $row['concepto_ret'];
						$id_retencion = (int)$row['id_ret'];
					}

					$detalles_sql[] = array(
						'serie' => $serie_retencion,
						'sec' => $secuencial_ret,
						'ruc' => $ruc_empresa,
						'id_ret' => $id_retencion,
						'ejercicio' => $ejercicio_fiscal,
						'base' => $base_imponible,
						'cod_ret' => $codigo_retencion,
						'imp' => $impuesto_txt,
						'porc' => $porcentaje_retener,
						'valor' => $valor_retenido,
						'concepto' => $concepto_ret
					);
				}
			}
		} else { // 2.0.0
			if (isset($object_xml->docsSustento) && isset($object_xml->docsSustento->docSustento)) {
				$first = true;
				foreach ($object_xml->docsSustento->docSustento as $doc) {
					if ($first) {
						$fecha_documento  = $parseFecha($doc->fechaEmisionDocSustento);
						$tipo_comprobante = (string)$doc->codDocSustento;
						$numRaw           = preg_replace('~\D~', '', (string)$doc->numDocSustento);
						if (strlen($numRaw) >= 9) {
							$numero_comprobante = substr($numRaw, 0, 3) . '-' . substr($numRaw, 3, 3) . '-' . substr($numRaw, 6);
						} else {
							$numero_comprobante = (string)$doc->numDocSustento;
						}
						$first = false;
					}

					// OJO: en v2 los impuestos están dentro de cada docSustento
					if (isset($doc->retenciones) && isset($doc->retenciones->retencion)) {
						foreach ($doc->retenciones->retencion as $det) {
							$codigo_imp = isset($det->codigo) ? (string)$det->codigo : '';
							if ($codigo_imp === '1') $impuesto_txt = 'RENTA';
							else if ($codigo_imp === '2') $impuesto_txt = 'IVA';
							else if ($codigo_imp === '6') $impuesto_txt = 'ISD';
							else $impuesto_txt = $codigo_imp;

							$codigo_retencion   = (string)$det->codigoRetencion;
							$base_imponible     = $toDecimal(isset($det->baseImponible) ? $det->baseImponible : 0, 2);
							$porcentaje_retener = (string)(isset($det->porcentajeRetener) ? $det->porcentajeRetener : '0');
							$valor_retenido     = $toDecimal(isset($det->valorRetenido) ? $det->valorRetenido : 0, 2);
							$total_retencion_num += (float)$valor_retenido;

							$rs_ret = mysqli_query($con, "SELECT id_ret, concepto_ret FROM retenciones_sri WHERE codigo_ret = '" . mysqli_real_escape_string($con, $codigo_retencion) . "' LIMIT 1");
							$concepto_ret = '';
							$id_retencion = 0;
							if ($rs_ret && ($row = mysqli_fetch_assoc($rs_ret))) {
								$concepto_ret = $row['concepto_ret'];
								$id_retencion = (int)$row['id_ret'];
							}

							$detalles_sql[] = array(
								'serie' => $serie_retencion,
								'sec' => $secuencial_ret,
								'ruc' => $ruc_empresa,
								'id_ret' => $id_retencion,
								'ejercicio' => $ejercicio_fiscal,
								'base' => $base_imponible,
								'cod_ret' => $codigo_retencion,
								'imp' => $impuesto_txt,
								'porc' => $porcentaje_retener,
								'valor' => $valor_retenido,
								'concepto' => $concepto_ret
							);
						}
					}
				}
			}
		}

		// --- Escapes base ---
		$ruc_empresa_sql = mysqli_real_escape_string($con, $ruc_empresa);
		$id_prov_sql     = (int)$id_proveedor;
		$serie_sql       = mysqli_real_escape_string($con, $serie_retencion);
		$sec_sql         = mysqli_real_escape_string($con, $secuencial_ret);
		$aut_sri_sql     = mysqli_real_escape_string($con, $aut_sri);
		$fec_emis_sql    = mysqli_real_escape_string($con, $fecha_emision);
		$fec_doc_sql     = mysqli_real_escape_string($con, $fecha_documento);
		$id_usuario_sql  = (int)$id_usuario;
		$tipo_comp_sql   = mysqli_real_escape_string($con, $tipo_comprobante);
		$num_comp_sql    = mysqli_real_escape_string($con, $numero_comprobante);
		$total_sql       = mysqli_real_escape_string($con, number_format($total_retencion_num, 2, '.', ''));

		// --- Transacción ---
		mysqli_begin_transaction($con);

		// Encabezado con total calculado
		$sql_enc = "
        INSERT INTO encabezado_retencion
        VALUES (
            NULL,
            '{$ruc_empresa_sql}',
            '{$id_prov_sql}',
            '{$serie_sql}',
            '{$sec_sql}',
            '{$total_sql}',
            '{$aut_sri_sql}',
            'AUTORIZADO',
            '{$fec_emis_sql}',
            '{$fec_doc_sql}',
            '{$id_usuario_sql}',
            '{$tipo_comp_sql}',
            '{$num_comp_sql}',
            '0','0','2','ENVIADO'
        )
    ";
		if (!mysqli_query($con, $sql_enc)) {
			mysqli_rollback($con);
			return false;
		}

		// Detalles
		foreach ($detalles_sql as $d) {
			$sql_det = "
            INSERT INTO cuerpo_retencion
            VALUES (
                NULL,
                '" . mysqli_real_escape_string($con, $d['serie']) . "',
                '" . mysqli_real_escape_string($con, $d['sec']) . "',
                '" . mysqli_real_escape_string($con, $d['ruc']) . "',
                '" . (int)$d['id_ret'] . "',
                '" . mysqli_real_escape_string($con, $d['ejercicio']) . "',
                '" . mysqli_real_escape_string($con, $d['base']) . "',
                '" . mysqli_real_escape_string($con, $d['cod_ret']) . "',
                '" . mysqli_real_escape_string($con, $d['imp']) . "',
                '" . mysqli_real_escape_string($con, $d['porc']) . "',
                '" . mysqli_real_escape_string($con, $d['valor']) . "',
                '" . mysqli_real_escape_string($con, $d['concepto']) . "'
            )
        ";
			if (!mysqli_query($con, $sql_det)) {
				mysqli_rollback($con);
				return false;
			}
		}

		// Info adicional
		if (isset($object_xml->infoAdicional) && isset($object_xml->infoAdicional->campoAdicional)) {
			foreach ($object_xml->infoAdicional->campoAdicional as $campo) {
				// <campoAdicional nombre="...">valor</campoAdicional>
				$nombre = isset($campo['nombre']) ? $strCleanLocal((string)$campo['nombre']) : '';
				$valor  = $strCleanLocal((string)$campo);

				$sql_ad = "
                INSERT INTO detalle_adicional_retencion
                VALUES (NULL,'{$ruc_empresa_sql}','{$serie_sql}','{$sec_sql}',
                        '" . mysqli_real_escape_string($con, $nombre) . "',
                        '" . mysqli_real_escape_string($con, $valor) . "')
            ";
				if (!mysqli_query($con, $sql_ad)) {
					mysqli_rollback($con);
					return false;
				}
			}
		}

		mysqli_commit($con);
		// -------- Asientos contables --------
		if ($contabilizacion) {
			$emi_iso = formatea_fecha_emision($fecha_emision);
			$desde = $emi_iso . ' 00:00:00';
			$hasta = $emi_iso . ' 23:59:59';
			$contabilizacion->documentosRetencionesCompras($con, $ruc_empresa, $desde, $hasta);
			$contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'retenciones_compras');
		}
		return true;
	}


	public function guarda_retencion_venta($con, $ruc_empresa, $object_xml, $id_usuario, $id_cliente)
	{
		// --- Helpers (PHP 5.6) ---
		$toDecimal = function ($val, $scale = 2) {
			$s = trim((string)$val);
			// limpia espacios, incluyendo NBSP
			$s = str_replace(array("\xC2\xA0", " ", "\t"), "", $s);
			if ($s === '' || $s === '+' || $s === '-') {
				return number_format(0, $scale, '.', '');
			}

			$hasDot   = strpos($s, '.') !== false;
			$hasComma = strpos($s, ',') !== false;

			if ($hasDot && $hasComma) {
				// Si hay ambos, el ÚLTIMO símbolo suele ser el separador decimal
				$lastDot   = strrpos($s, '.');
				$lastComma = strrpos($s, ',');
				if ($lastComma > $lastDot) {
					// coma decimal, punto miles -> quita puntos y cambia coma por punto
					$s = str_replace('.', '', $s);
					$s = str_replace(',', '.', $s);
				} else {
					// punto decimal, coma miles -> quita comas
					$s = str_replace(',', '', $s);
					// deja el punto decimal
				}
			} elseif ($hasComma) {
				// Solo comas: ¿miles o decimal?
				if (preg_match('/^\d{1,3}(,\d{3})+$/', $s)) {
					// "1,234,567" -> miles
					$s = str_replace(',', '', $s);
				} else {
					// "1234,56" / "1,000000" -> coma decimal
					$s = str_replace(',', '.', $s);
				}
			} elseif ($hasDot) {
				// Solo puntos: ¿miles o decimal?
				if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
					// "1.234.567" -> miles
					$s = str_replace('.', '', $s);
				} // si es "1.000000" -> punto decimal (no tocar)
			}

			if (!is_numeric($s)) $s = '0';
			// Devuelve string con punto y $scale decimales (como venías usando)
			return number_format((float)$s, $scale, '.', '');
		};

		$strCleanLocal = function ($s) {
			if (function_exists('strClean')) return strClean($s);
			$s = strip_tags((string)$s);
			$s = preg_replace('/\s+/', ' ', $s);
			return trim($s);
		};
		$parseFecha = function ($raw) {
			$raw = trim((string)$raw);
			if ($raw === '') return date('Y-m-d');
			if (preg_match('~^\d{2}/\d{2}/\d{4}$~', $raw)) {
				$dt = DateTime::createFromFormat('d/m/Y', $raw);
				if ($dt) return $dt->format('Y-m-d');
			}
			if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $raw)) return $raw;
			$ts = strtotime($raw);
			return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
		};

		$contabilizacion = new contabilizacion();

		// --- Datos base ---
		$fecha_emision     = $parseFecha($object_xml->infoCompRetencion->fechaEmision);
		$serie_retencion   = (string)$object_xml->infoTributaria->estab . "-" . (string)$object_xml->infoTributaria->ptoEmi;
		$secuencial_ret    = (string)$object_xml->infoTributaria->secuencial;
		$codigo_documento  = codigo_aleatorio(20);
		$aut_sri           = (string)$object_xml->infoTributaria->claveAcceso;
		$ejercicio_fiscal  = (string)$object_xml->infoCompRetencion->periodoFiscal;
		$version_xml       = isset($object_xml['version']) ? (string)$object_xml['version'] : '1.0.0';

		// Para encabezado: facturas únicas referenciadas
		$docs_referenciados = array();
		$total_ret_num = 0.00; // total retenido (suma de valorRetenido)

		// --- Recolecta detalles (v1.0.0) ---
		if ($version_xml === '1.0.0') {
			if (isset($object_xml->impuestos) && isset($object_xml->impuestos->impuesto)) {
				foreach ($object_xml->impuestos->impuesto as $det) {
					$codigo_impuesto     = isset($det->codigo) ? (string)$det->codigo : '';
					$codigo_retencion    = isset($det->codigoRetencion) ? (string)$det->codigoRetencion : '';
					$base_imponible      = $toDecimal(isset($det->baseImponible) ? $det->baseImponible : 0, 2);
					$porcentaje_retenido = (string)(isset($det->porcentajeRetener) ? $det->porcentajeRetener : '0');
					$valor_retenido      = $toDecimal(isset($det->valorRetenido) ? $det->valorRetenido : 0, 2);
					$tipo_documento      = isset($det->codDocSustento) ? (string)$det->codDocSustento : '';
					$numero_documento    = isset($det->numDocSustento) ? (string)$det->numDocSustento : '';

					$docs_referenciados[] = $numero_documento;
					$detalles[] = array(
						'serie' => $serie_retencion,
						'sec' => $secuencial_ret,
						'ruc' => $ruc_empresa,
						'ejercicio' => $ejercicio_fiscal,
						'base' => $base_imponible,
						'cod_ret' => $codigo_retencion,
						'cod_imp' => $codigo_impuesto,
						'porc' => $porcentaje_retenido,
						'valor' => $valor_retenido,
						'cod_doc' => $codigo_documento,
						'tipo_doc' => $tipo_documento,
						'num_doc' => $numero_documento
					);
					$total_ret_num += (float)$valor_retenido;
				}
			}
		} else {
			// --- Recolecta detalles (v2.0.0) ---
			if (isset($object_xml->docsSustento) && isset($object_xml->docsSustento->docSustento)) {
				foreach ($object_xml->docsSustento->docSustento as $doc) {
					$tipo_documento   = isset($doc->codDocSustento) ? (string)$doc->codDocSustento : '';
					$numero_documento = isset($doc->numDocSustento) ? (string)$doc->numDocSustento : '';
					$docs_referenciados[] = $numero_documento;

					if (isset($doc->retenciones) && isset($doc->retenciones->retencion)) {
						foreach ($doc->retenciones->retencion as $det) {
							$codigo_impuesto     = isset($det->codigo) ? (string)$det->codigo : '';
							$codigo_retencion    = isset($det->codigoRetencion) ? (string)$det->codigoRetencion : '';
							$base_imponible      = $toDecimal(isset($det->baseImponible) ? $det->baseImponible : 0, 2);
							$porcentaje_retenido = (string)(isset($det->porcentajeRetener) ? $det->porcentajeRetener : '0');
							$valor_retenido      = $toDecimal(isset($det->valorRetenido) ? $det->valorRetenido : 0, 2);

							$detalles[] = array(
								'serie' => $serie_retencion,
								'sec' => $secuencial_ret,
								'ruc' => $ruc_empresa,
								'ejercicio' => $ejercicio_fiscal,
								'base' => $base_imponible,
								'cod_ret' => $codigo_retencion,
								'cod_imp' => $codigo_impuesto,
								'porc' => $porcentaje_retenido,
								'valor' => $valor_retenido,
								'cod_doc' => $codigo_documento,
								'tipo_doc' => $tipo_documento,
								'num_doc' => $numero_documento
							);
							$total_ret_num += (float)$valor_retenido;
						}
					}
				}
			}
		}

		// Facturas únicas para el encabezado (concatenadas por //)
		$documentos_unicos = array_unique($docs_referenciados);
		$facturas_concat   = implode("//", $documentos_unicos);

		// --- Escapes base ---
		$ruc_empresa_sql  = mysqli_real_escape_string($con, $ruc_empresa);
		$id_cliente_sql   = (int)$id_cliente;
		$serie_sql        = mysqli_real_escape_string($con, $serie_retencion);
		$sec_sql          = mysqli_real_escape_string($con, $secuencial_ret);
		$aut_sri_sql      = mysqli_real_escape_string($con, $aut_sri);
		$fec_emis_sql     = mysqli_real_escape_string($con, $fecha_emision);
		$id_usuario_sql   = (int)$id_usuario;
		$cod_doc_sql      = mysqli_real_escape_string($con, $codigo_documento);
		$facturas_sql     = mysqli_real_escape_string($con, $facturas_concat);
		$total_sql        = mysqli_real_escape_string($con, number_format($total_ret_num, 2, '.', ''));

		// --- Transacción ---
		mysqli_begin_transaction($con);

		// Detalles primero (por consistencia con tu flujo original, se puede también hacer al revés)
		if (!empty($detalles)) {
			foreach ($detalles as $d) {
				$sql_det = "
                INSERT INTO cuerpo_retencion_venta
                VALUES (
                    NULL,
                    '" . mysqli_real_escape_string($con, $d['serie']) . "',
                    '" . mysqli_real_escape_string($con, $d['sec']) . "',
                    '" . mysqli_real_escape_string($con, $d['ruc']) . "',
                    '" . mysqli_real_escape_string($con, $d['ejercicio']) . "',
                    '" . mysqli_real_escape_string($con, $d['base']) . "',
                    '" . mysqli_real_escape_string($con, $d['cod_ret']) . "',
                    '" . mysqli_real_escape_string($con, $d['cod_imp']) . "',
                    '" . mysqli_real_escape_string($con, $d['porc']) . "',
                    '" . mysqli_real_escape_string($con, $d['valor']) . "',
                    '" . mysqli_real_escape_string($con, $d['cod_doc']) . "',
                    '" . mysqli_real_escape_string($con, $d['tipo_doc']) . "',
                    '" . mysqli_real_escape_string($con, $d['num_doc']) . "'
                )
            ";
				if (!mysqli_query($con, $sql_det)) {
					mysqli_rollback($con);
					return false;
				}
			}
		}

		// Encabezado (dejo tu orden de columnas; ahora guardamos el total calculado)
		$sql_enc = "
        INSERT INTO encabezado_retencion_venta
        VALUES (
            NULL,
            '{$ruc_empresa_sql}',
            '{$id_cliente_sql}',
            '{$serie_sql}',
            '{$sec_sql}',
            '{$aut_sri_sql}',
            '{$fec_emis_sql}',
            '{$id_usuario_sql}',
            '0',
            '{$cod_doc_sql}',
            '{$facturas_sql}'
        )
    ";
		if (!mysqli_query($con, $sql_enc)) {
			mysqli_rollback($con);
			return false;
		}

		// Info adicional
		if (isset($object_xml->infoAdicional) && isset($object_xml->infoAdicional->campoAdicional)) {
			foreach ($object_xml->infoAdicional->campoAdicional as $campo) {
				// <campoAdicional nombre="...">valor</campoAdicional>
				$nombre = isset($campo['nombre']) ? $strCleanLocal((string)$campo['nombre']) : '';
				$valor  = $strCleanLocal((string)$campo);

				$sql_ad = "
                INSERT INTO detalle_adicional_retencion_venta
                VALUES (NULL, '{$ruc_empresa_sql}', '{$serie_sql}', '{$sec_sql}',
                        '" . mysqli_real_escape_string($con, $nombre) . "',
                        '" . mysqli_real_escape_string($con, $valor) . "',
                        '{$cod_doc_sql}')
            ";
				if (!mysqli_query($con, $sql_ad)) {
					mysqli_rollback($con);
					return false;
				}
			}
		}

		mysqli_commit($con);
		// -------- Asientos contables --------
		if ($contabilizacion) {
			$emi_iso = formatea_fecha_emision($fecha_emision);
			$desde = $emi_iso . ' 00:00:00';
			$hasta = $emi_iso . ' 23:59:59';
			$contabilizacion->documentosRetencionesVentas($con, $ruc_empresa, $desde, $hasta);
			$contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'retenciones_ventas');
		}
		return true;
	}


	public function guarda_nc($con, $ruc_empresa, $object_xml, $id_usuario, $id_proveedor)
	{
		// --- Helpers (PHP 5.6) ---
		$toDecimal = function ($val, $scale = 2) {
			$s = trim((string)$val);
			// limpia espacios, incluyendo NBSP
			$s = str_replace(array("\xC2\xA0", " ", "\t"), "", $s);
			if ($s === '' || $s === '+' || $s === '-') {
				return number_format(0, $scale, '.', '');
			}

			$hasDot   = strpos($s, '.') !== false;
			$hasComma = strpos($s, ',') !== false;

			if ($hasDot && $hasComma) {
				// Si hay ambos, el ÚLTIMO símbolo suele ser el separador decimal
				$lastDot   = strrpos($s, '.');
				$lastComma = strrpos($s, ',');
				if ($lastComma > $lastDot) {
					// coma decimal, punto miles -> quita puntos y cambia coma por punto
					$s = str_replace('.', '', $s);
					$s = str_replace(',', '.', $s);
				} else {
					// punto decimal, coma miles -> quita comas
					$s = str_replace(',', '', $s);
					// deja el punto decimal
				}
			} elseif ($hasComma) {
				// Solo comas: ¿miles o decimal?
				if (preg_match('/^\d{1,3}(,\d{3})+$/', $s)) {
					// "1,234,567" -> miles
					$s = str_replace(',', '', $s);
				} else {
					// "1234,56" / "1,000000" -> coma decimal
					$s = str_replace(',', '.', $s);
				}
			} elseif ($hasDot) {
				// Solo puntos: ¿miles o decimal?
				if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
					// "1.234.567" -> miles
					$s = str_replace('.', '', $s);
				} // si es "1.000000" -> punto decimal (no tocar)
			}

			if (!is_numeric($s)) $s = '0';
			// Devuelve string con punto y $scale decimales (como venías usando)
			return number_format((float)$s, $scale, '.', '');
		};

		$strCleanLocal = function ($s) {
			if (function_exists('strClean')) return strClean($s);
			$s = strip_tags((string)$s);
			$s = preg_replace('/\s+/', ' ', $s);
			return trim($s);
		};
		$parseFecha = function ($raw) {
			$raw = trim((string)$raw);
			if ($raw === '') return date('Y-m-d');
			if (preg_match('~^\d{2}/\d{2}/\d{4}$~', $raw)) {
				$dt = DateTime::createFromFormat('d/m/Y', $raw);
				if ($dt) return $dt->format('Y-m-d');
			}
			if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $raw)) return $raw;
			$ts = strtotime($raw);
			return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
		};

		$contabilizacion = new contabilizacion();
		$sanitize        = class_exists('sanitize') ? new sanitize() : null;

		// --- Datos base ---
		$fecha_registro   = date("Y-m-d H:i:s");
		$fecha_emision    = $parseFecha($object_xml->infoNotaCredito->fechaEmision);
		$estab            = (string)$object_xml->infoTributaria->estab;
		$ptoEmi           = (string)$object_xml->infoTributaria->ptoEmi;
		$secuencial       = (string)$object_xml->infoTributaria->secuencial;

		$numero_nc        = $estab . "-" . $ptoEmi . "-" . $secuencial;
		$num_doc_modif    = (string)$object_xml->infoNotaCredito->numDocModificado;    // ej: 001001000000123
		$cod_doc          = (string)$object_xml->infoTributaria->codDoc;               // 04
		$aut_sri          = (string)$object_xml->infoTributaria->claveAcceso;
		$total_nc         = $toDecimal($object_xml->infoNotaCredito->valorModificacion, 2);
		$motivo           = (string)$object_xml->infoNotaCredito->motivo;
		$cod_doc_modif    = (string)$object_xml->infoNotaCredito->codDocModificado;    // 01 (factura), etc.
		$nombre_proveedor = (string)$object_xml->infoTributaria->razonSocial;
		$tipoIdComprador  = isset($object_xml->infoNotaCredito->tipoIdentificacionComprador)
			? (string)$object_xml->infoNotaCredito->tipoIdentificacionComprador : '';

		$codigo_documento = codigo_aleatorio(20);

		// --- Escapes base ---
		$ruc_empresa_sql    = mysqli_real_escape_string($con, $ruc_empresa);
		$fecha_emision_sql  = mysqli_real_escape_string($con, $fecha_emision);
		$numero_nc_sql      = mysqli_real_escape_string($con, $numero_nc);
		$codigo_doc_sql     = mysqli_real_escape_string($con, $codigo_documento);
		$id_proveedor_sql   = (int)$id_proveedor;
		$cod_doc_sql        = mysqli_real_escape_string($con, $cod_doc);
		$aut_sri_sql        = mysqli_real_escape_string($con, $aut_sri);
		$secuencial_sql     = mysqli_real_escape_string($con, $secuencial);
		$fecha_registro_sql = mysqli_real_escape_string($con, $fecha_registro);
		$id_usuario_sql     = (int)$id_usuario;
		$total_nc_sql       = mysqli_real_escape_string($con, $total_nc);
		$num_doc_modif_sql  = mysqli_real_escape_string($con, $num_doc_modif);
		$tipoId_sql         = mysqli_real_escape_string($con, $tipoIdComprador);
		$cod_doc_modif_sql  = mysqli_real_escape_string($con, $cod_doc_modif);
		$motivo_sql         = mysqli_real_escape_string($con, $strCleanLocal($motivo));
		$nombre_prov_sql    = mysqli_real_escape_string($con, $nombre_proveedor);

		// --- Transacción ---
		mysqli_begin_transaction($con);

		// Encabezado (mismo orden de tu tabla original)
		$sql_enc = "
        INSERT INTO encabezado_compra
        VALUES (
            NULL,
            '{$fecha_emision_sql}',
            '{$ruc_empresa_sql}',
            '{$numero_nc_sql}',
            '{$codigo_doc_sql}',
            '{$id_proveedor_sql}',
            '{$cod_doc_sql}',
            '1',
            '{$aut_sri_sql}',
            '{$fecha_emision_sql}',
            '{$secuencial_sql}',
            '{$secuencial_sql}',
            '{$fecha_registro_sql}',
            '{$id_usuario_sql}',
            '{$total_nc_sql}',
            '{$num_doc_modif_sql}',
            '0',
            'ELECTRÓNICA',
            '{$tipoId_sql}',
            0,
            0,
            '{$cod_doc_modif_sql}'
        )
    ";
		if (!mysqli_query($con, $sql_enc)) {
			mysqli_rollback($con);
			return false;
		}

		// --- Detalles ---
		if (isset($object_xml->detalles) && isset($object_xml->detalles->detalle)) {
			foreach ($object_xml->detalles->detalle as $det) {
				// Algunos esquemas usan codigoInterno; otros codigoPrincipal
				$codigo_detalle = isset($det->codigoInterno) ? (string)$det->codigoInterno : (isset($det->codigoPrincipal) ? (string)$det->codigoPrincipal : '');
				$descripcion    = isset($det->descripcion) ? (string)$det->descripcion : '';

				$cantidad       = $toDecimal(isset($det->cantidad) ? $det->cantidad : 0, 6);
				$precio_unit    = $toDecimal(isset($det->precioUnitario) ? $det->precioUnitario : 0, 6);
				$descuento      = $toDecimal(isset($det->descuento) ? $det->descuento : 0, 6);

				// En NC suele venir precioTotalSinImpuesto (base del renglón)
				$base_subtotal  = $toDecimal(isset($det->precioTotalSinImpuesto) ? $det->precioTotalSinImpuesto : ((float)$cantidad * (float)$precio_unit - (float)$descuento), 2);

				$codigo_detalle_sql = mysqli_real_escape_string($con, $strCleanLocal($codigo_detalle));
				$descripcion_sql    = mysqli_real_escape_string($con, $strCleanLocal($descripcion));

				// Si no hay impuestos, inserta una línea con IVA 0 (código 2, porcentaje 0)
				$tieneImpuestos = (isset($det->impuestos) && isset($det->impuestos->impuesto));

				if (!$tieneImpuestos) {
					$sql_det = "
                    INSERT INTO cuerpo_compra
                    VALUES (
                        NULL,
                        '{$ruc_empresa_sql}',
                        '{$codigo_doc_sql}',
                        '{$codigo_detalle_sql}',
                        '{$descripcion_sql}',
                        '{$cantidad}',
                        '{$precio_unit}',
                        '{$descuento}',
                        '2',
                        '0',
                        '{$base_subtotal}',
                        0
                    )
                ";
					if (!mysqli_query($con, $sql_det)) {
						mysqli_rollback($con);
						return false;
					}
				} else {
					// Puede haber varios impuestos por detalle
					foreach ($det->impuestos->impuesto as $imp) {
						$tipo_impuesto    = isset($imp->codigo) ? (string)$imp->codigo : '2';           // 2: IVA, 3: ICE, etc.
						$impuesto_detalle = isset($imp->codigoPorcentaje) ? (string)$imp->codigoPorcentaje : '0';
						// base puede venir en el impuesto o usamos el subtotal
						$base_imponible   = $toDecimal(isset($imp->baseImponible) ? $imp->baseImponible : $base_subtotal, 2);

						$tipo_impuesto_sql    = mysqli_real_escape_string($con, $tipo_impuesto);
						$impuesto_detalle_sql = mysqli_real_escape_string($con, $impuesto_detalle);
						$base_imponible_sql   = mysqli_real_escape_string($con, $base_imponible);

						$sql_det = "
                        INSERT INTO cuerpo_compra
                        VALUES (
                            NULL,
                            '{$ruc_empresa_sql}',
                            '{$codigo_doc_sql}',
                            '{$codigo_detalle_sql}',
                            '{$descripcion_sql}',
                            '{$cantidad}',
                            '{$precio_unit}',
                            '{$descuento}',
                            '{$tipo_impuesto_sql}',
                            '{$impuesto_detalle_sql}',
                            '{$base_imponible_sql}',
                            0
                        )
                    ";
						if (!mysqli_query($con, $sql_det)) {
							mysqli_rollback($con);
							return false;
						}
					}
				}
			}
		}

		// --- Info adicional (usa atributo nombre) ---
		if (isset($object_xml->infoAdicional) && isset($object_xml->infoAdicional->campoAdicional)) {
			foreach ($object_xml->infoAdicional->campoAdicional as $campo) {
				$nombre = isset($campo['nombre']) ? (string)$campo['nombre'] : '';
				$valor  = (string)$campo;

				if ($sanitize) {
					$nombre = $sanitize->string_sanitize($nombre, false, false);
					$valor  = $sanitize->string_sanitize($valor,  false, false);
				} else {
					$nombre = $strCleanLocal($nombre);
					$valor  = $strCleanLocal($valor);
				}

				$nombre_sql = mysqli_real_escape_string($con, $nombre);
				$valor_sql  = mysqli_real_escape_string($con, $valor);

				$sql_ad = "
                INSERT INTO detalle_adicional_compra
                VALUES (NULL, '{$ruc_empresa_sql}', '{$codigo_doc_sql}', '{$nombre_sql}', '{$valor_sql}')
            ";
				if (!mysqli_query($con, $sql_ad)) {
					mysqli_rollback($con);
					return false;
				}
			}
		}

		// --- Motivo (como detalle adicional estandarizado) ---
		$sql_mot = "
        INSERT INTO detalle_adicional_compra
        VALUES (NULL, '{$ruc_empresa_sql}', '{$codigo_doc_sql}', 'MOTIVO', '{$motivo_sql}')
    ";
		if (!mysqli_query($con, $sql_mot)) {
			mysqli_rollback($con);
			return false;
		}

		mysqli_commit($con);
		// -------- Asientos contables --------
		if ($contabilizacion) {
			$emi_iso = formatea_fecha_emision($fecha_emision);
			$desde = $emi_iso . ' 00:00:00';
			$hasta = $emi_iso . ' 23:59:59';
			$contabilizacion->documentosAdquisiciones($con, $ruc_empresa, $desde, $hasta);
			$contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'compras_servicios');
		}

		return true;
	}


	public function guarda_nd($con, $ruc_empresa, $object_xml, $id_usuario, $id_proveedor)
	{
		// --- Helpers (PHP 5.6+) ---
		$toDecimal = function ($val, $scale = 2) {
			$s = trim((string)$val);
			// limpia espacios, incluyendo NBSP
			$s = str_replace(array("\xC2\xA0", " ", "\t"), "", $s);
			if ($s === '' || $s === '+' || $s === '-') {
				return number_format(0, $scale, '.', '');
			}

			$hasDot   = strpos($s, '.') !== false;
			$hasComma = strpos($s, ',') !== false;

			if ($hasDot && $hasComma) {
				// Si hay ambos, el ÚLTIMO símbolo suele ser el separador decimal
				$lastDot   = strrpos($s, '.');
				$lastComma = strrpos($s, ',');
				if ($lastComma > $lastDot) {
					// coma decimal, punto miles -> quita puntos y cambia coma por punto
					$s = str_replace('.', '', $s);
					$s = str_replace(',', '.', $s);
				} else {
					// punto decimal, coma miles -> quita comas
					$s = str_replace(',', '', $s);
					// deja el punto decimal
				}
			} elseif ($hasComma) {
				// Solo comas: ¿miles o decimal?
				if (preg_match('/^\d{1,3}(,\d{3})+$/', $s)) {
					// "1,234,567" -> miles
					$s = str_replace(',', '', $s);
				} else {
					// "1234,56" / "1,000000" -> coma decimal
					$s = str_replace(',', '.', $s);
				}
			} elseif ($hasDot) {
				// Solo puntos: ¿miles o decimal?
				if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
					// "1.234.567" -> miles
					$s = str_replace('.', '', $s);
				} // si es "1.000000" -> punto decimal (no tocar)
			}

			if (!is_numeric($s)) $s = '0';
			// Devuelve string con punto y $scale decimales (como venías usando)
			return number_format((float)$s, $scale, '.', '');
		};

		$strCleanLocal = function ($s) {
			if (function_exists('strClean')) return strClean($s);
			$s = strip_tags((string)$s);
			$s = preg_replace('/\s+/', ' ', $s);
			return trim($s);
		};
		$parseFecha = function ($raw) {
			$raw = trim((string)$raw);
			if ($raw === '') return date('Y-m-d');
			if (preg_match('~^\d{2}/\d{2}/\d{4}$~', $raw)) {
				$dt = DateTime::createFromFormat('d/m/Y', $raw);
				if ($dt) return $dt->format('Y-m-d');
			}
			if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $raw)) return $raw;
			$ts = strtotime($raw);
			return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
		};
		$contabilizacion = new contabilizacion();
		// --- Datos base ---
		$fecha_registro   = date("Y-m-d H:i:s");
		$fecha_emision    = $parseFecha($object_xml->infoNotaDebito->fechaEmision);
		$estab            = (string)$object_xml->infoTributaria->estab;
		$ptoEmi           = (string)$object_xml->infoTributaria->ptoEmi;
		$secuencial_nd    = (string)$object_xml->infoTributaria->secuencial;
		$numero_nd        = $estab . "-" . $ptoEmi . "-" . $secuencial_nd;

		$num_doc_mod      = (string)$object_xml->infoNotaDebito->numDocModificado; // 001001000000123
		$cod_doc          = (string)$object_xml->infoTributaria->codDoc;           // 05
		$aut_sri          = (string)$object_xml->infoTributaria->claveAcceso;
		$total_nd         = $toDecimal($object_xml->infoNotaDebito->valorTotal, 2);
		$cod_doc_mod      = (string)$object_xml->infoNotaDebito->codDocModificado; // 01, etc.
		//$nombre_proveedor = (string)$object_xml->infoTributaria->razonSocial;
		$tipoIdComprador  = isset($object_xml->infoNotaDebito->tipoIdentificacionComprador)
			? (string)$object_xml->infoNotaDebito->tipoIdentificacionComprador : '';

		$codigo_documento = codigo_aleatorio(20);

		// --- Impuestos de la ND (aplican al total; pueden venir varios) ---
		$impuestos_nd = array(); // cada item: ['codigo'=>'2','porcentaje'=>'12', 'base'=>'...', 'valor'=>'...']
		if (isset($object_xml->infoNotaDebito->impuestos) && isset($object_xml->infoNotaDebito->impuestos->impuesto)) {
			foreach ($object_xml->infoNotaDebito->impuestos->impuesto as $imp) {
				$impuestos_nd[] = array(
					'codigo'          => isset($imp->codigo) ? (string)$imp->codigo : '2',
					'codigoPorcentaje' => isset($imp->codigoPorcentaje) ? (string)$imp->codigoPorcentaje : '0',
					'baseImponible'   => $toDecimal(isset($imp->baseImponible) ? $imp->baseImponible : 0, 2),
					'valor'           => $toDecimal(isset($imp->valor) ? $imp->valor : 0, 2)
				);
			}
		}

		// --- Escapes base ---
		$ruc_empresa_sql    = mysqli_real_escape_string($con, $ruc_empresa);
		$fecha_emision_sql  = mysqli_real_escape_string($con, $fecha_emision);
		$numero_nd_sql      = mysqli_real_escape_string($con, $numero_nd);
		$codigo_doc_sql     = mysqli_real_escape_string($con, $codigo_documento);
		$id_proveedor_sql   = (int)$id_proveedor;
		$cod_doc_sql        = mysqli_real_escape_string($con, $cod_doc);
		$aut_sri_sql        = mysqli_real_escape_string($con, $aut_sri);
		$secuencial_sql     = mysqli_real_escape_string($con, $secuencial_nd);
		$fecha_registro_sql = mysqli_real_escape_string($con, $fecha_registro);
		$id_usuario_sql     = (int)$id_usuario;
		$total_nd_sql       = mysqli_real_escape_string($con, $total_nd);
		$num_doc_mod_sql    = mysqli_real_escape_string($con, $num_doc_mod);
		$tipoId_sql         = mysqli_real_escape_string($con, $tipoIdComprador);
		$cod_doc_mod_sql    = mysqli_real_escape_string($con, $cod_doc_mod);
		//$nombre_prov_sql    = mysqli_real_escape_string($con, $nombre_proveedor);

		// --- Transacción ---
		mysqli_begin_transaction($con);

		// Encabezado (mismo orden de tu tabla)
		$sql_enc = "
        INSERT INTO encabezado_compra
        VALUES (
            NULL,
            '{$fecha_emision_sql}',
            '{$ruc_empresa_sql}',
            '{$numero_nd_sql}',
            '{$codigo_doc_sql}',
            '{$id_proveedor_sql}',
            '{$cod_doc_sql}',
            '1',
            '{$aut_sri_sql}',
            '{$fecha_emision_sql}',
            '{$secuencial_sql}',
            '{$secuencial_sql}',
            '{$fecha_registro_sql}',
            '{$id_usuario_sql}',
            '{$total_nd_sql}',
            '{$num_doc_mod_sql}',
            '0',
            'ELECTRÓNICA',
            '{$tipoId_sql}',
            0,
            0,
            '{$cod_doc_mod_sql}'
        )
    			";
		if (!mysqli_query($con, $sql_enc)) {
			mysqli_rollback($con);
			return false;
		}

		// --- Detalles desde MOTIVOS ---
		// Cada motivo genera una línea (cantidad 1, precio = valor, descuento 0)
		if (isset($object_xml->motivos) && isset($object_xml->motivos->motivo)) {
			foreach ($object_xml->motivos->motivo as $mot) {
				$descripcion   = isset($mot->razon) ? (string)$mot->razon : '';
				$cantidad      = "1.000000";
				$precio        = $toDecimal(isset($mot->valor) ? $mot->valor : 0, 6);
				$descuento     = "0.000000";
				$base_subtotal = $toDecimal($precio, 2); // precio * 1

				$descripcion_sql = mysqli_real_escape_string($con, $strCleanLocal($descripcion));
				$codigo_detalle_sql = mysqli_real_escape_string($con, ""); // no hay código de ítem en ND

				if (empty($impuestos_nd)) {
					// Sin impuestos: guarda con IVA 0 (código 2, porcentaje 0)
					$sql_det = "
                    INSERT INTO cuerpo_compra
                    VALUES (
                        NULL,
                        '{$ruc_empresa_sql}',
                        '{$codigo_doc_sql}',
                        '{$codigo_detalle_sql}',
                        '{$descripcion_sql}',
                        '{$cantidad}',
                        '{$precio}',
                        '{$descuento}',
                        '2',
                        '0',
                        '{$base_subtotal}',
                        0
                    )
                ";
					if (!mysqli_query($con, $sql_det)) {
						mysqli_rollback($con);
						return false;
					}
				} else {
					// Con impuestos de la ND: genera una línea por código/porcentaje
					foreach ($impuestos_nd as $imp) {
						$tipo_imp  = mysqli_real_escape_string($con, $imp['codigo']);            // 2: IVA, 3: ICE
						$porc_imp  = mysqli_real_escape_string($con, $imp['codigoPorcentaje']);  // ej 2, 3, 0
						// La base puede ser la base del impuesto, o el subtotal del motivo si no viene
						$base_imp  = $toDecimal($imp['baseImponible'] !== '0.00' ? $imp['baseImponible'] : $base_subtotal, 2);
						$base_imp_sql = mysqli_real_escape_string($con, $base_imp);

						$sql_det = "
                        INSERT INTO cuerpo_compra
                        VALUES (
                            NULL,
                            '{$ruc_empresa_sql}',
                            '{$codigo_doc_sql}',
                            '{$codigo_detalle_sql}',
                            '{$descripcion_sql}',
                            '{$cantidad}',
                            '{$precio}',
                            '{$descuento}',
                            '{$tipo_imp}',
                            '{$porc_imp}',
                            '{$base_imp_sql}',
                            0
                        )
                    ";
						if (!mysqli_query($con, $sql_det)) {
							mysqli_rollback($con);
							return false;
						}
					}
				}
			}
		}

		// --- Info adicional (atributo nombre) ---
		if (isset($object_xml->infoAdicional) && isset($object_xml->infoAdicional->campoAdicional)) {
			foreach ($object_xml->infoAdicional->campoAdicional as $campo) {
				$nombre = isset($campo['nombre']) ? (string)$campo['nombre'] : '';
				$valor  = (string)$campo;

				$nombre = $strCleanLocal($nombre);
				$valor  = $strCleanLocal($valor);

				$sql_ad = "
                INSERT INTO detalle_adicional_compra
                VALUES (
                    NULL,
                    '{$ruc_empresa_sql}',
                    '{$codigo_doc_sql}',
                    '" . mysqli_real_escape_string($con, $nombre) . "',
                    '" . mysqli_real_escape_string($con, $valor) . "'
                )
            ";
				if (!mysqli_query($con, $sql_ad)) {
					mysqli_rollback($con);
					return false;
				}
			}
		}

		// --- Forma de pago (default crédito 1 día) ---
		$sql_pago = "
        INSERT INTO formas_pago_compras
        VALUES (NULL, '{$ruc_empresa_sql}', '{$codigo_doc_sql}', '20', '{$total_nd_sql}', '1', 'dias')
    ";
		if (!mysqli_query($con, $sql_pago)) {
			mysqli_rollback($con);
			return false;
		}

		mysqli_commit($con);
		// -------- Asientos contables --------
		if ($contabilizacion) {
			$emi_iso = formatea_fecha_emision($fecha_emision);
			$desde = $emi_iso . ' 00:00:00';
			$hasta = $emi_iso . ' 23:59:59';
			$contabilizacion->documentosAdquisiciones($con, $ruc_empresa, $desde, $hasta);
			$contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'compras_servicios');
		}
		return true;
	}

	//para guardar el cliente o el proveedor
	public function proveedor_cliente($tipo, $con, $ruc_empresa, $object_xml, $ruc_proveedor)
	{
		// --- Helpers (compatibles con PHP 5.6) ---
		$clean = function ($s) {
			if (function_exists('strClean')) return strClean($s);
			$s = strip_tags((string)$s);
			$s = preg_replace('/\s+/', ' ', $s);
			return trim($s);
		};
		$attrNombre = function ($campo) {
			return isset($campo['nombre']) ? trim((string)$campo['nombre']) : '';
		};
		$normalizeEmail = function ($e) {
			$e = trim((string)$e);
			if ($e === '') return '';
			// filtro básico
			return (filter_var($e, FILTER_VALIDATE_EMAIL)) ? $e : '';
		};
		$normalizePhone = function ($t) {
			// deja dígitos y +, recorta a 20
			$t = preg_replace('/[^0-9+]/', '', (string)$t);
			return substr($t, 0, 20);
		};

		// --- Defaults (más neutrales) ---
		$direccion_proveedor_cliente = "Ecuador";
		$mail_proveedor_cliente      = "";
		$telefono_proveedor_cliente  = "";

		// Lee infoAdicional si existe (usa atributo nombre)
		if (isset($object_xml->infoAdicional) && isset($object_xml->infoAdicional->campoAdicional)) {
			foreach ($object_xml->infoAdicional->campoAdicional as $campo) {
				$nombre = mb_strtolower($this_name = $attrNombre($campo), 'UTF-8');
				$valor  = $clean((string)$campo);

				if (in_array($nombre, array('mail', 'email', 'correo', 'correo electrónico', 'correo electronico'))) {
					$mail_proveedor_cliente = $normalizeEmail($valor);
				}
				if (in_array($nombre, array('teléfono', 'telefono', 'tel', 'telf', 'celular'))) {
					$telefono_proveedor_cliente = $normalizePhone($valor);
				}
				if (in_array($nombre, array('dirección', 'direccion', 'dir', 'domicilio'))) {
					$direccion_proveedor_cliente = $clean($valor);
				}
			}
		}

		// --- Datos del XML base ---
		$tipo_documento = isset($object_xml->infoTributaria->codDoc) ? (string)$object_xml->infoTributaria->codDoc : '';
		$ruc_emisor     = isset($object_xml->infoTributaria->ruc) ? (string)$object_xml->infoTributaria->ruc : '';

		// ============================================================
		// PROVEEDOR
		// ============================================================
		if ($tipo === "proveedor") {
			// Ya existe?
			$sql = "SELECT id_proveedor FROM proveedores WHERE ruc_proveedor='" . mysqli_real_escape_string($con, $ruc_proveedor) . "' AND ruc_empresa='" . mysqli_real_escape_string($con, $ruc_empresa) . "' LIMIT 1";
			$rs  = mysqli_query($con, $sql);
			if ($rs && mysqli_num_rows($rs) > 0) {
				$row = mysqli_fetch_assoc($rs);
				return (int)$row['id_proveedor'];
			}

			// Si no existe, construir campos desde el XML según el doc
			$razon_social = "";
			$nombre_comercial = "";
			$lleva_contabilidad = "NO";

			if (substr($ruc_emisor, 0, 10) === substr($ruc_empresa, 0, 10) && $tipo_documento === "03") {
				$razon_social        = $clean($object_xml->infoLiquidacionCompra->razonSocialProveedor);
				$nombre_comercial    = $razon_social;
				$lleva_contabilidad  = isset($object_xml->infoLiquidacionCompra->obligadoContabilidad) ? (string)$object_xml->infoLiquidacionCompra->obligadoContabilidad : "NO";
				$direccion_proveedor_cliente = isset($object_xml->infoLiquidacionCompra->dirEstablecimiento) ? $clean($object_xml->infoLiquidacionCompra->dirEstablecimiento) : $direccion_proveedor_cliente;
			} elseif (substr($ruc_emisor, 0, 10) === substr($ruc_empresa, 0, 10) && $tipo_documento === "07") {
				$razon_social        = $clean($object_xml->infoCompRetencion->razonSocialSujetoRetenido);
				$nombre_comercial    = $razon_social;
				$lleva_contabilidad  = isset($object_xml->infoCompRetencion->obligadoContabilidad) ? (string)$object_xml->infoCompRetencion->obligadoContabilidad : "NO";
				$direccion_proveedor_cliente = isset($object_xml->infoCompRetencion->dirEstablecimiento) ? $clean($object_xml->infoCompRetencion->dirEstablecimiento) : $direccion_proveedor_cliente;
			} elseif ($tipo_documento === "01") {
				$razon_social        = $clean($object_xml->infoTributaria->razonSocial);
				$nombre_comercial    = $clean($object_xml->infoTributaria->nombreComercial);
				$lleva_contabilidad  = isset($object_xml->infoFactura->obligadoContabilidad) ? (string)$object_xml->infoFactura->obligadoContabilidad : "NO";
				$direccion_proveedor_cliente = isset($object_xml->infoTributaria->dirMatriz) ? $clean($object_xml->infoTributaria->dirMatriz) : $direccion_proveedor_cliente;
			} elseif ($tipo_documento === "04") {
				$razon_social        = $clean($object_xml->infoTributaria->razonSocial);
				$nombre_comercial    = $clean($object_xml->infoTributaria->nombreComercial);
				$lleva_contabilidad  = isset($object_xml->infoNotaCredito->obligadoContabilidad) ? (string)$object_xml->infoNotaCredito->obligadoContabilidad : "NO";
				$direccion_proveedor_cliente = isset($object_xml->infoTributaria->dirMatriz) ? $clean($object_xml->infoTributaria->dirMatriz) : $direccion_proveedor_cliente;
			} elseif ($tipo_documento === "05") {
				$razon_social        = $clean($object_xml->infoTributaria->razonSocial);
				$nombre_comercial    = $clean($object_xml->infoTributaria->nombreComercial);
				$lleva_contabilidad  = isset($object_xml->infoNotaDebito->obligadoContabilidad) ? (string)$object_xml->infoNotaDebito->obligadoContabilidad : "NO";
				$direccion_proveedor_cliente = isset($object_xml->infoTributaria->dirMatriz) ? $clean($object_xml->infoTributaria->dirMatriz) : $direccion_proveedor_cliente;
			}

			// Mapa de tipo de empresa (según tu lógica por 3er dígito)
			$digito_verificador = substr($ruc_proveedor, 2, 1);
			if (strtoupper($lleva_contabilidad) === "SI") {
				switch ($digito_verificador) {
					case "9":
						$tipo_empresa = "03";
						break; // sociedad privada
					case "6":
						$tipo_empresa = "05";
						break; // empresa pública
					default:
						$tipo_empresa = "02";
						break; // persona natural obligada
				}
			} else {
				$tipo_empresa = "01"; // persona natural no obligada
			}

			// Escapes e inserción
			$sql_ins = sprintf(
				"INSERT INTO proveedores
            (razon_social,nombre_comercial,ruc_empresa,tipo_id_proveedor,ruc_proveedor,mail_proveedor,dir_proveedor,telf_proveedor,tipo_empresa)
            VALUES ('%s','%s','%s','04','%s','%s','%s','%s','%s')",
				mysqli_real_escape_string($con, $razon_social),
				mysqli_real_escape_string($con, $nombre_comercial ?: $razon_social),
				mysqli_real_escape_string($con, $ruc_empresa),
				mysqli_real_escape_string($con, $ruc_proveedor),
				mysqli_real_escape_string($con, $mail_proveedor_cliente),
				mysqli_real_escape_string($con, $direccion_proveedor_cliente),
				mysqli_real_escape_string($con, $telefono_proveedor_cliente),
				mysqli_real_escape_string($con, $tipo_empresa)
			);
			if (!mysqli_query($con, $sql_ins)) {
				return 0; // o lanza excepción según tu framework
			}

			$rsNew = mysqli_query($con, "SELECT id_proveedor FROM proveedores WHERE id_proveedor=LAST_INSERT_ID()");
			$rowN  = mysqli_fetch_assoc($rsNew);
			return (int)$rowN['id_proveedor'];
		}

		// ============================================================
		// CLIENTE
		// ============================================================
		if ($tipo === "cliente") {
			// Ya existe?
			$sql = "SELECT id FROM clientes WHERE ruc='" . mysqli_real_escape_string($con, $ruc_proveedor) . "' AND ruc_empresa='" . mysqli_real_escape_string($con, $ruc_empresa) . "' LIMIT 1";
			$rs  = mysqli_query($con, $sql);
			if ($rs && mysqli_num_rows($rs) > 0) {
				$row = mysqli_fetch_assoc($rs);
				return (int)$row['id'];
			}

			// Construir desde el XML
			$razon_social = "";
			if (substr($ruc_emisor, 0, 10) === substr($ruc_empresa, 0, 10) && $tipo_documento === "01") {
				$razon_social = $clean($object_xml->infoFactura->razonSocialComprador);
				$direccion_proveedor_cliente = isset($object_xml->infoTributaria->dirMatriz) ? $clean($object_xml->infoTributaria->dirMatriz) : $direccion_proveedor_cliente;
			} elseif ($tipo_documento === "07") {
				$razon_social = $clean($object_xml->infoTributaria->razonSocial);
				$direccion_proveedor_cliente = isset($object_xml->infoTributaria->dirMatriz) ? $clean($object_xml->infoTributaria->dirMatriz) : $direccion_proveedor_cliente;
			} else {
				// fallback: usa razón social del emisor si no es factura/retención
				$razon_social = $clean($object_xml->infoTributaria->razonSocial);
			}

			// Usuario (mantengo tu uso de sesión para no romper interfaz)
			$id_usuario = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;

			$sql_ins = sprintf(
				"INSERT INTO clientes
            (ruc_empresa,nombre,tipo_id,ruc,telefono,email,direccion,fecha_agregado,plazo,id_usuario,provincia,ciudad,status,id_vendedor)
            VALUES ('%s','%s','04','%s','%s','%s','%s','%s','1','%d','17','189','1','')",
				mysqli_real_escape_string($con, $ruc_empresa),
				mysqli_real_escape_string($con, $razon_social),
				mysqli_real_escape_string($con, $ruc_proveedor),
				mysqli_real_escape_string($con, $telefono_proveedor_cliente),
				mysqli_real_escape_string($con, $mail_proveedor_cliente),
				mysqli_real_escape_string($con, $direccion_proveedor_cliente),
				mysqli_real_escape_string($con, date("Y-m-d H:i:s")),
				$id_usuario
			);
			if (!mysqli_query($con, $sql_ins)) {
				return 0;
			}

			$rsNew = mysqli_query($con, "SELECT id FROM clientes WHERE id=LAST_INSERT_ID()");
			$rowN  = mysqli_fetch_assoc($rsNew);
			return (int)$rowN['id'];
		}

		// Si 'tipo' no coincide
		return 0;
	}


	public function lee_ride($clave_acceso)
	{
		$consulta = new ConsultaCompSri();
		$consulta->claveacceso = $clave_acceso;
		$comp = $consulta->consultar();

		if ($comp) {
			return $comp;
		} else {
			if (count($consulta->get_errores()) > 0) {
				foreach ($consulta->get_errores() as $err) {
					echo $err . "<br>";
				}
			}
			return false;
		}
		return false;
	}

	public function lee_xml_notaria($xml)
	{
		$xml = simplexml_load_file($xml);
		$cdataContent = $xml->comprobante;
		$comprobanteArray = simplexml_load_string($xml);
		return $xml;
	}

	public function lee_xml($archivoXml)
	{
		libxml_use_internal_errors(true);

		$contenido = false;

		// 1. Intentar con file_get_contents()
		if (filter_var($archivoXml, FILTER_VALIDATE_URL)) {
			$contenido = @file_get_contents($archivoXml);
		} elseif (file_exists($archivoXml)) {
			$contenido = file_get_contents($archivoXml);
		}

		// 2. Si file_get_contents() falla y es URL, usar cURL
		if ($contenido === false && filter_var($archivoXml, FILTER_VALIDATE_URL)) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $archivoXml);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 20);
			$contenido = curl_exec($ch);
			curl_close($ch);
		}

		// 3. Verifica si se obtuvo contenido
		if ($contenido === false || empty($contenido)) {
			libxml_clear_errors();
			return false;
		}

		// 4. Cargar XML principal
		$xmlPrincipal = simplexml_load_string($contenido);
		if ($xmlPrincipal === false) {
			libxml_clear_errors();
			return false;
		}

		// 5. Extraer <comprobante> y cargar como XML
		$contenidoComprobante = isset($xmlPrincipal->comprobante)
			? (string)$xmlPrincipal->comprobante
			: null;

		if (!$contenidoComprobante) {
			return false;
		}

		$xmlComprobante = simplexml_load_string($contenidoComprobante);
		if ($xmlComprobante === false) {
			libxml_clear_errors();
			return false;
		}

		return $xmlComprobante;
	}


	//ESTA ES PARA LEER DESDE UN XML Y MOSTRAR LOS DATOS PARA ANULAR DOCUMENTO
	public function estado_ride($clave_acceso)
	{
		$consulta = new ConsultaCompSri();
		$consulta->claveacceso = $clave_acceso;
		$estado_comp = $consulta->consultar_estado();

		if ($estado_comp) {
			return $estado_comp;
		} else {

			if (count($consulta->get_errores()) > 0) {
				foreach ($consulta->get_errores() as $err) {
					return $err;
				}
			}
			return false;
		}
		return false;
	}


	//ESTA ES PARA LEER LA FECHA DE AUTORIZACION
	public function fecha_autorizacion($clave_acceso)
	{
		$consulta = new ConsultaCompSri();
		$consulta->claveacceso = $clave_acceso;
		$fecha_autorizacion = $consulta->consultar_fecha_autorizacion();

		if ($fecha_autorizacion) {
			return $fecha_autorizacion;
		} else {

			if (count($consulta->get_errores()) > 0) {
				foreach ($consulta->get_errores() as $err) {
					echo $err;
				}
			}
			return false;
		}
		return false;
	}
}

//para comprobar si existen ya resgistros de este documento
function comprueba_registrados($con, $ruc_empresa, $registrar_en, $serie, $secuencial, $id_proveedor_cliente, $aut_sri)
{
	// Valor por defecto si algo falla
	$total = 0;

	// Normalizaciones
	$ruc10        = substr((string)$ruc_empresa, 0, 10);
	$secuencial_i = (int)$secuencial;                        // en varias tablas es INT
	$numdoc_str   = $serie . "-" . (string)$secuencial;      // para encabezado_compra.* (string)

	// Mapa de casos → metadatos de tabla y columnas
	$cases = array(
		// --- Compras (encabezado_compra) ---
		"factura_compra" => array(
			"table"  => "encabezado_compra",
			"cols"   => array("LEFT(ruc_empresa,10)=?", "numero_documento=?", "id_proveedor=?", "id_comprobante='1'", "aut_sri=?"),
			"values" => array($ruc10, $numdoc_str, (int)$id_proveedor_cliente, $aut_sri),
		),
		"nc_compra" => array(
			"table"  => "encabezado_compra",
			"cols"   => array("LEFT(ruc_empresa,10)=?", "numero_documento=?", "id_proveedor=?", "id_comprobante='4'", "aut_sri=?"),
			"values" => array($ruc10, $numdoc_str, (int)$id_proveedor_cliente, $aut_sri),
		),
		"lc_compra" => array(
			"table"  => "encabezado_compra",
			"cols"   => array("LEFT(ruc_empresa,10)=?", "numero_documento=?", "id_proveedor=?", "id_comprobante='7'", "aut_sri=?"),
			"values" => array($ruc10, $numdoc_str, (int)$id_proveedor_cliente, $aut_sri),
		),
		"nd_compra" => array(
			"table"  => "encabezado_compra",
			"cols"   => array("LEFT(ruc_empresa,10)=?", "numero_documento=?", "id_proveedor=?", "id_comprobante='5'", "aut_sri=?"),
			"values" => array($ruc10, $numdoc_str, (int)$id_proveedor_cliente, $aut_sri),
		),

		// --- Ventas (encabezado_factura) ---
		"factura_venta" => array(
			"table"  => "encabezado_factura",
			"cols"   => array("LEFT(ruc_empresa,10)=?", "serie_factura=?", "secuencial_factura=?", "id_cliente=?", "aut_sri=?"),
			"values" => array($ruc10, $serie, $secuencial_i, (int)$id_proveedor_cliente, $aut_sri),
		),

		// --- Retenciones Compras (encabezado_retencion) ---
		"retencion_compra" => array(
			"table"  => "encabezado_retencion",
			"cols"   => array("LEFT(ruc_empresa,10)=?", "serie_retencion=?", "secuencial_retencion=?", "id_proveedor=?", "aut_sri=?"),
			"values" => array($ruc10, $serie, $secuencial_i, (int)$id_proveedor_cliente, $aut_sri),
		),

		// --- Retenciones Ventas (encabezado_retencion_venta) ---
		"retencion_venta" => array(
			"table"  => "encabezado_retencion_venta",
			"cols"   => array("LEFT(ruc_empresa,10)=?", "serie_retencion=?", "secuencial_retencion=?", "id_cliente=?", "aut_sri=?"),
			"values" => array($ruc10, $serie, $secuencial_i, (int)$id_proveedor_cliente, $aut_sri),
		),

		// --- Liquidación de compras/servicios (encabezado_liquidacion) ---
		"liquidacion" => array(
			"table"  => "encabezado_liquidacion",
			"cols"   => array("LEFT(ruc_empresa,10)=?", "serie_liquidacion=?", "secuencial_liquidacion=?", "id_proveedor=?", "aut_sri=?"),
			"values" => array($ruc10, $serie, $secuencial_i, (int)$id_proveedor_cliente, $aut_sri),
		),

		// --- Nota de crédito ventas (encabezado_nc) ---
		"nota_credito" => array(
			"table"  => "encabezado_nc",
			"cols"   => array("LEFT(ruc_empresa,10)=?", "serie_nc=?", "secuencial_nc=?", "id_cliente=?", "aut_sri=?"),
			"values" => array($ruc10, $serie, $secuencial_i, (int)$id_proveedor_cliente, $aut_sri),
		),
	);

	if (!isset($cases[$registrar_en])) {
		return 0; // caso no reconocido
	}

	$meta  = $cases[$registrar_en];
	$where = implode(" AND ", $meta["cols"]);
	$sql   = "SELECT COUNT(*) AS total FROM {$meta['table']} WHERE {$where}";

	// Prepara statement
	$stmt = mysqli_prepare($con, $sql);
	if (!$stmt) {
		return 0;
	}

	// Arma tipos y valores a bindear
	$types = '';
	$binds = array();
	foreach ($meta["values"] as $v) {
		if (is_int($v)) {
			$types .= 'i';
			$binds[] = $v;             // entero real
		} else {
			$types .= 's';
			$binds[] = (string)$v;     // string real
		}
	}

	// ---- bind_param necesita REFERENCIAS a variables ----
	$refs = array();
	foreach ($binds as $k => $v) {
		$refs[$k] = &$binds[$k];       // referencia al elemento del array
	}
	// Inserta primero el string de tipos
	array_unshift($refs, $types);

	// En estilo OO para simplificar la firma
	call_user_func_array(array($stmt, 'bind_param'), $refs);

	// Ejecuta
	if (!mysqli_stmt_execute($stmt)) {
		mysqli_stmt_close($stmt);
		return 0;
	}

	// Obtiene resultado (mysqlnd) o fallback (sin mysqlnd)
	$res = function_exists('mysqli_stmt_get_result') ? mysqli_stmt_get_result($stmt) : false;

	if ($res) {
		$row = mysqli_fetch_assoc($res);
		$total = isset($row['total']) ? (int)$row['total'] : 0;
		mysqli_free_result($res);
	} else {
		// Fallback: bind_result
		mysqli_stmt_store_result($stmt);
		mysqli_stmt_bind_result($stmt, $totalTmp);
		if (mysqli_stmt_fetch($stmt)) {
			$total = (int)$totalTmp;
		} else {
			$total = 0;
		}
	}

	mysqli_stmt_close($stmt);
	return $total;
}


/**
 * Formatea una fecha de emisión a 'YYYY-mm-dd'.
 * Acepta: 'dd/mm/yyyy', 'yyyy-mm-dd', 'dd-mm-yyyy', 'yyyy/mm/dd'.
 * Si no puede parsear, devuelve la fecha de hoy.
 */
function formatea_fecha_emision($raw)
{
	date_default_timezone_set('America/Guayaquil');

	$s = trim((string)$raw);
	if ($s === '') {
		return date('Y-m-d');
	}

	// Intentos específicos (validación estricta por formato)
	$formatos = array('d/m/Y', 'Y-m-d', 'd-m-Y', 'Y/m/d');
	foreach ($formatos as $fmt) {
		$dt = DateTime::createFromFormat($fmt, $s);
		if ($dt && $dt->format($fmt) === $s) {
			return $dt->format('Y-m-d');
		}
	}

	// Fallback genérico
	$ts = strtotime($s);
	return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
}
