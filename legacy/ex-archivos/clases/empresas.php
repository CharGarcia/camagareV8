<?php
class empresas{
	public $ruc_empresa;
	public $datos_empresas;
	private $con;

	//con esto consulto toda la info de empresas y luego puedo consultar individualmente con ['nombre'];
	public function datos_empresas($ruc_empresa){
	$con=conenta_login();
	$busca_datos_empresas = "SELECT * FROM empresas where ruc='".$ruc_empresa."' ";
	$resultados_empresas = $con->query($busca_datos_empresas);
	$datos_empresas = mysqli_fetch_array($resultados_empresas);
	return $datos_empresas;	
	}

	public function tipo_empresas($ruc_empresa){
		$con=conenta_login();
		$busca_datos_empresas = "SELECT * FROM config_electronicos where ruc_empresa='".$ruc_empresa."' ";
		$resultados_empresas = $con->query($busca_datos_empresas);
		$datos_empresas = mysqli_fetch_array($resultados_empresas);
		return $datos_empresas;	
		}
}	
?>