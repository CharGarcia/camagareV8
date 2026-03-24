<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
require_once("../ajax/pagination.php"); //include pagination file
require_once("../helpers/helpers.php"); //include pagination file
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'eliminar_cuenta_bancaria') {
	$id_cuenta = intval($_GET['id_cuenta']);
	$query_cuentas_bancarias = mysqli_query($con, "SELECT * from formas_pagos_ing_egr WHERE id_cuenta='" . $id_cuenta . "'");
	$count = mysqli_num_rows($query_cuentas_bancarias);

	if ($count > 0) {
		echo "<script>$.notify('No se puede eliminar. Existen registros realizados con esta cuenta.','error')</script>";
	} else {
		if ($deleteuno = mysqli_query($con, "UPDATE cuentas_bancarias SET status='0' WHERE id_cuenta='" . $id_cuenta . "'")) {
			echo "<script>$.notify('Cuenta eliminada.','success')</script>";
		} else {
			echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
		}
	}
}

if ($action == 'buscar_cuentas_bancarias') {
	// escaping, additionally removing everything that could be (html/javascript-) code
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$aColumns = array('be.nombre_banco', 'cb.numero_cuenta'); //Columnas de busqueda
	$sTable = "bancos_ecuador as be, cuentas_bancarias as cb";
	$sWhere = "WHERE cb.ruc_empresa ='" .  $ruc_empresa . " ' and cb.id_banco=be.id_bancos and cb.status !='0'";
	if ($_GET['q'] != "") {
		$sWhere = "WHERE (cb.ruc_empresa ='" .  $ruc_empresa . " ' and cb.id_banco=be.id_bancos and cb.status !='0' AND ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' and cb.id_banco=be.id_bancos and cb.status !='0' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND cb.ruc_empresa = '" .  $ruc_empresa . " ' and cb.id_banco=be.id_bancos and cb.status !='0'", -3);
		$sWhere .= ')';
	}
	$sWhere .= " order by be.nombre_banco asc";
	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 10; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '';
	//main query to fetch the data
	$sql = "SELECT cb.id_cuenta as id_cuenta, cb.id_banco as id_banco, cb.numero_cuenta as numero_cuenta, be.nombre_banco as nombre_banco, cb.id_tipo_cuenta as id_tipo_cuenta, cb.status as status  FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {
?>

		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table">
					<tr class="info">
						<td>#</td>
						<td>Banco</td>
						<td>Tipo cuenta</td>
						<td>Número cuenta</td>
						<td>Status</td>
						<td class='text-right'>Opciones</td>
					</tr>
					<?php
					$n = 0;
					while ($fila = mysqli_fetch_assoc($query)) {
						$id_cuenta = $fila['id_cuenta'];
						$status = $fila['status'];
						$n++;
						switch ($fila['id_tipo_cuenta']) {
							case "1":
								$tipo_cuenta = 'AHORROS';
								break;
							case "2":
								$tipo_cuenta = 'CORRIENTE';
								break;
							case "3":
								$tipo_cuenta = 'VIRTUAL';
								break;
							case "4":
								$tipo_cuenta = 'TARJETA';
								break;
						}

						$sql_empresa = mysqli_query($con, "SELECT nombre, 
						CASE 
        WHEN tipo = '01' THEN CONCAT('Cédula ', SUBSTRING(ruc, 1, 10))
        WHEN tipo = '02' THEN CONCAT('Cédula ', SUBSTRING(ruc, 1, 10))
        WHEN tipo = '03' THEN CONCAT('RUC ', SUBSTRING(ruc, 1, 10), '001')
        WHEN tipo = '04' THEN CONCAT('RUC ', SUBSTRING(ruc, 1, 10), '001')
        WHEN tipo = '05' THEN CONCAT('RUC ', SUBSTRING(ruc, 1, 10), '001')
        ELSE ruc -- En caso de que no coincida con ninguna de las anteriores, se mantiene el valor original
    END AS ruc
	 FROM empresas WHERE ruc = '" . $ruc_empresa . "' ");
						$row_empresa = mysqli_fetch_array($sql_empresa);
						$nombre_ruc_empresa = " a nombre de " . $row_empresa['nombre'] . " " . $row_empresa['ruc'];


						$mensaje = "Cuenta " . $tipo_cuenta . " Banco " . strtoupper($fila['nombre_banco']) . " No. " . $fila['numero_cuenta'] . $nombre_ruc_empresa;
					?>
						<input type="hidden" value="<?php echo $fila['id_banco']; ?>" id="banco_mod<?php echo $id_cuenta; ?>">
						<input type="hidden" value="<?php echo $status; ?>" id="status_mod<?php echo $id_cuenta; ?>">
						<input type="hidden" value="<?php echo $fila['id_tipo_cuenta']; ?>" id="tipo_mod<?php echo $id_cuenta; ?>">
						<input type="hidden" value="<?php echo $fila['numero_cuenta']; ?>" id="cuenta_mod<?php echo $id_cuenta; ?>">
						<tr>
							<td> <?php echo $n ?></td>
							<td> <?php echo (strtoupper($fila['nombre_banco'])) ?> </td>
							<td> <?php echo ($tipo_cuenta) ?> </td>
							<td> <?php echo ($fila['numero_cuenta']) ?> </td>
							<td><?php echo $status == 1 ? "<span class='label label-success'>Activo</span>" : "<span class='label label-danger'>Inactivo</span>"; ?></td>
							<td><span class="pull-right">
									<?php
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'cuentas_bancarias')['w'] == 1) {
									?>
										<a class='btn btn-success btn-xs' title='Enviar cuenta por whatsapp' onclick="enviar_cb_whatsapp('<?php echo $ruc_empresa; ?>', '', '<?php echo $mensaje; ?>')" data-toggle="modal" data-target="#EnviarDocumentosWhatsapp"><img src="../image/whatsapp.png" alt="Logo" width="15px"> </a>
									<?php
									}
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'cuentas_bancarias')['u'] == 1) {
									?>
										<a class="btn btn-info btn-xs" title="Editar cuenta bancaria" onclick="editar_cuenta_bancaria('<?php echo $fila['id_cuenta']; ?>');" data-toggle="modal" data-target="#CuentaBancaria"><i class="glyphicon glyphicon-edit"></i></a>
									<?php
									}
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'cuentas_bancarias')['d'] == 1) {
									?>
										<a class="btn btn-danger btn-xs" title="Eliminar cuenta bancaria" onclick="eliminar_cuenta_bancaria('<?php echo $fila['id_cuenta']; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
									<?php
									}
									?>
								</span></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="6"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?></span></td>
					</tr>
				</table>
			</div>
		</div>
