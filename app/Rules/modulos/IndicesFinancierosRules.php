<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\repositories\modulos\IndicesFinancierosRepository;

class IndicesFinancierosRules
{
    private const GRUPOS_NIVEL1 = ['ACTIVO_CORRIENTE', 'ACTIVO_NO_CORRIENTE', 'PASIVO_CORRIENTE', 'PASIVO_NO_CORRIENTE'];

    /** Fuentes reconocidas por el evaluador (ver IndicesFinancierosService::resolverFuente). */
    public const FUENTES_VALIDAS = [
        'ACTIVO_TOTAL', 'PASIVO_TOTAL', 'PATRIMONIO', 'INGRESOS', 'COSTOS', 'GASTOS', 'UTILIDAD_BRUTA', 'UTILIDAD_NETA',
        'CXC_SALDO', 'CXP_SALDO', 'INVENTARIO_VALOR', 'ACTIVO_FIJO_NETO', 'VENTAS', 'COMPRAS',
    ];

    private const CATEGORIAS_VALIDAS = ['liquidez', 'endeudamiento', 'rentabilidad', 'actividad'];
    private const UNIDADES_VALIDAS = ['razon', 'porcentaje', 'dias', 'monto'];
    private const PROFUNDIDAD_MAXIMA = 8;

    public function __construct(private IndicesFinancierosRepository $repository)
    {
    }

    public function validarClasificacion(string $grupo): void
    {
        if (!in_array($grupo, self::GRUPOS_NIVEL1, true)) {
            throw new \Exception('Grupo de clasificación inválido: ' . $grupo);
        }
    }

    public function validarGrupo(array $data, ?int $idExcluir = null): void
    {
        $codigo = trim($data['codigo'] ?? '');
        $nombre = trim($data['nombre'] ?? '');

        if ($codigo === '' || !preg_match('/^[A-Za-z0-9_]+$/', $codigo)) {
            throw new \Exception('El código del grupo es obligatorio y solo admite letras, números y guion bajo.');
        }
        if ($nombre === '') {
            throw new \Exception('El nombre del grupo es obligatorio.');
        }
        if ($this->repository->codigoGrupoExiste((int) $data['id_empresa'], $codigo, $idExcluir)) {
            throw new \Exception('Ya existe un grupo con ese código.');
        }
    }

    public function validarIndice(array $data, ?int $idExcluir = null, array $gruposDisponibles = []): void
    {
        $codigo = trim($data['codigo'] ?? '');
        $nombre = trim($data['nombre'] ?? '');
        $categoria = $data['categoria'] ?? '';
        $unidad = $data['unidad'] ?? 'razon';

        if ($codigo === '' || !preg_match('/^[A-Za-z0-9_]+$/', $codigo)) {
            throw new \Exception('El código del índice es obligatorio y solo admite letras, números y guion bajo.');
        }
        if ($nombre === '') {
            throw new \Exception('El nombre del índice es obligatorio.');
        }
        if (!in_array($categoria, self::CATEGORIAS_VALIDAS, true)) {
            throw new \Exception('Categoría inválida.');
        }
        if (!in_array($unidad, self::UNIDADES_VALIDAS, true)) {
            throw new \Exception('Unidad inválida.');
        }
        if ($this->repository->codigoIndiceExiste((int) $data['id_empresa'], $codigo, $idExcluir)) {
            throw new \Exception('Ya existe un índice con ese código.');
        }

        $formula = $data['formula'];
        if (is_string($formula)) {
            $formula = json_decode($formula, true);
            if (!is_array($formula)) {
                throw new \Exception('La fórmula no es un JSON válido.');
            }
        }

        $gruposValidos = array_merge(self::GRUPOS_NIVEL1, $gruposDisponibles);
        $this->validarNodoFormula($formula, $gruposValidos, 0);
    }

    private function validarNodoFormula($nodo, array $gruposValidos, int $profundidad): void
    {
        if ($profundidad > self::PROFUNDIDAD_MAXIMA) {
            throw new \Exception('La fórmula supera la profundidad máxima permitida.');
        }
        if (!is_array($nodo)) {
            throw new \Exception('Nodo de fórmula inválido.');
        }

        if (array_key_exists('const', $nodo)) {
            if (!is_numeric($nodo['const'])) {
                throw new \Exception('El valor constante debe ser numérico.');
            }
            return;
        }

        if (array_key_exists('grupo', $nodo)) {
            if (!in_array($nodo['grupo'], $gruposValidos, true)) {
                throw new \Exception('Grupo de cuentas no reconocido en la fórmula: ' . $nodo['grupo']);
            }
            return;
        }

        if (array_key_exists('fuente', $nodo)) {
            if (!in_array($nodo['fuente'], self::FUENTES_VALIDAS, true)) {
                throw new \Exception('Fuente no reconocida en la fórmula: ' . $nodo['fuente']);
            }
            return;
        }

        if (array_key_exists('op', $nodo)) {
            if (!in_array($nodo['op'], ['+', '-', '*', '/'], true)) {
                throw new \Exception('Operador no permitido en la fórmula: ' . $nodo['op']);
            }
            if (!isset($nodo['left']) || !isset($nodo['right'])) {
                throw new \Exception('Operación incompleta en la fórmula (falta left/right).');
            }
            $this->validarNodoFormula($nodo['left'], $gruposValidos, $profundidad + 1);
            $this->validarNodoFormula($nodo['right'], $gruposValidos, $profundidad + 1);
            return;
        }

        throw new \Exception('Nodo de fórmula no reconocido (debe tener const, grupo, fuente u op).');
    }
}
