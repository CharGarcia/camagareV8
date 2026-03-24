<?php
//include("../paginas/menu_de_empresas.php");
//Lectura con apertura de archivo y la función fread
/*
$a = fopen("datos.csv","r");
echo fread($a,10);
fclose($a);
*/
// file_get_contents() Guarda el contenido del archivo en una variable tipo string
/*$s = file_get_contents("datos.csv");
echo $s;
*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
$archivo = file("clientes.csv");
/*
?>
<tr  class="info">
<th>Tablas</th>
</tr>
	<select class="form-control" >
			<option value="0" >Seleccione tabla</option>
			<?php
			$tablas_name = "select TABLE_NAME from INFORMATION_SCHEMA.TABLES where table_schema = 'sistema'";
			$res = mysqli_query($con,$tablas_name);
			while($table = mysqli_fetch_assoc($res)){
			?>
			<option value="<?php echo $table['TABLE_NAME']?>"><?php echo $table['TABLE_NAME']?></option>
			<?php
			}
			?>
	</select>
<?php


$columnas_name = mysqli_query($con, "select COLUMN_NAME from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME = 'clientes'");

?>
<tr>
		<th colspan="1">Datos a subir</th>
	</tr>
<?php




while ($columnas=mysqli_fetch_array($columnas_name)){
$nombre_columna = $columnas['COLUMN_NAME'];
?>
<table border="1" align="center">
<td><?php echo $nombre_columna;?></td>
</table>
<?php
}

*/
foreach($archivo as $linea){
	$campo = explode(";",$linea);
	
	$sql = mysqli_query($con, "INSERT INTO clientes VALUES($campo[0],'$campo[1]','$campo[2]','$campo[3]','$campo[4]','$campo[5]','$campo[6]','$campo[7]','$campo[8]','$campo[9]','$campo[10]','$campo[11]',$campo[12])");
	//trim($campo[12])
	//"\n";
	//echo "<br>" . $sql;
	//$con->query($sql);
	
	//file_put_contents("clientes.sql",$sql,FILE_APPEND | LOCK_EX);

?>
	<table border="1" align="left">
	<tr>
		<td><?php echo $campo[0] ;?></td>
		<td><?php echo $campo[1] ;?></td>
		<td><?php echo $campo[2] ;?></td>
		<td><?php echo $campo[3] ;?></td>
		<td><?php echo $campo[4] ;?></td>
		<td><?php echo $campo[5] ;?></td>
		<td><?php echo $campo[6] ;?></td>
		<td><?php echo $campo[7] ;?></td>
		<td><?php echo $campo[8] ;?></td>
		<td><?php echo $campo[9] ;?></td>
		<td><?php echo $campo[10] ;?></td>
		<td><?php echo $campo[11] ;?></td>
		<td><?php echo $campo[12] ;?></td>
	</tr>
	<?php
}
?>
	</table>
