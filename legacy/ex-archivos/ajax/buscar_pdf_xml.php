<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	require_once("../helpers/helpers.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	$fecha_registro=date("Y-m-d H:i:s");

//PARA BUSCAR LAS FACTURAS
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'fa'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['fa'], ENT_QUOTES)));
		 $ordenado = mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
		 $por = mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
		 $aColumns = array('fecha_factura','secuencial_factura', 'serie_factura','nombre','ruc','estado_sri');//Columnas de busqueda
		 $sTable = "encabezado_factura as ef, clientes as cl";
		 $sWhere = "WHERE ef.id_cliente = cl.id and ef.ruc_empresa='".$ruc_empresa."' " ;
		if ( $_GET['fa'] != "" )
		{
			$sWhere = "WHERE (ef.id_cliente = cl.id and ef.ruc_empresa='".$ruc_empresa."' AND ";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' AND ef.id_cliente = cl.id and ef.ruc_empresa='".$ruc_empresa."' OR ";
			}
			
			$sWhere = substr_replace( $sWhere, "AND ef.id_cliente = cl.id and ef.ruc_empresa='".$ruc_empresa."' ", -3 );
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
		$reload = '../facturas.php';
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
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_factura");'>Fecha</button></th>
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre");'>Emitida a</button></th>
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("secuencial_factura");'>Número</button></th>														
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("estado_sri");'>Estado</button></th>		
				<th class='text-right'>Opciones</th>								
				<input type="hidden" value="<?php echo $page;?>" id="pagina">
				</tr>
				<?php

				while ($row=mysqli_fetch_array($query)){
						$id_encabezado_factura=$row['id_encabezado_factura'];
						$ruc_empresa=$row['ruc_empresa'];
						$fecha_factura=$row['fecha_factura'];
						$serie_factura=$row['serie_factura'];
						$secuencial_factura=$row['secuencial_factura'];
						$nombre_cliente_factura=$row['nombre'];
						$ruc_cliente=$row['ruc'];
						$estado_pago=$row['estado_pago'];
						$tipo_factura=$row['tipo_factura'];
						$estado_sri=$row['estado_sri'];
						$id_cliente=$row['id'];
					//para consultar el nombre de la empresa que emite la factura
					$busca_empresa = "SELECT * FROM empresas WHERE ruc = '".$ruc_empresa."' ";
					$result_empresa = $con->query($busca_empresa);
					$datos_empresa = mysqli_fetch_array($result_empresa);
					$nombre_empresa=$datos_empresa['nombre'];

					$numero_factura = $serie_factura . "-" . str_pad($secuencial_factura, 9, "000000000", STR_PAD_LEFT);
						
							
					//estado sri
					switch ($estado_sri) {
					case "PENDIENTE":
						$label_class_sri='label-warning';
						break;
					case "ANULADA":
						$label_class_sri='label-danger';
						break;
					case "NO APLICA":
						$label_class_sri='label-info';
						break;
					case "AUTORIZADO":
						$label_class_sri='label-success';
						break;
						}
					
					?>
					<input type="hidden" value="<?php echo $id_encabezado_factura;?>" id="id_documento<?php echo $id_encabezado_factura;?>">
					<input type="hidden" value="factura" id="documento<?php echo $id_encabezado_factura;?>">

					<tr>
						<td><?php echo date("d/m/Y", strtotime($fecha_factura)); ?></td>
						<td class='col-md-4'><?php echo strtoupper ($nombre_cliente_factura); ?></td>
						<td><?php echo $serie_factura; ?>-<?php echo str_pad($secuencial_factura,9,"000000000",STR_PAD_LEFT); ?></td>
						<td><span class="label <?php echo $label_class_sri;?>"><?php echo $estado_sri; ?></span></td>
					
					<td class='col-md-3'><span class="pull-right">
						<a href="#" class='btn btn-success btn-xs' onclick="generar_pdf_xml('<?php echo $id_encabezado_factura;?>');" title='Generar pdf xml'><i class="glyphicon glyphicon-cog"></i> Generar pdf xml</a>				
							<?php
						$status_servidor=status_servidor(); //esto le puse que venga de helpers cuando el servidor no quiere descargar las facturas
						if($status_servidor){
							?>
						<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_factura) ?>&tipo_documento=factura&tipo_archivo=pdf" class='btn btn-default btn-xs' title='Descargar' download target="_blank">Pdf <i class="glyphicon glyphicon-download"></i></i></a>
						<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_factura) ?>&tipo_documento=factura&tipo_archivo=xml" class='btn btn-default btn-xs' title='Descargar' download target="_blank">Xml <i class="glyphicon glyphicon-download"></i></i></a>
					<?php
						}else{
						?>
						<a href="http://64.225.69.65:8000/facturas_autorizadas/<?php echo $ruc_empresa ?>/<?php echo $ruc_cliente ?>/FAC<?php echo $numero_factura ?>.pdf" class='btn btn-default btn-xs' title='Descargar' target="_blank" download>Pdf</i> </a>
						<a href="http://64.225.69.65:8000/facturas_autorizadas/<?php echo $ruc_empresa ?>/<?php echo $ruc_cliente ?>/FAC<?php echo $numero_factura ?>.xml" class='btn btn-default btn-xs' title='Descargar' target="_blank" download>Xml</i> </a>
					<?php
						}
						?>
						</span></td>
					
					</tr>
				<?php
				}
				?>
				<tr>
					<td colspan="9" ><span class="pull-right">
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
	
	//para bUscar las retenciones
		if($action == 're'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['re'], ENT_QUOTES)));
		 $aColumns = array('fecha_emision','secuencial_retencion', 'serie_retencion','numero_comprobante','razon_social','nombre_comercial');//Columnas de busqueda
		 $sTable = "encabezado_retencion er, proveedores pr";
		 $sWhere = "WHERE er.id_proveedor = pr.id_proveedor and er.ruc_empresa='".$ruc_empresa."' " ;
		if ( $_GET['re'] != "" )
		{
			$sWhere = "WHERE (er.id_proveedor = pr.id_proveedor and er.ruc_empresa='".$ruc_empresa."' AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' AND er.id_proveedor = pr.id_proveedor and er.ruc_empresa='".$ruc_empresa."' OR ";
			}
			$sWhere = substr_replace( $sWhere, "AND er.id_proveedor = pr.id_proveedor and er.ruc_empresa='".$ruc_empresa."' ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by er.id_encabezado_retencion desc";
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
		$reload = '../retenciones_compras.php';
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
					<th>Fecha retención</th>
					<th>Emitida a</th>
					<th>Número retención</th>
					<th>Estado</th>
					<th class='text-right'>Opciones</th>
				</tr>
				<?php

				while ($row=mysqli_fetch_array($query)){
						$id_encabezado_retencion=$row['id_encabezado_retencion'];
						$fecha_retencion=$row['fecha_emision'];
						$fecha_documento=$row['fecha_documento'];
						$serie_retencion=$row['serie_retencion'];
						$secuencial_retencion=$row['secuencial_retencion'];
						$nombre_proveedor=$row['razon_social'];
						$ruc_proveedor=$row['ruc_proveedor'];
						$estado_sri=$row['estado_sri'];
						$ruc_empresa=$row['ruc_empresa'];

					//para consultar el nombre de la empresa que emite la factura
					$busca_empresa = "SELECT * FROM empresas WHERE ruc = '".$ruc_empresa."' ";
					$result_empresa = $con->query($busca_empresa);
					$datos_empresa = mysqli_fetch_array($result_empresa);
					$nombre_empresa=$datos_empresa['nombre'];

					$numero_ret = $serie_retencion . "-" . str_pad($secuencial_retencion, 9, "000000000", STR_PAD_LEFT);
									
					
					//estado sri
					switch ($estado_sri) {
					case "PENDIENTE":
						$label_class_sri='label-warning';
						break;
					case "ANULADA":
						$label_class_sri='label-danger';;
						break;
					case "NO APLICA":
						$label_class_sri='label-info';;
						break;
					case "AUTORIZADO":
						$label_class_sri='label-success';
						break;
						}
					
					?>
					<input type="hidden" value="<?php echo $id_encabezado_retencion;?>" id="id_documento<?php echo $id_encabezado_retencion;?>">
					<input type="hidden" value="retencion" id="documento<?php echo $id_encabezado_retencion;?>">
					<tr>
						<td><?php echo date("d/m/Y", strtotime($fecha_retencion)); ?></td>
    					<td class="col-xs-2"><?php echo strtoupper ($nombre_proveedor); ?></td>
						<td><?php echo $serie_retencion; ?>-<?php echo str_pad($secuencial_retencion,9,"000000000",STR_PAD_LEFT); ?></td>
						<td><span class="label <?php echo $label_class_sri;?>"><?php echo $estado_sri; ?></span></td>
				
					<td class='col-md-3'><span class="pull-right">
						<a href="#" class='btn btn-success btn-xs' onclick="generar_pdf_xml('<?php echo $id_encabezado_retencion;?>');" title='Generar pdf xml'><i class="glyphicon glyphicon-cog"></i> Generar pdf xml</a>				
						
					<?php
					$status_servidor=status_servidor(); //esto le puse que venga de helpers cuando el servidor no quiere descargar las facturas
					if($status_servidor){
						?>
						<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_retencion) ?>&tipo_documento=retencion&tipo_archivo=pdf" class='btn btn-default btn-xs' title='Descargar' download target="_blank">Pdf <i class="glyphicon glyphicon-download"></i></i></a>
						<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_retencion) ?>&tipo_documento=retencion&tipo_archivo=xml" class='btn btn-default btn-xs' title='Descargar' download target="_blank">Xml <i class="glyphicon glyphicon-download"></i></i></a>
					<?php
					}else{
						?>
						<a href="http://64.225.69.65:8000/retenciones_autorizadas/<?php echo $ruc_empresa ?>/<?php echo $ruc_proveedor ?>/CR<?php echo $numero_ret ?>.pdf" class='btn btn-default btn-xs' title='Descargar' target="_blank" download>Pdf</i> </a>
						<a href="http://64.225.69.65:8000/retenciones_autorizadas/<?php echo $ruc_empresa ?>/<?php echo $ruc_proveedor ?>/CR<?php echo $numero_ret ?>.xml" class='btn btn-default btn-xs' title='Descargar' target="_blank" download>Xml</i> </a>
					<?php
					}
					?>
					</span></td>
										
					</tr>
				<?php
				}
				?>
				<tr>
					<td colspan="10" ><span class="pull-right">
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
	
	//para buscar las notas de credito
