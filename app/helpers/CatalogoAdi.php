<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Catálogo oficial del Anexo de Dividendos (ADI) del SRI.
 *
 * Fuente: "Catalogo_anexo_de_dividendos.xlsx" (hoja Tablas) y la Ficha Técnica
 * del Anexo de Dividendos, ambas publicadas por el SRI. Son tablas GLOBALES del
 * organismo de control (no dependen de empresa ni cambian por cliente), por eso
 * viven en un helper y no en la base — igual que CatalogoMedidas.
 *
 * Tablas incluidas:
 *   1  Tipo de identificación (R/C/E/P)
 *   2  Tipo de beneficiario (01..13)
 *   3  País (257 códigos)
 *   4  Respuesta SI/NO (01/02)
 *   7  Tipo de informante (01..06)
 *   9  Tipo de dividendo, beneficio o utilidad distribuido (01..26), con su
 *      vigencia por año y los tipos de beneficiario admitidos.
 *
 * Además expone las reglas de compatibilidad cruzada de la ficha, que son las
 * que rechaza el portal al cargar el archivo:
 *   - qué tipos de beneficiario admite cada tipo de identificación,
 *   - qué países admite cada tipo de beneficiario,
 *   - qué tipos de dividendo admite cada tipo de beneficiario y en qué años.
 */
final class CatalogoAdi
{
    // ── Tabla 1: tipo de identificación ──────────────────────────────────────

    public const TIPO_IDENTIFICACION = [
        'R' => 'RUC',
        'C' => 'Cédula',
        'E' => 'Identificación tributaria del exterior',
        'P' => 'Pasaporte',
    ];

    /** Códigos de identificación del sistema (tipo_id de clientes/proveedores) → tabla 1. */
    private const MAP_TIPO_ID_SISTEMA = [
        '04' => 'R', // RUC
        '05' => 'C', // Cédula
        '06' => 'P', // Pasaporte
        '08' => 'E', // Identificación del exterior
        // Los códigos 01/02/03 aparecen en documentos con la misma semántica.
        '01' => 'R',
        '02' => 'C',
        '03' => 'P',
    ];

    // ── Tabla 2: tipo de beneficiario ────────────────────────────────────────

    public const TIPO_BENEFICIARIO = [
        '01' => 'Persona natural residente en Ecuador',
        '02' => 'Persona natural no residente en Ecuador',
        '03' => 'Establecimiento permanente en el Ecuador o base fija de persona natural residente en el exterior',
        '04' => 'Establecimiento permanente en el Ecuador de sociedad extranjera no domiciliada en Ecuador',
        '05' => 'Sucursales de sociedades extranjeras domiciliadas en el Ecuador',
        '06' => 'Sociedad domiciliada en Ecuador',
        '07' => 'Sociedad extranjera no domiciliada en el Ecuador sin establecimiento permanente',
        '08' => 'Establecimientos permanentes en el exterior de sociedades domiciliadas en el Ecuador',
        '09' => 'Sociedades residentes o establecidas en paraísos fiscales o jurisdicciones de menor imposición',
        '10' => 'Sucesiones indivisas',
        '11' => 'Sociedad extranjera no domiciliada ni establecida en el Ecuador con beneficiario efectivo persona natural residente en el Ecuador',
        '12' => 'Establecimiento permanente o base fija en el exterior de persona natural residente en Ecuador',
        '13' => 'Contribuyente beneficiario de la Ley para Asociaciones Público Privadas',
    ];

    /** Tipos de beneficiario admitidos por cada tipo de identificación (sección C.1, campo 3). */
    public const BENEFICIARIOS_POR_TIPO_ID = [
        'R' => ['01', '03', '04', '05', '06', '10', '13'],
        'C' => ['01', '02', '13'],
        'E' => ['02', '07', '08', '09', '10', '11', '12', '13'],
        'P' => ['01', '02', '13'],
    ];

