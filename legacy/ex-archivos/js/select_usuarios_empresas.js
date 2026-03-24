$(function(){
	// Lista de usuarios
	//$.post( '../paginas/select_usuarios.php' ).done( function(respuesta)
	//{
	//	$( '#usuarios' ).html( respuesta );
	//});

	// lista de usuarios	
	$('#usuarios').change(function()
	{
		var id_usuario = $(this).val();
		// Lista de empresas
		$.post( '../paginas/select_empresas.php', {id_usu: id_usuario}).done( function( respuesta )
		{
			$( '#empresas' ).html( respuesta );
		});
	});

})