if($action == 'nc'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['nc'], ENT_QUOTES)));
		 $ordenado = mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
		 $por = mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
		 $aColumns = array('fecha_nc','secuencial_nc', 'serie_nc','nombre','ruc','estado_sri');//Columnas de busqueda
		 $sTable = "encabezado_nc as enc, clientes as cl";
		 $sWhere = "WHERE enc.id_cliente = cl.id and enc.ruc_empresa='".$ruc_empresa."' " ;
		if ( $_GET['nc'] != "" )
		{
			$sWhere = "WHERE (enc.id_cliente = cl.id and enc.ruc_empresa='".$ruc_empresa."' AND ";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' AND enc.id_cliente = cl.id and enc.ruc_empresa='".$ruc_empresa."' OR ";
			}
			
			$sWhere = substr_replace( $sWhere, "AND enc.id_cliente = cl.id and enc.ruc_empresa='".$ruc_empresa."' ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by id_encabezado_nc desc";
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
		$reload = '../facturas.php';
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
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_factura");'>Fecha</button></th>
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre");'>Emitida a</button></th>
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("secuencial_factura");'>Número</button></th>														
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("estado_sri");'>Estado</button></th>		
				<th class='text-right'>Opciones</th>								
				<input type="hidden" value="<?php echo $page;?>" id="pagina">
				</tr>
				<?php

				while ($row=mysqli_fetch_array($query)){
						$id_encabezado_nc=$row['id_encabezado_nc'];
						$ruc_empresa=$row['ruc_empresa'];
						$fecha_nc=$row['fecha_nc'];
						$serie_nc=$row['serie_nc'];
						$secuencial_nc=$row['secuencial_nc'];
						$nombre_cliente_nc=$row['nombre'];
						$ruc_cliente=$row['ruc'];
						$estado_sri=$row['estado_sri'];
						$id_cliente=$row['id'];
					//para consultar el nombre de la empresa que emite la nota de credito
					$busca_empresa = "SELECT * FROM empresas WHERE ruc = '".$ruc_empresa."' ";
					$result_empresa = $con->query($busca_empresa);
					$datos_empresa = mysqli_fetch_array($result_empresa);
					$nombre_empresa=$datos_empresa['nombre'];

					$numero_nc = $serie_nc . "-" . str_pad($secuencial_nc, 9, "000000000", STR_PAD_LEFT);
						
					//estado sri
					switch ($estado_sri) {
					case "PENDIENTE":
						$label_class_sri='label-warning';
						break;
					case "ANULADA":
						$label_class_sri='label-danger';
						break;
					case "NO APLICA":
						$label_class_sri='label-info';
						break;
					case "AUTORIZADO":
						$label_class_sri='label-success';
						break;
						}
					
					?>
					<input type="hidden" value="<?php echo $id_encabezado_nc;?>" id="id_documento<?php echo $id_encabezado_nc;?>">
					<input type="hidden" value="nc" id="documento<?php echo $id_encabezado_nc;?>">

					<tr>
						<td><?php echo date("d/m/Y", strtotime($fecha_nc)); ?></td>
						<td class='col-md-4'><?php echo strtoupper ($nombre_cliente_nc); ?></td>
						<td><?php echo $serie_nc; ?>-<?php echo str_pad($secuencial_nc,9,"000000000",STR_PAD_LEFT); ?></td>
						<td><span class="label <?php echo $label_class_sri;?>"><?php echo $estado_sri; ?></span></td>
					
					<td class='col-md-3'><span class="pull-right">
						<a href="#" class='btn btn-success btn-xs' onclick="generar_pdf_xml('<?php echo $id_encabezado_nc;?>');" title='Generar pdf xml'><i class="glyphicon glyphicon-cog"></i> Generar pdf xml</a>				
						
				<?php
					$status_servidor=status_servidor(); //esto le puse que venga de helpers cuando el servidor no quiere descargar las facturas
					if($status_servidor){
						?>
						<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_nc) ?>&tipo_documento=nc&tipo_archivo=pdf" class='btn btn-default btn-xs' title='Descargar' download target="_blank">Pdf <i class="glyphicon glyphicon-download"></i></i></a>
						<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_nc) ?>&tipo_documento=nc&tipo_archivo=xml" class='btn btn-default btn-xs' title='Descargar' download target="_blank">Xml <i class="glyphicon glyphicon-download"></i></i></a>
				<?php
					}else{
						?>
						<a href="http://64.225.69.65:8000/nc_autorizadas/<?php echo $ruc_empresa ?>/<?php echo $ruc_cliente ?>/NC<?php echo $numero_nc ?>.pdf" class='btn btn-default btn-xs' title='Descargar' target="_blank" download>Pdf</i> </a>
						<a href="http://64.225.69.65:8000/nc_autorizadas/<?php echo $ruc_empresa ?>/<?php echo $ruc_cliente ?>/NC<?php echo $numero_nc ?>.xml" class='btn btn-default btn-xs' title='Descargar' target="_blank" download>Xml</i> </a>
						<?php
					}
					?>
						</span></td>
					
					</tr>
				<?php
				}
				?>
				<tr>
					<td colspan="9" ><span class="pull-right">
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

	
		//para buscar las guias de remision
