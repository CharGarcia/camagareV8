<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * CRM: reglas de oportunidades. Las que dependen de datos (etapa del embudo,
 * contacto, cliente) reciben las filas ya leídas por el Service.
 */
class CrmOportunidadRules
{
    public const VALOR_MAXIMO = 999999999999.99; // numeric(14,2)

    public function validarDatos(array $d): void
    {
        $titulo = trim((string) ($d['titulo'] ?? ''));
        if ($titulo === '') {
            throw new \InvalidArgumentException('El título de la oportunidad es obligatorio.');
        }
        if (mb_strlen($titulo) > 200) {
            throw new \InvalidArgumentException('El título no puede superar 200 caracteres.');
        }
        if (empty($d['id_contacto'])) {
            throw new \InvalidArgumentException('Seleccione el contacto de la oportunidad.');
        }
        $valor = $d['valor_estimado'] ?? 0;
        if ($valor === '' || $valor === null) {
            $valor = 0;
        }
        if (!is_numeric($valor) || (float) $valor < 0 || (float) $valor > self::VALOR_MAXIMO) {
            throw new \InvalidArgumentException('El valor estimado debe ser un número mayor o igual a cero.');
        }
        $f = (string) ($d['fecha_cierre_estimada'] ?? '');
        if ($f !== '' && !$this->fechaValida($f)) {
            throw new \InvalidArgumentException('La fecha de cierre estimada no es válida.');
        }
    }

    /** La etapa debe existir, pertenecer al embudo y ser intermedia (abierta). */
    public function validarEtapaAbierta(?array $etapa, int $idEmbudo): void
    {
        if ($etapa === null || (int) $etapa['id_embudo'] !== $idEmbudo) {
            throw new \InvalidArgumentException('La etapa no pertenece al embudo seleccionado.');
        }
        if ($etapa['tipo'] !== 'abierta') {
            throw new \InvalidArgumentException('Para ganar o perder una oportunidad use las acciones Ganar / Perder.');
        }
    }

    public function validarEditable(array $op): void
    {
        if ($op['estado'] !== 'abierta') {
            throw new \InvalidArgumentException('La oportunidad está cerrada. Reábrala para modificarla.');
        }
    }

    /** Ganar exige cliente (decisión 7 del diseño). */
    public function validarGanar(array $op, ?array $cliente): void
    {
        $this->validarEditable($op);
        if ($cliente === null) {
            throw new \InvalidArgumentException('Para marcarla como ganada seleccione o cree el cliente.');
        }
    }

    public function validarPerder(array $op, ?array $motivo, string $detalle): void
    {
        $this->validarEditable($op);
        if ($motivo === null) {
            throw new \InvalidArgumentException('Seleccione el motivo por el que se perdió la oportunidad.');
        }
        if (mb_strlen($detalle) > 2000) {
            throw new \InvalidArgumentException('El detalle no puede superar 2000 caracteres.');
        }
    }

    public function validarReabrir(array $op): void
    {
        if ($op['estado'] === 'abierta') {
            throw new \InvalidArgumentException('La oportunidad ya está abierta.');
        }
    }

    public function validarOrden($orden): float
    {
        if ($orden === null || $orden === '') {
            return 0.0;
        }
        if (!is_numeric($orden) || !is_finite((float) $orden) || abs((float) $orden) > 1e9) {
            throw new \InvalidArgumentException('Posición no válida.');
        }
        return (float) $orden;
    }

    private function fechaValida(string $f): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $f);
        return $dt !== false && $dt->format('Y-m-d') === $f;
    }
}
