<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class GuiaRemisionRules
{
    public function validarGuardar(array $data): void
    {
        if (empty($data['id_establecimiento'])) {
            throw new \InvalidArgumentException('Seleccione el establecimiento.');
        }
        if (empty($data['id_punto_emision'])) {
            throw new \InvalidArgumentException('Seleccione el punto de emisión.');
        }
        if (empty($data['secuencial'])) {
            throw new \InvalidArgumentException('El secuencial es requerido.');
        }
        if (empty($data['id_cliente'])) {
            throw new \InvalidArgumentException('Seleccione el destinatario (cliente).');
        }
        if (empty($data['id_transportista'])) {
            throw new \InvalidArgumentException('Seleccione el transportista.');
        }
        if (empty(trim($data['placa'] ?? ''))) {
            throw new \InvalidArgumentException('Ingrese la placa del vehículo transportista.');
        }
        if (empty($data['fecha_emision'])) {
            throw new \InvalidArgumentException('La fecha de emisión es requerida.');
        }
        if (empty($data['fecha_inicio_transporte'])) {
            throw new \InvalidArgumentException('La fecha de inicio de transporte es requerida.');
        }
        if (empty($data['fecha_fin_transporte'])) {
            throw new \InvalidArgumentException('La fecha de fin de transporte es requerida.');
        }
        // Orden lógico del traslado: emisión → salida → llegada.
        if (strtotime($data['fecha_inicio_transporte']) < strtotime($data['fecha_emision'])) {
            throw new \InvalidArgumentException('La fecha de inicio de transporte no puede ser anterior a la fecha de emisión.');
        }
        if (strtotime($data['fecha_fin_transporte']) < strtotime($data['fecha_inicio_transporte'])) {
            throw new \InvalidArgumentException('La fecha de fin de transporte no puede ser anterior a la fecha de inicio.');
        }
        $errorSustento = self::errorDocSustento($data);
        if ($errorSustento !== null) {
            throw new \InvalidArgumentException($errorSustento);
        }
        if (empty(trim($data['direccion_partida'] ?? ''))) {
            throw new \InvalidArgumentException('Ingrese la dirección de partida.');
        }
        if (empty(trim($data['direccion_destino'] ?? ''))) {
            throw new \InvalidArgumentException('Ingrese la dirección de destino.');
        }
        if (empty(trim($data['motivo_traslado'] ?? ''))) {
            throw new \InvalidArgumentException('Ingrese el motivo del traslado.');
        }
        if (empty(trim($data['ruta'] ?? ''))) {
            throw new \InvalidArgumentException('Ingrese la ruta del traslado.');
        }
        if (empty(trim($data['cod_establecimiento_destino'] ?? ''))) {
            throw new \InvalidArgumentException('Ingrese el código del establecimiento de destino.');
        }

        $detalles = $data['detalles'] ?? [];
        if (empty($detalles) || !is_array($detalles)) {
            throw new \InvalidArgumentException('Debe agregar al menos un producto a la guía de remisión.');
        }
        foreach ($detalles as $i => $d) {
            $num = $i + 1;
            if (empty(trim($d['descripcion'] ?? ''))) {
                throw new \InvalidArgumentException("Fila {$num}: la descripción del producto es requerida.");
            }
            $cantidad = (float) ($d['cantidad'] ?? 0);
            if ($cantidad <= 0) {
                throw new \InvalidArgumentException("Fila {$num}: la cantidad debe ser mayor a cero.");
            }
        }

        // Longitudes y decimales de la Ficha Técnica del SRI. La información
        // adicional ya la revisa validarAdicionales(), que además impone un
        // límite propio más estricto al nombre del campo.
        $errores = \App\Helpers\SriFichaTecnica::erroresDetalles($detalles);
        if ($errores) {
            throw new \InvalidArgumentException(implode(' ', $errores));
        }
    }

    /**
     * Documento de sustento: si se eligió un tipo (factura, liquidación, NC…),
     * el número y la fecha de ese documento son obligatorios — el SRI rechaza la
     * guía con un error de fecha cuando llega <codDocSustento> sin su número ni su
     * fecha. Con "Sin documento de sustento" (tipo vacío) no se pide nada y esos
     * datos no viajan en el XML. La usa también el envío al SRI, para que un
     * borrador guardado antes de esta regla avise lo mismo en vez del error del SRI.
     *
     * @return string|null Mensaje del primer error, o null si está correcto.
     */
    public static function errorDocSustento(array $data): ?string
    {
        $cod = trim((string) ($data['cod_doc_sustento'] ?? ''));
        if ($cod === '') {
            return null;
        }
        $num = trim((string) ($data['num_doc_sustento'] ?? ''));
        if ($num === '') {
            return 'Ingrese el número del documento de sustento o elija «Sin documento sustento» en el tipo.';
        }
        if (!preg_match('/^\d{3}-\d{3}-\d{9}$/', $num)) {
            return 'El número del documento de sustento debe tener el formato 000-000-000000000.';
        }
        $fecha = trim((string) ($data['fecha_emision_doc_sustento'] ?? ''));
        if ($fecha === '' || strtotime($fecha) === false) {
            return 'Ingrese la fecha del documento de sustento.';
        }
        $inicio = trim((string) ($data['fecha_inicio_transporte'] ?? ''));
        if ($inicio !== '' && strtotime(substr($fecha, 0, 10)) > strtotime(substr($inicio, 0, 10))) {
            return 'La fecha del documento de sustento no puede ser posterior a la fecha de inicio de transporte.';
        }
        return null;
    }

    public function validarAdicionales(array $adicionales): void
    {
        if (count($adicionales) > 15) {
            throw new \InvalidArgumentException('La información adicional no puede superar 15 campos.');
        }
        $vistos = [];
        foreach ($adicionales as $a) {
            $nombre = trim($a['nombre'] ?? '');
            $valor  = trim($a['valor']  ?? '');
            if ($nombre === '') {
                throw new \InvalidArgumentException('El nombre del campo adicional no puede estar vacío.');
            }
            if (mb_strlen($nombre) > 30) {
                throw new \InvalidArgumentException("El nombre de campo adicional '{$nombre}' supera 30 caracteres.");
            }
            if ($valor === '') {
                throw new \InvalidArgumentException("El valor del campo '{$nombre}' no puede estar vacío.");
            }
            if (mb_strlen($valor) > 300) {
                throw new \InvalidArgumentException("El valor del campo '{$nombre}' supera 300 caracteres.");
            }
            $key = mb_strtolower($nombre);
            if (isset($vistos[$key])) {
                throw new \InvalidArgumentException("El campo adicional '{$nombre}' está duplicado.");
            }
            $vistos[$key] = true;
        }
    }
}