<?php
	}
}

//guardar o editar
if ($action == 'guarda_cuenta_bancaria') {
	$id_cuenta = intval($_POST['id_cuenta']);
	$banco = intval($_POST['banco']);
	$tipo = intval($_POST['tipo']);
	$cuenta = strClean($_POST['cuenta']);
	$status = $_POST['status'];

	if (empty($banco)) {
		echo "<script>
            $.notify('Seleccione un banco','error');
            </script>";
	} else if (empty($tipo)) {
		echo "<script>
		$.notify('Seleccione un tipo de cuenta','error');
		</script>";
	} else if (empty($cuenta)) {
		echo "<script>
		$.notify('Ingrese número de cuenta','error');
		</script>";
	} else {
		if (empty($id_cuenta)) {
			$busca_cuenta = mysqli_query($con, "SELECT * FROM cuentas_bancarias WHERE numero_cuenta = '" . $cuenta . "' and ruc_empresa = '" . $ruc_empresa . "' and id_banco ='" . $banco . "' and id_tipo_cuenta ='" . $tipo . "' and status !='0'");
			$count = mysqli_num_rows($busca_cuenta);
			if ($count > 0) {
				echo "<script>
                $.notify('La cuenta ya esta registrada','error');
                </script>";
			} else {
				$guarda_cuenta_bancaria = mysqli_query($con, "INSERT INTO cuentas_bancarias (ruc_empresa,
                                                                        id_banco,
                                                                        id_tipo_cuenta,
                                                                        numero_cuenta,
																		id_usuario,
																		status)
                                                                            VALUES ('" . $ruc_empresa . "',
                                                                                    '" . $banco . "',
                                                                                    '" . $tipo . "',
                                                                                    '" . $cuenta . "',
																					'" . $id_usuario . "',
																					'" . $status . "')");

				if ($guarda_cuenta_bancaria) {
					echo "<script>
                $.notify('Cuenta bancaria registrada','success');
                document.querySelector('#formCuentaBancaria').reset();
                load(1);
                </script>";
				} else {
					echo "<script>
                $.notify('Revisar los datos ingresados e intentar guardar de nuevo','error');
                </script>";
				}
			}
		} else {
			//modificar
			$busca_cuenta_bancaria = mysqli_query($con, "SELECT * FROM cuentas_bancarias WHERE numero_cuenta = '" . $cuenta . "' and id_cuenta != '" . $id_cuenta . "' and id_banco = '" . $banco . "' and id_tipo_cuenta = '" . $tipo . "' and ruc_empresa = '" . $ruc_empresa . "' and status !='0'");
			$count = mysqli_num_rows($busca_cuenta_bancaria);
			if ($count > 0) {
				echo "<script>
                $.notify('La cuenta bancaria ya esta registrada','error');
                </script>";
			} else {
				$update_cuenta_bancaria = mysqli_query($con, "UPDATE cuentas_bancarias SET id_banco='" . $banco . "',
																					id_tipo_cuenta='" . $tipo . "',
																					numero_cuenta='" . $cuenta . "',
																					id_usuario='" . $id_usuario . "',
																					status='" . $status . "'
																					WHERE id_cuenta ='" . $id_cuenta . "'");
				if ($update_cuenta_bancaria) {
					echo "<script>
                    $.notify('Cuenta bancaria actualizada','success');
                    setTimeout(function () {location.reload()}, 1000);
                        </script>";
				} else {
					echo "<script>
                        $.notify('Revisar los datos ingresados e intentar guardar de nuevo','error');
                        </script>";
				}
			}
		}
	}
}
?>