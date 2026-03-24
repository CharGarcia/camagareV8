<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	
	 if($action == 'eliminar_cuentas_contables'){
        $id_cuenta=intval($_GET['id_cuenta']);
		
			$consulta_cuentas = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE id_cuenta ='".$id_cuenta."' ");
			$row_codigo_cuenta=mysqli_fetch_array($consulta_cuentas);
			$codigo_cuenta = $row_codigo_cuenta['codigo_cuenta'];
			$nivel_entrada = $row_codigo_cuenta['nivel_cuenta'];
			$siguiente_nivel = $nivel_entrada+1;
			//buscar nivel superior al seleccionado
			
						switch ($nivel_entrada) {
					case "1":
						$mid_inicial_entrada="1";
						$mid_largo_entrada="1";
						$codigo_final = substr($codigo_cuenta,0,1);
						break;
					case "2":
						$mid_inicial_entrada="1";
						$mid_largo_entrada="3";
						$codigo_final = substr($codigo_cuenta,0,3);
						break;
					case "3":
						$mid_inicial_entrada="1";
						$mid_largo_entrada="6";
						$codigo_final = substr($codigo_cuenta,0,6);
						break;
					case "4":
						$mid_inicial_entrada="1";
						$mid_largo_entrada="9";
						$codigo_final = substr($codigo_cuenta,0,9);
						break;
					case "5":
						$mid_inicial_entrada="1";
						$mid_largo_entrada="13";
						$codigo_final = substr($codigo_cuenta,0,13);
						break;
						}
			
			$buscar_cuentas = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE mid(ruc_empresa,1,10) = '".substr($ruc_empresa,0,10)."' and nivel_cuenta='".$siguiente_nivel."' and mid(codigo_cuenta, $mid_inicial_entrada, $mid_largo_entrada) = '".$codigo_final."'");		
			$total_cuentas=mysqli_num_rows($buscar_cuentas);
			
			if ($total_cuentas==0){
				//aqui comprobar si hay registros con esa cuenta y empresa
				$buscar_cuentas_en_uso = mysqli_query($con, "SELECT * FROM detalle_diario_contable WHERE id_cuenta='".$id_cuenta."'");		
				$total_cuentas_en_uso = mysqli_num_rows($buscar_cuentas_en_uso);
					if ($total_cuentas_en_uso==0){
					$deleteuno=mysqli_query($con,"DELETE FROM plan_cuentas WHERE id_cuenta='".$id_cuenta."'");
					echo "<script>$.notify('Cuenta eliminada.','success')</script>";
					}else{
						echo "<script>$.notify('No es posible eliminar la cuenta, exiten registros en uso.','error')</script>";
					}
				}else{
					echo "<script>$.notify('No es posible eliminar la cuenta, exiten registros de cuentas con nivel superior al seleccionado.','error')</script>";
				}
	 }

		
	if($action == 'cuentas_contables'){
		 $q = $_REQUEST['q'];
		 $ordenado = $_GET['ordenado'];
		 $por = $_GET['por'];
		 
		 $aColumns = array('nombre_cuenta','codigo_cuenta','codigo_sri','codigo_supercias');//Columnas de busqueda
		 $sTable = "plan_cuentas";
		 $sWhere = "WHERE ruc_empresa = '".$ruc_empresa."'";
		 $text_buscar = explode(' ',$q);
		$like="";
		for ( $i=0 ; $i<count($text_buscar) ; $i++ )
		{
			$like .= "%".$text_buscar[$i];
		}
		if ( $_GET['q'] != "" )
		{
			$sWhere = "WHERE ( ruc_empresa = '".$ruc_empresa."' AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$like."%' AND ruc_empresa = '".$ruc_empresa."' OR ";
			}
			$sWhere = substr_replace( $sWhere, "AND ruc_empresa = '".$ruc_empresa."' ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by $ordenado $por";
		
		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 20; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '../plan_de_cuentas.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			
			?>
			<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table">
				<tr  class="info">
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_cuenta");'>Código</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_cuenta");'>Nombre</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nivel_cuenta");'>Nivel</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_sri");'>Código SRI</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_supercias");'>Código Supercias</button></th>
					<th>Agregar</th>
					<th class='text-right'>Opciones</th>				
				</tr>
				<?php
				$espacio="---";
				while ($row=mysqli_fetch_array($query)){
						$id_cuenta=$row['id_cuenta'];
						$nombre_cuenta=$row['nombre_cuenta'];
						$codigo_cuenta=$row['codigo_cuenta'];
						$codigo_sri=$row['codigo_sri'];
						$nivel_cuenta=$row['nivel_cuenta'];
						$codigo_supercias=$row['codigo_supercias'];
					?>
					<input type="hidden" value="<?php echo $nombre_cuenta;?>" id="nombre_cuenta<?php echo $id_cuenta;?>">
					<input type="hidden" value="<?php echo $codigo_sri;?>" id="codigo_sri<?php echo $id_cuenta;?>">
					<input type="hidden" value="<?php echo $codigo_supercias;?>" id="codigo_supercias<?php echo $id_cuenta;?>">
					<input type="hidden" value="<?php echo $nivel_cuenta;?>" id="nivel_cuenta<?php echo $id_cuenta;?>">
					<input type="hidden" value="<?php echo $codigo_cuenta;?>" id="codigo_cuenta<?php echo $id_cuenta;?>">
					<input type="hidden" value="<?php echo $page;?>" id="pagina">
					<tr>
						<td><?php echo $codigo_cuenta; ?></td>						
						<?php
						if ($nivel_cuenta=='1'){
						?>
						<td><?php echo $nombre_cuenta; ?></td>
						<?php
						}
						if ($nivel_cuenta=='2'){
						?>
						<td><?php echo "&nbsp"."&nbsp"."&nbsp".$nombre_cuenta; ?></td>
						<?php
						}
						if ($nivel_cuenta=='3'){
						?>
						<td><?php echo "&nbsp"."&nbsp"."&nbsp"."&nbsp"."&nbsp"."&nbsp".$nombre_cuenta; ?></td>
						<?php
						}
						if ($nivel_cuenta=='4'){
						?>
						<td><?php echo "&nbsp"."&nbsp"."&nbsp"."&nbsp"."&nbsp"."&nbsp"."&nbsp"."&nbsp"."&nbsp".$nombre_cuenta; ?></td>
						<?php
						}
						if ($nivel_cuenta=='5'){
						?>
						<td><?php echo "&nbsp"."&nbsp"."&nbsp"."&nbsp"."&nbsp"."&nbsp"."&nbsp"."&nbsp"."&nbsp"."&nbsp"."&nbsp"."&nbsp".ucwords($nombre_cuenta); ?></td>
						<?php
						}
						?>
						<td><?php echo $nivel_cuenta; ?></td>
						<td><?php echo $codigo_sri;?></td>
						<td><?php echo $codigo_supercias;?></td>
					<td >
					<?php
					if ($nivel_cuenta <='4'){
					?>
					<a href="#" class='btn btn-info btn-xs' title='Agregar nueva cuenta' onclick="mostrar_datos('<?php echo $id_cuenta;?>');" data-toggle="modal" data-target="#NuevaCuenta"><i class="glyphicon glyphicon-plus"></i>Agregar cuenta</a> 
					<?php
					}
					?>
					</td>
					<td ><span class="pull-right">
					<a href="#" class='btn btn-info btn-xs' title='Editar cuenta' onclick="obtener_datos('<?php echo $id_cuenta;?>');" data-toggle="modal" data-target="#EditarCuentaContable"><i class="glyphicon glyphicon-edit"></i></a> 
					<a href="#" class='btn btn-danger btn-xs' title='Eliminar cuenta' onclick="eliminar_cuenta_contable('<?php echo $id_cuenta;?>');"><i class="glyphicon glyphicon-trash"></i></a> 	
					</tr>
					<?php
				}
				?>
				<tr>
					<td colspan="8"><span class="pull-right">
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
	
	
	if (isset($errors)){
			
			?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Error!</strong> 
					<?php
						foreach ($errors as $error) {
								echo $error;
							}
						?>
			</div>
			<?php
			}
			if (isset($messages)){
				
				?>
				<div class="alert alert-success" role="alert">
						<button type="button" class="close" data-dismiss="alert">&times;</button>
						<strong>¡Bien hecho!</strong>
						<?php
							foreach ($messages as $message) {
									echo $message;
								}
							?>
				</div>
				<?php
			}
?>