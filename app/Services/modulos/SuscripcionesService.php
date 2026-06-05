<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\SuscripcionesRepository;
use App\repositories\PayphoneRepository;
use App\Rules\modulos\SuscripcionesRules;
use App\Services\LogSistemaService;
use App\Services\PayphoneService;
use App\Services\EnvioDocumentosSRIService;
use Exception;

class SuscripcionesService
{
    private SuscripcionesRepository $repository;
    private SuscripcionesRules      $rules;
    private LogSistemaService       $logService;

    public function __construct(
        SuscripcionesRepository $repository,
        SuscripcionesRules      $rules,
        LogSistemaService       $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getPeriodicidades(): array
    {
        return $this->repository->getPeriodicidades();
    }

    public function getDetalle(int $idSuscripcion, int $idEmpresa): array
    {
        $susc = $this->repository->findById($idSuscripcion, $idEmpresa);
        if (!$susc) {
            throw new Exception('Suscripción no encontrada.');
        }
        return $this->repository->getDetalle($idSuscripcion);
    }

    public function getPagosPorSuscripcion(int $idSuscripcion, int $idEmpresa): array
    {
        $susc = $this->repository->findById($idSuscripcion, $idEmpresa);
        if (!$susc) {
            throw new Exception('Suscripción no encontrada.');
        }
        return $this->repository->getPagosPorSuscripcion($idSuscripcion);
    }

    public function crear(array $data): int
    {
        $detalle = $this->_extraerDetalle($data);
        $this->rules->validar($data, $detalle);
        $data['proximo_cobro'] = $data['proximo_cobro'] ?? $data['fecha_inicio'];

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->create($data);

            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];

            foreach ($detalle as $item) {
                $item['id_suscripcion'] = $id;
                $item['id_empresa']     = $idEmpresa;
                $item['created_by']     = $idUsuario;
                $this->repository->insertDetalle($item);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'crear', 'suscripciones', $id, null, $data);

            $this->repository->commit();
            return $id;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $detalle = $this->_extraerDetalle($data);
        $this->rules->validar($data, $detalle);

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('La suscripción no existe o ha sido eliminada.');
        }

        $idUsuario = (int) $data['id_usuario'];

