<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

if (isset($_GET['id_proveedor'])){
$id_proveedor = $_GET['id_proveedor'];
//para traer el tipo de proveedores
$sql_empresa_retenida=mysqli_query($con, "select pr.tipo_empresa as tipo_empresa, te.nombre as nombre, pr.razon_social as retenido from proveedores pr, tipo_empresa te WHERE pr.id_proveedor = $id_proveedor and pr.tipo_empresa=te.codigo");
$row_tipo_empresa_retenida=mysqli_fetch_array($sql_empresa_retenida);
$codigo_empresa_retenida = intval($row_tipo_empresa_retenida['tipo_empresa']);

//para traer el tipo de empresa que soy
$sql_mi_empresa=mysqli_query($con, "select em.tipo as tipo, te.nombre as nombre, em.nombre_comercial as miempresa from empresas em, tipo_empresa te WHERE em.ruc = '$ruc_empresa' and em.tipo=te.codigo");
$row_tipo_mi_empresa=mysqli_fetch_array($sql_mi_empresa);
$codigo_mi_empresa = intval($row_tipo_mi_empresa['tipo']);

	switch ($codigo_mi_empresa) {
	case "5":
		if ($codigo_empresa_retenida<5){
			$advertencia[]="Se debería retener RENTA e IVA. siempre que el contribuyente NO sea RIMPE NEGOCIO POPULAR.";
		}
		break;
	case "4":
		if ($codigo_empresa_retenida<=4 ){
			$advertencia[]="Se debería retener RENTA e IVA. siempre que el contribuyente NO sea RIMPE NEGOCIO POPULAR.";
		}
		break;
	case "3":
		if ($codigo_empresa_retenida==1){
			$advertencia[]="Se debería retener RENTA e IVA. siempre que el contribuyente NO sea RIMPE NEGOCIO POPULAR.";
		}
		break;
	case "2":
		if ($codigo_empresa_retenida<2){
			$advertencia[]="Se debería retener RENTA e IVA. siempre que el contribuyente NO sea RIMPE NEGOCIO POPULAR.";
		}
		break;
		}
		
		if (isset($advertencia)){
			?>
			<div class="alert alert-warning" role="alert" style ="padding: 4px; margin-bottom: 5px; margin-top: -5px;">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>¡Sugerencia! </strong>
					<?php
						foreach ($advertencia as $message) {
								echo $message;
							}
						?>
			</div>
			<?php
		}
		
}
?>