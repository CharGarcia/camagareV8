<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Piezas comunes de los controladores del CRM (CrmController,
 * CrmConfiguracionController): respuesta de error, aviso de SQL pendiente y
 * registros propios con la regla del CRM (creado por o asignado a).
 * Requiere un BaseModuloController.
 */
trait CrmControllerTrait
{
    /** Corta con 503 si aún no se aplicó database/2026-11-08_crm_fase1.sql. */
    protected function requireCrmDisponible(): void
    {
        if (!\App\Services\modulos\CrmConfiguracionService::crear()->disponible()) {
            $this->json([
                'ok' => false,
                'error' => 'El CRM aún no está instalado en la base de datos (falta aplicar database/2026-11-08_crm_fase1.sql).',
            ], 503);
        }
    }

    protected function idEmpresa(): int
    {
        return (int) $_SESSION['id_empresa'];
    }

    protected function idUsuario(): int
    {
        return (int) $_SESSION['id_usuario'];
    }

    /**
     * Registros propios del CRM (decisión 4 del diseño): sin acceso total el
     * usuario solo ve lo que creó o lo que tiene asignado. Nivel 3 ve todo.
     */
    protected function idUsuarioFiltro(): ?int
    {
        if ((int) ($_SESSION['nivel'] ?? 1) >= 3) {
            return null;
        }
        return empty($this->getPermisos()['todo']) ? $this->idUsuario() : null;
    }

    /** Guard de un registro suelto: pasa si es del usuario por creación o por asignación. */
    protected function requireAccesoCrm(?array $registro): void
    {
        if ($registro !== null && (int) ($registro['id_responsable'] ?? 0) === $this->idUsuario()) {
            return;
        }
        $this->requireRegistroPropio($registro);
    }

    /**
     * Responde el error de una acción. Las validaciones de negocio
     * (InvalidArgumentException / RuntimeException de los Services) se muestran
     * tal cual; un error de base de datos u otro inesperado se registra y se
     * responde con un mensaje genérico.
     */
    protected function fallo(\Throwable $e, string $accion): never
    {
        $negocio = ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)
            && !($e instanceof \PDOException);
        if (!$negocio) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => $accion]);
        }
        $this->json([
            'ok' => false,
            'error' => $negocio ? $e->getMessage() : 'No se pudo completar la operación. Intente de nuevo.',
        ], $negocio ? 422 : 500);
    }

    protected function postInt(string $clave): int
    {
        return (int) ($_POST[$clave] ?? 0);
    }
}
