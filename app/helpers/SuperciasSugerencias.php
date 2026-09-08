<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Sugerencia de casilleros Supercías (ESF / ERI / columna ECP) para una cuenta del plan, a partir
 * de su código (clase) y su nombre. Es una ayuda para el usuario: NUNCA se aplica sola, la
 * confirma desde el diagnóstico de Estados Financieros.
 *
 * Fuente de los códigos: estructura oficial cargada en supercias_estructuras (ESF/ERI). Los gastos
 * se sugieren en el grupo administrativo (50202xx); si la cuenta cuelga de un grupo "ventas" o
 * "comercialización" se usa el grupo de gastos de venta (50201xx).
 */
final class SuperciasSugerencias
{
    /**
     * @param string $codigoCuenta  Código del plan (3.1.1.01.001).
     * @param string $nombre        Nombre de la cuenta.
     * @param string $nombrePadre   Nombre del grupo padre (nivel 4 o 3), para desempatar (ventas / administración).
     * @return array{esf:string, eri:string, ecp_columna:string, motivo:string}  Vacíos si no hay sugerencia.
     */
    public static function sugerir(string $codigoCuenta, string $nombre, string $nombrePadre = ''): array
    {
        $n = self::normalizar($nombre);
        $p = self::normalizar($nombrePadre);
        $clase = substr(trim($codigoCuenta), 0, 1);
        $has = fn(string ...$pal) => array_reduce($pal, fn($c, $w) => $c || str_contains($n, $w), false);
        $out = fn(string $esf, string $eri, string $motivo) => [
            'esf'         => $esf,
            'eri'         => $eri,
            'ecp_columna' => $esf !== '' && str_starts_with($esf, '3') ? SuperciasEcp::columnaDesdeEsf($esf) : '',
            'motivo'      => $motivo,
        ];
        $nada = ['esf' => '', 'eri' => '', 'ecp_columna' => '', 'motivo' => ''];

        switch ($clase) {
            case '1':
                if ($has('CAJA CHICA', 'FONDO FIJO')) return $out('1010101', '', 'Caja');
                if ($has('CAJA') && !$has('AHORRO')) return $out('1010101', '', 'Caja');
                if ($has('BANCO', 'CTA CTE', 'CUENTA CORRIENTE', 'AHORRO', 'COOPERATIVA', 'PRODUBANCO', 'PICHINCHA', 'GUAYAQUIL', 'PACIFICO', 'BOLIVARIANO', 'INTERNACIONAL', 'AUSTRO', 'MACHALA', 'DINERS', 'BANECUADOR', 'BCE'))
                    return $out($has('BANECUADOR', 'BCE', 'CENTRAL') ? '1010102' : '1010103', '', 'Bancos');
                if ($has('DEPRECIACION ACUM')) return $out('1020112', '', 'Depreciación acumulada de PPE');
                if ($has('AMORTIZACION ACUM')) return $out('1020405', '', 'Amortización acumulada de intangibles');
                if ($has('TERRENO')) return $out('1020101', '', 'Terrenos');
                if ($has('EDIFICIO', 'LOCAL COMERCIAL', 'INMUEBLE')) return $out('1020102', '', 'Edificios');
                if ($has('MUEBLE', 'ENSERES')) return $out('1020105', '', 'Muebles y enseres');
                if ($has('MAQUINARIA', 'EQUIPO DE TRABAJO', 'HERRAMIENTA')) return $out('1020106', '', 'Maquinaria y equipo');
                if ($has('COMPUT', 'LAPTOP', 'IMPRESORA', 'SERVIDOR')) return $out('1020108', '', 'Equipo de computación');
                if ($has('VEHICULO', 'CAMION', 'MOTO', 'AUTOMOVIL')) return $out('1020109', '', 'Vehículos');
                if ($has('INSTALACION')) return $out('1020104', '', 'Instalaciones');
                if ($has('SOFTWARE', 'LICENCIA', 'INTANGIBLE', 'MARCA', 'PATENTE')) return $out('1020402', '', 'Intangibles');
                if ($has('ANTICIPO') && $has('EMPLEAD', 'TRABAJADOR', 'SUELDO')) return $out('10102050221', '', 'Anticipos a empleados: otras cuentas por cobrar');
                if ($has('ANTICIPO') && $has('PROVEEDOR')) return $out('1010403', '', 'Anticipos a proveedores');
                if ($has('ANTICIPO') && $has('RENTA', 'IMPUESTO')) return $out('1010503', '', 'Anticipo de impuesto a la renta');
                if ($has('IVA')) return $out('1010501', '', 'Crédito tributario IVA (IVA en compras, retenciones de IVA recibidas)');
                if ($has('RETENCION', 'RET.') && $has('RENTA', 'FUENTE', 'IR')) return $out('1010502', '', 'Crédito tributario impuesto a la renta (retenciones recibidas)');
                if ($has('RETENCION')) return $out('1010501', '', 'Crédito tributario IVA');
                if ($has('CLIENTE', 'POR COBRAR', 'DOCUMENTOS POR COBRAR', 'CUENTAS POR COBRAR')) return $out('10102050201', '', 'Cuentas y documentos por cobrar a clientes');
                if ($has('SOCIO', 'ACCIONISTA') && $has('COBRAR', 'PRESTAMO')) return $out('101020601', '', 'Por cobrar a accionistas');
                if ($has('INVENTARIO', 'MERCADER', 'PRODUCTO TERMINADO', 'EXISTENCIA')) return $out('1010306', '', 'Inventario de mercaderías compradas a terceros');
                if ($has('MATERIA PRIMA')) return $out('1010301', '', 'Inventario de materia prima');
                if ($has('SUMINISTRO', 'REPUESTO')) return $out('1010311', '', 'Inventario de repuestos y suministros');
                if ($has('SEGURO') && $has('ANTICIPADO', 'PREPAGADO', 'PAGADO')) return $out('1010401', '', 'Seguros pagados por anticipado');
                if ($has('ARRIENDO', 'ARRENDAMIENTO') && $has('ANTICIPADO', 'PREPAGADO', 'PAGADO')) return $out('1010402', '', 'Arriendos pagados por anticipado');
                if ($has('GARANTIA')) return $out('1020802', '', 'Depósitos en garantía');
                if ($has('INVERSION', 'POLIZA', 'DEPOSITO A PLAZO')) return $out('1010203', '', 'Activos financieros al costo amortizado');
                if ($has('PROVISION') && $has('INCOBRABLE', 'CUENTAS')) return $out('1010207', '', 'Provisión por cuentas incobrables');
                return $nada;

            case '2':
                if ($has('IESS', 'SEGURIDAD SOCIAL', 'APORTE') && !$has('FUTURA')) return $out('2010703', '', 'Con el IESS');
                if ($has('PARTICIPACION') && $has('TRABAJ')) return $out('2010705', '', 'Participación trabajadores por pagar');
                if ($has('IMPUESTO A LA RENTA', 'IMP. RENTA', 'IMPUESTO RENTA') && $has('PAGAR')) return $out('2010702', '', 'Impuesto a la renta por pagar');
                if ($has('IVA', 'RETENCION', 'SRI', 'ADMINISTRACION TRIBUTARIA', 'IMPUESTO')) return $out('2010701', '', 'Con la administración tributaria (IVA, retenciones por pagar)');
                if ($has('DECIMO', 'VACACION', 'BENEFICIO', 'SUELDO', 'SALARIO', 'NOMINA', 'REMUNERACION', 'FONDO DE RESERVA', 'LIQUIDACION DE HABERES'))
                    return $out('2010704', '', 'Por beneficios de ley a empleados');
                if ($has('JUBILACION', 'DESAHUCIO')) return $out('2011201', '', 'Jubilación patronal');
                if ($has('DIVIDENDO', 'UTILIDADES POR PAGAR')) return $out('2010706', '', 'Dividendos por pagar');
                if ($has('ANTICIPO') && $has('CLIENTE')) return $out('2011001', '', 'Anticipos de clientes');
                if ($has('PRESTAMO', 'OBLIGACION') && $has('SOCIO', 'ACCIONISTA', 'RELACIONAD')) return $out('201080101', '', 'Préstamos de accionistas');
                if ($has('PRESTAMO', 'OBLIGACION', 'SOBREGIRO', 'HIPOTEC', 'CREDITO') && $has('BANC', 'FINANC', 'COOPERATIVA', 'PICHINCHA', 'GUAYAQUIL', 'PRODUBANCO', 'PACIFICO', 'BOLIVARIANO', 'INTERNACIONAL', 'AUSTRO', 'MACHALA', 'BANECUADOR', 'CFN'))
                    return $out('2010401', '', 'Obligaciones con instituciones financieras locales');
                if ($has('PRESTAMO', 'SOBREGIRO')) return $out('2010401', '', 'Obligaciones con instituciones financieras locales');
                if ($has('ARRENDAMIENTO', 'LEASING')) return $out('20102', '', 'Pasivos por contratos de arrendamiento');
                if ($has('TARJETA')) return $out('2010401', '', 'Obligaciones con instituciones financieras (tarjeta de crédito)');
                if ($has('PROVEEDOR', 'POR PAGAR', 'ACREEDOR', 'DOCUMENTOS POR PAGAR')) return $out('201030102', '', 'Cuentas y documentos por pagar a proveedores locales');
                if ($has('PROVISION')) return $out('2010501', '', 'Provisiones locales');
                return $nada;

            case '3':
                if ($has('FUTURA') && $has('CAPITALIZ')) return $out('302', '', 'Aportes para futura capitalización');
                if ($has('PRIMA')) return $out('303', '', 'Prima por emisión primaria de acciones');
                if ($has('RESERVA LEGAL')) return $out('30401', '', 'Reserva legal');
                if ($has('RESERVA FACULTATIVA', 'RESERVA ESTATUTARIA')) return $out('30402', '', 'Reservas facultativa y estatutaria');
                if ($has('RESERVA DE CAPITAL')) return $out('30604', '', 'Reserva de capital');
                if ($has('RESULTADOS INTEGRALES', 'SUPERAVIT', 'REVALUACION')) return $out('30504', '', 'Otros superávit por revaluación');
                if ($has('NIIF', 'ADOPCION')) return $out('30603', '', 'Resultados acumulados por adopción NIIF');
                $anterior = $has('ANTERIOR', 'ACUMULAD', 'NO DISTRIBUID', 'RETENID') || preg_match('/20(1[0-9]|2[0-5])/', $n);
                if ($has('PERDIDA')) return $out($anterior ? '30602' : '30702', '', $anterior ? 'Pérdidas acumuladas' : 'Pérdida neta del período');
                if ($has('UTILIDAD', 'GANANCIA', 'RESULTADO', 'EXCEDENTE')) return $out($anterior ? '30601' : '30701', '', $anterior ? 'Ganancias acumuladas' : 'Ganancia neta del período');
                if ($has('CAPITAL', 'SOCIO', 'ACCIONISTA', 'PATRIMONIO', 'APORTE') || str_starts_with($codigoCuenta, '3.1')) return $out('30101', '', 'Capital suscrito o asignado');
                return $nada;

            case '4':
                if ($has('DESCUENTO')) return $out('', '40112', 'Descuento en ventas');
                if ($has('DEVOLUCION')) return $out('', '40113', 'Devoluciones en ventas');
                if ($has('BONIFICACION')) return $out('', '40114', 'Bonificación en producto');
                if ($has('INTERES') && $has('VENTA', 'CREDITO', 'CLIENTE', 'MORA')) return $out('', '4010601', 'Intereses generados por ventas a crédito');
                if ($has('INTERES', 'RENDIMIENTO', 'FINANCIERO')) return $out('', '4010602', 'Intereses y rendimientos financieros');
                if ($has('DIVIDENDO')) return $out('', '40107', 'Dividendos');
                if ($has('COMISION')) return $out('', '40109', 'Ingresos por comisiones');
                if ($has('ARRIENDO', 'ARRENDAMIENTO', 'ALQUILER', 'REGALIA')) return $out('', '40105', 'Regalías y arriendos');
                if ($has('VENTA DE ACTIVO', 'VENTA DE PROPIEDAD', 'VENTA DE VEHICULO')) return $out('', '40301', 'Ganancia en venta de propiedad, planta y equipo');
                if ($has('SERVICIO', 'HONORARIO', 'CONSULTORIA', 'ASESORIA', 'MANO DE OBRA', 'TRANSPORTE')) return $out('', '40102', 'Prestación de servicios');
                if ($has('VENTA', 'INGRESO', 'MERCADER', 'PRODUCTO', 'BIEN', 'LOCAL', 'EXPORTA')) return $out('', '40101', 'Venta de bienes');
                if ($has('OTRO', 'VARIO', 'DIVERSO', 'AJUSTE', 'REDONDEO', 'PROPINA')) return $out('', '40303', 'Otros ingresos');
                return $nada;

            case '5':
            case '6':
                // Costo de ventas (grupo 501) vs gastos (502). Gastos: venta 50201xx / administración 50202xx.
                $esCosto = str_contains($p, 'COSTO') || $has('COSTO DE VENTA', 'COSTO DE MERCADER', 'COSTO DE PRODUCTO', 'COMPRAS');
                if ($esCosto && $has('MANO DE OBRA')) return $out('', $has('INDIRECT') ? '50103' : '50102', 'Mano de obra');
                if ($esCosto) return $out('', '5010102', 'Compras netas de bienes no producidos por la compañía (costo de ventas)');
                $g = (str_contains($p, 'VENTA') || str_contains($p, 'COMERCIAL') || $has('VENDEDOR', 'PUBLICIDAD', 'PROMOCION')) ? '50201' : '50202';
                if ($has('SUELDO', 'SALARIO', 'REMUNERACION', 'HORAS EXTRA', 'BONIFICACION')) return $out('', $g . '01', 'Sueldos, salarios y demás remuneraciones');
                if ($has('IESS', 'APORTE PATRONAL', 'FONDO DE RESERVA', 'SEGURIDAD SOCIAL')) return $out('', $g . '02', 'Aportes a la seguridad social');
                if ($has('DECIMO', 'VACACION', 'DESAHUCIO', 'INDEMNIZ', 'BENEFICIO', 'JUBILACION', 'UNIFORME', 'ALIMENTACION')) return $out('', $g . '03', 'Beneficios sociales e indemnizaciones');
                if ($has('HONORARIO', 'DIETA', 'PROFESIONAL', 'CONTADOR', 'ABOGADO')) return $out('', $g . '05', 'Honorarios, comisiones y dietas a personas naturales');
                if ($has('MANTENIMIENTO', 'REPARACION')) return $out('', $g . '08', 'Mantenimiento y reparaciones');
                if ($has('ARRIENDO', 'ARRENDAMIENTO', 'ALQUILER')) return $out('', $g . '09', 'Arrendamiento');
                if ($has('COMISION') && !$has('BANC')) return $out('', $g . '10', 'Comisiones');
                if ($has('PUBLICIDAD', 'PROMOCION', 'PROPAGANDA', 'MARKETING')) return $out('', $g . '11', 'Promoción y publicidad');
                if ($has('COMBUSTIBLE', 'GASOLINA', 'DIESEL')) return $out('', $g . '12', 'Combustibles');
                if ($has('LUBRICANTE', 'ACEITE')) return $out('', $g . '13', 'Lubricantes');
                if ($has('SEGURO')) return $out('', $g . '14', 'Seguros y reaseguros');
                if ($has('TRANSPORTE', 'FLETE', 'ENVIO', 'COURIER', 'MOVILIZACION')) return $out('', $g . '15', 'Transporte');
                if ($has('AGASAJO', 'GESTION', 'ATENCION', 'REFRIGERIO')) return $out('', $g . '16', 'Gastos de gestión');
                if ($has('VIAJE', 'VIATICO', 'HOSPEDAJE', 'HOTEL')) return $out('', $g . '17', 'Gastos de viaje');
                if ($has('AGUA', 'LUZ', 'ENERGIA', 'TELEFON', 'INTERNET', 'CELULAR', 'SERVICIOS BASICOS', 'TELECOMUNIC')) return $out('', $g . '18', 'Agua, energía, luz y telecomunicaciones');
                if ($has('NOTARI', 'REGISTRO', 'LEGAL', 'JUDICIAL')) return $out('', $g . '19', 'Notarios y registradores');
                if ($has('IMPUESTO A LA RENTA') || ($has('IMPUESTO') && $has('RENTA'))) return $out('', '603', 'Impuesto a la renta causado');
                if ($has('PARTICIPACION') && $has('TRABAJ')) return $out('', '601', 'Participación trabajadores');
                if ($has('IMPUESTO', 'CONTRIBUCION', 'PATENTE', 'TASA', 'PREDIAL', 'MUNICIP', 'SUPERINTENDENCIA', 'IVA')) return $out('', '5020220', 'Impuestos, contribuciones y otros');
                if ($has('DEPRECIACION')) return $out('', $g === '50201' ? '5020120' : '5020221', 'Depreciaciones');
                if ($has('AMORTIZACION')) return $out('', $g === '50201' ? '5020121' : '5020222', 'Amortizaciones');
                if ($has('DETERIORO', 'INCOBRABLE', 'CUENTAS MALAS')) return $out('', $g === '50201' ? '5020122' : '5020223', 'Gasto deterioro');
                if ($has('SUMINISTRO', 'MATERIAL', 'PAPELERIA', 'UTILES', 'LIMPIEZA', 'ASEO')) return $out('', $g . '27', 'Suministros y materiales');
                if ($has('INTERES')) return $out('', '5020301', 'Intereses (gastos financieros)');
                if ($has('BANCARI', 'CHEQUERA', 'TRANSFERENCIA', 'COMISION BANC')) return $out('', '5020302', 'Comisiones bancarias (gastos financieros)');
                if ($has('DIFERENCIA EN CAMBIO', 'DIFERENCIAL')) return $out('', '5020307', 'Diferencia en cambio');
                if ($has('PERDIDA') && $has('VENTA') && $has('ACTIVO', 'PROPIEDAD', 'VEHICULO')) return $out('', '5020310', 'Pérdida en venta de propiedad, planta y equipo');
                if ($has('OTRO', 'VARIO', 'DIVERSO', 'GENERAL', 'AJUSTE', 'REDONDEO', 'MULTA', 'DONACION', 'NO DEDUCIBLE', 'PROPINA')) return $out('', $g . '28' === '5020128' ? '5020128' : '5020229', 'Otros gastos');
                return $nada;
        }
        return $nada;
    }

    /** Mayúsculas, sin acentos ni signos, espacios simples. */
    public static function normalizar(string $s): string
    {
        $s = mb_strtoupper(trim($s), 'UTF-8');
        $s = strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
        $s = preg_replace('/[^A-Z0-9 .%]/', ' ', $s) ?? $s;
        return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    }
}
