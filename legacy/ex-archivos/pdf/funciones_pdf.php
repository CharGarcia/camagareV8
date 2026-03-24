<?php
require('../pdf/fpdf.php');
class funciones_pdf extends FPDF
{

	var $B = 0;
	var $I = 0;
	var $U = 0;
	var $HREF = '';
	var $ALIGN = '';
	var $columnas;
	var $widths;
	var $aligns;

	protected $ProcessingTable = false;
	protected $aCols = array();
	protected $TableX;
	protected $HeaderColor;
	protected $RowColors;
	protected $ColorIndex;

	/*
function Header()
{
    // Print the table header if necessary
    if($this->ProcessingTable)
        $this->TableHeader();
}
*/

	//para armar la tabla
	function SetWidths($w)
	{
		//Set the array of column widths
		$this->widths = $w;
	}

	function SetAligns($a)
	{
		//Set the array of column alignments
		$this->aligns = $a;
	}


	//para mostrar una tabla donde cada celda crece dependiento de la cantidad de datos que tenga
	/* function Row_tabla($data)
	{

		//Calculate the height of the row
		$nb = 0;
		for ($i = 0; $i < count($data); $i++)
			$nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
		$h = 5 * $nb;
		//Issue a page break first if needed
		$this->CheckPageBreak($h);
		//Draw the cells of the row
		for ($i = 0; $i < count($data); $i++) {
			$w = $this->widths[$i];

			if (substr($data[$i], 0, 1) == " ") {
				$a = 'L';
			} else {
				if (is_numeric($data[$i])) {
					$a = 'R';
				} else {
					$a = 'L';
				}
			}
			//$a=isset($this->aligns[$i]) ? $this->aligns[$i] : 'C';
			//Save the current position
			$x = $this->GetX();
			$y = $this->GetY();
			//Draw the border
			$this->Rect($x, $y, $w, $h);
			//Print the text
			$this->MultiCell($w, 5, $data[$i], 0, $a);
			//Put the position to the right of the cell
			$this->SetXY($x + $w, $y);
		}
		//Go to the next line
		$this->Ln($h);
	} */

	function Row_tabla($data, $fill = false, $fillColor = null, $textColor = null)
	{
		// Calcular la altura de la fila
		$nb = 0;
		for ($i = 0; $i < count($data); $i++) {
			$nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
		}
		$h = 5 * $nb;

		// Verificar si se necesita un salto de página
		$this->CheckPageBreak($h);

		// Si se han proporcionado colores, aplicarlos
		if ($fillColor !== null) {
			$this->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
		} else {
			$this->SetFillColor(255, 255, 255); // Fondo blanco por defecto
		}

		if ($textColor !== null) {
			$this->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
		} else {
			$this->SetTextColor(0, 0, 0); // Texto negro por defecto
		}

		// Dibujar las celdas de la fila
		for ($i = 0; $i < count($data); $i++) {
			$w = $this->widths[$i];

			// Alineación de la celda
			if (substr($data[$i], 0, 1) == " ") {
				$a = 'L';
			} else {
				if (is_numeric($data[$i])) {
					$a = 'R';
				} else {
					$a = 'L';
				}
			}

			// Guardar la posición actual
			$x = $this->GetX();
			$y = $this->GetY();

			// Dibujar la celda con fondo si aplica
			$this->Rect($x, $y, $w, $h, 'DF'); // 'DF' significa dibujar borde y rellenar
			$this->MultiCell($w, 5, $data[$i], 0, $a, $fill);

			// Restaurar la posición de la celda
			$this->SetXY($x + $w, $y);
		}

		// Ir a la siguiente línea
		$this->Ln($h);
	}



	function CheckPageBreak($h)
	{
		//If the height h would cause an overflow, add a new page immediately
		if ($this->GetY() + $h > $this->PageBreakTrigger)
			$this->AddPage($this->CurOrientation);
	}

