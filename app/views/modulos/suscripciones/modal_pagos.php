<?php
$urlBase = BASE_URL . '/modulos/suscripciones';
?>

<div class="modal fade" id="modalPagosSusc" tabindex="-1" aria-labelledby="modalPagosSuscLabel" aria-hidden="true" style="z-index:1070">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold" id="modalPagosSuscLabel">
                    <i class="bi bi-clock-history text-primary me-2"></i>Historial de Pagos
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0">
                <div id="pagosAlert" class="alert d-none mx-3 mt-3 mb-0 py-2 small shadow-sm border-0"></div>

                <div id="pagosCargando" class="text-center py-5 text-muted d-none">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Cargando historial...
                </div>

                <div class="px-3 py-2">
                    <div style="max-height:400px; overflow-y:auto">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-2">Fecha Cobro</th>
                                    <th class="text-end">Monto</th>
                                    <th class="text-center">Estado</th>
                                    <th>Factura</th>
                                    <th>Trans. Kushki</th>
                                    <th class="text-center">Intentos</th>
                                    <th class="text-end pe-2">Registrado</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyPagosSusc">
                                <tr><td colspan="7" class="text-center py-4 text-muted">Sin registros.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light border-top p-2 justify-content-end">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    const urlBase = '<?= $urlBase ?>';

    const estadoClases = {
        pendiente: 'warning',
        exitoso:   'success',
        fallido:   'danger',
    };

    window.cargarModalPagos = async function (idSusc) {
        const tbody   = document.getElementById('tbodyPagosSusc');
        const spinner = document.getElementById('pagosCargando');
        const alerta  = document.getElementById('pagosAlert');

        tbody.innerHTML  = '';
        alerta.className = 'alert d-none';
        spinner.classList.remove('d-none');

        const modal = new bootstrap.Modal(document.getElementById('modalPagosSusc'));
        modal.show();

        try {
            const r = await fetch(`${urlBase}/getPagosAjax?id=${idSusc}`);
            const d = await r.json();
            spinner.classList.add('d-none');

            if (!d.ok) {
                alerta.className   = 'alert mx-3 mt-3 mb-0 py-2 small shadow-sm border-0 alert-danger';
                alerta.textContent = d.mensaje ?? 'Error al cargar pagos.';
                return;
            }

            if (!d.pagos?.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Sin registros de pago.</td></tr>';
                return;
            }

            tbody.innerHTML = d.pagos.map(p => {
                const cls       = estadoClases[p.estado] ?? 'secondary';
                const lbl       = p.estado.charAt(0).toUpperCase() + p.estado.slice(1);
                const fecha     = p.fecha_cobro    ? new Date(p.fecha_cobro + 'T00:00:00').toLocaleDateString('es-EC') : '-';
                const registrado = p.created_at    ? new Date(p.created_at).toLocaleDateString('es-EC') : '-';
                const monto     = parseFloat(p.monto ?? 0).toFixed(2);
                const factura   = p.factura_numero ? `<small class="text-success fw-bold">${p.factura_numero}</small>` : '<small class="text-muted">-</small>';
                const trans     = p.kushki_transaction_id ? `<small class="text-muted">${p.kushki_transaction_id}</small>` : '-';

                return `<tr>
                    <td class="ps-2">${fecha}</td>
                    <td class="text-end fw-bold">$${monto}</td>
                    <td class="text-center">
                        <span class="badge bg-${cls} bg-opacity-10 text-${cls} border border-${cls} border-opacity-25">${lbl}</span>
                    </td>
                    <td>${factura}</td>
                    <td>${trans}</td>
                    <td class="text-center">${p.intentos ?? 0}</td>
                    <td class="text-end pe-2"><small class="text-muted">${registrado}</small></td>
                </tr>`;
            }).join('');

        } catch (e) {
            spinner.classList.add('d-none');
            alerta.className   = 'alert mx-3 mt-3 mb-0 py-2 small shadow-sm border-0 alert-danger';
            alerta.textContent = 'Error de conexión al cargar pagos.';
        }
    };
})();
</script>
