<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class LiquidacionCompraRules
{
    /**
     * Valida los datos de la liquidación antes de guardar.
     */
    public function validar(array $data): void
    {
        if (empty($data['id_proveedor'])) {
            throw new \Exception('Debe seleccionar un proveedor.');
        }

        if (empty($data['fecha_emision'])) {
            throw new \Exception('Debe ingresar la fecha de emisión.');
        }

        if (empty($data['id_punto_emision'])) {
            throw new \Exception('Debe seleccionar la serie de emisión.');
        }

        if (empty($data['id_sustento_tributario'])) {
            throw new \Exception('Debe seleccionar el código de sustento tributario.');
        }

        if (empty($data['secuencial'])) {
            throw new \Exception('El secuencial es obligatorio.');
        }

        if (empty($data['detalles']) || !is_array($data['detalles'])) {
            throw new \Exception('La liquidación debe tener al menos un ítem.');
        }

        $incompletos = self::erroresItemsIncompletos($data['detalles']);
        if ($incompletos) {
            throw new \Exception(implode(' ', $incompletos));
        }

        foreach ($data['detalles'] as $idx => $det) {
            if ((float)($det['cantidad'] ?? 0) <= 0) {
                throw new \Exception("La cantidad del ítem " . ($idx + 1) . " debe ser mayor a cero.");
            }
        }

        if (empty($data['pagos']) || !is_array($data['pagos'])) {
            throw new \Exception('Debe ingresar al menos una forma de pago.');
        }

        // Validar tipo de identificación del proveedor (Cédula 05 o Pasaporte 06)
        $this->validarTipoIdentificacionProveedor((int)$data['id_proveedor'], (int)$data['id_empresa']);

        $this->validarFichaTecnicaSri($data);
    }

    /**
     * Ítems que el SRI devolvería por estructura: `codigoPrincipal` y `descripcion`
     * son obligatorios en el `<detalle>` del esquema de la liquidación de compra.
     *
     * `XmlLiquidacionCompraService` escribe lo que recibe —un ítem sin código deja
     * `<codigoPrincipal></codigoPrincipal>`—, así que la liquidación se guardaba y
     * numeraba sin ruido y recién al enviarla el SRI la devolvía con "ERROR EN
     * ESTRUCTURA DE COMPROBANTE", sin decir qué línea la causó.
     *
     * Es público y estático porque se comprueba en dos momentos: al guardar (aquí,
     * en validar()) y antes de firmar y enviar (`SriEnvioService`), ya que las
     * liquidaciones guardadas antes de esta validación pueden tener ítems sin código.
     *
     * @param  array $detalles Líneas del documento.
     * @return string[] Un mensaje por ítem incompleto, con el número de línea.
     */
    public static function erroresItemsIncompletos(array $detalles): array
    {
        $errores = [];

        foreach (array_values($detalles) as $i => $det) {
            $n = $i + 1;

            $codigo = trim((string) ($det['codigo_principal'] ?? $det['codigo'] ?? ''));
            if ($codigo === '') {
                $errores[] = "El ítem {$n} no tiene código; el SRI lo exige y rechazaría el comprobante.";
            }

            $descripcion = trim((string) ($det['descripcion'] ?? ''));
            if ($descripcion === '') {
                $errores[] = "El ítem {$n} no tiene descripción; el SRI la exige y rechazaría el comprobante.";
            }
        }

        return $errores;
    }

    /**
     * Formato exigido por la Ficha Técnica del SRI (longitudes y decimales).
     *
     * El generador de XML no comprueba nada: escribe lo que recibe. Sin esto, un
     * texto demasiado largo o un precio con ocho decimales pasa sin ruido y el
     * comprobante se rechaza al enviarlo, ya creado y numerado.
     */
    private function validarFichaTecnicaSri(array $data): void
    {
        $errores = array_merge(
            \App\Helpers\SriFichaTecnica::erroresDetalles($data['detalles'] ?? []),
            \App\Helpers\SriFichaTecnica::erroresInfoAdicional($data['info_adicional'] ?? [])
        );

        if ($errores) {
            throw new \Exception(implode(' ', $errores));
        }
    }

    private function validarTipoIdentificacionProveedor(int $idProveedor, int $idEmpresa): void
    {
        $db = \App\core\Database::getConnection();
        $st = $db->prepare("SELECT tipo_id_proveedor, identificacion FROM proveedores WHERE id = ? AND id_empresa = ? AND eliminado = false");
        $st->execute([$idProveedor, $idEmpresa]);
        $prov = $st->fetch();

        if (!$prov) {
            throw new \Exception('Proveedor no encontrado o eliminado.');
        }

        $tipoId = str_pad((string)($prov['tipo_id_proveedor'] ?? ''), 2, '0', STR_PAD_LEFT);
        // 01=RUC, 02=Cédula, 03=Pasaporte (Tipo 1)
        // 04=RUC, 05=Cédula, 06=Pasaporte, 08=Ext (Tipo 2)
        // Las liquidaciones solo permiten Cédula, Pasaporte o Exterior.
        if (!in_array($tipoId, ['02', '03', '05', '06', '08'])) {
            throw new \Exception('Las liquidaciones de compra solo pueden emitirse a proveedores con Cédula, Pasaporte o Identificación del Exterior. El tipo "' . $tipoId . '" no es permitido para este documento.');
        }
    }
}
