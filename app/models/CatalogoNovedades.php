<?php

declare(strict_types=1);

namespace App\models;

/**
 * Catálogo fijo (en código) de tipos de novedades de nómina y de motivos de
 * aviso de salida. No es una tabla: son valores estándar (IESS/SRI) usados por
 * la vista (selects), las reglas (validación) y el servicio (denormalizar nombre).
 */
final class CatalogoNovedades
{
    /** Código del tipo "Aviso de salida" (lleva motivo y no lleva valor). */
    public const COD_AVISO_SALIDA = '14';

    /** Tipos cuyo "valor" representa cantidad de horas. */
    public const CODS_HORAS = ['4', '5', '6'];

    /** Tipos cuyo "valor" representa cantidad de días. */
    public const CODS_DIAS = ['10'];

    /**
     * Tipos que solo afectan al rol MENSUAL, sin importar el "afecta a" que se elija:
     * los días no laborados (restan del sueldo ganado del mes) y el aviso de salida.
     * En la quincena y en la semana solo hay ingresos (con o sin IESS) y descuentos.
     */
    public const CODS_SOLO_ROL = ['10', self::COD_AVISO_SALIDA];

    public static function soloRolMensual(string $codigo): bool
    {
        return in_array($codigo, self::CODS_SOLO_ROL, true);
    }

    /**
     * Ingresos en los que el usuario marca si aportan al IESS (Sí / No), igual que
     * en los rubros fijos del empleado: Otros Ingresos y las horas nocturnas,
     * suplementarias y extraordinarias. En los demás tipos la marca no aplica.
     */
    public const CODS_OPCION_IESS = ['1', '4', '5', '6'];

    public static function admiteOpcionIess(string $codigo): bool
    {
        return in_array($codigo, self::CODS_OPCION_IESS, true);
    }

    /**
     * Si aporta al IESS una novedad que NO trae la marca (registrada antes de que
     * existiera la opción, migrada o de una plantilla que no la tiene): lo que el
     * rol hacía siempre, que las horas aportan y Otros Ingresos no.
     */
    public static function aportaIessPorDefecto(string $codigo): bool
    {
        return in_array($codigo, self::CODS_HORAS, true);
    }

    /**
     * Marca efectiva de una novedad: la guardada (bool, 't'/'f', 'si'/'no'...) o,
     * si no tiene, la del tipo. Siempre false en los tipos sin la opción.
     */
    public static function aportaIess(string $codigo, $marca): bool
    {
        if (!self::admiteOpcionIess($codigo)) {
            return false;
        }
        if ($marca === null || $marca === '') {
            return self::aportaIessPorDefecto($codigo);
        }
        if (is_bool($marca)) {
            return $marca;
        }
        return in_array(mb_strtolower(trim((string) $marca)), ['1', 't', 'true', 'si', 'sí', 's'], true);
    }

    /**
     * Para mostrar en el listado y en los reportes: null si el tipo no lleva la
     * opción; si la lleva, si el ingreso realmente paga IESS (está marcado y el
     * empleado aporta al IESS; sin dato del empleado, se asume que aporta).
     */
    public static function pagaIess(string $codigo, $marca, $empleadoAporta): ?bool
    {
        if (!self::admiteOpcionIess($codigo)) {
            return null;
        }
        $empAporta = $empleadoAporta === null || in_array($empleadoAporta, [true, 't', 1, '1', 'true'], true);
        return $empAporta && self::aportaIess($codigo, $marca);
    }

    /**
     * Tipos que NO se descuentan en el rol al registrarse: se ENTREGAN al empleado y
     * se pagan primero con un egreso (aparecen en Egresos → Nómina); el rol descuenta
     * solo lo pagado por egreso. Solo el ANTICIPO (3), que es de un único mes.
     * Los préstamos (7/8/9) se manejan como CUOTA: descuento directo en el rol (una
     * novedad por cuota, manual o por Excel), y el desembolso del capital es un egreso aparte.
     */
    public const CODS_PAGO_EGRESO = ['3'];

    public static function esPagoPorEgreso(string $codigo): bool
    {
        return in_array($codigo, self::CODS_PAGO_EGRESO, true);
    }

    /**
     * Préstamos cuya cuota descuenta directo SOLO si el préstamo ya fue desembolsado
     * (pagado) por la EMPRESA. Solo el Préstamo Empresa (9): en Quirografario (7) e
     * Hipotecario (8) el desembolso lo hace el IESS/banco directamente al empleado, no
     * la empresa, así que su cuota descuenta directo (como un Descuento normal).
     */
    public const CODS_PRESTAMO = ['9'];

    public static function esPrestamo(string $codigo): bool
    {
        return in_array($codigo, self::CODS_PRESTAMO, true);
    }

