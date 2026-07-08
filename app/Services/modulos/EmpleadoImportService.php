<?php

declare(strict_types=1);

namespace App\Services\modulos;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PDO;
use Exception;

/**
 * Importa empleados desde un Excel reutilizando EmpleadoService::crear
 * (validaciones, defaults, sincronización y auditoría). Fila por fila, con
 * reporte de errores por número de fila.
 *
 * Columnas (orden): TIPO_ID, IDENTIFICACION, NOMBRES_APELLIDOS, EMAIL, TELEFONO,
 * DIRECCION, FECHA_NACIMIENTO, SEXO, CARGO, DEPARTAMENTO, SUELDO_BASE,
 * VALOR_SEMANAL, VALOR_QUINCENA, REGION, APORTA_IESS, FONDOS_RESERVA,
 * DECIMO_TERCERO, DECIMO_CUARTO, BANCO, TIPO_CUENTA, NUMERO_CUENTA, FECHA_INGRESO.
 */
class EmpleadoImportService
{
    private EmpleadoService $svc;
    private PDO $db;
    private array $iess;

    public function __construct(EmpleadoService $svc, PDO $db)
    {
        $this->svc = $svc;
        $this->db = $db;
        $this->iess = $svc->getIessDefaults();
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
                continue;
            }
            $nf = $i + 1;
            try {
                $this->svc->crear($this->mapearFila($fila, $idEmpresa, $idUsuario));
                $creadas++;
            } catch (\Throwable $e) {
                $errores[] = ['fila' => $nf, 'error' => $e->getMessage()];
            }
        }
        return ['creadas' => $creadas, 'errores' => $errores, 'total' => $creadas + count($errores)];
    }

    private function mapearFila(array $f, int $idEmpresa, int $idUsuario): array
    {
        $tipoId = strtolower(trim((string) ($f[0] ?? '')));
        $tipoId = in_array($tipoId, ['pasaporte', 'pasapote'], true) ? 'pasaporte' : 'cedula';

        $sexo = strtoupper(trim((string) ($f[7] ?? '')));
        if (!in_array($sexo, ['M', 'F', 'O'], true)) $sexo = 'M';

        $region = strtolower(trim((string) ($f[13] ?? '')));
        if (!in_array($region, ['costa', 'sierra', 'oriente', 'insular'], true)) $region = 'costa';

        $aportaIess = strtolower(trim((string) ($f[14] ?? 'si')));
        $aportaIess = in_array($aportaIess, ['no', 'n', 'false', '0'], true) ? 'no' : 'si';

        $fondos = strtolower(trim((string) ($f[15] ?? '')));
        if (!in_array($fondos, ['rol', 'planilla', 'no_se_paga'], true)) $fondos = 'no_se_paga';

        $dt = strtolower(trim((string) ($f[16] ?? '')));
        $dt = $dt === 'mensualiza' ? 'mensualiza' : 'acumula';
        $dc = strtolower(trim((string) ($f[17] ?? '')));
        $dc = $dc === 'mensualiza' ? 'mensualiza' : 'acumula';

        $tipoCuenta = strtolower(trim((string) ($f[19] ?? '')));
        if (!in_array($tipoCuenta, ['ahorros', 'corriente', 'virtual'], true)) $tipoCuenta = '';

        // Fecha de ingreso → crea el periodo laboral (tabla empleado_periodos).
        $fechaIngreso = $this->normalizarFecha($f[21] ?? '');
        $periodos = $fechaIngreso !== '' ? [['fecha_ingreso' => $fechaIngreso, 'fecha_salida' => null, 'motivo_salida' => null]] : [];

        return [
            'id_empresa'            => $idEmpresa,
            'id_usuario'            => $idUsuario,
            'tipo_id'               => $tipoId,
            'identificacion'        => trim((string) ($f[1] ?? '')),
            'nombres_apellidos'     => trim((string) ($f[2] ?? '')),
            'email'                 => trim((string) ($f[3] ?? '')),
            'telefono'              => trim((string) ($f[4] ?? '')),
            'direccion'             => trim((string) ($f[5] ?? '')),
            'fecha_nacimiento'      => $this->normalizarFecha($f[6] ?? ''),
            'sexo'                  => $sexo,
            'cargo'                 => trim((string) ($f[8] ?? '')),
            'departamento'          => trim((string) ($f[9] ?? '')),
            'sueldo_base'           => (float) ($f[10] ?? 0),
            'valor_semanal'         => (float) ($f[11] ?? 0),
            'valor_quincena'        => (float) ($f[12] ?? 0),
            'region'                => $region,
            'aporta_iess'           => $aportaIess,
            'fondos_reserva'        => $fondos,
            'decimo_tercero'        => $dt,
            'decimo_cuarto'         => $dc,
            'aporte_personal'       => (float) ($this->iess['aporte_personal'] ?? 9.45),
            'aporte_patronal'       => (float) ($this->iess['aporte_patronal'] ?? 11.15),
            'id_banco_ecuador'      => $this->resolverBanco($f[18] ?? ''),
            'tipo_cuenta'           => $tipoCuenta,
            'numero_cuenta'         => trim((string) ($f[20] ?? '')),
            'estado'                => 'activo',
            'periodos'              => $periodos,
        ];
    }

    private function resolverBanco($nombre): ?int
    {
        $nombre = trim((string) $nombre);
        if ($nombre === '') return null;
        $st = $this->db->prepare("SELECT id FROM bancos_ecuador WHERE UPPER(TRIM(nombre_banco)) = UPPER(TRIM(?)) LIMIT 1");
        $st->execute([$nombre]);
        $id = $st->fetchColumn();
        if (!$id) {
            $st2 = $this->db->prepare("SELECT id FROM bancos_ecuador WHERE UPPER(nombre_banco) LIKE UPPER(?) ORDER BY id LIMIT 1");
            $st2->execute(['%' . $nombre . '%']);
            $id = $st2->fetchColumn();
        }
        if (!$id) throw new Exception("Banco no reconocido: '{$nombre}'.");
        return (int) $id;
    }

    private function normalizarFecha($valor): string
    {
        if (is_numeric($valor) && $valor > 30000) {
            try { return ExcelDate::excelToDateTimeObject((float) $valor)->format('Y-m-d'); } catch (\Throwable $e) {}
        }
        $s = trim((string) $valor);
        if ($s === '') return '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return substr($s, 0, 10);
        $ts = strtotime(str_replace('/', '-', $s));
        return $ts ? date('Y-m-d', $ts) : '';
    }
}
