<?php
$configuracion_proformas = new guarda_info_conf_proforma();
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
//para guardar o editar si aplica iventarios
if (isset($_POST['guarda_aplica_inventario'])){
			if (empty($_POST['serie_sucursal_trabaja_con_inventario'])) {
			   echo "<script> $.notify('Seleccione una sucursal.','error')</script>";
			}else if (empty($_POST['opcion_trabaja_inventario'])) {
				echo "<script> $.notify('Seleccione si desea trabajar con inventario.','error')</script>";
			} else if (!empty($_POST['serie_sucursal_trabaja_con_inventario'])&& !empty($_POST['opcion_trabaja_inventario'])){
			$id_confi=$_POST["id_conf_aplica_inventario"];
			$serie_sucursal=mysqli_real_escape_string($con,(strip_tags($_POST["serie_sucursal_trabaja_con_inventario"],ENT_QUOTES)));
			$opcion_trabaja_inventario=mysqli_real_escape_string($con,(strip_tags($_POST["opcion_trabaja_inventario"],ENT_QUOTES)));
			$consulta_configuracion=$configuracion_proformas->consulta_configuracion_proformas($id_confi, $con);	
			if(isset($id_confi) && !empty($id_confi)){
			echo $configuracion_proformas->actualiza_configuracion_proformas($id_confi, $serie_sucursal, $consulta_configuracion['clientes'], $consulta_configuracion['productos'], $consulta_configuracion['medida'], $consulta_configuracion['lote'], $consulta_configuracion['bodega'], $consulta_configuracion['vencimiento'], $consulta_configuracion['lote_impreso'], $consulta_configuracion['medida_impreso'], $consulta_configuracion['bodega_impreso'], $consulta_configuracion['vencimiento_impreso'], $opcion_trabaja_inventario, $consulta_configuracion['calculo_salida'],  $con);	
			}else{
			echo $configuracion_proformas->guarda_configuracion_proformas($serie_sucursal, '', '', '', '', '', '', '', '', '', '', $opcion_trabaja_inventario,'',  $con);
			} 
			}else {
			   echo "<script> $.notify('Error desconocido.','error')</script>";	
			}
}


//para guardar o editar compartir clientes y productos
if (isset($_POST['guarda_productos_clientes'])){
			if (empty($_POST['serie_sucursal_productos_clientes'])) {
			   echo "<script> $.notify('Seleccione una sucursal.','error')</script>";
			}else if (empty($_POST['compartir_clientes'])) {
				echo "<script> $.notify('Seleccione si desea compartir clientes.','error')</script>";
			}else if (empty($_POST['compartir_productos'])) {
				echo "<script> $.notify('Seleccione si desea compartir productos.','error')</script>";
			} else if (!empty($_POST['serie_sucursal_productos_clientes'])&& !empty($_POST['compartir_clientes']) && !empty($_POST['compartir_productos'])){
			$id_confi=$_POST["id_conf_facturacion_productos_clientes"];
			$serie_sucursal=mysqli_real_escape_string($con,(strip_tags($_POST["serie_sucursal_productos_clientes"],ENT_QUOTES)));
			$compartir_clientes=mysqli_real_escape_string($con,(strip_tags($_POST["compartir_clientes"],ENT_QUOTES)));
			$compartir_productos=mysqli_real_escape_string($con,(strip_tags($_POST["compartir_productos"],ENT_QUOTES)));
			$consulta_configuracion=$configuracion_proformas->consulta_configuracion_proformas($id_confi, $con);
			if(isset($id_confi) && !empty($id_confi)){
			echo $configuracion_proformas->actualiza_configuracion_proformas($id_confi, $serie_sucursal, $compartir_clientes, $compartir_productos, $consulta_configuracion['medida'], $consulta_configuracion['lote'], $consulta_configuracion['bodega'], $consulta_configuracion['vencimiento'], $consulta_configuracion['lote_impreso'], $consulta_configuracion['medida_impreso'], $consulta_configuracion['bodega_impreso'], $consulta_configuracion['vencimiento_impreso'], $consulta_configuracion['inventario'], $consulta_configuracion['calculo_salida'], $con);	
			}else{
			echo $configuracion_proformas->guarda_configuracion_proformas($serie_sucursal, $compartir_clientes, $compartir_productos, '', '', '', '', '', '', '','', '','',  $con);
			} 
			}else {
			   echo "<script> $.notify('Error desconocido.','error')</script>";	
			}
}

