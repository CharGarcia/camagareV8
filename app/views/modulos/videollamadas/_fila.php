<?php

/**
 * Una fila del listado de videollamadas.
 *
 * Se incluye desde dos lugares para que el render inicial y el del buscador
 * AJAX no se desincronicen nunca:
 *   - index.php (carga de la página)
 *   - VideollamadasController::searchAjax() (búsqueda y paginación)
 *
 * @var array $r Fila de videollamadas_salas con anfitrion_nombre y total_participantes.
 */

$estado = $r['estado'] ?? 'programada';

$estadoClass = match ($estado) {
    'en_curso'   => 'bg-success bg-opacity-10 text-success border-success',
    'finalizada' => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
    'cancelada'  => 'bg-danger bg-opacity-10 text-danger border-danger',
    default      => 'bg-primary bg-opacity-10 text-primary border-primary',
};

$estadoLabel = match ($estado) {
    'en_curso'   => 'En curso',
    'finalizada' => 'Finalizada',
    'cancelada'  => 'Cancelada',
    default      => 'Programada',
};

$tipoLabel = match ($r['tipo'] ?? '') {
    'programada' => 'Programada',
    'permanente' => 'Permanente',
    default      => 'Instantánea',
};

$fecha = !empty($r['fecha_inicio'])
    ? date('d-m-Y H:i:s', strtotime((string) $r['fecha_inicio']))
    : '—';
?>
<tr class="vc-row" role="button" onclick="VC_abrirModalVer(<?= (int) $r['id'] ?>)">
    <td class="ps-3 vc-td-titulo" data-col="titulo">
        <?php if ($estado === 'en_curso'): ?>
            <span class="vc-punto-vivo" title="Reunión en curso"></span>
        <?php endif; ?>
        <?= htmlspecialchars((string) ($r['titulo'] ?? '')) ?>
    </td>
    <td data-col="codigo"><code><?= htmlspecialchars((string) ($r['codigo'] ?? '')) ?></code></td>
    <td data-col="tipo"><span class="badge bg-light text-dark border"><?= $tipoLabel ?></span></td>
    <td data-col="fecha_inicio"><?= $fecha ?></td>
    <td data-col="anfitrion_nombre"><?= htmlspecialchars((string) ($r['anfitrion_nombre'] ?? '')) ?></td>
    <td class="text-center" data-col="total_participantes">
        <span class="badge bg-light text-dark border"><?= (int) ($r['total_participantes'] ?? 0) ?></span>
    </td>
    <td class="text-center pe-3" data-col="estado">
        <span class="badge <?= $estadoClass ?> border border-opacity-25"><?= $estadoLabel ?></span>
    </td>
</tr>