    /** Beneficiarios cuyo país es forzosamente Ecuador (593). */
    public const BENEFICIARIOS_PAIS_ECUADOR = ['01', '03', '04', '05', '06'];

    /** Beneficiarios que habilitan "¿el dividendo está gravado en el estado de residencia?". */
    public const BENEFICIARIOS_REGIMEN_FISCAL = ['02', '07', '08', '09', '11', '12'];

    /** Beneficiarios que habilitan el beneficiario efectivo. */
    public const BENEFICIARIOS_CON_BENEF_EFECTIVO = ['09', '11'];

    /** El beneficiario efectivo solo admite RUC, cédula o pasaporte (nunca "E"). */
    public const TIPOS_ID_BENEFICIARIO_EFECTIVO = ['R', 'C', 'P'];

    // ── Tabla 4: respuesta ───────────────────────────────────────────────────

    public const RESPUESTA = ['01' => 'SI', '02' => 'NO'];

    // ── Tabla 7: tipo de informante ──────────────────────────────────────────

    public const TIPO_INFORMANTE = [
        '01' => 'Sociedad',
        '02' => 'Persona natural',
        '03' => 'Sucesión indivisa',
        '04' => 'Establecimiento permanente en el Ecuador o base fija de persona natural residente en el exterior',
        '05' => 'Establecimiento permanente en el Ecuador de sociedad extranjera no domiciliada en Ecuador',
        '06' => 'Sucursal de sociedad extranjera domiciliada en el Ecuador',
    ];

    /**
     * Tipos de informante que NO reportan la sección B (utilidades) ni la
     * sección C (dividendos distribuidos): persona natural y sucesión indivisa.
     */
    public const INFORMANTES_SIN_SECCION_B = ['02', '03'];
    public const INFORMANTES_SIN_SECCION_C = ['02'];

    // ── Tabla 9: tipo de dividendo distribuido ───────────────────────────────