    public const TIPOS = [
        ['codigo' => '1',  'nombre' => 'Otros Ingresos'],
        ['codigo' => '2',  'nombre' => 'Descuento'],
        ['codigo' => '3',  'nombre' => 'Anticipo'],
        ['codigo' => '4',  'nombre' => 'Horas Nocturnas'],
        ['codigo' => '5',  'nombre' => 'Horas Suplementarias'],
        ['codigo' => '6',  'nombre' => 'Horas Extraordinarias'],
        ['codigo' => '7',  'nombre' => 'Préstamo Quirografario'],
        ['codigo' => '8',  'nombre' => 'Préstamo hipotecario'],
        ['codigo' => '9',  'nombre' => 'Préstamo Empresa'],
        ['codigo' => '10', 'nombre' => 'Días no laborados'],
        ['codigo' => '14', 'nombre' => 'Aviso de salida'],
    ];

    public const MOTIVOS_SALIDA = [
        ['codigo' => 'T', 'nombre' => 'Terminación del contrato'],
        ['codigo' => 'V', 'nombre' => 'Renuncia voluntaria'],
        ['codigo' => 'B', 'nombre' => 'Visto bueno'],
        ['codigo' => 'R', 'nombre' => 'Despido unilateral por parte del empleador'],
        ['codigo' => 'S', 'nombre' => 'Suspensión de partida'],
        ['codigo' => 'D', 'nombre' => 'Desaparición del puesto dentro de la estructura de la empresa'],
        ['codigo' => 'I', 'nombre' => 'Incapacidad permanente del trabajador'],
        ['codigo' => 'F', 'nombre' => 'Muerte del trabajador'],
        ['codigo' => 'A', 'nombre' => 'Abandono voluntario'],
    ];

    /** A qué pago afecta la novedad. */
    public const APLICA_EN = [
        'rol'      => 'Rol de Pagos',
        'quincena' => 'Quincena',
        'semanal'  => 'Pago Semanal',
    ];

    public const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public static function tipos(): array
    {
        return self::TIPOS;
    }

    public static function motivosSalida(): array
    {
        return self::MOTIVOS_SALIDA;
    }

    public static function aplicaEn(): array
    {
        return self::APLICA_EN;
    }

    public static function esAplicaEnValido(string $v): bool
    {
        return array_key_exists($v, self::APLICA_EN);
    }

    public static function nombreAplicaEn(string $v): string
    {
        return self::APLICA_EN[$v] ?? $v;
    }

    public static function esTipoValido(string $codigo): bool
    {
        foreach (self::TIPOS as $t) {
            if ($t['codigo'] === $codigo) return true;
        }
        return false;
    }

    public static function nombreTipo(string $codigo): ?string
    {
        foreach (self::TIPOS as $t) {
            if ($t['codigo'] === $codigo) return $t['nombre'];
        }
        return null;
    }

    public static function esMotivoValido(string $codigo): bool
    {
        foreach (self::MOTIVOS_SALIDA as $m) {
            if ($m['codigo'] === $codigo) return true;
        }
        return false;
    }

    public static function nombreMotivo(string $codigo): ?string
    {
        foreach (self::MOTIVOS_SALIDA as $m) {
            if ($m['codigo'] === $codigo) return $m['nombre'];
        }
        return null;
    }

    public static function esAvisoSalida(string $codigo): bool
    {
        return $codigo === self::COD_AVISO_SALIDA;
    }

    /** 'horas' | 'dias' | 'monto' | 'ninguno' (aviso de salida). */
    public static function unidadValor(string $codigo): string
    {
        if (self::esAvisoSalida($codigo)) return 'ninguno';
        if (in_array($codigo, self::CODS_HORAS, true)) return 'horas';
        if (in_array($codigo, self::CODS_DIAS, true)) return 'dias';
        return 'monto';
    }

    /** Etiqueta del campo "valor" según el tipo. */
    public static function labelValor(string $codigo): string
    {
        return match (self::unidadValor($codigo)) {
            'horas'   => 'N° de Horas',
            'dias'    => 'N° de Días',
            'monto'   => 'Monto ($)',
            default   => '',
        };
    }

    /** Representación del valor para listados/PDF según el tipo. */
    public static function formatValor(string $codigo, $valor): string
    {
        $v = (float) $valor;
        return match (self::unidadValor($codigo)) {
            'horas'  => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . ' h',
            'dias'   => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . ' días',
            'monto'  => '$ ' . number_format($v, 2),
            default  => '—',
        };
    }

    /** Config compacta para el frontend (JS). */
    public static function paraJs(): array
    {
        $unidades = [];
        $labels = [];
        foreach (self::TIPOS as $t) {
            $unidades[$t['codigo']] = self::unidadValor($t['codigo']);
            $labels[$t['codigo']]   = self::labelValor($t['codigo']);
        }
        $iessPorDefecto = [];
        foreach (self::CODS_OPCION_IESS as $cod) {
            $iessPorDefecto[$cod] = self::aportaIessPorDefecto($cod);
        }
        return [
            'cod_aviso_salida' => self::COD_AVISO_SALIDA,
            'unidades'         => $unidades,
            'labels'           => $labels,
            'iess_por_defecto' => $iessPorDefecto, // solo los tipos con la opción
            'cods_solo_rol'    => self::CODS_SOLO_ROL,
        ];
    }
}
