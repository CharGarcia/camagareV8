<?php
require_once("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
require_once("../ajax/pagination.php"); //include pagination file
//require_once("../helpers/helpers.php"); //include pagination file
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';


if ($action == 'eliminar_maquila') {
    $id_maquila=intval($_POST['id_maquila']);
    $update_maquila = mysqli_query($con, "UPDATE encabezado_maquila SET status='3' WHERE id='" . $id_maquila . "'");
    if($update_maquila){
       echo "<script>
        $.notify('Maquila anulada','success');
        load(1);
        </script>";
       }else{
        echo "<script>
        $.notify('Intente de nuevo','error');
        </script>";
       }

 }
 //para consultar los datos de la maquila a editar
 if ($action == 'datos_editar') {
    $id_maquila = intval($_GET['id_maquila']);
	//traer informacion y detalle de los arrays de gastos y pagos
	$sql_pagos_gastos = mysqli_query($con, "SELECT * FROM detalle_maquila WHERE id_maquila='" . $id_maquila . "'");
	$arrayPagos = array();
	$arrayGastos = array();
	while ($row_pagos_gastos = mysqli_fetch_array($sql_pagos_gastos)) {
		if ($row_pagos_gastos['tipo'] == '1') {
			$datosPagos = array('id' => rand(5, 50), 'fecha' => date("d-m-Y", strtotime($row_pagos_gastos['fecha'])), 'valor' => number_format($row_pagos_gastos['valor'], 2, '.', ''), 'detalle' => $row_pagos_gastos['detalle']);
			array_push($arrayPagos, $datosPagos);
		} else {
			$datosGastos = array('id' => rand(5, 50), 'fecha' => date("d-m-Y", strtotime($row_pagos_gastos['fecha'])), 'valor' => number_format($row_pagos_gastos['valor'], 2, '.', ''), 'detalle' => $row_pagos_gastos['detalle']);
			array_push($arrayGastos, $datosGastos);
		}
	}

	$_SESSION['arrayPagos'] = $arrayPagos;
	$_SESSION['arrayGastos'] = $arrayGastos;


    $valor_total_pago = total_pagos_gastos()['pagos'];
    $valor_total_gastos = total_pagos_gastos()['gastos'];

    //para el encabezado
    $sql = mysqli_query($con, "SELECT maq.status as status,
    maq.referencia as referencia, maq.factura as factura, maq.id_proveedor as id_proveedor, pro.razon_social as proveedor,
      maq.id_producto as id_producto, ser.nombre_producto as producto, maq.fecha_inicio as fecha_inicio, maq.fecha_entrega as fecha_entrega, maq.cantidad as cantidad,
      maq.bultos as bultos, maq.precio_costo as precio_costo, maq.precio_venta as precio_venta FROM encabezado_maquila as maq INNER JOIN proveedores as pro ON
    pro.id_proveedor=maq.id_proveedor INNER JOIN productos_servicios as ser On ser.id=maq.id_producto 
    WHERE maq.ruc_empresa ='".$ruc_empresa."' and maq.id ='".$id_maquila."'");
    $row_maquila = mysqli_fetch_array($sql);

    $data= array('status'=> $row_maquila['status'], 'referencia'=> $row_maquila['referencia'], 'factura'=> $row_maquila['factura'],
     'id_proveedor'=> $row_maquila['id_proveedor'], 'proveedor'=> $row_maquila['proveedor'], 'producto'=> $row_maquila['producto'],
     'id_producto'=> $row_maquila['id_producto'], 'fecha_inicio'=> date("d-m-Y", strtotime($row_maquila['fecha_inicio'])), 'fecha_entrega'=> date("d-m-Y", strtotime($row_maquila['fecha_entrega'])), 'cantidad'=> $row_maquila['cantidad'],
       'bultos'=> $row_maquila['bultos'], 'precio_costo'=> number_format($row_maquila['precio_costo'], 2, '.', ''), 
       'precio_venta'=> number_format($row_maquila['precio_venta'], 2, '.', ''), 'utilidad_por_unidad'=> number_format($row_maquila['precio_venta']-$row_maquila['precio_costo'], 2, '.', ''), 
    'total_venta'=>number_format($row_maquila['cantidad']*$row_maquila['precio_venta'], 2, '.', ''), 'total_costos'=>number_format($row_maquila['cantidad']*$row_maquila['precio_costo'], 2, '.', ''),
    'total_gastos'=>number_format($valor_total_gastos, 2, '.', ''), 'utilidad_neta'=>number_format(($row_maquila['cantidad'] * $row_maquila['precio_venta'])-($row_maquila['cantidad']*$row_maquila['precio_costo'])-$valor_total_gastos, 2, '.', ''), 
    'por_pagar'=>number_format(($row_maquila['cantidad']*$row_maquila['precio_costo'])-$valor_total_pago, 2, '.', ''));


    if ($sql){
        $arrResponse = array("status" => true, "data" => $data);
    }else{
        $arrResponse = array("status" => false, "msg" => 'Datos no encontrados');
    }

    echo json_encode($arrResponse, JSON_UNESCAPED_UNICODE);//, JSON_UNESCAPED_UNICODE
    die();

 }

 if ($action == 'consulta_pagos_gastos_actuales') {
    $valor_total_pago = total_pagos_gastos()['pagos'];
    $valor_total_gastos = total_pagos_gastos()['gastos'];

    $data=array('total_pagos'=>$valor_total_pago, 'total_gastos'=>$valor_total_gastos);

    if ($data){
        $arrResponse = array("status" => true, "data" => $data, );
    }else{
        $arrResponse = array("status" => false, "msg" => 'Datos no encontrados');
    }

    echo json_encode($arrResponse, JSON_UNESCAPED_UNICODE);//, JSON_UNESCAPED_UNICODE
    die();
 }


 function total_pagos_gastos(){
    $valor_total_pago=0;
    $valor_total_gastos=0;
    if (isset($_SESSION['arrayPagos'])) {
        foreach ($_SESSION['arrayPagos'] as $detalle) {
            $valor_pago = number_format($detalle['valor'], 2, '.', '');
            $valor_total_pago += $valor_pago;
        }
    }

    if (isset($_SESSION['arrayGastos'])) {
        foreach ($_SESSION['arrayGastos'] as $detalle) {
            $valor_pago = number_format($detalle['valor'], 2, '.', '');
            $valor_total_gastos += $valor_pago;
        }
    }

    $resultados = array('pagos'=>$valor_total_pago, 'gastos'=>$valor_total_gastos);

    return $resultados;

}
//buscar maquila
if ($action == 'buscar_maquila') {
    $condicion_ruc_empresa=	"enc.ruc_empresa = '". $ruc_empresa ."'";
     $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
     $ordenado = mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
     $por = mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
     $aColumns = array('enc.razon_social','prod.nombre_producto','enc.referencia','enc.fecha_inicio','enc.fecha_entrega');//Columnas de busqueda
     $sTable = "encabezado_maquila as enc INNER JOIN proveedores as pro ON pro.id_proveedor=enc.id_proveedor 
     INNER JOIN productos_servicios as prod ON prod.id=enc.id_producto ";
     $sWhere = "WHERE $condicion_ruc_empresa";
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
            $sWhere .= $aColumns[$i]." LIKE '".$like."%' AND $condicion_ruc_empresa OR ";
        }
        $sWhere = substr_replace( $sWhere, "AND $condicion_ruc_empresa ", -3 );
        $sWhere .= ')';
    }
    $sWhere.=" order by $ordenado $por";
    
    $page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
    $per_page = 20; //how much records you want to show
    $adjacents  = 4; //gap between pages after number of adjacents
    $offset = ($page - 1) * $per_page;
    //Count the total number of row in your table*/
    $count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
    $row= mysqli_fetch_array($count_query);
    $numrows = $row['numrows'];
    $total_pages = ceil($numrows/$per_page);
    $reload = '../maquila.php';
    //main query to fetch the data
    $sql="SELECT enc.id as id, enc.referencia as referencia, pro.razon_social as razon_social,
     prod.nombre_producto as nombre_producto, enc.cantidad as cantidad, enc.fecha_inicio as fecha_inicio,
     enc.fecha_entrega as fecha_entrega, enc.status as status FROM  $sTable $sWhere LIMIT $offset,$per_page";
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows>0){
        
        ?>
        <div class="panel panel-info">
        <div class="table-responsive">
          <table class="table">
            <tr  class="info">
                <th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_inicio");'>Fecha Inicio</button></th>
                <th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_entrega");'>Fecha Entrega</button></th>
                <th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("referencia");'>Referencia</button></th>
                <th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("razon_social");'>Proveedor</button></th>
                <th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_producto");'>Producto</button></th>
                <th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cantidad");'>Cantidad</button></th>
                <th class="text-center">Status</th>
                <th class="text-right">Opciones</th>				
            </tr>
            <?php
            while ($row=mysqli_fetch_array($query)){
                    $id_maquila=$row['id'];
                    $referencia=strtoupper($row['referencia']);
                    $proveedor=strtolower($row['razon_social']);
                    $producto=strtolower($row['nombre_producto']);
                    $cantidad=$row['cantidad'];
                    $fecha_inicio=$row['fecha_inicio'];
                    $fecha_entrega=$row['fecha_entrega'];
                    $status=$row['status'];
                    
						//estado del pago
						switch ($status) {
							case "1":
								$status_final = "<span class='label label-info'>En proceso</span>";
								break;
							case "2":
								$status_final = "<span class='label label-success'>Terminado</span>";
								break;
							case "3":
								$status_final = "<span class='label label-danger'>Anulado</span>";
								break;
						}
                ?>
                <tr>				
                    <td><?php echo date("d-m-Y", strtotime($fecha_inicio));?></td>	
                    <td><?php echo date("d-m-Y", strtotime($fecha_entrega));?></td>		
                    <td><?php echo strtoupper($referencia); ?></td>
                    <td><?php echo strtoupper($proveedor); ?></td>
                    <td><?php echo strtoupper($producto); ?></td>
                    <td class="text-center"><?php echo number_format($cantidad, 2, '.', '');?></td>
                    <td class="text-center"><?php echo $status_final; ?></td>
                <td ><span class="pull-right">
                <a href="#" class="btn btn-info btn-xs" title="Editar maquila" onclick="editar_maquila('<?php echo $id_maquila;?>');" data-toggle="modal" data-target="#maquila"><i class="glyphicon glyphicon-edit"></i></a> 
                <a href="#" class="btn btn-danger btn-xs" title="Eliminar maquila" onclick="eliminar_maquila('<?php echo $id_maquila;?>');"><i class="glyphicon glyphicon-trash"></i></a> 	
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

if ($action == 'nueva_maquila') {
    unset($_SESSION['arrayPagos']);
    unset($_SESSION['arrayGastos']);
}

//guardar o editar maquila
if ($action == 'guardar_maquila') {
    $id_maquila = intval($_POST['id_maquila']);
    $status = intval($_POST['status']);
    $referencia = strClean($_POST['referencia']);
    $factura = strClean($_POST['factura']);
    $id_proveedor = intval($_POST['id_proveedor']);
    $id_producto = intval($_POST['id_producto']);
    $cantidad = $_POST['cantidad'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_entrega = $_POST['fecha_entrega'];
    $bultos = $_POST['bultos'];
    $precio_costo = $_POST['precio_costo'];
    $precio_venta = $_POST['precio_venta'];

    if (empty($referencia)) {
        echo "<script>
            $.notify('Ingrese referencia','error');
            </script>";
    } else if (empty($id_proveedor)) {
        echo "<script>
        $.notify('Seleccione un proveedor','error');
        </script>";   
    } else if (empty($id_producto)) {
        echo "<script>
        $.notify('Seleccione un producto','error');
        </script>";
    } else if (empty($cantidad)) {
        echo "<script>
        $.notify('Agregue cantidad','error');
        </script>";    
    } else if (empty($fecha_inicio)) {
        echo "<script>
        $.notify('Ingrese fecha de inicio','error');
        </script>";
    } else if (empty($fecha_entrega)) {
        echo "<script>
        $.notify('Ingrese fecha de entrega','error');
        </script>";
    } else if (empty($bultos)) {
        echo "<script>
        $.notify('Ingrese número de bultos','error');
        </script>";
    } else if (empty($precio_costo)) {
        echo "<script>
        $.notify('Ingrese el precio de costo','error');
        </script>";
    } else if (empty($precio_venta)) {
        echo "<script>
        $.notify('Ingrese el precio de venta','error');
        </script>";
    } else {
        if (empty($id_maquila)) {
            $guarda_maquila = mysqli_query($con, "INSERT INTO encabezado_maquila (ruc_empresa,
                                                                        id_proveedor,
                                                                        id_producto,
                                                                        precio_costo,
                                                                        precio_venta,
                                                                        fecha_inicio,
                                                                        fecha_entrega,
                                                                        cantidad,
                                                                        bultos,
                                                                        referencia,
                                                                        factura,
                                                                        status)
                                                                        VALUES ('" . $ruc_empresa . "',
                                                                                    '" . $id_proveedor . "',
                                                                                    '" . $id_producto . "',
                                                                                    '" . $precio_costo . "',
                                                                                    '" . $precio_venta . "',
                                                                                    '" . date("Y-m-d", strtotime($fecha_inicio)) . "',
                                                                                    '" . date("Y-m-d", strtotime($fecha_entrega)) . "',
                                                                                    '" . $cantidad . "',
                                                                                    '" . $bultos . "',
                                                                                    '" . $referencia . "',
                                                                                    '" . $factura . "',
                                                                                    '" . $status . "')");
                                                                                                  
               if($guarda_maquila){
                $lastid = mysqli_insert_id($con);
                guarda_pagos_gastos($con, $lastid);
               echo "<script>
                $.notify('Maquila registrada','success');
                document.querySelector('#guardar_maquila').reset();
                load(1);
                </script>";
               }else{
                echo "<script>
                $.notify('Intente de nuevo','error');
                </script>";
               }
        } else {
            //modificar el maquila
            $update_maquila = mysqli_query($con, "UPDATE encabezado_maquila SET id_proveedor='" . $id_proveedor . "',
                                                                    id_producto='" . $id_producto . "',
                                                                    precio_costo='" . $precio_costo . "',
                                                                    precio_venta='" . $precio_venta . "',
                                                                    fecha_inicio='" . date("Y-m-d", strtotime($fecha_inicio)) . "',
                                                                    fecha_entrega='" . date("Y-m-d", strtotime($fecha_entrega)) . "',
                                                                    cantidad='".$cantidad."',
                                                                    bultos='" . $bultos . "',
                                                                    referencia='" . $referencia . "',
                                                                    factura='" . $factura . "',
                                                                    status='" . $status . "'
                                                                    WHERE id='" . $id_maquila . "'");
                                                                   
                if($update_maquila){
                    guarda_pagos_gastos($con, $id_maquila);
                    echo "<script>
                    $.notify('Maquila actualizada','success');
                    setTimeout(function () {location.reload()}, 1000);
                        </script>";
                    }else{
                        echo "<script>
                        $.notify('Intente de nuevo','error');
                        </script>";
                    }
                //setTimeout(function (){location.reload()}, 1000);
                
        }
    }
}

function guarda_pagos_gastos($con, $id_maquila)
{
    $query_guarda_pagos_gastos=true;
    $eliminar=mysqli_query($con,"DELETE FROM detalle_maquila WHERE id_maquila='".$id_maquila."'");
	if (isset($_SESSION['arrayPagos'])) {
		foreach ($_SESSION['arrayPagos'] as $detalle) {
			$tipo = '1';
            $fecha = date("Y-m-d", strtotime( $detalle['fecha']));
            $valor = $detalle['valor'];
			$detalle = $detalle['detalle'];
			$query_guarda_pagos_gastos = mysqli_query($con, "INSERT INTO detalle_maquila VALUES (null, '" . $id_maquila . "','" . $tipo . "','" . $fecha . "','" . $valor . "', '" . $detalle . "')");
		}
	}

    if (isset($_SESSION['arrayGastos'])) {
		foreach ($_SESSION['arrayGastos'] as $detalle) {
			$tipo = '2';
			$fecha = date("Y-m-d", strtotime( $detalle['fecha']));
            $valor = $detalle['valor'];
			$detalle = $detalle['detalle'];
			$query_guarda_pagos_gastos = mysqli_query($con, "INSERT INTO detalle_maquila VALUES (null, '" . $id_maquila . "','" . $tipo . "','" . $fecha . "','" . $valor . "', '" . $detalle . "')");
		}
	}
	return ($query_guarda_pagos_gastos);
}

if ($action == 'muestra_pagos') {
    detalle_de_pagos();
}

if ($action == 'muestra_gastos') {
    detalle_de_gastos();
}

if ($action == 'agrega_pago') {
	$fecha = $_POST['fecha_pago'];
	$valor = $_POST['valor_pago'];
    $detalle = strClean($_POST['detalle_pago']);

	if (!empty($fecha) && !empty($valor) && !empty($detalle)) {
			$arrayPagos = array();
			$arrayDatosPago = array('id' => rand(5, 50), 'fecha' => $fecha, 'valor' => $valor, 'detalle'=> $detalle);
			if (isset($_SESSION['arrayPagos'])) {
				$arrayPagos = $_SESSION['arrayPagos'];
				array_push($arrayPagos, $arrayDatosPago);
				$_SESSION['arrayPagos'] = $arrayPagos;
			} else {
				array_push($arrayPagos, $arrayDatosPago);
				$_SESSION['arrayPagos'] = $arrayPagos;
			}
	} else {
		echo "<script>
		$.notify('Ingrese fecha, valor y detalle','error');
		</script>";
	}

	detalle_de_pagos();
}

if ($action == 'agrega_gasto') {
	$fecha = $_POST['fecha_gasto'];
	$valor = $_POST['valor_gasto'];
    $detalle = strClean($_POST['detalle_gasto']);

	if (!empty($fecha) && !empty($valor) && !empty($detalle)) {
			$arrayGastos = array();
			$arrayDatosGasto = array('id' => rand(5, 50), 'fecha' => $fecha, 'valor' => $valor, 'detalle'=> $detalle);
			if (isset($_SESSION['arrayGastos'])) {
				$arrayGastos = $_SESSION['arrayGastos'];
				array_push($arrayGastos, $arrayDatosGasto);
				$_SESSION['arrayGastos'] = $arrayGastos;
			} else {
				array_push($arrayGastos, $arrayDatosGasto);
				$_SESSION['arrayGastos'] = $arrayGastos;
			}
	} else {
		echo "<script>
		$.notify('Ingrese fecha, valor y detalle','error');
		</script>";
	}

	detalle_de_gastos();
}


function detalle_de_pagos()
{
    ?>
	<table class="table table-hover" style="padding: 0px; margin-bottom: 0px;">
		<?php
        if (isset($_SESSION['arrayPagos'])) {
            foreach ($_SESSION['arrayPagos'] as $detalle) {
                $id = $detalle['id'];
                $fecha = $detalle['fecha'];
                $valor = $detalle['valor'];
                $detalle = $detalle['detalle'];
        ?>
                <tr>
                    <td style="padding: 2px;" class="col-xs-3"><?php echo date('d-m-Y', strtotime($fecha)); ?></td>
                    <td style="padding: 2px;" class="col-xs-2"><?php echo number_format($valor, 2, '.', ''); ?></td>
                    <td style="padding: 2px;" class="col-xs-6"><?php echo $detalle; ?></td>
                    <td style="padding: 2px;" class="col-xs-1"><button type="button" style="height:17px;" class="btn btn-danger btn-xs" title="Eliminar" onclick="eliminar_pago('<?php echo $id; ?>')"><span class="glyphicon glyphicon-remove"></span></button></td>
                </tr>
        <?php
            }
        }
        ?>
	</table>
<?php
}

function detalle_de_gastos()
{
    ?>
	<table class="table table-hover" style="padding: 0px; margin-bottom: 0px;">
		<?php
        if (isset($_SESSION['arrayGastos'])) {
            foreach ($_SESSION['arrayGastos'] as $detalle) {
                $id = $detalle['id'];
                $fecha = $detalle['fecha'];
                $valor = $detalle['valor'];
                $detalle = $detalle['detalle'];
        ?>
                <tr>
                    <td style="padding: 2px;" class="col-xs-3"><?php echo date('d-m-Y', strtotime($fecha)); ?></td>
                    <td style="padding: 2px;" class="col-xs-2"><?php echo number_format($valor, 2, '.', ''); ?></td>
                    <td style="padding: 2px;" class="col-xs-6"><?php echo $detalle; ?></td>
                    <td style="padding: 2px;" class="col-xs-1"><button type="button" style="height:17px;" class="btn btn-danger btn-xs" title="Eliminar" onclick="eliminar_gasto('<?php echo $id; ?>')"><span class="glyphicon glyphicon-remove"></span></button></td>
                </tr>
        <?php
            }
        }
        ?>
	</table>
<?php
}

if ($action == 'eliminar_pago') {
	$intid = $_POST['id'];
	$arrData = $_SESSION['arrayPagos'];
	for ($i = 0; $i < count($arrData); $i++) {
		if ($arrData[$i]['id'] == $intid) {
			unset($arrData[$i]);
			echo "<script>
            $.notify('Eliminado','error');
            </script>";
		}
	}
	sort($arrData); //para reordenar el array
	$_SESSION['arrayPagos'] = $arrData;

	detalle_de_pagos();
}

if ($action == 'eliminar_gasto') {
	$intid = $_POST['id'];
	$arrData = $_SESSION['arrayGastos'];
	for ($i = 0; $i < count($arrData); $i++) {
		if ($arrData[$i]['id'] == $intid) {
			unset($arrData[$i]);
			echo "<script>
            $.notify('Eliminado','error');
            </script>";
		}
	}
	sort($arrData); //para reordenar el array
	$_SESSION['arrayGastos'] = $arrData;

	detalle_de_gastos();
}
    
?>