//para guardar o editar si quiere que aparezca lote, medida, vencimiento, bodega
if (isset($_POST['guarda_lista_opciones_inventarios'])){
			if (empty($_POST['serie_sucursal_opciones_inventario'])) {
			   echo "<script> $.notify('Seleccione una sucursal.','error')</script>";
			}else if (empty($_POST['mostrar_medida'])) {
				echo "<script> $.notify('Seleccione si desea que aparezca medida al facturar un producto.','error')</script>";
			}else if (empty($_POST['mostrar_lote'])) {
				echo "<script> $.notify('Seleccione si desea que aparezca lote al facturar un producto.','error')</script>";
			}else if (empty($_POST['mostrar_bodega'])) {
				echo "<script> $.notify('Seleccione si desea que aparezca bodega al facturar un producto.','error')</script>";
			}else if (empty($_POST['mostrar_caducidad'])) {
				echo "<script> $.notify('Seleccione si desea que aparezca caducidad al facturar un producto.','error')</script>";
			} else if (!empty($_POST['serie_sucursal_opciones_inventario'])&& !empty($_POST['mostrar_lote']) && !empty($_POST['mostrar_medida']) && !empty($_POST['mostrar_bodega']) && !empty($_POST['mostrar_caducidad'])){
			$id_confi=$_POST["id_conf_mostrar_opciones_inventario"];
			$serie_sucursal=mysqli_real_escape_string($con,(strip_tags($_POST["serie_sucursal_opciones_inventario"],ENT_QUOTES)));
			$mostrar_lote=mysqli_real_escape_string($con,(strip_tags($_POST["mostrar_lote"],ENT_QUOTES)));
			$mostrar_medida=mysqli_real_escape_string($con,(strip_tags($_POST["mostrar_medida"],ENT_QUOTES)));
			$mostrar_bodega=mysqli_real_escape_string($con,(strip_tags($_POST["mostrar_bodega"],ENT_QUOTES)));
			$mostrar_caducidad=mysqli_real_escape_string($con,(strip_tags($_POST["mostrar_caducidad"],ENT_QUOTES)));
			$consulta_configuracion=$configuracion_proformas->consulta_configuracion_proformas($id_confi, $con);
			if(isset($id_confi) && !empty($id_confi)){
			echo $configuracion_proformas->actualiza_configuracion_proformas($id_confi, $serie_sucursal, $consulta_configuracion['clientes'], $consulta_configuracion['productos'], $mostrar_medida, $mostrar_lote, $mostrar_bodega, $mostrar_caducidad, $consulta_configuracion['lote_impreso'], $consulta_configuracion['medida_impreso'], $consulta_configuracion['bodega_impreso'], $consulta_configuracion['vencimiento_impreso'], $consulta_configuracion['inventario'], $consulta_configuracion['calculo_salida'], $con);	
			}else{
			echo $configuracion_proformas->guarda_configuracion_proformas($serie_sucursal, '', '', $mostrar_medida, $mostrar_lote, $mostrar_bodega, $mostrar_caducidad, '', '', '','', '','', $con);
			} 
			}else {
			   echo "<script> $.notify('Error desconocido.','error')</script>";	
			}
}

//para guardar o editar si quiere imprimir en la factura lote, medida, vencimiento, bodega
if (isset($_POST['guarda_imprime_etiquetas'])){
			if (empty($_POST['serie_sucursal_mostrar_impresion'])) {
			   echo "<script> $.notify('Seleccione una sucursal.','error')</script>";
			}else if (empty($_POST['mostrar_medida_impresion'])) {
				echo "<script> $.notify('Seleccione si desea que aparezca medida en la impresión.','error')</script>";
			}else if (empty($_POST['mostrar_lote_impresion'])) {
				echo "<script> $.notify('Seleccione si desea que aparezca lote en la impresión.','error')</script>";
			}else if (empty($_POST['mostrar_bodega_impresion'])) {
				echo "<script> $.notify('Seleccione si desea que aparezca bodega en la impresión.','error')</script>";
			}else if (empty($_POST['mostrar_caducidad_impresion'])) {
				echo "<script> $.notify('Seleccione si desea que aparezca caducidad en la impresión.','error')</script>";
			} else if (!empty($_POST['serie_sucursal_mostrar_impresion'])&& !empty($_POST['mostrar_medida_impresion']) && !empty($_POST['mostrar_lote_impresion']) && !empty($_POST['mostrar_bodega_impresion']) && !empty($_POST['mostrar_caducidad_impresion'])){
			$id_confi=$_POST["id_conf_imprime_etiquetas"];
			$serie_sucursal=mysqli_real_escape_string($con,(strip_tags($_POST["serie_sucursal_mostrar_impresion"],ENT_QUOTES)));
			$mostrar_medida_impresion=mysqli_real_escape_string($con,(strip_tags($_POST["mostrar_medida_impresion"],ENT_QUOTES)));
			$mostrar_lote_impresion=mysqli_real_escape_string($con,(strip_tags($_POST["mostrar_lote_impresion"],ENT_QUOTES)));
			$mostrar_bodega_impresion=mysqli_real_escape_string($con,(strip_tags($_POST["mostrar_bodega_impresion"],ENT_QUOTES)));
			$mostrar_caducidad_impresion=mysqli_real_escape_string($con,(strip_tags($_POST["mostrar_caducidad_impresion"],ENT_QUOTES)));
			$consulta_configuracion=$configuracion_proformas->consulta_configuracion_proformas($id_confi, $con);
			if(isset($id_confi) && !empty($id_confi)){
			echo $configuracion_proformas->actualiza_configuracion_proformas($id_confi, $serie_sucursal, $consulta_configuracion['clientes'], $consulta_configuracion['productos'], $consulta_configuracion['medida'], $consulta_configuracion['lote'], $consulta_configuracion['bodega'], $consulta_configuracion['vencimiento'], $mostrar_lote_impresion, $mostrar_medida_impresion, $mostrar_bodega_impresion, $mostrar_caducidad_impresion, $consulta_configuracion['inventario'], $consulta_configuracion['calculo_salida'], $con);	
			}else{
			echo $configuracion_proformas->guarda_configuracion_proformas($serie_sucursal, '', '', '', '', '', '', $mostrar_lote_impresion, $mostrar_medida_impresion, $mostrar_bodega_impresion, $mostrar_caducidad_impresion, '','',  $con);
			} 
			}else {
			   echo "<script> $.notify('Error desconocido.','error')</script>";	
			}
}
	