    /**
     * codigo => [nombre, año desde (null = sin límite), año hasta (null = vigente),
     *            tipos de beneficiario admitidos, gravado (bool)]
     */
    public const TIPO_DIVIDENDO = [
        '01' => ['Dividendo gravado a persona natural residente en Ecuador', null, null, ['01'], true],
        '02' => ['Dividendo gravado distribuido en acciones, participaciones y similares (reinversión sin derecho a reducción)', null, 2019, ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13'], true],
        '03' => ['Dividendo gravado a sociedad residente o establecida en paraíso fiscal, régimen fiscal preferente o jurisdicción de menor imposición', null, null, ['07', '08', '09', '10', '12'], true],
        '04' => ['Utilidad gravada a miembros de entidades de la economía popular y solidaria', null, null, ['01', '07', '08', '09', '10', '11', '12'], true],
        '05' => ['Dividendo exento a persona natural no residente en Ecuador', null, 2019, ['02', '03'], false],
        '06' => ['Dividendo exento distribuido en acciones, participaciones y similares (reinversión con derecho a reducción)', null, 2019, ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13'], false],
        '07' => ['Dividendo exento a sociedad domiciliada en Ecuador', null, null, ['03', '04', '05', '06'], false],
        '08' => ['Dividendo exento a sociedad domiciliada en el exterior (no paraíso fiscal) sin beneficiario efectivo', null, 2019, ['07', '08'], false],
        '09' => ['Utilidad exenta a miembros de entidades de la economía popular y solidaria', null, null, ['01', '02', '03', '04', '05', '06', '10'], false],
        '10' => ['Dividendo gravado a sociedad domiciliada en el exterior (no paraíso fiscal) con beneficiario efectivo persona natural residente en Ecuador', null, null, ['11', '12'], true],
        '11' => ['Dividendo exento distribuido por sociedad que se dedique a la actividad bananera', null, 2018, ['02', '03', '04', '05', '06', '07', '08'], false],
        '12' => ['Dividendo gravado que incrementa el valor de los derechos representativos de capital por reinversión de utilidades', null, 2019, ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13'], true],
        '13' => ['Beneficios exentos distribuidos por fideicomisos, fondos de inversión y fondos complementarios', null, null, ['01', '02', '03', '04', '05', '06', '07', '08', '10', '11', '12', '13'], false],
        '14' => ['Dividendo exento distribuido a contribuyente beneficiario de la Ley para Asociaciones Público Privadas', 2016, null, ['13'], false],
        '15' => ['Dividendo gravado distribuido a contribuyente beneficiario de la Ley para Asociaciones Público Privadas', 2018, null, ['13'], true],
        '16' => ['Dividendo exento proveniente de fideicomisos de titularización que cumplan con los requisitos de la ley', 2018, null, ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13'], false],
        '17' => ['Dividendo gravado por no informar beneficiario efectivo', 2018, null, ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13'], true],
        '18' => ['Dividendo exento a sociedad domiciliada en el exterior (paraíso fiscal) sin beneficiario efectivo', 2018, 2019, ['09'], false],
        '19' => ['Dividendo exento a beneficiarios efectivos por reinversión de utilidades cuando se informó la composición societaria', 2018, 2019, ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13'], false],
        '20' => ['Dividendo gravado por actividades sujetas a impuesto a la renta único', 2019, null, ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13'], true],
        '21' => ['Dividendo exento por actividades sujetas a impuesto a la renta único', 2019, null, ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13'], false],
        '22' => ['Dividendo gravado a persona natural no residente en Ecuador', 2020, null, ['02', '12'], true],
        '23' => ['Dividendo gravado a sociedad residente o establecida en el exterior (no paraíso fiscal) sin beneficiario efectivo', 2020, null, ['07', '08', '12', '13'], true],
        '24' => ['Dividendo en acciones no objeto de impuesto a la renta (capitalización de utilidades)', 2020, null, ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13'], false],
        '25' => ['Dividendo en acciones retenido (capitalización de utilidades)', 2020, null, ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13'], true],
        '26' => ['Dividendo gravado a sociedad residente o establecida en paraíso fiscal, régimen fiscal preferente o jurisdicción de menor imposición con beneficiario efectivo en el Ecuador', 2020, null, ['09', '11', '13'], true],
    ];

    // ── Tabla 3: país ────────────────────────────────────────────────────────

    public const PAIS = [
        '016' => 'AMERICAN SAMOA',
        '074' => 'BOUVET ISLAND',
        '101' => 'ARGENTINA',
        '102' => 'BOLIVIA',
        '103' => 'BRASIL',
        '104' => 'CANADA',
        '105' => 'COLOMBIA',
        '106' => 'COSTA RICA',
        '107' => 'CUBA',
        '108' => 'CHILE',
        '109' => 'ANGUILA',
        '110' => 'ESTADOS UNIDOS',
        '111' => 'GUATEMALA',
        '112' => 'HAITI',
        '113' => 'HONDURAS',
        '114' => 'JAMAICA',
        '115' => 'MALVINAS ISLAS',
        '116' => 'MEXICO',
        '117' => 'NICARAGUA',
        '118' => 'PANAMA',
        '119' => 'PARAGUAY',
        '120' => 'PERU',
        '121' => 'PUERTO RICO',
        '122' => 'REPUBLICA DOMINICANA',
        '123' => 'EL SALVADOR',
        '124' => 'TRINIDAD Y TOBAGO',
        '125' => 'URUGUAY',
        '126' => 'VENEZUELA',
        '127' => 'CURAZAO',
        '129' => 'BAHAMAS',
        '130' => 'BARBADOS',
        '131' => 'GRANADA',
        '132' => 'GUYANA',
        '133' => 'SURINAM',
        '134' => 'ANTIGUA Y BARBUDA',
        '135' => 'BELICE',
        '136' => 'DOMINICA',
        '137' => 'SAN CRISTOBAL Y NEVIS',
        '138' => 'SANTA LUCIA',
        '139' => 'SAN VICENTE Y LAS GRANAD.',
        '140' => 'ANTILLAS HOLANDESAS',
        '141' => 'ARUBA',
        '142' => 'BERMUDA',
        '143' => 'GUADALUPE',
        '144' => 'GUYANA FRANCESA',
        '145' => 'ISLAS CAIMAN',
        '146' => 'ISLAS VIRGENES (BRITANICAS)',
        '147' => 'JOHNSTON ISLA',
        '148' => 'MARTINICA',
        '149' => 'MONTSERRAT ISLA',
        '151' => 'TURCAS Y CAICOS ISLAS',
        '152' => 'VIRGENES,ISLAS(NORT.AMER.)',
        '201' => 'ALBANIA',
        '202' => 'ALEMANIA',
        '203' => 'AUSTRIA',
        '204' => 'BELGICA',
        '205' => 'BULGARIA',
        '207' => 'ALBORAN Y PEREJIL',
        '208' => 'DINAMARCA',
        '209' => 'ESPAÑA',
        '211' => 'FRANCIA',
        '212' => 'FINLANDIA',
        '213' => 'REINO UNIDO',
        '214' => 'GRECIA',
        '215' => 'PAISES BAJOS (HOLANDA)',
        '216' => 'HUNGRIA',
        '217' => 'IRLANDA',
        '218' => 'ISLANDIA',
        '219' => 'ITALIA',
        '220' => 'LUXEMBURGO',
        '221' => 'MALTA',
        '222' => 'NORUEGA',
        '223' => 'POLONIA',
        '224' => 'PORTUGAL',
        '225' => 'RUMANIA',
        '226' => 'SUECIA',
        '227' => 'SUIZA',
        '228' => 'CANARIAS ISLAS',
        '229' => 'UCRANIA',
        '230' => 'RUSIA',
        '231' => 'YUGOSLAVIA',
        '233' => 'ANDORRA',
        '234' => 'LIECHTENSTEIN',
        '235' => 'MONACO',
        '237' => 'SAN MARINO',
        '238' => 'VATICANO (SANTA SEDE)',
        '239' => 'GIBRALTAR',
        '241' => 'BELARUS',
        '242' => 'BOSNIA Y HERZEGOVINA',
        '243' => 'CROACIA',
        '244' => 'ESLOVENIA',
        '245' => 'ESTONIA',
        '246' => 'GEORGIA',
        '247' => 'GROENLANDIA',
        '248' => 'LETONIA',
        '249' => 'LITUANIA',
        '250' => 'MOLDOVA',
        '251' => 'MACEDONIA',
        '252' => 'ESLOVAQUIA',
        '253' => 'ISLAS FAROE',
        '260' => 'FRENCH SOUTHERN TERRITORIES',
        '301' => 'AFGANISTAN',
        '302' => 'ARABIA SAUDITA',
        '303' => 'MYANMAR (BURMA)',
        '304' => 'CAMBOYA',
        '306' => 'COREA NORTE',
        '307' => 'TAIWAN (CHINA)',
        '308' => 'FILIPINAS',
        '309' => 'INDIA',
        '310' => 'INDONESIA',
        '311' => 'IRAK',
        '312' => 'IRAN (REPUBLICA ISLAMICA)',
        '313' => 'ISRAEL',
        '314' => 'JAPON',
        '315' => 'JORDANIA',
        '316' => 'KUWAIT',
        '317' => 'LAOS, REP. POP. DEMOC.',
        '318' => 'LIBANO',
        '319' => 'MALASIA',
        '321' => 'MONGOLIA (MANCHURIA)',
        '322' => 'PAKISTAN',
        '323' => 'SIRIA',
        '325' => 'TAILANDIA',
        '327' => 'BAHREIN',
        '328' => 'BANGLADESH',
        '329' => 'BUTAN',
        '330' => 'COREA DEL SUR',
        '331' => 'CHINA POPULAR',
        '332' => 'CHIPRE',
        '333' => 'EMIRATOS ARABES UNIDOS',
        '334' => 'QATAR',
        '335' => 'MALDIVAS',
        '336' => 'NEPAL',
        '337' => 'OMAN',
        '338' => 'SINGAPUR',
        '339' => 'SRI LANKA (CEILAN)',
        '341' => 'VIETNAM',
        '342' => 'YEMEN',
        '343' => 'ISLAS HEARD Y MCDONALD',
        '344' => 'BRUNEI DARUSSALAM',
        '346' => 'TURQUIA',
        '347' => 'AZERBAIJAN',
        '348' => 'KAZAJSTAN',
        '349' => 'KIRGUIZISTAN',
        '350' => 'TAJIKISTAN',
        '351' => 'TURKMENISTAN',
        '352' => 'UZBEKISTAN',
        '353' => 'PALESTINA',
        '354' => 'HONG KONG',
        '355' => 'MACAO',
        '356' => 'ARMENIA',
        '402' => 'BURKINA FASO',
        '403' => 'ARGELIA',
        '404' => 'BURUNDI',
        '405' => 'CAMERUN',
        '406' => 'CONGO',
        '407' => 'ETIOPIA',
        '408' => 'GAMBIA',
        '409' => 'GUINEA',
        '410' => 'LIBERIA',
        '412' => 'MADAGASCAR',
        '413' => 'MALAWI',
        '414' => 'MALI',
        '415' => 'MARRUECOS',
        '416' => 'MAURITANIA',
        '417' => 'NIGERIA',
        '419' => 'ZIMBABWE (RHODESIA)',
        '420' => 'SENEGAL',
        '421' => 'SUDAN',
        '422' => 'SUDAFRICA (CISKEI)',
        '423' => 'SIERRA LEONA',
        '425' => 'TANZANIA',
        '426' => 'UGANDA',
        '427' => 'ZAMBIA',
        '428' => 'ÅLAND ISLANDS',
        '429' => 'BENIN',
        '430' => 'BOTSWANA',
        '431' => 'REPUBLICA CENTROAFRICANA',
        '432' => 'COSTA DE MARFIL',
        '433' => 'CHAD',
        '434' => 'EGIPTO',
        '435' => 'GABON',
        '436' => 'GHANA',
        '437' => 'GUINEA-BISSAU',
        '438' => 'GUINEA ECUATORIAL',
        '439' => 'KENIA',
        '440' => 'LESOTHO',
        '441' => 'MAURICIO',
        '442' => 'MOZAMBIQUE',
        '443' => 'MAYOTTE',
        '444' => 'NIGER',
        '445' => 'RWANDA',
        '446' => 'SEYCHELLES',
        '447' => 'SAHARA OCCIDENTAL',
        '448' => 'SOMALIA',
        '449' => 'SANTO TOME Y PRINCIPE',
        '450' => 'SWAZILANDIA',
        '451' => 'TOGO',
        '452' => 'TUNEZ',
        '453' => 'ZAIRE',
        '454' => 'ANGOLA',
        '456' => 'CABO VERDE',
        '458' => 'COMORAS',
        '459' => 'DJIBOUTI',
        '460' => 'NAMIBIA',
        '463' => 'ERITREA',
        '464' => 'MOROCCO',
        '465' => 'REUNION',
        '466' => 'SANTA ELENA',
        '499' => 'JERSEY',
        '501' => 'AUSTRALIA',
        '503' => 'NUEVA ZELANDA',
        '504' => 'SAMOA OCCIDENTAL',
        '506' => 'FIJI',
        '507' => 'PAPUA NUEVA GUINEA',
        '508' => 'TONGA',
        '509' => 'PALAO (BELAU) ISLAS',
        '510' => 'KIRIBATI',
        '511' => 'MARSHALL ISLAS',
        '512' => 'MICRONESIA',
        '513' => 'NAURU',
        '514' => 'SALOMON ISLAS',
        '515' => 'TUVALU',
        '516' => 'VANUATU',
        '517' => 'GUAM',
        '518' => 'ISLAS COCOS (KEELING)',
        '519' => 'ISLAS COOK',
        '520' => 'ISLAS NAVIDAD',
        '521' => 'MIDWAY ISLAS',
        '522' => 'NIUE ISLA',
        '523' => 'NORFOLK ISLA',
        '524' => 'NUEVA CALEDONIA',
        '525' => 'PITCAIRN, ISLA',
        '526' => 'POLINESIA FRANCESA',
        '529' => 'TIMOR DEL ESTE',
        '530' => 'TOKELAI',
        '531' => 'WAKE ISLA',
        '532' => 'WALLIS Y FUTUNA, ISLAS',
        '593' => 'ECUADOR',
        '594' => 'AGUAS INTERNACIONALES',
        '595' => 'ALTO VOLTA',
        '596' => 'BIELORRUSIA',
        '597' => 'COTE DÍVOIRE',
        '598' => 'CYPRUS',
        '599' => 'REPUBLICA CHECA',
        '600' => 'FALKLAND ISLANDS',
        '601' => 'LATVIA',
        '602' => 'LIBIA',
        '603' => 'NORTHERN MARIANA ISL',
        '604' => 'ST. PIERRE AND MIQUE',
        '605' => 'SYRIAN ARAB REPUBLIC',
        '606' => 'TERRITORIO ANTARTICO BRITANICO',
        '607' => 'TERRITORIO BRITANICO OCEANO IN',
        '688' => 'SERBIA',
        '831' => 'GUERNSEY',
        '833' => 'ISLE OF MAN',
        '999' => 'GENERAL (persona natural sin residencia en un Estado)',
    ];

    public const PAIS_ECUADOR = '593';
    public const PAIS_GENERAL = '999';

    // ── Consultas de conveniencia ────────────────────────────────────────────

    /** Traduce el tipo_id del sistema (clientes/proveedores/empleados) al de la tabla 1. */
    public static function tipoIdDesdeSistema(?string $tipoIdSistema): ?string
    {
        $cod = trim((string) $tipoIdSistema);
        return self::MAP_TIPO_ID_SISTEMA[$cod] ?? null;
    }

    /**
     * Tipo de beneficiario más probable para una identificación dada.
     * En un RUC ecuatoriano el tercer dígito indica la naturaleza del
     * contribuyente: menor a 6 = persona natural, 6 = sector público,
     * 9 = sociedad privada.
     */
    public static function beneficiarioSugerido(string $tipoId, string $identificacion): string
    {
        $id = (string) preg_replace('/\D/', '', $identificacion);

        if ($tipoId === 'R') {
            $tercer = (int) substr($id, 2, 1);
            return $tercer < 6 ? '01' : '06';
        }
        if ($tipoId === 'E') {
            return '07';
        }
        return '01';
    }

    /**
     * Tipo de informante deducido de su identificación (tabla 7).
     * El tercer dígito del RUC distingue la naturaleza del contribuyente, igual
     * que en beneficiarioSugerido(). Con cédula o pasaporte solo cabe la persona
     * natural.
     */
    public static function informanteSugerido(string $tipoId, string $identificacion): string
    {
        if ($tipoId !== 'R') {
            return '02'; // persona natural
        }

        $id     = (string) preg_replace('/\D/', '', $identificacion);
        $tercer = (int) substr($id, 2, 1);

        return $tercer < 6 ? '02' : '01';
    }

    /**
     * Tipo de informante del anexo a partir del tipo de contribuyente que tiene
     * registrada la empresa (catálogo `tipo_empresa`):
     *
     *   1 Persona natural                                → 02 Persona natural
     *   2 Persona natural obligada a llevar contabilidad → 02 Persona natural
     *   3 Sociedad / 4 Contribuyente especial / 5 Sector público → 01 Sociedad
     *
     * Es más fiable que mirar el RUC, así que este es el camino principal; si la
     * empresa no tiene el tipo de contribuyente configurado, se cae al RUC.
     */
    public static function informantePorEmpresa(?string $tipoContribuyente, string $ruc): string
    {
        $tipo = (int) trim((string) $tipoContribuyente);

        return match ($tipo) {
            1, 2    => '02',
            3, 4, 5 => '01',
            default => self::informanteSugerido('R', $ruc),
        };
    }

    /** El tipo de dividendo está vigente para el año informado. */
    public static function dividendoVigente(string $codigo, int $anio): bool
    {
        if (!isset(self::TIPO_DIVIDENDO[$codigo])) {
            return false;
        }
        [, $desde, $hasta] = self::TIPO_DIVIDENDO[$codigo];
        if ($desde !== null && $anio < $desde) {
            return false;
        }
        if ($hasta !== null && $anio > $hasta) {
            return false;
        }
        return true;
    }

    /** El tipo de dividendo es de los gravados (genera ingreso gravado y retención). */
    public static function dividendoGravado(string $codigo): bool
    {
        return (bool) (self::TIPO_DIVIDENDO[$codigo][4] ?? false);
    }

    /** Tipos de dividendo utilizables con ese beneficiario en ese año. */
    public static function dividendosParaBeneficiario(string $tipoBeneficiario, int $anio): array
    {
        $out = [];
        foreach (self::TIPO_DIVIDENDO as $cod => $def) {
            if (!self::dividendoVigente((string) $cod, $anio)) {
                continue;
            }
            if (!in_array($tipoBeneficiario, $def[3], true)) {
                continue;
            }
            $out[(string) $cod] = $def[0];
        }
        return $out;
    }

    /**
     * Tipo de dividendo sugerido según el beneficiario, para el esquema 2020 en
     * adelante. Es una sugerencia: el usuario siempre puede cambiarla.
     */
    public static function dividendoSugerido(string $tipoBeneficiario, int $anio): string
    {
        $sugerido = match ($tipoBeneficiario) {
            '01'                   => '01', // PN residente en Ecuador, gravado
            '02', '12'             => '22', // PN no residente, gravado (desde 2020)
            '03', '04', '05', '06' => '07', // sociedad domiciliada en Ecuador, exento
            '07', '08'             => '23', // sociedad del exterior sin beneficiario efectivo
            '09', '11'             => '26', // paraíso fiscal con beneficiario efectivo en Ecuador
            '10'                   => '03',
            '13'                   => '15',
            default                => '01',
        };

        if (self::dividendoVigente($sugerido, $anio)
            && in_array($tipoBeneficiario, self::TIPO_DIVIDENDO[$sugerido][3], true)) {
            return $sugerido;
        }

        $posibles = self::dividendosParaBeneficiario($tipoBeneficiario, $anio);
        return (string) (array_key_first($posibles) ?? '01');
    }

    /** El país es válido para ese tipo de beneficiario (notas de la tabla 3). */
    public static function paisValido(string $tipoBeneficiario, string $pais): bool
    {
        if (!isset(self::PAIS[$pais])) {
            return false;
        }
        if (in_array($tipoBeneficiario, self::BENEFICIARIOS_PAIS_ECUADOR, true)) {
            return $pais === self::PAIS_ECUADOR;
        }
        if (in_array($tipoBeneficiario, ['10', '13'], true)) {
            return $pais !== self::PAIS_GENERAL;
        }
        if ($tipoBeneficiario === '02') {
            return $pais !== self::PAIS_ECUADOR;
        }
        // 07, 08, 09, 11, 12
        return $pais !== self::PAIS_ECUADOR && $pais !== self::PAIS_GENERAL;
    }

    /** Países ordenados por nombre, para poblar un select. */
    public static function paisesOrdenados(): array
    {
        $p = self::PAIS;
        asort($p, SORT_NATURAL | SORT_FLAG_CASE);
        return $p;
    }
}
