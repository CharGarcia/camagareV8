<?php
	/* Connect To Database*/
	require_once("../conexiones/conectalogin.php");
    require_once("../ajax/pagination.php"); //include pagination file
    require_once("../helpers/helpers.php"); //include pagination file
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
    $id_usuario = $_SESSION['id_usuario'];
    ini_set('date.timezone', 'America/Guayaquil');
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';


	if ($action == 'actualiza_todos_los_costos') {
		$delete = mysqli_query($con, "DELETE co FROM costos_productos AS co JOIN productos_servicios AS pro ON pro.id = co.id_producto WHERE pro.ruc_empresa = '".$ruc_empresa."' and pro.tipo_produccion='01'");
		$query_actualiza_costos = mysqli_query($con, "INSERT INTO costos_productos (id_costo , id_producto, costo) 
		SELECT null, pro.id as id_producto, AVG(inv.costo_unitario) as costo_promedio FROM inventarios as inv INNER JOIN productos_servicios AS pro
		ON pro.id=inv.id_producto WHERE pro.ruc_empresa ='". $ruc_empresa ."' and inv.operacion ='ENTRADA' and inv.costo_unitario >0 
		and pro.tipo_produccion='01' GROUP BY inv.id_producto;");

	if ($query_actualiza_costos){		
		echo "<script>
		$.notify('Costos actualizados','info');
		</script>";
		}else{
			echo "<script>
		$.notify('Vuelva a intentar','error');
		</script>";
		}
	}

	//para actualizar el costo individual
if ($action == 'actualiza_costo_directo') {
	$costo_producto = $_POST['costo_producto'];
	$id_producto = $_POST['id_producto'];
	
	$sql_buscar = mysqli_query($con, "SELECT count(id_costo) as numrows FROM costos_productos WHERE id_producto='".$id_producto."'");
	$row_buscar = mysqli_fetch_array($sql_buscar);
	$resultado_buscar = $row_buscar['numrows'];
	$fecha_registro=date("Y-m-d H:i:s");

	if($resultado_buscar>0){
	$update = mysqli_query($con, "UPDATE costos_productos SET costo='" . $costo_producto . "' WHERE id_producto='" . $id_producto . "'");
	echo "<script>
	$.notify('Actualizado','info');
	</script>";
	}else{
		$query_guarda_costo = mysqli_query($con, "INSERT INTO costos_productos VALUES (null, '" . $id_producto . "', '" . $costo_producto . "', '".$fecha_registro."') ");
		if ($query_guarda_costo){		
		echo "<script>
		$.notify('Agregado','info');
		</script>";
		}else{
			echo "<script>
		$.notify('No se guarda','error');
		</script>";
		}
	}
}

//para buscar un producto
	if($action == 'buscar_costos_productos'){	
		$condicion_ruc_empresa=	"ruc_empresa = '". $ruc_empresa ."'";
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $ordenado = mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
		 $por = mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
		 $aColumns = array('codigo_producto', 'codigo_auxiliar', 'nombre_producto');//Columnas de busqueda
		 $sTable = "productos_servicios as pro_ser LEFT JOIN costos_productos as cos ON cos.id_producto=pro_ser.id LEFT JOIN unidad_medida as uni_med ON uni_med.id_medida=pro_ser.id_unidad_medida ";
		
		$sWhere = "WHERE $condicion_ruc_empresa " ;


        $text_buscar = explode(' ',$q);
        $like="";
        for ( $i=0 ; $i<count($text_buscar) ; $i++ )
        {
            $like .= "%".$text_buscar[$i];
        }

		if ( $_GET['q'] != "" )
		{
			$sWhere = "WHERE ($condicion_ruc_empresa AND ";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$like."%' AND $condicion_ruc_empresa  OR ";
			}
			
			$sWhere = substr_replace( $sWhere, "AND $condicion_ruc_empresa ", -3 );
			$sWhere .= ')';
		}
		
		$sWhere.=" order by $ordenado $por";	
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 20; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '../costos_productos.php';
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
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_producto");'>Código</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_auxiliar");'>Auxiliar</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_producto");'>Producto</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("tipo_produccion");'>Tipo</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("tarifa_iva");'>Tarifa</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("id_unidad_medida");'>Medida</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" >Marca</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("costo");'>Costo</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("precio_producto");'>Precio</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("costo");'>Utilidad</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("costo");'>Margen</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("precio_producto");'>P.V.P</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" >Status</button></th>
					<input type="hidden" value="<?php echo $page; ?>" id="pagina">
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_producto=$row['id'];
						$codigo_producto=$row['codigo_producto'];
						$codigo_auxiliar=$row['codigo_auxiliar'];
						$nombre_producto=$row['nombre_producto'];
						$tipo_produccion=$row['tipo_produccion'];
						$tarifa_iva=$row['tarifa_iva'];
						$tarifa_ice=$row['tarifa_ice'];
						$tarifa_botellas=$row['tarifa_botellas'];
						$precio_producto=$row['precio_producto'];
						$id_unidad_medida=$row['id_unidad_medida'];
						$nombre_unidad_medida = $row['nombre_medida'];
						$id_tipo_medida = $row['id_tipo_medida'];
						$status = $row['status'];
						$costo = $row['costo'];
						$utilidad = number_format($precio_producto - $costo,2);
						$utilidad_final= $utilidad >=0 ?"<span class='label label-success'>".$utilidad."</span>":"<span class='label label-danger'>".$utilidad."</span>";
						//$id_costo = $row['id_costo'];
						
						//para buscar la marca
						$sql_marca=mysqli_query($con,"SELECT mar_pro.id_marca as marca, mar.nombre_marca as nombre_marca FROM marca as mar INNER JOIN marca_producto as mar_pro ON mar.id_marca=mar_pro.id_marca WHERE mar_pro.id_producto = '".$id_producto."' and mar.ruc_empresa='".$ruc_empresa."'");
						$row_marca=mysqli_fetch_array($sql_marca);
						$id_marca = isset($row_marca['marca'])?$row_marca['marca']:"";
						$nombre_marca = isset($row_marca['nombre_marca'])?$row_marca['nombre_marca']:"";
						
					?>	
					<input type="hidden" id="costo_original<?php echo $id_producto; ?>" value="<?php echo $costo; ?>">
					<input type="hidden" id="id_producto_costo<?php echo $id_producto; ?>" value="<?php echo $id_producto; ?>">				
					<tr>
						<td><?php echo $codigo_producto; ?></td>
						<td><?php echo $codigo_auxiliar; ?></td>
						<td class="col-xs-3"><?php echo strtoupper($nombre_producto); ?></td>
						
						<?php
						$sql="SELECT * FROM tipo_produccion where codigo = '".$tipo_produccion."' ";
						$queri_tipo = mysqli_query($con, $sql);
						$fila_tipo=mysqli_fetch_array($queri_tipo);
						$tipo_nombre = $fila_tipo['nombre'];
						?>
						<td ><?php echo strtoupper($tipo_nombre); ?></td>
						<?php
						$sql="SELECT * FROM tarifa_iva where codigo = '".$tarifa_iva."' ";
						$queri_iva = mysqli_query($con, $sql);
						$fila_iva=mysqli_fetch_array($queri_iva);
						$nombre_tarifa_iva = $fila_iva['tarifa'];
						$porcentaje_iva = 1+($fila_iva['porcentaje_iva']/100);
						?>
						<td ><?php echo $nombre_tarifa_iva; ?></td>
						<td ><?php echo $nombre_unidad_medida; ?></td>
						<td ><?php echo $nombre_marca; ?></td>
						<td class="col-xs-1">
						<input type="text" style="text-align:right; height:25px;" class="form-control input-sm" title="Costo" id="costo_producto<?php echo $id_producto; ?>" onchange="actualiza_costo_producto('<?php echo $id_producto; ?>');" value="<?php echo number_format($costo,4,'.',''); ?>"></td>	
						<td><span class="pull-right"><?php echo number_format($precio_producto,4,'.','');?></span></td>
						<td><span class="pull-right"><?php echo $utilidad_final;?></span></td>
						<td><span class="pull-right"><?php echo number_format((($precio_producto-$costo)/$precio_producto)*100,2,'.','');?>%</span></td>
						<td><span class="pull-right"><?php echo number_format($precio_producto * $porcentaje_iva,2);?></span></td>

					<td ><?php echo $status==1?"<span class='label label-success'>Activo</span>":"<span class='label label-danger'>Inactivo</span>"; ?></td>
					</tr>
					<?php
				}
				?>
				<tr>
					<td colspan="13" ><span class="pull-right">
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