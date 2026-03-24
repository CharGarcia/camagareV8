<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];

//para eliminar un iten del ingreso temporal
if (isset($_GET['id'])){
$id_tmp=intval($_GET['id']);	
$delete=mysqli_query($con, "DELETE FROM formas_pagos_tmp WHERE id_tmp='".$id_tmp."'");
}

$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

//para agregar formas de pago en ingresos
		 
if($action == 'pago_ingresos'){
if (isset($_POST['forma_pago'])){$forma_pago = $_POST['forma_pago'];}
if (isset($_POST['valor'])){$valor_pago=$_POST['valor'];}
if (isset($_POST['detalle'])){$detalle=$_POST['detalle'];}
$cuenta_bancaria=$_POST['cuenta_bancaria'];
if (!empty($forma_pago) and !empty($valor_pago)){
	$sql_forma_pago=mysqli_query($con, "select * from formas_de_pago WHERE id_forma_pago=$forma_pago");
	$row_forma=mysqli_fetch_array($sql_forma_pago);
	$codigo_pago=$row_forma['codigo_pago'];
		if (($codigo_pago=="03" or $codigo_pago=="04" or $codigo_pago=="05") and ($cuenta_bancaria !="0")){
		$insert_tmp=mysqli_query($con, "INSERT INTO formas_pagos_tmp VALUES (null,$forma_pago,$cuenta_bancaria,$valor_pago,'$detalle',$id_usuario,'INGRESO')");
		}else if (($codigo_pago=="01" or $codigo_pago=="02" )){
		$insert_tmp=mysqli_query($con, "INSERT INTO formas_pagos_tmp VALUES (null,$forma_pago,'--',$valor_pago,'$detalle',$id_usuario,'INGRESO')");
		}else{
		echo "<script>alert('Seleccione cuenta bancaria.')</script>";
		echo "<script>window.close();</script>";	
		}
}
}

?>


<div class="panel panel-info">
<table class="table">
<tr class="info">
	<th>Forma pago</th>
	<th class='text-center'>Valor</th>
	<th>Cuenta Bancaria</th>
	<th>Detalle</th>
	<th>Eliminar</th>
</tr>
<?php
// PARA MOSTRAR LOS ITEMS DEL INGRESO
	$valor_cobro=0;
	$sql=mysqli_query($con, "select * from formas_pagos_tmp fpt where id_usuario = $id_usuario and tipo_documento='INGRESO' ");
	while ($row=mysqli_fetch_array($sql)){
	$id_tmp=$row["id_tmp"];
	$id_forma_pago=$row["id_forma_pago"];
	$id_cuenta=$row["id_cuenta"];
	$valor=number_format($row['valor_pago'],2,'.','');
	$valor_cobro+=$valor;
	$detalle=$row['detalle_pago'];

	// forma de pago
	$sql_forma_pago=mysqli_query($con, "select * from formas_de_pago where id_forma_pago = $id_forma_pago ");
	$row_forma_pago=mysqli_fetch_array($sql_forma_pago);
	$forma_pago=$row_forma_pago['nombre_pago'];
	
	//cuenta bancaria
	$sql_cuenta_bancaria=mysqli_query($con, "SELECT * FROM cuentas_bancarias cb, bancos_ecuador be where cb.ruc_empresa='$ruc_empresa' and cb.id_banco = be.id_bancos and cb.id_cuenta=$id_cuenta ");
	$row_cuenta_bancaria=mysqli_fetch_array($sql_cuenta_bancaria);
	$banco = strtoupper($row_cuenta_bancaria['nombre_banco']);
	$numero = strtoupper($row_cuenta_bancaria['numero_cuenta']);
	$id_tipo_cuenta = $row_cuenta_bancaria['id_tipo_cuenta'];
	
	switch ($id_tipo_cuenta){
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
	
	$detalle_cuenta = $banco ."-". $tipo_cuenta_pago ."-". $numero ;
		?>
		<tr>
			<td><?php echo $forma_pago;?></td>
			<td class='text-center'><?php echo $valor;?></td>
			<td><?php echo $detalle_cuenta;?></td>
			<td><?php echo $detalle;?></td>
			<td>
			<a href="#" class='btn btn-danger btn-xs' onclick="eliminar_forma_cobro('<?php echo $id_tmp; ?>')" title ="Eliminar item"><i class="glyphicon glyphicon-trash"></i></a></td>
		</tr>		

		<?php
}
?>

<tr class="info">
	<td class='text-right' >TOTAL COBRO: </td>
	<td class='text-center'><?php echo number_format($valor_cobro,2,'.','');?></td>
	<input type="hidden" id="suma_cobros" value="<?php echo number_format($valor_cobro ,2,'.','');?>">
	<script>
			var total_cobros = $("#suma_cobros").val();
			$("#pagos_ingreso").val(total_cobros);
	</script>
	<td colspan=4 ></td>
</tr>
</table>
</div>

