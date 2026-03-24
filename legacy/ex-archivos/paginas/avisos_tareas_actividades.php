<div class="panel panel-warning">
	<?php ini_set('date.timezone', 'America/Guayaquil'); ?>
	<div class="panel-heading">
		<h4><i class='glyphicon glyphicon-screenshot'></i> Actividades y tareas</h4>
	</div>
	<ul class="list-group">
		<div class="panel-group" id="accordion_avisos">
			<!--para mostrar mensaje del dia -->
			<?php
			$conexion = conenta_login();
			$sql_pensamientos = mysqli_query($conexion, "SELECT * FROM pensamientos ORDER BY rand() LIMIT 10;");
			$mensaje = mysqli_fetch_array($sql_pensamientos);
			$mensaje_mostrado = $mensaje['mensaje'];
			?>
			<li href="#" class="list-group-item list-group-item-info"><b><i> <?php echo $mensaje_mostrado ?> </i></b></li>

			<!--para mostrar contacto a whatsapp -->
			<li href="#" class="list-group-item list-group-item-info"><b><i> Escribe al Whatsapp <img src="../sistema/image/whatsapp.png" alt="Logo" width="18px"> <a href="https://api.whatsapp.com/send?phone=593958924831&text=Mensaje para soporte CaMaGaRe" target="_blank" title="Enviar mensaje por whastapp"> 0958924831</a></i></b></li>
			<li href="#" class="list-group-item list-group-item-info"><b><i> Comunícate con nosotros <a href="tel:+5930958924831" title="Click aquí para hacer la llamada"><span class="glyphicon glyphicon-phone"></span> 0958924831</a></i></b></li>
			<li href="#" class="list-group-item list-group-item-info"><b><i> Envíanos un correo </span><a href="mailto:info@camagare.com"> <span class="glyphicon glyphicon-envelope"></span> info@camagare.com</a></i></b></li>
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion_avisos" href="#collapse_cuentas"><span class="caret"></span> Cuentas bancarias disponibles para pagos</a>
				<div id="collapse_cuentas" class="collapse">
					<b>
						<FONT SIZE=3 COLOR="blue">Tipo de cuenta: Ahorros </FONT>
					</b><br>
					Beneficiario: Carlos Garcia Revelo <br>
					Mail: carlosgarciarevelo@gmail.com <br>
					Cédula: 1717136574 <br>
					Teléfono: 0958924831 <br>
					Banco Bolivariano 5021029887 <br>
					Banco Guayaquil 9160030 <br>
					Banco Pichincha 2206333897 <br>
					Banco Produbanco 12008249739 <br>
					Banco Pacífico 1062913255 <br>
					<b>
						<FONT SIZE=3 COLOR="blue">Tipo de cuenta: Corriente </FONT>
					</b><br>
					Beneficiario: CMG Business Administration S.A.S <br>
					Mail: cmg.ba.sas@gmail.com <br>
					RUC: 1793204600001 <br>
					Teléfono: 0958924831 <br>
					Banco Pacífico 8242356
				</div>
			</div>

			<!--para contar cuantas documentos electronicos emitidos -->
			<?php
			$id_usuario = $_SESSION['id_usuario'];
			$sql_facturas_emitidas = mysqli_query($conexion, "SELECT * FROM encabezado_factura WHERE tipo_factura='ELECTRÓNICA'");
			$count_facturas_emitidas = mysqli_num_rows($sql_facturas_emitidas);

			$sql_retenciones_emitidas = mysqli_query($conexion, "SELECT * FROM encabezado_retencion ;");
			$count_retenciones_emitidas = mysqli_num_rows($sql_retenciones_emitidas);

			$sql_notas_emitidas = mysqli_query($conexion, "SELECT * FROM encabezado_nc");
			$count_nc_emitidas = mysqli_num_rows($sql_notas_emitidas);

			$sql_guias_emitidas = mysqli_query($conexion, "SELECT * FROM encabezado_gr");
			$count_gr_emitidas = mysqli_num_rows($sql_guias_emitidas);

			$sql_lc = mysqli_query($conexion, "SELECT * FROM encabezado_liquidacion");
			$count_lc_emitidas = mysqli_num_rows($sql_lc);

			$total_documentos_emitidos = $count_facturas_emitidas + $count_retenciones_emitidas + $count_nc_emitidas + $count_gr_emitidas + $count_lc_emitidas;

			//documentos emitidos hoy
			$fecha_hoy = date("Y-m-d");
			$sql_facturas_emitidas = mysqli_query($conexion, "SELECT * FROM encabezado_factura WHERE tipo_factura='ELECTRÓNICA' and fecha_registro like '%" . $fecha_hoy . "%';");
			$count_facturas_emitidas = mysqli_num_rows($sql_facturas_emitidas);

			$sql_retenciones_emitidas_hoy = mysqli_query($conexion, "SELECT * FROM encabezado_retencion where fecha_emision like '%" . $fecha_hoy . "%';");
			$count_retenciones_emitidas_hoy = mysqli_num_rows($sql_retenciones_emitidas_hoy);

			$sql_notas_emitidas = mysqli_query($conexion, "SELECT * FROM encabezado_nc where fecha_registro like '%" . $fecha_hoy . "%';");
			$count_nc_emitidas = mysqli_num_rows($sql_notas_emitidas);

			$sql_guias_emitidas = mysqli_query($conexion, "SELECT * FROM encabezado_gr where fecha_registro like '%" . $fecha_hoy . "%';");
			$count_gr_emitidas = mysqli_num_rows($sql_guias_emitidas);

			$sql_lc_emitidas = mysqli_query($conexion, "SELECT * FROM encabezado_liquidacion where fecha_registro like '%" . $fecha_hoy . "%';");
			$count_lc_emitidas = mysqli_num_rows($sql_lc_emitidas);

			$sql_empresas_totales = mysqli_query($conexion, "SELECT * FROM empresas");
			$count_empresas = mysqli_num_rows($sql_empresas_totales);

			$sql_empresas_activas = mysqli_query($conexion, "SELECT * FROM empresas WHERE estado='1'");
			$count_empresas_activas = mysqli_num_rows($sql_empresas_activas);

			$sql_empresas_pasivas = mysqli_query($conexion, "SELECT * FROM empresas WHERE estado='2'");
			$count_empresas_pasivas = mysqli_num_rows($sql_empresas_pasivas);

			$sql_usuarios_totales = mysqli_query($conexion, "SELECT * FROM usuarios");
			$count_usuarios = mysqli_num_rows($sql_usuarios_totales);

			$sql_usuarios_activos = mysqli_query($conexion, "SELECT * FROM usuarios WHERE estado='1'");
			$count_usuarios_activos = mysqli_num_rows($sql_usuarios_activos);

			$sql_usuarios_pasivos = mysqli_query($conexion, "SELECT * FROM usuarios WHERE estado='2'");
			$count_usuarios_pasivos = mysqli_num_rows($sql_usuarios_pasivos);

			//$actualizar_estado_usuarios=mysqli_query($conexion, "UPDATE control_usuarios SET estado='OFFLINE' WHERE estado='ONLINE' and TIMESTAMPDIFF(hour, fecha_entrada, now()) > 6 ");
			//$sql_usuarios_trabajando = mysqli_query($conexion, "SELECT * FROM control_usuarios WHERE estado='ONLINE'");
			//$count_usuarios_trabajando = mysqli_num_rows($sql_usuarios_trabajando);

			$total_documentos_emitidos_hoy = $count_facturas_emitidas + $count_retenciones_emitidas_hoy + $count_nc_emitidas + $count_gr_emitidas + $count_lc_emitidas;
			//estadistica
			if ($id_usuario == 1) {
			?>
				<div class="panel panel-info">
					<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion_avisos" href="#collapse2"><span class="caret"></span> Estadística</a>
					<div id="collapse2" class="collapse">
						<li class="list-group-item list-group-item-success"><span class="glyphicon glyphicon-ok" aria-hidden="true"></span> Total documentos emitidos <span class="badge"><?php echo $total_documentos_emitidos  ?> </span></li>
						<li class="list-group-item list-group-item-success"><span class="glyphicon glyphicon-ok" aria-hidden="true"></span> Documentos emitidos hoy <span class="badge"><?php echo $total_documentos_emitidos_hoy  ?> </span></li>
						<li class="list-group-item list-group-item-info"><span class="glyphicon glyphicon-tower" aria-hidden="true"></span> Total empresas <span class="badge"><?php echo $count_empresas ?> </span></li>
						<li class="list-group-item list-group-item-info"><span class="glyphicon glyphicon-tower" aria-hidden="true"></span> Empresas Activas <span class="badge"><?php echo $count_empresas_activas ?> </span></li>
						<li class="list-group-item list-group-item-info"><span class="glyphicon glyphicon-tower" aria-hidden="true"></span> Empresas Pasivas <span class="badge"><?php echo $count_empresas_pasivas ?> </span></li>
						<li class="list-group-item list-group-item-success"><span class="glyphicon glyphicon-user" aria-hidden="true"></span> Total Usuarios <span class="badge"><?php echo $count_usuarios ?> </span></li>
						<li class="list-group-item list-group-item-success"><span class="glyphicon glyphicon-user" aria-hidden="true"></span> Usuarios activos <span class="badge"><?php echo $count_usuarios_activos ?> </span></li>
						<li class="list-group-item list-group-item-success"><span class="glyphicon glyphicon-user" aria-hidden="true"></span> Usuarios pasivos <span class="badge"><?php echo $count_usuarios_pasivos ?> </span></li>
						<!--<li class="list-group-item list-group-item-success"><span class="glyphicon glyphicon-user" aria-hidden="true"></span> Usuarios ON-LINE <span class="badge"><?php //echo $count_usuarios_trabajando 
																																														?> </span></li>-->
					</div>
				</div>
			<?php
			}
			?>

			<!--para mostrar el mensaje de descarga de anular documentos -->
			<a href="../sistema/descargas/anulacionDocumentos.pdf" class="list-group-item list-group-item-info" target="_blank"><span class="glyphicon glyphicon-download-alt" aria-hidden="true"></span> Guia para anulación de comprobantes electrónicos SRI </a>
			<a href="https://firmas.camagare.com/" target="_blank" class="list-group-item list-group-item-info" title="Adquirir una firma electrónica"><span class="glyphicon glyphicon-hand-right" aria-hidden="true"></span> Solicitar firma electrónica</a>

			<!--para mostrar la caducidad de la firma electronica -->
			<?php
			ini_set('date.timezone', 'America/Guayaquil');
			$sql_empresas_asignadas = "SELECT * FROM empresa_asignada emp_asig, empresas emp WHERE emp_asig.id_usuario='" . $id_usuario . "' and emp.id=emp_asig.id_empresa order by emp.nombre asc";
			$result_empresa_asignada = $conexion->query($sql_empresas_asignadas);
			while ($row_empresa_asignada = mysqli_fetch_array($result_empresa_asignada)) {
				$ruc_empresa_asignada = $row_empresa_asignada['ruc'];
				$nombre_empresa_asignada = $row_empresa_asignada['nombre'];

				$sql_vencimiento_firma = "SELECT * FROM config_electronicos as con INNER JOIN empresas as emp ON emp.ruc=con.ruc_empresa WHERE mid(con.ruc_empresa,1,10)='" . substr($ruc_empresa_asignada, 0, 10) . "' and emp.estado='1' and fecha_fin_firma > 0";
				$vencimiento = $conexion->query($sql_vencimiento_firma);
				$mensaje_vencimiento = mysqli_fetch_array($vencimiento);
				$fecha_vencimiento_firma = isset($mensaje_vencimiento['fecha_fin_firma']) ? $mensaje_vencimiento['fecha_fin_firma'] : "";

				$fecha_hoy = date_create(date("Y-m-d H:i:s"));
				$fecha_vencimiento = date_create($fecha_vencimiento_firma);
				$diferencia_dias = date_diff($fecha_hoy, $fecha_vencimiento);
				$dias_restantes = $diferencia_dias->format('%a');

				if (!empty($fecha_vencimiento_firma && $dias_restantes < 15 && $fecha_vencimiento > $fecha_hoy)) {
			?>
					<li href="#" class="list-group-item list-group-item-warning"><i> <?php echo "La firma electrónica de " . $nombre_empresa_asignada . " se caduca en: " . $diferencia_dias->format('%a días') ?> <a href="https://firmas.camagare.com/" target="_blank" title="Solicitar firma electrónica"> <b><u>Solicitar aquí</u></b></a></i></li>
				<?php
				}
				if (!empty($fecha_vencimiento_firma &&  $fecha_hoy > $fecha_vencimiento)) {
				?>
					<li href="#" class="list-group-item list-group-item-danger"><i><?php echo "La firma electrónica de " . $nombre_empresa_asignada . " se caducó hace: " . $diferencia_dias->format('%a días') ?> <a href="https://firmas.camagare.com/" target="_blank" title="Solicitar firma electrónica"> <b><u>Solicitar aquí</u></b></a></i></li>
			<?php
				}
			}

			?>
			<!--para mostrar los avisos de camagare a a todos los usuarios en general de las empresas -->
			<?php
			$sql_avisos_camagare_todos = "SELECT * FROM avisos_camagare WHERE ruc_empresa='9999999999999';";
			$result_avisos_camagare_todos = $conexion->query($sql_avisos_camagare_todos);
			while ($row_avisos_camagare_todos = mysqli_fetch_array($result_avisos_camagare_todos)) {
				$aviso_mostrado = $row_avisos_camagare_todos['detalle_aviso'];
				if (isset($aviso_mostrado)) {
			?>
					<li href="#" class="list-group-item list-group-item-info"><i>
							<marquee><strong> Nota: </strong> <?php echo $aviso_mostrado ?> </marquee>
						</i></li>
			<?php

				}
			}
			?>
			<!--para mostrar los avisos de camagare a las empresas especifico para cada empresa-->
			<?php
			$sql_avisos_camagare = "SELECT * FROM empresa_asignada as emp_asig LEFT JOIN empresas as emp ON emp.id=emp_asig.id_empresa LEFT JOIN avisos_camagare as avi_ca ON avi_ca.ruc_empresa=emp.ruc WHERE emp_asig.id_usuario='" . $id_usuario . "';";
			$result_avisos_camagare = $conexion->query($sql_avisos_camagare);
			while ($row_avisos_camagare = mysqli_fetch_array($result_avisos_camagare)) {
				$nombre_empresa_recibe = $row_avisos_camagare['nombre'];
				$aviso_mostrado = $row_avisos_camagare['detalle_aviso'];

				/*	
						$ruc_empresa_aviso=$row_avisos_camagare['ruc'];
							$sql_aviso = "SELECT * FROM avisos_camagare WHERE ruc_empresa='".$ruc_empresa_aviso."';";
							$result_aviso = $conexion->query($sql_aviso);
							$row_aviso = mysqli_fetch_array($result_aviso);
							$aviso_mostrado=isset($row_aviso['detalle_aviso'])?$row_aviso['detalle_aviso']:"";
						*/

				if (isset($aviso_mostrado)) {
			?>
					<li href="#" class="list-group-item list-group-item-danger"><i><strong> Mensaje importante: </strong> <?php echo $nombre_empresa_recibe . " detalle: " . $aviso_mostrado ?> </i></li>
			<?php

				}
			}
			?>
			<!--para mostrar los avisos de camagare que yo emiti -->
			<?php
			$sql_aviso_emisor = "SELECT * FROM avisos_camagare avca, empresas emp WHERE avca.id_usuario='" . $id_usuario . "' and emp.ruc=avca.ruc_empresa;";
			$result_aviso_emisor = $conexion->query($sql_aviso_emisor);
			while ($row_aviso_emisor = mysqli_fetch_array($result_aviso_emisor)) {
				$aviso_emitido = $row_aviso_emisor['detalle_aviso'];
				$nombre_empresa_aviso = $row_aviso_emisor['nombre'];

				if (isset($aviso_emitido)) {
			?>
					<li href="#" class="list-group-item list-group-item-info"><i><strong> Mensaje enviado a: </strong> <?php echo $nombre_empresa_aviso . " Detalle: " . $aviso_emitido ?> </i></li>
			<?php
				}
			}

			mysqli_close($conexion);
			?>

		</div>
	</ul>
</div>