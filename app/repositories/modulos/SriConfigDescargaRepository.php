<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\models\SriConfigDescarga;
use App\models\SriDescargaAutoLog;
use App\Services\modulos\SriDescargaAutomaticaService;

class SriConfigDescargaRepository
{
    private SriConfigDescarga  $model;
    private SriDescargaAutoLog $logModel;

    public function __construct()
    {
        $this->model    = new SriConfigDescarga();
        $this->logModel = new SriDescargaAutoLog();
    }

    public function getConfigEmpresa(int $idEmpresa): ?array
    {
        $config = $this->model->getPorEmpresa($idEmpresa);
        if (!$config) return null;

        // No exponer la clave cifrada al frontend
        $config['sri_clave_guardada'] = !empty($config['sri_clave']);
        unset($config['sri_clave']);

        // Asegurar que los campos de bloqueo siempre estén presentes
        $config['login_bloqueado']        = (bool) ($config['login_bloqueado'] ?? false);
        $config['login_bloqueado_motivo'] = $config['login_bloqueado_motivo'] ?? null;

        return $config;
    }

    public function guardarConfig(array $data, int $idEmpresa, int $idUsuario): array
    {
        $usuario    = trim($data['sri_usuario'] ?? '');
        $estado     = in_array($data['estado'] ?? '', ['activo', 'inactivo'], true)
                        ? $data['estado'] : 'inactivo';
        $tipos      = $this->sanitizarTipos($data['tipos_documento'] ?? 'todos');
        $nuevaClave = trim($data['sri_clave'] ?? '');

        if (empty($usuario)) {
            return ['ok' => false, 'error' => 'El usuario SRI es requerido.'];
        }

        $configActual = $this->model->getPorEmpresa($idEmpresa);

        // Si viene clave nueva la ciframos; si no, mantenemos la existente
        if ($nuevaClave !== '') {
            // Validación básica: mínimo 6 caracteres
            if (strlen($nuevaClave) < 6) {
                return ['ok' => false, 'error' => 'La clave SRI debe tener al menos 6 caracteres.'];
            }
            $claveGuardar = SriDescargaAutomaticaService::encriptarClave($nuevaClave);
        } elseif ($configActual && !empty($configActual['sri_clave'])) {
            $claveGuardar = $configActual['sri_clave'];
        } else {
            return ['ok' => false, 'error' => 'Debe ingresar la clave del portal SRI en Línea.'];
        }

        // Al guardar una clave nueva se desbloquea automáticamente (upsert ya lo hace)
        $ok = $this->model->upsert([
            'id_empresa'      => $idEmpresa,
            'sri_usuario'     => $usuario,
            'sri_clave'       => $claveGuardar,
            'estado'          => $estado,
            'tipos_documento' => $tipos,
            'created_by'      => $idUsuario,
            'updated_by'      => $idUsuario,
        ]);

        return $ok
            ? ['ok' => true, 'mensaje' => 'Configuración guardada correctamente.']
            : ['ok' => false, 'error' => 'Error al guardar la configuración.'];
    }

    public function getHistorial(int $idEmpresa, int $limite = 20): array
    {
        return $this->logModel->getHistorial($idEmpresa, $limite);
    }

    private function sanitizarTipos(string $tipos): string
    {
        if ($tipos === 'todos') return 'todos';
        $validos = ['facturas', 'retenciones', 'notas_credito', 'notas_debito', 'liquidaciones'];
        $partes  = array_filter(
            array_map('trim', explode(',', $tipos)),
            fn($t) => in_array($t, $validos, true)
        );
        return empty($partes) ? 'todos' : implode(',', array_unique($partes));
    }
}