//para guardar o editar la configuracion en base a que registra la salida del inventario
if (isset($_POST['guarda_salidas_inventario'])){
			if (empty($_POST['serie_sucursal_salidas_inventario'])) {
			   echo "<script> $.notify('Seleccione una sucursal.','error')</script>";
			}else if (empty($_POST['tipo_salida_inventario'])) {
				echo "<script> $.notify('Seleccione en base a que se calcula la salida del inventario.','error')</script>";
			} else if (!empty($_POST['serie_sucursal_salidas_inventario'])&& !empty($_POST['tipo_salida_inventario']) ){
			$id_confi=$_POST["id_conf_salidas_inventario"];
			$serie_sucursal=mysqli_real_escape_string($con,(strip_tags($_POST["serie_sucursal_salidas_inventario"],ENT_QUOTES)));
			$tipo_salida_inventario=mysqli_real_escape_string($con,(strip_tags($_POST["tipo_salida_inventario"],ENT_QUOTES)));
			$consulta_configuracion=$configuracion_proformas->consulta_configuracion_proformas($id_confi, $con);
			if(isset($id_confi) && !empty($id_confi)){
			echo $configuracion_proformas->actualiza_configuracion_proformas($id_confi, $serie_sucursal, $consulta_configuracion['clientes'], $consulta_configuracion['productos'], $consulta_configuracion['medida'], $consulta_configuracion['lote'], $consulta_configuracion['bodega'], $consulta_configuracion['vencimiento'], $consulta_configuracion['lote_impreso'], $consulta_configuracion['medida_impreso'], $consulta_configuracion['bodega_impreso'], $consulta_configuracion['vencimiento_impreso'], $consulta_configuracion['inventario'], $tipo_salida_inventario, $con);	
			}else{
			echo $configuracion_proformas->guarda_configuracion_proformas($serie_sucursal, '', '', '', '', '', '', '', '', '', '', '', $tipo_salida_inventario, $con);
			} 
			}else {
			   echo "<script> $.notify('Error desconocido.','error')</script>";	
			}
}
	
	

	
	
