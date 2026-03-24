<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$fecha_registro=date("Y-m-d H:i:s");

//PARA BUSCAR LOS ALUMNOS
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'ajax'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $ordenado = mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
		 $por = mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
		 $aColumns = array('cedula_alumno', 'nombres_alumno','apellidos_alumno','nombre','ruc');//Columnas de busqueda
		 $sTable = "alumnos as al LEFT JOIN clientes as cl ON al.id_cliente = cl.id";
		$sWhere = "WHERE al.ruc_empresa ='".  $ruc_empresa ." '  " ;
		if ( $_GET['q'] != "" )
		{
			$sWhere = "WHERE (al.ruc_empresa ='".  $ruc_empresa ." ' AND ";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' AND al.ruc_empresa = '".  $ruc_empresa ."' OR ";
			}
			
			$sWhere = substr_replace( $sWhere, "AND al.ruc_empresa = '".  $ruc_empresa ."' ", -3 );
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
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '../alumnos.php';
		//main query to fetch the data
		$sql="SELECT al.id_alumno as id_alumno, al.tipo_id as tipo_id_alumno, al.cedula_alumno as cedula_alumno, nombres_alumno as nombres_alumno, al.apellidos_alumno as apellidos_alumno,
		al.fecha_nacimiento_alumno as fecha_nacimiento_alumno, al.fecha_ingreso_alumno as fecha_ingreso_alumno, al.sexo_alumno as sexo_alumno, al.horario_alumno as horario_alumno,
		al.sucursal_alumno as sucursal_alumno, al.paralelo_alumno as paralelo_alumno, al.estado_alumno as estado_alumno, al.serie_facturar as serie_facturar, al.id_cliente as id_cliente  FROM $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
			<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr  class="info">
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cedula_alumno");'>Ced/pass</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombres_alumno");'>Nombres</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("apellidos_alumno");'>Apellidos</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_nacimiento_alumno");'>Nacimiento</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_ingreso_alumno");'>Ingreso</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("sexo_alumno");'>Sexo</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("horario_alumno");'>Horario</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("sucursal_alumno");'>Campus</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("paralelo_alumno");'>Nivel</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("estado_alumno");'>Estado</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("id_cliente");'>Facturar a</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" >Detalle</button></th>
				
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_reg=$row['id_alumno'];
						$tipo_id=$row['tipo_id_alumno'];
						$cedula_alumno=$row['cedula_alumno'];
						$nombres_alumno=$row['nombres_alumno'];
						$apellidos_alumno=$row['apellidos_alumno'];
						$fecha_nacimiento=$row['fecha_nacimiento_alumno'];
						$fecha_ingreso=$row['fecha_ingreso_alumno'];
						$sexo_alumno=$row['sexo_alumno'];
						$horario_alumno=$row['horario_alumno'];
						$sucursal_alumno=$row['sucursal_alumno'];
						$nivel_alumno=$row['paralelo_alumno'];
						$estado_alumno=$row['estado_alumno'];
						$serie_facturar=$row['serie_facturar'];
						$id_cliente=$row['id_cliente'];
						//buscar datos del cliente
						$datos_cliente = mysqli_query($con, "SELECT tipo_id as tipo, ruc as ruc_cliente, nombre as nombre_cliente, telefono as telefono_cliente, direccion as direccion_cliente, plazo as plazo, email as email  FROM clientes WHERE id= '".$id_cliente."'");
						$row_cliente= mysqli_fetch_array($datos_cliente);
						$tipo_id_cliente = $row_cliente['tipo'];
						$ruc_cliente = $row_cliente['ruc_cliente'];
						$nombre_cliente = $row_cliente['nombre_cliente'];
						$telefono_cliente = $row_cliente['telefono_cliente'];
						$direccion_cliente = $row_cliente['direccion_cliente'];
						$plazo_cliente = $row_cliente['plazo'];
						$email_cliente = $row_cliente['email'];
													
					?>
					<input type="hidden" value="<?php echo $id_reg;?>" id="id_alumno<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo intval($tipo_id);?>" id="tipo_id_alumno<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo $cedula_alumno;?>" id="cedula_alumno<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo $nombres_alumno;?>" id="nombres_alumno<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo $apellidos_alumno;?>" id="apellidos_alumno<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo date("d-m-Y",strtotime($fecha_nacimiento));?>" id="fecha_nacimiento_alumno<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo date("d-m-Y",strtotime($fecha_ingreso));?>" id="fecha_ingreso_alumno<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo $sexo_alumno;?>" id="sexo_alumno<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo $horario_alumno;?>" id="horario_alumno<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo $sucursal_alumno;?>" id="sucursal_alumno<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo $nivel_alumno;?>" id="nivel_alumno<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo $estado_alumno;?>" id="estado_alumno<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo $serie_facturar;?>" id="serie_facturar<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo $id_cliente;?>" id="id_cliente<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo $tipo_id_cliente;?>" id="tipo_id<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo $ruc_cliente;?>" id="ruc_cliente<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo $nombre_cliente;?>" id="nombre_cliente<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo $telefono_cliente;?>" id="telefono_cliente<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo $direccion_cliente;?>" id="direccion_cliente<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo $plazo_cliente;?>" id="plazo_cliente<?php echo $id_reg;?>">
					<input type="hidden" value="<?php echo $email_cliente;?>" id="email_cliente<?php echo $id_reg;?>">
					<tr>
						<td><?php echo $cedula_alumno; ?></td>
						<td><a href="#" title='Editar datos alumno' onclick="obtener_datos_editar_alumnos('<?php echo $id_reg; ?>')" data-toggle="modal" data-target="#editarAlumno"><?php echo strtoupper ($nombres_alumno); ?> </a></td>
						<td><a href="#" title='Editar datos alumno' onclick="obtener_datos_editar_alumnos('<?php echo $id_reg; ?>')" data-toggle="modal" data-target="#editarAlumno"><?php echo strtoupper ($apellidos_alumno); ?> </a></td>
						<td><?php echo date("d-m-Y",strtotime($fecha_nacimiento)); ?></td>
						<td><?php echo date("d-m-Y",strtotime($fecha_ingreso)); ?></td>
						<td><?php echo $sexo_alumno; ?></td>
						<?php
						//buscar el horario
						$busca_datos_horario = "SELECT * FROM horarios_alumnos WHERE id_horario = '$horario_alumno' ";
						$result = $con->query($busca_datos_horario);
						$datos_horario = mysqli_fetch_array($result);
						$horario_alumno_detalle =$datos_horario['nombre_horario'];
						?>
						
						<td><?php echo $horario_alumno_detalle; ?></td>
						<?php
						//buscar el campus
						$busca_datos_campus = "SELECT * FROM campus_alumnos WHERE id_campus = '$sucursal_alumno' ";
						$result = $con->query($busca_datos_campus);
						$datos_campus = mysqli_fetch_array($result);
						$campus_alumno =$datos_campus['nombre_campus'];
						?>
						
						<td><?php echo strtoupper ($campus_alumno); ?></td>
						<?php
						//buscar el nivel del estudiante
						$busca_datos_nivel = "SELECT * FROM nivel_alumnos WHERE id_nivel = '$nivel_alumno' ";
						$result = $con->query($busca_datos_nivel);
						$datos_nivel = mysqli_fetch_array($result);
						$nombre_nivel =$datos_nivel['nombre_nivel'];
						?>
						<td><?php echo strtoupper ($nombre_nivel); ?></td>
						
						<?php
						if ($estado_alumno=='1'){
							$estado_alumno_final ="ACTIVO";
						}else{
							$estado_alumno_final ="PASIVO";
						}
						?>
						<td><?php echo $estado_alumno_final; ?></td>

					<td>
					<?php
						//buscar el cliente
						$busca_datos_cliente = "SELECT * FROM clientes WHERE id = '$id_cliente' ";
						$result = $con->query($busca_datos_cliente);
						$datos_cliente = mysqli_fetch_array($result);
						$nombre_cliente =$datos_cliente['nombre'];
						if ($nombre_cliente==""){
						$nombre_cliente_alumno=	"<span class='label label-danger'>SIN DATOS</span>";
						}else{
						$nombre_cliente_alumno=strtoupper($nombre_cliente);
						}
						?>
					<a href="#"  title='Editar datos facturación' onclick="agrega_datos_facturacion('<?php echo $id_reg; ?>')" data-toggle="modal" data-target="#agregarClienteAlumno"><?php echo $nombre_cliente_alumno; ?></a>
					</td>
					<?php
					if ($estado_alumno=='1'){
					?>
					<td><a href="#" class='btn btn-info btn-md' title='Detalle factura' onclick="detalle_factura_alumno('<?php echo $id_reg; ?>')" data-toggle="modal" data-target="#detalleFacturaAlumno"><i class="glyphicon glyphicon-list"></i> </a></td>
					<?php
					}else{
					?>
					<td><a href="#" class='btn btn-danger btn-md' title='Estado pasivo' ><i class="glyphicon glyphicon-list"></i> </a></td>
					<?php
					}
					?>
					</tr>
				<?php
				}
				?>
				<tr>
					<td colspan="14" ><span class="pull-right">
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