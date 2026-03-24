<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	include("../validadores/periodo_contable.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	$fecha_registro=date("Y-m-d H:i:s");

//PARA BUSCAR LOS PAGOS DE LOS INGRESOS
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'ajax'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $detpago = mysqli_real_escape_string($con,(strip_tags($_REQUEST['detpago'], ENT_QUOTES)));
		 $aColumns = array('numero_ing_egr','detalle_pago');//Columnas de busqueda
		 $sTable = "formas_pagos_ing_egr fpie, formas_de_pago fdp";
		 $sWhere = "WHERE fpie.ruc_empresa ='".  $ruc_empresa ." ' and fpie.tipo_documento='INGRESO' and fpie.id_forma_pago=fdp.id_forma_pago " ;
		if ( $_GET['detpago'] != "" ){
			$sWhere = "WHERE (fpie.ruc_empresa ='".  $ruc_empresa ." ' and fpie.tipo_documento='INGRESO' and fpie.id_forma_pago=fdp.id_forma_pago AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$detpago."%' and fpie.tipo_documento='INGRESO' and fpie.id_forma_pago=fdp.id_forma_pago OR ";
			}
			$sWhere = substr_replace( $sWhere, "AND fpie.ruc_empresa = '".  $ruc_empresa ."' and fpie.tipo_documento='INGRESO' and fpie.id_forma_pago=fdp.id_forma_pago ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by fpie.numero_ing_egr desc";
		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 10; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '../ingresos.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
			<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr  class="info">
					<th>Número ingreso</th>
					<th>Forma de pago</th>
					<th>Cuenta bancaria</th>
					<th>Valor</th>
					<th>Detalle</th>
				</tr>
				<?php

				while ($row=mysqli_fetch_array($query)){
						$numero_ingreso=$row['numero_ing_egr'];
						$forma_pago=$row['nombre_pago'];
						$valor_forma_pago=$row['valor_forma_pago'];
						$detalle_pago=$row['detalle_pago'];
						$id_cuenta=$row['id_cuenta'];
						//para buscar el detalle de la cuenta bancaria
							$sql_cuenta_bancaria = "SELECT * FROM cuentas_bancarias where ruc_empresa='$ruc_empresa' and id_cuenta=$id_cuenta ";
							$respuesta_cuenta_bancaria = mysqli_query($con,$sql_cuenta_bancaria);
							$row_cuenta_bancaria = mysqli_fetch_array($respuesta_cuenta_bancaria);
							$numero_cuenta=$row_cuenta_bancaria['numero_cuenta'];
							$tipo_cuenta=$row_cuenta_bancaria['id_tipo_cuenta'];
							$id_banco=$row_cuenta_bancaria['id_banco'];
						//para buscar el banco
							$sql_bancos = "SELECT * FROM bancos_ecuador where id_bancos= '$id_banco'";
							$respuesta_bancos = mysqli_query($con,$sql_bancos);
							$row_banco = mysqli_fetch_array($respuesta_bancos);	
							$nombre_banco=$row_banco['nombre_banco'];
							
							switch ($tipo_cuenta){
							case 1:
								$tipo_cuenta_pago='AHORROS';
								break;
							case 2:
								$tipo_cuenta_pago='CORRIENTE';
								break;
							case 3:
								$tipo_cuenta_pago='VIRTUAL';
								break;
							case 4:
								$tipo_cuenta_pago='TARJETA';
								default;
								$tipo_cuenta_pago='';
								}
								
								$cuenta_bancaria = $nombre_banco."-".$tipo_cuenta_pago."-".$numero_cuenta;
					?>
					<tr>

						<td><?php echo $numero_ingreso; ?></td>
						<td><?php echo $forma_pago; ?></td>
						<td><?php echo $cuenta_bancaria; ?></td>
						<td><?php echo $valor_forma_pago; ?></td>
						<td><?php echo $detalle_pago; ?></td>
					</tr>
				<?php
				}
				?>
				<tr>
					<td colspan=9 ><span class="pull-right">
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
?>