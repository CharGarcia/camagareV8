		<table class="table">
			<tr class="info">
				<th class='text-center'>Forma de pago</th>
				<th class='text-center'>Detalle</th>
				<th class='text-center'>Cta Banco</th>
				<th class='text-right'>Valor</th>
				<th class='text-right'>Eliminar</th>
				<td></td>
			</tr>
			<tr class="info">
				<td class='text-right' colspan=4 >TOTAL: </td>
				<td class='text-right'><?php echo number_format(0 ,2,'.','');?></td>
				<td><input type="hidden" id="suma_factura" value="<?php echo number_format(0 ,2,'.','');?>"></td>
			</tr>
		</table>