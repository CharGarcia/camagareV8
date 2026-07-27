<?php
/**
 * Tablero del taller: una columna por departamento con los vehículos que están
 * ahí ahora mismo. Es la vista del jefe de taller — de un vistazo se ve qué hay
 * en pintura, qué lleva días parado y qué está esperando aprobación.
 *
 * Esta página tiene contenido sobre la tabla (columnas tipo kanban), así que
 * desactiva el app-shell para poder hacer scroll normal (regla §9 de CLAUDE.md).
 *
 * @var string $titulo
 * @var array  $perm
 * @var string $rutaModulo   modulos/taller-tablero
 * @var string $rutaOrdenes  modulos/taller — a donde se va al abrir una orden
 * @var array  $tablero  ['columnas' => [...], 'sin_departamento' => [...], 'total' => int]
 */
$base       = rtrim(BASE_URL, '/');
$urlBase    = $base . '/' . ltrim($rutaModulo, '/');
$urlOrdenes = $base . '/' . ltrim($rutaOrdenes ?? 'modulos/taller', '/');
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .tb-scroll-h { overflow-x: auto; padding-bottom: 8px; }
    .tb-columnas { display: flex; gap: 12px; align-items: flex-start; min-height: 200px; }
    .tb-col { flex: 0 0 280px; max-width: 280px; background: #f6f7f9; border-radius: 10px; }
    .tb-col-head {
        padding: 8px 10px; border-radius: 10px 10px 0 0; font-weight: 700; font-size: .82rem;
        display: flex; justify-content: space-between; align-items: center; color: #fff;
    }
    .tb-col-body { padding: 8px; max-height: calc(100dvh - 260px); overflow-y: auto; }
    .tb-card {
        background: #fff; border: 1px solid #e9ecef; border-radius: 8px; padding: 8px 10px;
        margin-bottom: 8px; cursor: pointer; transition: box-shadow .15s;
    }
    .tb-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.1); }
    .tb-card.urgente { border-left: 4px solid #dc3545; }
    .tb-card.alta    { border-left: 4px solid #fd7e14; }
    .tb-placa { font-weight: 800; font-size: 1rem; letter-spacing: .5px; }
    .tb-veh { font-size: .76rem; color: #6c757d; }
    .tb-motivo { font-size: .76rem; color: #495057; margin-top: 4px; }
    .tb-meta { font-size: .7rem; color: #868e96; margin-top: 4px; display: flex; justify-content: space-between; }
    .tb-vacio { font-size: .76rem; color: #adb5bd; text-align: center; padding: 20px 6px; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-kanban text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-primary bg-opacity-10 text-primary" id="tb_total">
            <?= (int) ($tablero['total'] ?? 0) ?> vehículos en el taller
        </span>
        <button class="btn btn-outline-secondary btn-sm" onclick="tbRefrescar()" title="Actualizar">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
    </div>
</div>

<div class="tb-scroll-h">
    <div class="tb-columnas" id="tb_columnas"></div>
</div>

<script>
    window.TB_RUTA = '<?= $urlBase ?>';
    // El detalle de la orden vive en el módulo de Órdenes de Trabajo.
    window.TB_RUTA_ORDENES = '<?= $urlOrdenes ?>';
    window.TB_DATA = <?= json_encode($tablero ?? ['columnas' => [], 'sin_departamento' => [], 'total' => 0]) ?>;
</script>

<script>
(function () {
    'use strict';

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /** Cuánto lleva el vehículo dentro del taller. */
    function transcurrido(desde) {
        if (!desde) return '';
        const d = new Date(String(desde).replace(' ', 'T'));
        if (isNaN(d)) return '';
        const min = Math.round((Date.now() - d.getTime()) / 60000);
        if (min < 60) return min + ' min';
        const h = Math.floor(min / 60);
        if (h < 24) return h + 'h';
        return Math.floor(h / 24) + ' d';
    }

    function tarjeta(o) {
        const aprobada = o.aprobado === true || o.aprobado === 't' || o.aprobado === 'true';
        const clase = (o.prioridad === 'urgente') ? ' urgente' : (o.prioridad === 'alta' ? ' alta' : '');
        const vehiculo = [o.marca, o.modelo, o.anio].filter(Boolean).join(' ');

        return `
            <div class="tb-card${clase}" onclick="tbAbrir(${o.id})">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="tb-placa">${esc(o.placa || '—')}</span>
                    ${aprobada
                        ? '<i class="bi bi-check-circle-fill text-success" title="Presupuesto aprobado"></i>'
                        : '<i class="bi bi-clock-history text-warning" title="Falta aprobación del cliente"></i>'}
                </div>
                <div class="tb-veh">${esc(vehiculo)}</div>
                <div class="tb-motivo text-truncate" title="${esc(o.motivo_ingreso || '')}">
                    ${esc(o.motivo_ingreso || '')}
                </div>
                <div class="tb-meta">
                    <span><i class="bi bi-clock"></i> ${esc(transcurrido(o.fecha_ingreso))}</span>
                    <span>${esc(o.numero_orden || '')}</span>
                </div>
            </div>`;
    }

    function pintar(data) {
        const cont = document.getElementById('tb_columnas');
        const columnas = data.columnas || [];
        const sinDep = data.sin_departamento || [];
        let html = '';

        // Primero, lo recién recibido que todavía no fue enviado a ningún lado.
        if (sinDep.length) {
            html += `
                <div class="tb-col">
                    <div class="tb-col-head" style="background:#6c757d">
                        <span><i class="bi bi-inbox me-1"></i>Recibidos</span>
                        <span class="badge bg-light text-dark">${sinDep.length}</span>
                    </div>
                    <div class="tb-col-body">${sinDep.map(tarjeta).join('')}</div>
                </div>`;
        }

        html += columnas.map((c) => {
            const d = c.departamento || {};
            const ordenes = c.ordenes || [];
            return `
                <div class="tb-col">
                    <div class="tb-col-head" style="background:${esc(d.color || '#0d6efd')}">
                        <span><i class="bi ${esc(d.icono || 'bi-tools')} me-1"></i>${esc(d.nombre || '')}</span>
                        <span class="badge bg-light text-dark">${ordenes.length}</span>
                    </div>
                    <div class="tb-col-body">
                        ${ordenes.length ? ordenes.map(tarjeta).join('') : '<div class="tb-vacio">Sin vehículos</div>'}
                    </div>
                </div>`;
        }).join('');

        if (!columnas.length && !sinDep.length) {
            html = `<div class="text-muted p-4">
                        No hay departamentos configurados ni vehículos en el taller.
                    </div>`;
        }

        cont.innerHTML = html;
        document.getElementById('tb_total').textContent = (data.total || 0) + ' vehículos en el taller';
    }

    window.tbAbrir = async function (id) {
        // El id viaja en sesión, no en la URL: se navega a modulos/taller sin
        // parámetros y allá se abre el modal de esa orden.
        try {
            const fd = new FormData();
            fd.append('id', id);
            const res = await fetch(`${window.TB_RUTA}/entrarAjax`, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.ok) {
                Swal.fire('Atención', data.error || 'No se pudo abrir la orden.', 'error');
                return;
            }
        } catch (e) {
            console.error(e);
        }
        window.location.href = window.TB_RUTA_ORDENES;
    };

    window.tbRefrescar = async function () {
        try {
            const res = await fetch(`${window.TB_RUTA}/tableroAjax`);
            const data = await res.json();
            if (data.ok) pintar(data.data);
        } catch (e) {
            console.error(e);
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        pintar(window.TB_DATA);
        setInterval(window.tbRefrescar, 30000);
    });
})();
</script>
