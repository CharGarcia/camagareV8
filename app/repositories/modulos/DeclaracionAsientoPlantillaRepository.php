<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;

/**
 * Plantilla sugerida de cuentas contables por casillero, compartida entre
 * Declaración de IVA y Declaración de Retenciones. Ver migración
 * 20260814c_declaracion_asiento_plantilla.sql.
 */
class DeclaracionAsientoPlantillaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('declaracion_asiento_plantilla');
    }

    protected function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /**
     * @return array<string, array{id_cuenta_contable:int, lado:string, codigo_cuenta:?string, nombre_cuenta:?string}>
     *         indexado por casillero.
     */
    public function getPlantilla(int $idEmpresa, string $tipoDeclaracion): array
    {
        $sql = "SELECT p.casillero, p.id_cuenta_contable, p.lado, pc.codigo AS codigo_cuenta, pc.nombre AS nombre_cuenta
                FROM declaracion_asiento_plantilla p
                LEFT JOIN plan_cuentas pc ON pc.id = p.id_cuenta_contable
                WHERE p.id_empresa = :emp AND p.tipo_declaracion = :tipo AND p.eliminado = false";
        $rows = $this->query($sql, [':emp' => $idEmpresa, ':tipo' => $tipoDeclaracion])->fetchAll(\PDO::FETCH_ASSOC);

        $plantilla = [];
        foreach ($rows as $r) {
            $plantilla[$r['casillero']] = [
                'id_cuenta_contable' => (int) $r['id_cuenta_contable'],
                'lado'               => $r['lado'],
                'codigo_cuenta'      => $r['codigo_cuenta'],
                'nombre_cuenta'      => $r['nombre_cuenta'],
            ];
        }
        return $plantilla;
    }

    /**
     * Upsert por (id_empresa, tipo_declaracion, casillero): cada guardado de asiento
     * actualiza la sugerencia para el próximo período.
     *
     * @param array<int, array{casillero:string, id_cuenta_contable:int, lado:string}> $lineas
     */
    public function guardarPlantilla(int $idEmpresa, string $tipoDeclaracion, array $lineas, int $idUsuario): void
    {
        foreach ($lineas as $l) {
            $casillero = trim((string) ($l['casillero'] ?? ''));
            $idCuenta  = (int) ($l['id_cuenta_contable'] ?? 0);
            $lado      = ($l['lado'] ?? '') === 'haber' ? 'haber' : 'debe';
            if ($casillero === '' || $idCuenta <= 0) {
                continue;
            }

            $sql = "INSERT INTO declaracion_asiento_plantilla
                        (id_empresa, tipo_declaracion, casillero, id_cuenta_contable, lado, created_by, updated_by)
                    VALUES (:emp, :tipo, :cas, :cuenta, :lado, :usr, :usr)
                    ON CONFLICT (id_empresa, tipo_declaracion, casillero) WHERE eliminado = false
                    DO UPDATE SET id_cuenta_contable = EXCLUDED.id_cuenta_contable,
                                  lado = EXCLUDED.lado,
                                  updated_by = EXCLUDED.updated_by,
                                  updated_at = now()";
            $this->query($sql, [
                ':emp' => $idEmpresa, ':tipo' => $tipoDeclaracion, ':cas' => $casillero,
                ':cuenta' => $idCuenta, ':lado' => $lado, ':usr' => $idUsuario,
            ]);
        }
    }
}