//para consultar cuando se seleccione una sucursal en cada una de las opciones de configuracion de facturacion
	if (isset($_POST['opcion_consulta'])){
		$opcion_consulta=$_POST['opcion_consulta'];
		if (isset($_POST['serie_sucursal'])){
		$serie_sucursal =$_POST['serie_sucursal'];	
		$sql = mysqli_query($con,"SELECT * FROM configuracion_proformas where ruc_empresa ='".$ruc_empresa."' and serie_sucursal ='".$serie_sucursal."';");
		$info_configuracion = mysqli_fetch_array($sql);
		$id_conf = $info_configuracion['id_confi'];
		$inventario = $info_configuracion['inventario'];
		$clientes = $info_configuracion['clientes'];
		$productos = $info_configuracion['productos'];
		$medida = $info_configuracion['medida'];
		$lote = $info_configuracion['lote'];
		$bodega = $info_configuracion['bodega'];
		$vencimiento = $info_configuracion['vencimiento'];
		$lote_impreso = $info_configuracion['lote_impreso'];
		$medida_impreso = $info_configuracion['medida_impreso'];
		$bodega_impreso = $info_configuracion['bodega_impreso'];
		$vencimiento_impreso = $info_configuracion['vencimiento_impreso'];
		$calculo_salida = $info_configuracion['calculo_salida'];

			switch ($opcion_consulta) {
				case "aplica_inventario":
					?>
					<input type="hidden" value="<?php echo $id_conf;?>" id="id_configuracion">
					<input type="hidden" value="<?php echo $inventario;?>" id="inventario">
					<?php
					break;
				case "productos_clientes":
					?>
					<input type="hidden" value="<?php echo $id_conf;?>" id="id_configuracion">
					<input type="hidden" value="<?php echo $productos;?>" id="productos">
					<input type="hidden" value="<?php echo $clientes;?>" id="clientes">
					<?php
					break;
				case "opciones_inventario":
					?>
					<input type="hidden" value="<?php echo $id_conf;?>" id="id_configuracion">
					<input type="hidden" value="<?php echo $medida;?>" id="medida">
					<input type="hidden" value="<?php echo $lote;?>" id="lote">
					<input type="hidden" value="<?php echo $bodega;?>" id="bodega">
					<input type="hidden" value="<?php echo $vencimiento;?>" id="vencimiento">
					<?php
					break;
				case "impresion_etiquetas":
					?>
					<input type="hidden" value="<?php echo $id_conf;?>" id="id_configuracion">
					<input type="hidden" value="<?php echo $medida_impreso;?>" id="imprime_medida">
					<input type="hidden" value="<?php echo $lote_impreso;?>" id="imprime_lote">
					<input type="hidden" value="<?php echo $bodega_impreso;?>" id="imprime_bodega">
					<input type="hidden" value="<?php echo $vencimiento_impreso;?>" id="imprime_vencimiento">
					<?php
					break;
				case "calculo_salida_inventario":
					?>
					<input type="hidden" value="<?php echo $id_conf;?>" id="id_configuracion">
					<input type="hidden" value="<?php echo $calculo_salida;?>" id="calculo_salida">
					<?php
					break;
					}		
		}
	}
	

	//clase para guardar editar y seleccionar en la base de datos la info
class guarda_info_conf_proforma{
	///para guardar en la tabla configuracion_proformas
		public function guarda_configuracion_proformas($serie_sucursal, $clientes, $productos, $medida, $lote, $bodega, $vencimiento, $lote_impreso, $medida_impreso, $bodega_impreso, $vencimiento_impreso, $inventario,$calculo_salida, $con){
		//session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];   
 		$guarda_configuracion = mysqli_query($con, "INSERT INTO configuracion_proformas VALUES (null, '".$ruc_empresa."', '".$serie_sucursal."', '".$clientes."', '".$productos."', '".$medida."', '".$lote."', '".$bodega."', '".$vencimiento."', '".$lote_impreso."', '".$medida_impreso."', '".$bodega_impreso."', '".$vencimiento_impreso."', '".$inventario."','".$calculo_salida."')");
		if ($guarda_configuracion){
			return "<script> $.notify('Configurado.','success')</script>";
			} else{
				return "<script> $.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
			}
		}
		
		///para actualizar en la tabla configuracion_proformas
		public function actualiza_configuracion_proformas($id_confi, $serie_sucursal, $clientes, $productos, $medida, $lote, $bodega, $vencimiento, $lote_impreso, $medida_impreso, $bodega_impreso, $vencimiento_impreso, $inventario, $calculo_salida, $con){
		$actualiza_configuracion = mysqli_query($con, "UPDATE configuracion_proformas SET serie_sucursal='".$serie_sucursal."', clientes='".$clientes."', productos='".$productos."', medida='".$medida."', lote='".$lote."', bodega='".$bodega."', vencimiento='".$vencimiento."', lote_impreso='".$lote_impreso."', medida_impreso='".$medida_impreso."', bodega_impreso='".$bodega_impreso."', vencimiento_impreso='".$vencimiento_impreso."', inventario='".$inventario."', calculo_salida='".$calculo_salida."' WHERE id_confi='".$id_confi."' ");
		
		if ($actualiza_configuracion){
					return "<script> $.notify('Actualizado.','success')</script>";
				} else{
					return "<script> $.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
				}
		}
		
		
		///para consultar datos de la tabla
		public function consulta_configuracion_proformas($id_confi, $con){	
		$consulta_configuracion = mysqli_query($con, "SELECT * FROM configuracion_proformas WHERE id_confi='".$id_confi."' ");
		return mysqli_fetch_array( $consulta_configuracion);
		}
	
}
		
?>



