<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CargaClientesRepository;

/**
 * Catálogos de la carga de clientes (provincias, ciudades, vendedores, formas de
 * pago SRI) precargados una sola vez y traducidos en ambos sentidos:
 *   - id/código  => texto que se escribe en el Excel (plantilla), y
 *   - texto      => id/código (validador).
 *
 * Que la ida y la vuelta vivan en la misma clase garantiza que una fila
 * descargada y subida sin tocar se resuelva exactamente a los mismos valores.
 */
class CargaClientesCatalogos
{
    /** codigo => nombre */
    private array $provinciaNombre = [];
    /** nombre normalizado => codigo */
    private array $provinciaPorNombre = [];

    /** cod_prov|codigo => nombre */
    private array $ciudadNombre = [];
    /** cod_prov => [nombre normalizado => codigo] */
    private array $ciudadPorNombre = [];
    /** cod_prov => [codigo => true] */
    private array $ciudadCodigos = [];

    /** id => fila del vendedor */
    private array $vendedores = [];
    /** nombre normalizado => id[] */
    private array $vendedorPorNombre = [];
    /** identificacion => id */
    private array $vendedorPorIdent = [];

    /** id => codigo */
    private array $formaPagoCodigo = [];
    /** codigo => ['id', 'nombre', 'activa'] */
    private array $formaPagoPorCodigo = [];

    public function __construct(CargaClientesRepository $repository, int $idEmpresa)
    {
        foreach ($repository->getProvincias() as $p) {
            $codigo = trim((string) $p['codigo']);
            $this->provinciaNombre[$codigo] = trim((string) $p['nombre']);
            $this->provinciaPorNombre[self::normalizar($p['nombre'])] = $codigo;
        }

        foreach ($repository->getCiudades() as $c) {
            $prov   = trim((string) $c['cod_prov']);
            $codigo = trim((string) $c['codigo']);
            $this->ciudadNombre[$prov . '|' . $codigo] = trim((string) $c['nombre']);
            $this->ciudadCodigos[$prov][$codigo] = true;
            // Con nombres repetidos dentro de la misma provincia gana el primero.
            $this->ciudadPorNombre[$prov][self::normalizar($c['nombre'])] ??= $codigo;
        }

        foreach ($repository->getVendedores($idEmpresa) as $v) {
            $id = (int) $v['id'];
            $this->vendedores[$id] = $v;
            $this->vendedorPorNombre[self::normalizar($v['nombre'])][] = $id;
            $ident = trim((string) ($v['identificacion'] ?? ''));
            if ($ident !== '') {
                $this->vendedorPorIdent[$ident] = $id;
            }
        }

        foreach ($repository->getFormasPagoSri() as $f) {
            $codigo = trim((string) $f['codigo']);
            $this->formaPagoCodigo[(int) $f['id']] = $codigo;
            $this->formaPagoPorCodigo[$codigo] = [
                'id'     => (int) $f['id'],
                'nombre' => (string) $f['nombre'],
                'activa' => (int) ($f['status'] ?? 1) === 1,
            ];
        }
    }

    /** Mayúsculas, sin tildes y con espacios simples: para comparar nombres. */
    public static function normalizar($texto): string
    {
        $t = strtr(trim((string) $texto), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);
        return strtoupper((string) preg_replace('/\s+/', ' ', $t));
    }

    // ── Hacia el Excel ───────────────────────────────────────────────────────

    public function nombreProvincia(?string $codigo): string
    {
        $codigo = trim((string) $codigo);
        return $codigo === '' ? '' : ($this->provinciaNombre[$codigo] ?? $codigo);
    }