	function NbLines($w, $txt)
	{
		//Computes the number of lines a MultiCell of width w will take
		$cw = &$this->CurrentFont['cw'];
		if ($w == 0)
			$w = $this->w - $this->rMargin - $this->x;
		$wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
		$s = str_replace("\r", '', $txt);
		$nb = strlen($s);
		if ($nb > 0 and $s[$nb - 1] == "\n")
			$nb--;
		$sep = -1;
		$i = 0;
		$j = 0;
		$l = 0;
		$nl = 1;
		while ($i < $nb) {
			$c = $s[$i];
			if ($c == "\n") {
				$i++;
				$sep = -1;
				$j = $i;
				$l = 0;
				$nl++;
				continue;
			}
			if ($c == ' ')
				$sep = $i;
			$l += $cw[$c];
			if ($l > $wmax) {
				if ($sep == -1) {
					if ($i == $j)
						$i++;
				} else
					$i = $sep + 1;
				$sep = -1;
				$j = $i;
				$l = 0;
				$nl++;
			} else
				$i++;
		}
		return $nl;
	}



	function detalle_html($html)
	{
		//HTML parser
		$html = str_replace("\n", ' ', $html);
		$a = preg_split('/<(.*)>/U', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
		foreach ($a as $i => $e) {
			if ($i % 2 == 0) {
				//Text
				if ($this->HREF)
					$this->PutLink($this->HREF, $e);
				elseif ($this->ALIGN == 'center')
					$this->Cell(0, 5, $e, 0, 1, 'C');
				else
					$this->Write(5, $e);
			} else {
				//Tag
				if ($e[0] == '/')
					$this->CloseTag(strtoupper(substr($e, 1)));
				else {
					//Extract properties
					$a2 = explode(' ', $e);
					$tag = strtoupper(array_shift($a2));
					$prop = array();
					foreach ($a2 as $v) {
						if (preg_match('/([^=]*)=["\']?([^"\']*)/', $v, $a3))
							$prop[strtoupper($a3[1])] = $a3[2];
					}
					$this->OpenTag($tag, $prop);
				}
			}
		}
	}

	function OpenTag($tag, $prop)
	{
		//Opening tag
		if ($tag == 'B' || $tag == 'I' || $tag == 'U')
			$this->SetStyle($tag, true);
		if ($tag == 'A')
			$this->HREF = $prop['HREF'];
		if ($tag == 'BR')
			$this->Ln(5);
		if ($tag == 'P')
			$this->ALIGN = $prop['ALIGN'];
		if ($tag == 'HR') {
			if (!empty($prop['WIDTH']))
				$Width = $prop['WIDTH'];
			else
				$Width = $this->w - $this->lMargin - $this->rMargin;
			$this->Ln(2);
			$x = $this->GetX();
			$y = $this->GetY();
			$this->SetLineWidth(0.4);
			$this->Line($x, $y, $x + $Width, $y);
			$this->SetLineWidth(0.2);
			$this->Ln(2);
		}
	}

	function CloseTag($tag)
	{
		//Closing tag
		if ($tag == 'B' || $tag == 'I' || $tag == 'U')
			$this->SetStyle($tag, false);
		if ($tag == 'A')
			$this->HREF = '';
		if ($tag == 'P')
			$this->ALIGN = '';
	}

	function SetStyle($tag, $enable)
	{
		//Modify style and select corresponding font
		$this->$tag += ($enable ? 1 : -1);
		$style = '';
		foreach (array('B', 'I', 'U') as $s)
			if ($this->$s > 0)
				$style .= $s;
		$this->SetFont('', $style);
	}

	function PutLink($URL, $txt)
	{
		//Put a hyperlink
		$this->SetTextColor(0, 0, 255);
		$this->SetStyle('U', true);
		$this->Write(5, $txt, $URL);
		$this->SetStyle('U', false);
		$this->SetTextColor(0);
	}


	//desde aqui para abajo la tabla desde mysql
	function TableHeader()
	{
		$this->SetFont('Arial', 'B', 10);
		$this->SetX($this->TableX);
		$fill = !empty($this->HeaderColor);
		if ($fill)
			$this->SetFillColor($this->HeaderColor[0], $this->HeaderColor[1], $this->HeaderColor[2]);
		foreach ($this->aCols as $col)
			$this->Cell($col['w'], 6, $col['c'], 1, 0, 'L', $fill);
		$this->Ln();
	}

	function Row($data, $tipo)
	{
		$this->SetX($this->TableX);
		$ci = $this->ColorIndex;
		$fill = !empty($this->RowColors[$ci]);
		if ($fill)
			$this->SetFillColor($this->RowColors[$ci][0], $this->RowColors[$ci][1], $this->RowColors[$ci][2]);
		foreach ($this->aCols as $col)
			if ($tipo == 'cascada') {
				$this->Cell($col['w'], 5, $data[$col['f']], 1, 0, $col['a'], $fill);
			} else {
				$this->MultiCell($col['w'], 5, $data[$col['f']], 1, 1, $col['a'], $fill);
			}
		$this->Ln();
		$this->ColorIndex = 1 - $ci;
	}

	function CalcWidths($width, $align)
	{
		// Compute the widths of the columns
		$TableWidth = 0;
		foreach ($this->aCols as $i => $col) {
			$w = $col['w'];
			if ($w == -1)
				$w = $width / count($this->aCols);
			elseif (substr($w, -1) == '%')
				$w = $w / 100 * $width;
			$this->aCols[$i]['w'] = $w;
			$TableWidth += $w;
		}
		// Compute the abscissa of the table
		if ($align == 'C')
			$this->TableX = max(($this->w - $TableWidth) / 2, 0);
		elseif ($align == 'R')
			$this->TableX = max($this->w - $this->rMargin - $TableWidth, 0);
		else
			$this->TableX = $this->lMargin;
	}

	function AddCol($field = -1, $width = -1, $caption = '', $align = 'L')
	{
		// Add a column to the table
		if ($field == -1)
			$field = count($this->aCols);
		$this->aCols[] = array('f' => $field, 'c' => $caption, 'w' => $width, 'a' => $align);
	}

	function Table($link, $query, $prop = array(), $tipo)
	{
		// Execute query
		$res = mysqli_query($link, $query) or die('Error: ' . mysqli_error($link) . "<br>Query: $query");
		// Add all columns if none was specified
		if (count($this->aCols) == 0) {
			$nb = mysqli_num_fields($res);
			for ($i = 0; $i < $nb; $i++)
				$this->AddCol();
		}
		// Retrieve column names when not specified
		foreach ($this->aCols as $i => $col) {
			if ($col['c'] == '') {
				if (is_string($col['f']))
					$this->aCols[$i]['c'] = ucfirst($col['f']);
				else
					$this->aCols[$i]['c'] = ucfirst(mysqli_fetch_field_direct($res, $col['f'])->name);
			}
		}
		// Handle properties
		if (!isset($prop['width']))
			$prop['width'] = 0;
		if ($prop['width'] == 0)
			$prop['width'] = $this->w - $this->lMargin - $this->rMargin;
		if (!isset($prop['align']))
			$prop['align'] = 'C';
		if (!isset($prop['padding']))
			$prop['padding'] = $this->cMargin;
		$cMargin = $this->cMargin;
		$this->cMargin = $prop['padding'];
		if (!isset($prop['HeaderColor']))
			$prop['HeaderColor'] = array();
		$this->HeaderColor = $prop['HeaderColor'];
		if (!isset($prop['color1']))
			$prop['color1'] = array();
		if (!isset($prop['color2']))
			$prop['color2'] = array();
		$this->RowColors = array($prop['color1'], $prop['color2']);
		// Compute column widths
		$this->CalcWidths($prop['width'], $prop['align']);
		// Print header
		$this->TableHeader();
		// Print rows
		$this->SetFont('Arial', '', 8);
		$this->ColorIndex = 0;
		$this->ProcessingTable = true;
		while ($row = mysqli_fetch_array($res))
			$this->Row($row, $tipo);
		$this->ProcessingTable = false;
		$this->cMargin = $cMargin;
		$this->aCols = array();
	}


	//esto es para encajar la imagen

	function icreate($filename)
	{
		$isize = getimagesize($filename);
		if ($isize['mime'] == 'image/jpeg')
			return imagecreatefromjpeg($filename);
		elseif ($isize['mime'] == 'image/png')
			return imagecreatefrompng($filename);
		/* Add as many formats as you can */
	}

	/**
	 * Simple image resample into new image
	 *
	 * @param $image Image resource
	 * @param $width
	 * @param $height
	 */
	function simpleresize($image, $width, $height)
	{
		$new = imageCreateTrueColor($width, $height);
		imagecopyresampled($new, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
		return $new;
	}


	function imagen_optimizada($filename, $width, $height)
	{
		$imgh = $this->icreate($filename);
		$imgr = $this->simpleresize($imgh, $width, $height);
		return $imgr;
	}
	//hasta aqui para encajar la imagen
}
