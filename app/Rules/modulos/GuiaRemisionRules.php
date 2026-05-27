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
        if (strtotime($data['fecha_fin_transporte']) < strtotime($data['fecha_inicio_transporte'])) {
            throw new \InvalidArgumentException('La fecha de fin de transporte no puede ser anterior a la fecha de inicio.');
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
