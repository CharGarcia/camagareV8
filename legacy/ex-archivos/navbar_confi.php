<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<link rel="shortcut icon" type="image/png" href="../image/logofinal.png" />
	<meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
	<meta charset="utf-8">
	<script src="../js/menu_responsive.js"></script>
	<script src="../js/menu_empresas.js"></script>
	<?php include("../head.php");
	//include("../includes/cierre_sesion.php");
	?>

	<style>
		.navbar-nav.navbar-center {
			display: flex;
			justify-content: center;
			width: 100%;
		}

		.navbar-nav.navbar-center>li {
			float: none;
		}
	</style>
</head>

<body style="background-size: 300px 300px; background-repeat: no-repeat ; background-position: center; background-color: hsla(180, 38%, 33%, 0.67);  background-attachment: fixed; padding: 0px;">

	<nav class="navbar navbar-default navbar-fixed-top" role="navigation">
		<div class="container-fluid">
			<div class="navbar-header">
				<Button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-sidebar-navbar-collapse-1"><span class="glyphicon glyphicon-list-alt"></span> Menú</button>
			</div>
			<div class="collapse navbar-collapse" id="bs-sidebar-navbar-collapse-1">
				<li class="list-group-item active">
					<span class="glyphicon glyphicon-th-list"></span> <?php echo ucwords(strtolower(utf8_decode($titulo_info))) ?>
				</li>
				<ul class="nav navbar-nav navbar-center">
					<li><a href="../index.php?menu=true"><span class="glyphicon glyphicon-home"></span> Inicio</a></li>
					<li><a href="../includes/logout.php"><span class="glyphicon glyphicon-off"></span> Salir</a></li>
				</ul>
			</div>
		</div>
	</nav>
	</br>
	</br>
	</br>
	</br>
	</br>

</body>

</html>