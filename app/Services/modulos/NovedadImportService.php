<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\NovedadRepository;
use App\models\CatalogoNovedades;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Exception;

/**
 * Importa novedades desde un Excel. Reutiliza NovedadService::crear (validaciones,
 * auditoría y transacción por fila). Procesa fila por fila y reporta los errores.
 *
 * Columnas esperadas (orden): IDENTIFICACION, TIPO, VALOR, MES, ANIO, AFECTA_A,
 * FECHA (opcional), OBSERVACION (opcional), MOTIVO (opcional).
 */
class NovedadImportService
{
    private NovedadService $svc;
    private NovedadRepository $repo;

    public function __construct(NovedadService $svc, NovedadRepository $repo)
    {
        $this->svc = $svc;
        $this->repo = $repo;
    }

    public function procesar(string $archivoTmp, int $idEmpresa, int $idUsuario): array
    {
        $spreadsheet = IOFactory::load($archivoTmp);
        $filas = $spreadsheet->getActiveSheet()->toArray();
        if (count($filas) <= 1) {
            throw new Exception('El archivo está vacío o solo contiene los encabezados.');
        }

        $creadas = 0;
        $errores = [];
        for ($i = 1; $i < count($filas); $i++) {
            $fila = $filas[$i];
            if (empty(array_filter($fila, fn($v) => trim((string) $v) !== ''))) {
                continue; // fila vacía
            }
            $nf = $i + 1;
            try {
                $data = $this->mapearFila($fila, $idEmpresa, $idUsuario, $nf);
                $this->svc->crear($data);
                $creadas++;
            } catch (\Throwable $e) {
                $errores[] = ['fila' => $nf, 'error' => $e->getMessage()];
            }
        }
        return ['creadas' => $creadas, 'errores' => $errores, 'total' => $creadas + count($errores)];
    }

    private function mapearFila(array $f, int $idEmpresa, int $idUsuario, int $nf): array
    {
        $ident   = trim((string) ($f[0] ?? ''));
        $tipoRaw = trim((string) ($f[1] ?? ''));
        $valor   = $f[2] ?? 0;
        $mes     = (int) ($f[3] ?? 0);
        $anio    = (int) ($f[4] ?? 0);
        $afecta  = trim((string) ($f[5] ?? 'rol'));
        $fecha   = $f[6] ?? '';
        $obs     = trim((string) ($f[7] ?? ''));
        $motivo  = trim((string) ($f[8] ?? ''));

        if ($ident === '') {
            throw new Exception("Falta la IDENTIFICACION del empleado.");
        }
        $idEmp = $this->repo->getIdEmpleadoPorIdentificacion($idEmpresa, $ident);
        if (!$idEmp) {
            throw new Exception("No existe un empleado con identificación '{$ident}'.");
        }

        return [
            'id_empresa'    => $idEmpresa,
            'id_usuario'    => $idUsuario,
            'id_empleado'   => $idEmp,
            'tipo_codigo'   => $this->resolverTipo($tipoRaw),
            'valor'         => (float) $valor,
            'periodo_mes'   => $mes,
            'periodo_anio'  => $anio,
            'aplica_en'     => $this->resolverAplicaEn($afecta),
            'fecha'         => $this->normalizarFecha($fecha),
            'observacion'   => $obs,
            'motivo_codigo' => $motivo !== '' ? $this->resolverMotivo($motivo) : '',
            'estado'        => 'activo',
        ];
    }

    private function resolverTipo(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') throw new Exception('Falta el TIPO de novedad.');
        if (CatalogoNovedades::esTipoValido($raw)) return $raw;
        foreach (CatalogoNovedades::TIPOS as $t) {
            if (mb_strtolower($t['nombre']) === mb_strtolower($raw)) return $t['codigo'];
        }
        throw new Exception("TIPO de novedad no reconocido: '{$raw}'.");
    }

    private function resolverAplicaEn(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));
        if ($raw === '') return 'rol';
        if (CatalogoNovedades::esAplicaEnValido($raw)) return $raw;
        foreach (CatalogoNovedades::APLICA_EN as $k => $label) {
            if (mb_strtolower($label) === $raw) return $k;
        }
        if (in_array($raw, ['mensual', 'rol de pagos'], true)) return 'rol';
        if (str_contains($raw, 'semana')) return 'semanal';
        if (str_contains($raw, 'quincena')) return 'quincena';
        throw new Exception("AFECTA_A no reconocido: '{$raw}' (use rol, quincena o semanal).");
    }

    private function resolverMotivo(string $raw): string
    {
        $raw = trim($raw);
        if (CatalogoNovedades::esMotivoValido($raw)) return $raw;
        foreach (CatalogoNovedades::MOTIVOS_SALIDA as $m) {
            if (mb_strtolower($m['nombre']) === mb_strtolower($raw)) return $m['codigo'];
        }
        throw new Exception("MOTIVO de salida no reconocido: '{$raw}'.");
    }

    private function normalizarFecha($valor): string
    {
        if (is_numeric($valor) && $valor > 30000) {
            try { return ExcelDate::excelToDateTimeObject((float) $valor)->format('Y-m-d'); } catch (\Throwable $e) {}
        }
        $s = trim((string) $valor);
        if ($s === '') return date('Y-m-d');
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return substr($s, 0, 10);
        $ts = strtotime(str_replace('/', '-', $s));
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }
}