        $this->repository->beginTransaction();
        try {
            $this->repository->update($id, $idEmpresa, $data);

            // Reemplazar detalle: soft-delete los existentes e insertar los nuevos
            $this->repository->deleteDetalle($id, $idUsuario);
            foreach ($detalle as $item) {
                $item['id_suscripcion'] = $id;
                $item['id_empresa']     = $idEmpresa;
                $item['created_by']     = $idUsuario;
                $this->repository->insertDetalle($item);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'actualizar', 'suscripciones', $id, $antes, $data);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function cambiarEstado(int $id, int $idEmpresa, string $estado, int $idUsuario): void
    {
        $estadosValidos = ['activo', 'pausado', 'suspendido', 'cancelado'];
        if (!in_array($estado, $estadosValidos, true)) {
            throw new Exception("Estado '$estado' no válido.");
        }

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('Suscripción no encontrada.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->updateEstado($id, $estado, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'cambiar_estado', 'suscripciones', $id, $antes, ['estado' => $estado]);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function guardarTokenKushki(int $id, int $idEmpresa, array $tokenData, int $idUsuario): void
    {
        $susc = $this->repository->findById($id, $idEmpresa);
        if (!$susc) {
            throw new Exception('Suscripción no encontrada.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->updateKushkiToken(
                $id,
                $idEmpresa,
                $tokenData['token'],
                $tokenData['last4'],
                $tokenData['brand'],
                $tokenData['card_holder_name'] ?? ''
            );
            $this->logService->registrar($idUsuario, $idEmpresa, 'actualizar_tarjeta', 'suscripciones', $id, null, ['last4' => $tokenData['last4']]);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Calcula el monto del período a partir del detalle guardado de la suscripción.
     * Retorna montos en CENTAVOS (formato Payphone): ['subtotal','iva','total'].
     */
    public function calcularMontoPeriodo(int $id, int $idEmpresa): array
    {
        $susc = $this->repository->findById($id, $idEmpresa);
        if (!$susc) {
            throw new Exception('Suscripción no encontrada.');
        }
        $detalle = $this->repository->getDetalle($id);
        if (empty($detalle)) {
            throw new Exception('La suscripción no tiene productos/servicios; no se puede determinar el monto.');
        }

        $subtotal = 0.0;
        $iva      = 0.0;
        foreach ($detalle as $d) {
            $base = round((float)$d['cantidad'] * (float)$d['precio_unitario'], 2);
            $subtotal += $base;
            $iva      += round($base * ((float)($d['porcentaje_iva'] ?? 0) / 100), 2);
        }
        $total = round($subtotal + $iva, 2);
        if ($total <= 0) {
            throw new Exception('El monto del período debe ser mayor a cero.');
        }

        return [
            'subtotal' => (int) round($subtotal * 100),
            'iva'      => (int) round($iva * 100),
            'total'    => (int) round($total * 100),
            'total_dolares' => $total,
            'suscripcion'   => $susc,
        ];
    }

    /**
     * Guarda/actualiza el método de pago Payphone de una suscripción.
     * @param array $d ['client_tx_id','estado','last4','brand']
     */
    public function guardarMetodoPayphone(int $id, int $idEmpresa, array $d, int $idUsuario): void
    {
        $susc = $this->repository->findById($id, $idEmpresa);
        if (!$susc) {
            throw new Exception('Suscripción no encontrada.');
        }
        $this->repository->beginTransaction();
        try {
            $this->repository->guardarMetodoPayphone($id, $idEmpresa, $d);
            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'metodo_pago_payphone', 'suscripciones', $id, null,
                ['estado' => $d['estado'] ?? '', 'last4' => $d['last4'] ?? '']
            );
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Prepara la Cajita de Payphone para registrar/cobrar el período de una suscripción.
     * Devuelve el config del widget para renderizar en el modal.
     */
    public function prepararCajitaPayphone(int $id, int $idEmpresa, int $idUsuario): array
    {
        $montos = $this->calcularMontoPeriodo($id, $idEmpresa);
        $susc   = $this->repository->findByIdConCliente($id, $idEmpresa) ?? [];

        $base = rtrim(BASE_URL, '/');
        $pp   = new PayphoneService(new PayphoneRepository());

        return $pp->prepararCajita($idEmpresa, [
            // Se envía el total sin desglosar IVA: Payphone solo procesa el cobro;
            // el desglose fiscal corresponde a la factura (otro proceso).
            'monto'           => $montos['total'],
            'descripcion'     => 'Suscripción #' . $id . ' - ' . ($susc['cliente_nombre'] ?? ''),
            'modulo'          => 'suscripciones',
            'id_referencia'   => $id,
            'url_retorno'     => $base . '/payphone/cajita-retorno',
            'url_cancelacion' => $base . '/payphone/cancelacion',
            'url_exito'       => $base . '/modulos/suscripciones?pago=ok',
            'id_usuario'      => $idUsuario,
            'email'           => $susc['cliente_email'] ?? '',
            'telefono'        => $susc['cliente_telefono'] ?? '',
        ]);
    }

    /**
     * Genera un enlace de pago Payphone y lo envía al correo del cliente.
     */
    public function enviarEnlacePagoPayphone(int $id, int $idEmpresa, int $idUsuario): array
    {
        $montos = $this->calcularMontoPeriodo($id, $idEmpresa);
        $susc   = $this->repository->findByIdConCliente($id, $idEmpresa);
        if (!$susc) {
            throw new Exception('Suscripción no encontrada.');
        }

        // El cliente puede tener varios correos separados por coma o punto y coma.
        $emailsValidos = [];
        foreach (preg_split('/[,;]+/', (string)($susc['cliente_email'] ?? '')) as $e) {
            $e = trim($e);
            if (filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $emailsValidos[] = $e;
            }
        }
        if (empty($emailsValidos)) {
            throw new Exception('El cliente no tiene un correo válido para enviar el enlace.');
        }
        $email = implode(',', $emailsValidos);

        $base = rtrim(BASE_URL, '/');
        $pp   = new PayphoneService(new PayphoneRepository());

        $prep = $pp->prepararPago($idEmpresa, [
            // Se envía el total sin desglosar IVA: Payphone solo procesa el cobro;
            // el desglose fiscal corresponde a la factura (otro proceso).
            'monto'           => $montos['total'],
            'descripcion'     => 'Suscripción #' . $id . ' - ' . ($susc['cliente_nombre'] ?? ''),
            'modulo'          => 'suscripciones',
            'id_referencia'   => $id,
            'url_retorno'     => $base . '/payphone/retorno',
            'url_cancelacion' => $base . '/payphone/cancelacion',
            'url_exito'       => $base . '/modulos/suscripciones?pago=ok',
            'id_usuario'      => $idUsuario,
        ]);

        if (empty($prep['ok']) || empty($prep['pay_url'])) {
            throw new Exception($prep['mensaje'] ?? 'No se pudo generar el enlace de pago.');
        }

        // Datos de empresa para el remitente
        $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        $empresaNombre = trim((string)($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? ''));

        $mailSvc = new EnvioDocumentosSRIService();
        $enviado = $mailSvc->enviarEnlacePagoTarjeta(
            $idEmpresa,
            $email,
            (string)($susc['cliente_nombre'] ?? 'Cliente'),
            $empresaNombre,
            $montos['total_dolares'],
            'Suscripción #' . $id,
            $prep['pay_url'],
            '' // sin PDF adjunto
        );

        $this->logService->registrar(
            $idUsuario, $idEmpresa, 'enlace_pago_payphone', 'suscripciones', $id, null,
            ['email' => $email, 'enviado' => $enviado, 'ctid' => $prep['client_transaction_id'] ?? '']
        );

        return [
            'ok'      => $enviado,
            'email'   => $email,
            'pay_url' => $prep['pay_url'],
            'mensaje' => $enviado
                ? 'Enlace de pago enviado a ' . $email
                : 'No se pudo enviar el correo. Verifique la configuración de correo de la empresa.',
        ];
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('La suscripción no existe o ya ha sido eliminada.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'suscripciones', $id, $antes, ['eliminado' => true]);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function calcularProximoCobro(string $fechaActual, int $meses, string $codigo = ''): string
    {
        $dt = new \DateTime($fechaActual);
        if ($codigo === 'DIARIO') {
            $dt->modify('+1 day');
        } elseif ($codigo === 'SEMANAL') {
            $dt->modify('+7 days');
        } elseif ($codigo === 'QUINCENAL') {
            $dt->modify('+15 days');
        } else {
            $dt->modify("+{$meses} months");
        }
        return $dt->format('Y-m-d');
    }

    /**
     * Extrae las filas de detalle del array $data enviado por el formulario.
     * El frontend envía: detalle[0][id_producto], detalle[0][descripcion], etc.
     */
    private function _extraerDetalle(array &$data): array
    {
        $detalle = $data['detalle'] ?? [];
        unset($data['detalle']);

        // Filtrar filas vacías (sin producto)
        return array_values(array_filter($detalle, fn($item) => !empty($item['id_producto'])));
    }
}
