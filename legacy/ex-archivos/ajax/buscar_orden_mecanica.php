<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	$fecha_registro=date("Y-m-d H:i:s");
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';


	if ($action == 'buscar_orden') {
	$codigo_unico_mecanica = $_GET['codigo'];
	$sql_orden_generada = mysqli_query($con, "SELECT count(*) AS numrows, reg.serie as serie, reg.numero_documento as secuencial, 
	reg.documento_generado as tipo_documento FROM registros_facturados as reg INNER JOIN encabezado_mecanica as mec 
	ON mec.numero_orden=reg.numero_orden WHERE mec.ruc_empresa='" . $ruc_empresa . "' and reg.ruc_empresa='" . $ruc_empresa . "' 
	and reg.emitido_desde='orden_mecanica' and mec.codigo_unico='".$codigo_unico_mecanica."' ");
	$row_orden = mysqli_fetch_array($sql_orden_generada);
	//$numrows = $row_orden['numrows'];
	$serie = $row_orden['serie'];
	$secuencial = $row_orden['secuencial'];
	$tipo_documento = $row_orden['tipo_documento'];
	
	$sql_status_factura = mysqli_query($con, "SELECT count(secuencial_factura) AS facturas FROM encabezado_factura WHERE ruc_empresa='" . $ruc_empresa . "' and serie_factura='" . $serie . "' and secuencial_factura='".$secuencial."' and estado_sri !='ANULADA'");
	$row_status_factura = mysqli_fetch_array($sql_status_factura);
	$facturas_emitidas= $row_status_factura['facturas']>0?$row_status_factura['facturas']." Factura(s)":"";
	
	$sql_status_recibo = mysqli_query($con, "SELECT count(secuencial_recibo) AS recibos FROM encabezado_recibo WHERE ruc_empresa='" . $ruc_empresa . "' and serie_recibo='" . $serie . "' and secuencial_recibo='".$secuencial."' and status !='2'");
	$row_status_recibo = mysqli_fetch_array($sql_status_recibo);
	$recibos_emitidas= $row_status_recibo['recibos']>0?$row_status_recibo['recibos']." Recibo(s)":"";
	

	
	if(isset($sql_orden_generada)){
		if($row_status_factura['facturas'] + $row_status_recibo['recibos'] > 0){
			$arrResponse = array("status" => true, "msg"=> $facturas_emitidas." ".$recibos_emitidas);
		}else{
			$arrResponse = array("status" => false);
		}
	}else{
			$arrResponse = array("status" => false);
	}

	echo json_encode($arrResponse);
	die();
	}


	//para agregar un producto a la lista de la factura cuando lee el codigo de barras
if ($action == 'bar_code') {
	$codigo_producto = $_GET['codigo_producto'];
	//$codigo_producto = $_GET['codigo_producto'];

	$sql_producto = mysqli_query($con, "SELECT pro.id as id, pro.nombre_producto as nombre_producto,
	pro.precio_producto as precio_producto, tar.porcentaje_iva as porcentaje_iva, med.nombre_medida as nombre_medida, 
	pro.id_unidad_medida as id_medida, pro.codigo_producto as codigo_producto, pro.tipo_produccion as tipo_produccion
	FROM productos_servicios as pro INNER JOIN tarifa_iva as tar ON tar.codigo=pro.tarifa_iva 
	INNER JOIN unidad_medida as med ON med.id_medida=pro.id_unidad_medida 
	WHERE pro.codigo_producto= '" . $codigo_producto . "' or pro.codigo_auxiliar= '" . $codigo_producto . "' and pro.ruc_empresa='" . $ruc_empresa . "'");//pro.codigo_producto= '" . $codigo_producto . "'  or
	$row_producto = mysqli_fetch_array($sql_producto);
	$id_producto = $row_producto["id"];
	$nombre_producto = $row_producto["nombre_producto"];
	$codigo_producto = $row_producto["codigo_producto"];
	$precio_producto = $row_producto["precio_producto"];
	$id_medida = $row_producto["id_medida"];
	$nombre_medida= $row_producto["nombre_medida"];
	$tipo_produccion= $row_producto["tipo_produccion"];
	$porcentaje_iva = number_format($row_producto["porcentaje_iva"]/100,2,'.','');


	$precio_producto_iva = number_format($precio_producto + ($row_producto["precio_producto"] * ($porcentaje_iva)),2,'.','');//number_format($total_a_pagar,2,'.','');

	if (isset($id_producto)) {
		$arrResponse = array('status' => true, 'id_producto' => $id_producto, 'nombre_producto' => $nombre_producto, 
		'precio_producto' => $precio_producto, 'precio_iva'=>$precio_producto_iva, 'porcentaje_iva'=> $porcentaje_iva, 
		'id_medida'=>$id_medida, 'nombre_medida'=>$nombre_medida, 'codigo_producto'=>$codigo_producto, 'tipo_produccion'=>$tipo_produccion);
	} else {
		$arrResponse = array("status" => false, "msg" => 'Producto no encontrado.');
	}
	echo json_encode($arrResponse);//, JSON_UNESCAPED_UNICODE
	die();

}


//PARA BUSCAR LAS ordenes de la mecanica
	
	if($action == 'ordenes_mecanica'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $text_buscar = explode(' ',$q);
		 $like="";
		 for ( $i=0 ; $i<count($text_buscar) ; $i++ )
		 {
			 $like .= "%".$text_buscar[$i];
		 }
		 $ordenado = mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
		 $por = mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
		 $aColumns = array('fecha_recepcion','nombre_usuario','nombre','ruc', 'marca', 'placa', 'propietario', 'chasis', 'numero_orden');//Columnas de busqueda
		 $sTable = "encabezado_mecanica as em LEFT JOIN clientes as cl ON em.id_cliente=cl.id INNER JOIN vehiculos as ve ON em.codigo_unico=ve.codigo_unico";
		 $sWhere = "WHERE em.ruc_empresa ='".  $ruc_empresa ." '  " ;
		if ( $_GET['q'] != "" )
		{
			$sWhere = "WHERE (em.ruc_empresa ='".  $ruc_empresa ." ' AND ";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$like."%' AND em.ruc_empresa = '".  $ruc_empresa ."' OR ";
			}
			
			$sWhere = substr_replace( $sWhere, "AND em.ruc_empresa = '".  $ruc_empresa ."'  ", -3 );
			$sWhere .= ')';
		}	
		$sWhere.=" order by $ordenado $por";

		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 20; //how much records you want to show
		$adjacents  = 10; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '../orden_mecanica.php';
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
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_recepcion");'>Entrada</button></th>
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("numero_orden");'>No.</button></th>
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre");'>Cliente</button></th>
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("placa");'>Placa</button></th>
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_usuario");'>Usuario</button></th>
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("marca");'>Marca</button></th>								
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("estado");'>Estado</button></th>
				<th style ="padding: 0px;" class="text-right"><a style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" >Total</th>								
				<th style ="padding: 0px;" class="text-right"><a style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" >Opciones</th>																		
				</tr>
				<?php

				while ($row=mysqli_fetch_array($query)){
						$id_encabezado_mecanica=$row['id_enc_mecanica'];
						$fecha_recepcion=$row['fecha_recepcion'];
						$hora_recepcion=$row['hora_recepcion'];
						$nombre_usuario=$row['nombre_usuario'];
						$nombre_cliente=$row['nombre'];
						$estado=$row['estado'];
						$id_cliente=$row['id_cliente'];
						$tipo_id=$row['tipo_id'];
						$ruc_cliente=$row['ruc'];
						$nombre_cliente=$row['nombre'];
						$telefono_cliente=$row['telefono'];
						$direccion_cliente=$row['direccion'];
						$plazo_cliente=$row['plazo'];
						$mail_cliente=$row['email'];
						$codigo_unico=$row['codigo_unico'];
						$placa=$row['placa'];
						$marca=$row['marca'];
						$chasis=$row['chasis'];
						$anio=$row['anio'];
						$numero_orden=$row['numero_orden'];
						$propietario=$row['propietario'];
						//usuario
						$nombre_usuario=$row['nombre_usuario'];
						$contacto_usuario=$row['contacto_usuario'];
						$correo_usuario=$row['correo_usuario'];
						//datos entrada y salida del vehiculo
						$fecha_entrega=($row['fecha_entrega']!=0)?date("d-m-Y", strtotime($row['fecha_entrega'])):"dd-mm-aaaa";
						$hora_entrega=$row['hora_entrega'];
						//prox chequeo
						$proximo_chequeo=($row['proximo_chequeo']!=0)?date("d-m-Y", strtotime($row['proximo_chequeo'])):"dd-mm-aaaa";
						$obs_proximo_chequeo=$row['obs_prox_chequeo'];
						
						if ($nombre_cliente==""){
						$nombre_cliente="AGREGAR";
						$clase="btn btn-danger btn-xs";
						}else{
						$nombre_cliente=strtoupper($nombre_cliente);
						$clase="";
						}
				
					//estado mecanica
					switch ($estado) {
					case "EN TALLER":
						$label_class_mecanica='label-info';
						break;
					case "CERRADA":
						$label_class_mecanica='label-danger';
						break;
					case "EN ESPERA":
						$label_class_mecanica='label-warning';
						break;
						}
						$sql_total_factura = mysqli_query($con, "SELECT sum(round(det.subtotal,2)) + sum(round(det.subtotal,2) * (tar.porcentaje_iva /100)) as subtotal 
						FROM detalle_factura_mecanica as det INNER JOIN productos_servicios as pro
						ON pro.id=det.id_producto INNER JOIN tarifa_iva as tar On tar.codigo=det.tarifa_iva 
						WHERE det.codigo_unico = '".$codigo_unico."' and det.ruc_empresa='".$ruc_empresa."' group by det.codigo_unico");
						$row_total_factura = mysqli_fetch_array($sql_total_factura);
						$total_a_pagar=number_format(isset($row_total_factura['subtotal'])?$row_total_factura['subtotal']:0,2,'.','');
						?>
					<input type="hidden" value="<?php echo $codigo_unico;?>" id="codigo_unico<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo $id_encabezado_mecanica;?>" id="id_mecanica<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo $tipo_id;?>" id="tipo_id<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo $id_cliente;?>" id="id_cliente<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo $ruc_cliente;?>" id="ruc_cliente<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo $nombre_cliente;?>" id="nombre_cliente<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo $telefono_cliente;?>" id="telefono_cliente<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo $direccion_cliente;?>" id="direccion_cliente<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo $plazo_cliente;?>" id="plazo_cliente<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo $mail_cliente;?>" id="mail_cliente<?php echo $id_encabezado_mecanica;?>">
					
					<input type="hidden" value="<?php echo date("d-m-Y", strtotime($fecha_recepcion));?>" id="fecha_recepcion<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo date("H:i", strtotime($hora_recepcion));?>" id="hora_recepcion<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo date("d-m-Y", strtotime($fecha_entrega));?>" id="fecha_entrega<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo date("H:i", strtotime($hora_entrega));?>" id="hora_entrega<?php echo $id_encabezado_mecanica;?>">

					<input type="hidden" value="<?php echo $placa;?>" id="placa<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo $marca;?>" id="marca<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo $chasis;?>" id="chasis<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo $anio;?>" id="anio<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo $propietario;?>" id="propietario<?php echo $id_encabezado_mecanica;?>">

					<input type="hidden" value="<?php echo $nombre_usuario;?>" id="nombre_usuario<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo $contacto_usuario;?>" id="contacto_usuario<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo $correo_usuario;?>" id="correo_usuario<?php echo $id_encabezado_mecanica;?>">
					
					<input type="hidden" value="<?php echo $proximo_chequeo;?>" id="proximo_chequeo<?php echo $id_encabezado_mecanica;?>">
					<input type="hidden" value="<?php echo $obs_proximo_chequeo;?>" id="obs_proximo_chequeo<?php echo $id_encabezado_mecanica;?>">
					
					<input type="hidden" value="<?php echo $estado;?>" id="estado<?php echo $id_encabezado_mecanica;?>">

					<tr>
						<td class='col-sm-1'><?php echo date("d-m-Y", strtotime($fecha_recepcion))." || ".date("H:i", strtotime($hora_recepcion)); ?></td>
						<td class='col-sm-1'><?php echo strtoupper ($numero_orden); ?></td>
						<?php
						if ($estado !='CERRADA'){
						?>
						<td class='col-sm-2'><a class='<?php echo $clase;?>' href="#" title='Editar datos facturación' onclick="agrega_datos_facturacion('<?php echo $id_encabezado_mecanica; ?>')" data-toggle="modal" data-target="#buscarAgregarEdiatrCliente"><?php echo $nombre_cliente; ?></a></td>
						<?php
						}else{
						?>
						<td class='col-sm-2'><?php echo $nombre_cliente; ?></td>
						<?php
						}
						?>
						<td class='col-sm-1'><?php echo strtoupper ($placa); ?></td>
						<td class='col-sm-2'><?php echo strtoupper ($nombre_usuario); ?></td>
						<td class='col-sm-2'><?php echo strtoupper ($marca); ?></td>
						<td><span class="label <?php echo $label_class_mecanica;?>"><?php echo $estado;?></span></td>
						<td class='col-sm-1 text-right'><?php echo $total_a_pagar;?></td>
						<td class='col-sm-5 text-right'>
						<a title='Detalle orden' href="../pdf/pdf_orden_mecanica.php?action=orden_mecanica&codigo_unico=<?php echo $codigo_unico ?>" class='btn btn-default btn-sm' title='Pdf' target="_blank">Pdf</a>
						<a href="#" class='btn btn-info btn-sm' title='Detalle orden' onclick="detalle_orden('<?php echo $id_encabezado_mecanica; ?>')" data-toggle="modal" data-target="#detalleOrdenMecanica"><i class="glyphicon glyphicon-dashboard"></i> </a>
						<a href="#" class='btn btn-success btn-sm' title='Detalle factura' onclick="detalle_factura_mecanica('<?php echo $id_encabezado_mecanica; ?>')" data-toggle="modal" data-target="#detalleFacturaMecanica"><i class="glyphicon glyphicon-list-alt"></i> </a>						
						<a href="#" class='btn btn-danger btn-sm' title='Eliminar orden' onclick="eliminar_orden_total('<?php echo $id_encabezado_mecanica; ?>')"><i class="glyphicon glyphicon-erase"></i> </a>
						</td>			
					</tr>
				<?php
				}
				?>
				<tr>
					<td colspan="9"><span class="pull-right">
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