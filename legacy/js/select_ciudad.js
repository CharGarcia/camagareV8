$(function(){
	// lista de ciudades	
	$('#provincia').change(function()
	{
		var provincia = $(this).val();
		$.post( '../paginas/select_ciudad.php', {codigo_provincia: provincia}).done( function( respuesta )
		{
			$( '#ciudad' ).html( respuesta );
		});
	});

})