if($action == 'gr'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['gr'], ENT_QUOTES)));
		 $ordenado = mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
		 $por = mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
		 $aColumns = array('fecha_gr','secuencial_gr', 'serie_gr','nombre','ruc','estado_sri');//Columnas de busqueda
		 $sTable = "encabezado_gr as egr, clientes as cl";
		 $sWhere = "WHERE egr.id_cliente = cl.id and egr.ruc_empresa='".$ruc_empresa."' " ;
		if ( $_GET['gr'] != "" )
		{
			$sWhere = "WHERE (egr.id_cliente = cl.id and egr.ruc_empresa='".$ruc_empresa."' AND ";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' AND egr.id_cliente = cl.id and egr.ruc_empresa='".$ruc_empresa."' OR ";
			}
			
			$sWhere = substr_replace( $sWhere, "AND egr.id_cliente = cl.id and egr.ruc_empresa='".$ruc_empresa."' ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by id_encabezado_gr desc";
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
		$reload = '../facturas.php';
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
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_factura");'>Fecha</button></th>
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre");'>Emitida a</button></th>
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("secuencial_factura");'>Número</button></th>														
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("estado_sri");'>Estado</button></th>		
				<th class='text-right'>Opciones</th>								
				<input type="hidden" value="<?php echo $page;?>" id="pagina">
				</tr>
				<?php

				while ($row=mysqli_fetch_array($query)){
						$id_encabezado_gr=$row['id_encabezado_gr'];
						$ruc_empresa=$row['ruc_empresa'];
						$fecha_gr=$row['fecha_gr'];
						$serie_gr=$row['serie_gr'];
						$secuencial_gr=$row['secuencial_gr'];
						$nombre_cliente_gr=$row['nombre'];
						$ruc_cliente=$row['ruc'];
						$estado_sri=$row['estado_sri'];
						$id_cliente=$row['id'];
						$id_transportista=$row['id_transportista'];
					//para consultar el nombre de la empresa que emite la nota de credito
					$busca_empresa = "SELECT * FROM empresas WHERE ruc = '".$ruc_empresa."' ";
					$result_empresa = $con->query($busca_empresa);
					$datos_empresa = mysqli_fetch_array($result_empresa);
					$nombre_empresa=$datos_empresa['nombre'];

					$busca_datos_transportista = mysqli_query($con,"SELECT nombre as nombre_transportista, ruc as ruc_transportista FROM clientes WHERE id = '".$id_transportista."' ");
					$resultado_transportista=mysqli_fetch_array($busca_datos_transportista);
					$nombre_transportista_gr=$resultado_transportista['nombre_transportista'];
					$ruc_transportista=$resultado_transportista['ruc_transportista'];

					$numero_gr = $serie_gr . "-" . str_pad($secuencial_gr, 9, "000000000", STR_PAD_LEFT);
						
					//estado sri
					switch ($estado_sri) {
					case "PENDIENTE":
						$label_class_sri='label-warning';
						break;
					case "ANULADA":
						$label_class_sri='label-danger';
						break;
					case "NO APLICA":
						$label_class_sri='label-info';
						break;
					case "AUTORIZADO":
						$label_class_sri='label-success';
						break;
						}
					
					?>
					<input type="hidden" value="<?php echo $id_encabezado_gr;?>" id="id_documento<?php echo $id_encabezado_gr;?>">
					<input type="hidden" value="gr" id="documento<?php echo $id_encabezado_gr;?>">

					<tr>
						<td><?php echo date("d/m/Y", strtotime($fecha_gr)); ?></td>
						<td class='col-md-4'><?php echo strtoupper ($nombre_cliente_gr); ?></td>
						<td><?php echo $serie_gr; ?>-<?php echo str_pad($secuencial_gr,9,"000000000",STR_PAD_LEFT); ?></td>
						<td><span class="label <?php echo $label_class_sri;?>"><?php echo $estado_sri; ?></span></td>
					
					<td class='col-md-3'><span class="pull-right">
						<a href="#" class='btn btn-success btn-xs' onclick="generar_pdf_xml('<?php echo $id_encabezado_gr;?>');" title='Generar pdf xml'><i class="glyphicon glyphicon-cog"></i> Generar pdf xml</a>				
						
						<?php
						$status_servidor=status_servidor(); //esto le puse que venga de helpers cuando el servidor no quiere descargar las facturas
						if($status_servidor){
							?>
						<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_gr) ?>&tipo_documento=gr&tipo_archivo=pdf" class='btn btn-default btn-xs' title='Descargar' download target="_blank">Pdf <i class="glyphicon glyphicon-download"></i></i></a>
						<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_gr) ?>&tipo_documento=gr&tipo_archivo=xml" class='btn btn-default btn-xs' title='Descargar' download target="_blank">Xml <i class="glyphicon glyphicon-download"></i></i></a>
						<?php
						}else{
							?>
						<a href="http://64.225.69.65:8000/guias_autorizadas/<?php echo $ruc_empresa ?>/<?php echo $ruc_transportista ?>/GR<?php echo $numero_gr ?>.pdf" class='btn btn-default btn-xs' title='Descargar' target="_blank" download>Pdf</i> </a>
						<a href="http://64.225.69.65:8000/guias_autorizadas/<?php echo $ruc_empresa ?>/<?php echo $ruc_transportista ?>/GR<?php echo $numero_gr ?>.xml" class='btn btn-default btn-xs' title='Descargar' target="_blank" download>Xml</i> </a>
					<?php
						}
						?>
						</span></td>
					
					</tr>
				<?php
				}
				?>
				<tr>
					<td colspan="9" ><span class="pull-right">
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

		//para buscar las liquidaciones