    public function nombreCiudad(?string $codProv, ?string $codigo): string
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return '';
        }
        return $this->ciudadNombre[trim((string) $codProv) . '|' . $codigo] ?? $codigo;
    }

    /**
     * Texto del vendedor para el Excel: su nombre o, si hay otro vendedor con el
     * mismo nombre, su identificación (para que al subirlo no quede ambiguo).
     */
    public function textoVendedor(?int $id): string
    {
        if (!$id || !isset($this->vendedores[$id])) {
            return '';
        }
        $v = $this->vendedores[$id];
        $repetido = count($this->vendedorPorNombre[self::normalizar($v['nombre'])] ?? []) > 1;
        $ident = trim((string) ($v['identificacion'] ?? ''));
        return ($repetido && $ident !== '') ? $ident : trim((string) $v['nombre']);
    }

    public function codigoFormaPago(?int $id): string
    {
        return $id ? ($this->formaPagoCodigo[$id] ?? '') : '';
    }

    // ── Desde el Excel ───────────────────────────────────────────────────────

    /**
     * @return array{0:?string,1:?string} [codigo, error]
     */
    public function resolverProvincia(string $texto): array
    {
        if ($texto === '') {
            return [null, null];
        }
        if (isset($this->provinciaNombre[$texto])) {
            return [$texto, null];
        }
        $codigo = $this->provinciaPorNombre[self::normalizar($texto)] ?? null;
        return $codigo !== null
            ? [$codigo, null]
            : [null, 'La PROVINCIA "' . $texto . '" no existe (vea la hoja ' . CargaClientesEsquema::HOJA_REF_PROVINCIAS . ').'];
    }

    /**
     * @return array{0:?string,1:?string} [codigo, error]
     */
    public function resolverCiudad(?string $codProv, string $texto): array
    {
        if ($texto === '') {
            return [null, null];
        }
        if ($codProv === null) {
            return [null, 'Para indicar la CIUDAD también debe indicar la PROVINCIA.'];
        }
        if (isset($this->ciudadCodigos[$codProv][$texto])) {
            return [$texto, null];
        }
        $codigo = $this->ciudadPorNombre[$codProv][self::normalizar($texto)] ?? null;
        return $codigo !== null
            ? [$codigo, null]
            : [null, 'La CIUDAD "' . $texto . '" no pertenece a la provincia '
                . $this->nombreProvincia($codProv) . ' (vea la hoja ' . CargaClientesEsquema::HOJA_REF_CIUDADES . ').'];
    }

    /**
     * @return array{0:?int,1:?string,2:?string} [id, error, aviso]
     */
    public function resolverVendedor(string $texto): array
    {
        if ($texto === '') {
            return [null, null, null];
        }

        $id = $this->vendedorPorIdent[$texto] ?? null;
        if ($id === null) {
            $ids = $this->vendedorPorNombre[self::normalizar($texto)] ?? [];
            if (count($ids) > 1) {
                return [null, 'Hay varios vendedores llamados "' . $texto
                    . '". Escriba su identificación (vea la hoja ' . CargaClientesEsquema::HOJA_REF_VENDEDORES . ').', null];
            }
            $id = $ids[0] ?? null;
        }

        if ($id === null) {
            return [null, 'El VENDEDOR "' . $texto . '" no existe (vea la hoja '
                . CargaClientesEsquema::HOJA_REF_VENDEDORES . ').', null];
        }

        $aviso = (int) ($this->vendedores[$id]['status'] ?? 1) !== 1
            ? 'El vendedor "' . trim((string) $this->vendedores[$id]['nombre']) . '" está inactivo.'
            : null;
        return [$id, null, $aviso];
    }

    /**
     * Acepta el código ("20"), "20 - OTROS…" o el nombre exacto.
     * @return array{0:?int,1:?string} [id, error]
     */
    public function resolverFormaPago(string $texto): array
    {
        if ($texto === '') {
            return [null, null];
        }

        $codigo = null;
        if (preg_match('/^\s*(\d{1,2})\b/', $texto, $m)) {
            $codigo = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        } else {
            $buscado = self::normalizar($texto);
            foreach ($this->formaPagoPorCodigo as $cod => $f) {
                if (self::normalizar($f['nombre']) === $buscado) {
                    $codigo = $cod;
                    break;
                }
            }
        }

        if ($codigo !== null && isset($this->formaPagoPorCodigo[$codigo])) {
            return [$this->formaPagoPorCodigo[$codigo]['id'], null];
        }
        return [null, 'La FORMA_PAGO_SRI "' . $texto . '" no existe (vea la hoja '
            . CargaClientesEsquema::HOJA_REF_FORMAS_PAGO . ').'];
    }

    // ── Para las hojas de referencia ─────────────────────────────────────────

    public function filasProvincias(): array
    {
        $filas = [];
        foreach ($this->provinciaNombre as $codigo => $nombre) {
            $filas[] = [$nombre, $codigo];
        }
        usort($filas, static fn($a, $b) => strcmp($a[0], $b[0]));
        return $filas;
    }

    public function filasCiudades(): array
    {
        $filas = [];
        foreach ($this->ciudadNombre as $clave => $nombre) {
            [$prov, $codigo] = explode('|', $clave, 2);
            $filas[] = [$this->provinciaNombre[$prov] ?? $prov, $nombre, $codigo];
        }
        return $filas;
    }

    public function filasVendedores(): array
    {
        $filas = [];
        foreach ($this->vendedores as $v) {
            $filas[] = [
                trim((string) $v['nombre']),
                trim((string) ($v['identificacion'] ?? '')),
                (int) ($v['status'] ?? 1) === 1 ? 'Activo' : 'Inactivo',
            ];
        }
        return $filas;
    }

    public function filasFormasPago(): array
    {
        $filas = [];
        foreach ($this->formaPagoPorCodigo as $codigo => $f) {
            if ($f['activa']) {
                $filas[] = [(string) $codigo, $f['nombre']];
            }
        }
        return $filas;
    }
}