if($action == 'lc'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['lc'], ENT_QUOTES)));
		 $ordenado = mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
		 $por = mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
		 $aColumns = array('fecha_liquidacion','secuencial_liquidacion', 'serie_liquidacion','razon_social','ruc','estado_sri');//Columnas de busqueda
		 $sTable = "encabezado_liquidacion as eli, proveedores as pro";
		 $sWhere = "WHERE eli.id_proveedor = pro.id_proveedor and eli.ruc_empresa='".$ruc_empresa."' " ;
		if ( $_GET['lc'] != "" )
		{
			$sWhere = "WHERE (eli.id_proveedor = pro.id_proveedor and eli.ruc_empresa='".$ruc_empresa."' AND ";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' AND eli.id_proveedor = pro.id_proveedor and eli.ruc_empresa='".$ruc_empresa."' OR ";
			}
			
			$sWhere = substr_replace( $sWhere, "AND eli.id_proveedor = pro.id_proveedor and eli.ruc_empresa='".$ruc_empresa."' ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by id_encabezado_liq desc";
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
		$reload = '../facturas.php';
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
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_factura");'>Fecha</button></th>
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre");'>Emitida a</button></th>
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("secuencial_factura");'>Número</button></th>														
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("estado_sri");'>Estado</button></th>		
				<th class='text-right'>Opciones</th>								
				<input type="hidden" value="<?php echo $page;?>" id="pagina">
				</tr>
				<?php

				while ($row=mysqli_fetch_array($query)){
						$id_encabezado_lc=$row['id_encabezado_liq'];
						$ruc_empresa=$row['ruc_empresa'];
						$fecha_lc=$row['fecha_liquidacion'];
						$serie_lc=$row['serie_liquidacion'];
						$secuencial_lc=$row['secuencial_liquidacion'];
						$nombre_proveedor=$row['razon_social'];
						$ruc_proveedor=$row['ruc_proveedor'];
						$estado_sri=$row['estado_sri'];
						$id_proveedor=$row['id_proveedor'];
					//para consultar el nombre de la empresa que emite la nota de credito
					$busca_empresa = "SELECT * FROM empresas WHERE ruc = '".$ruc_empresa."' ";
					$result_empresa = $con->query($busca_empresa);
					$datos_empresa = mysqli_fetch_array($result_empresa);
					$nombre_empresa=$datos_empresa['nombre'];

					$numero_liq = $serie_lc . "-" . str_pad($secuencial_lc, 9, "000000000", STR_PAD_LEFT);
						
					//estado sri
					switch ($estado_sri) {
					case "PENDIENTE":
						$label_class_sri='label-warning';
						break;
					case "ANULADA":
						$label_class_sri='label-danger';
						break;
					case "NO APLICA":
						$label_class_sri='label-info';
						break;
					case "AUTORIZADO":
						$label_class_sri='label-success';
						break;
						}
					
					?>
					<input type="hidden" value="<?php echo $id_encabezado_lc;?>" id="id_documento<?php echo $id_encabezado_lc;?>">
					<input type="hidden" value="liquidacion" id="documento<?php echo $id_encabezado_lc;?>">

					<tr>
						<td><?php echo date("d/m/Y", strtotime($fecha_lc)); ?></td>
						<td class='col-md-4'><?php echo strtoupper ($nombre_proveedor); ?></td>
						<td><?php echo $serie_lc; ?>-<?php echo str_pad($secuencial_lc,9,"000000000",STR_PAD_LEFT); ?></td>
						<td><span class="label <?php echo $label_class_sri;?>"><?php echo $estado_sri; ?></span></td>
					
					<td class='col-md-3'><span class="pull-right">
						<a href="#" class='btn btn-success btn-xs' onclick="generar_pdf_xml('<?php echo $id_encabezado_lc;?>');" title='Generar pdf xml'><i class="glyphicon glyphicon-cog"></i> Generar pdf xml</a>				
												
						<?php
							$status_servidor=status_servidor(); //esto le puse que venga de helpers cuando el servidor no quiere descargar las facturas
							if($status_servidor){
								?>
						<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_lc) ?>&tipo_documento=liquidacion&tipo_archivo=pdf" class='btn btn-default btn-xs' title='Descargar' download target="_blank">Pdf <i class="glyphicon glyphicon-download"></i></i></a>
						<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_lc) ?>&tipo_documento=liquidacion&tipo_archivo=xml" class='btn btn-default btn-xs' title='Descargar' download target="_blank">Xml <i class="glyphicon glyphicon-download"></i></i></a>
						<?php
							}else{
								?>
							<a href="http://64.225.69.65:8000/liquidaciones_autorizadas/<?php echo $ruc_empresa ?>/<?php echo $ruc_proveedor ?>/LIQ<?php echo $numero_liq ?>.pdf" class='btn btn-default btn-xs' title='Descargar' target="_blank" download>Pdf</i> </a>
							<a href="http://64.225.69.65:8000/liquidaciones_autorizadas/<?php echo $ruc_empresa ?>/<?php echo $ruc_proveedor ?>/LIQ<?php echo $numero_liq ?>.xml" class='btn btn-default btn-xs' title='Descargar' target="_blank" download>Xml</i> </a>
					<?php
							}
							?>			
						</span></td>
					
					</tr>
				<?php
				}
				?>
				<tr>
					<td colspan="9" ><span class="pull-right">
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