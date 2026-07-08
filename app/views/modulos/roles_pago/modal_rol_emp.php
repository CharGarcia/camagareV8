<div class="modal fade" id="modalRolEmp" tabindex="-1" aria-hidden="true" style="z-index:1065;">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-person-badge me-2 text-primary"></i>
                    <span id="rolemp_nombre">Empleado</span>
                    <span class="text-muted small ms-2" id="rolemp_ident"></span>
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-danger" id="rolemp_pdf" title="PDF del rol"><i class="bi bi-file-earmark-pdf"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-info" id="rolemp_email" title="Enviar por correo"><i class="bi bi-envelope"></i></button>
                    <button type="button" class="btn-close shadow-none ms-1" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item"><a class="nav-link active py-2 small" data-bs-toggle="tab" href="#rolemp-general" role="tab"><i class="bi bi-cash-coin me-1"></i>General</a></li>
                    <li class="nav-item"><a class="nav-link py-2 small" data-bs-toggle="tab" href="#rolemp-prov" role="tab"><i class="bi bi-piggy-bank me-1"></i>Provisiones</a></li>
                    <li class="nav-item"><a class="nav-link py-2 small" data-bs-toggle="tab" href="#rolemp-asiento" role="tab"><i class="bi bi-journal-text me-1"></i>Asiento contable</a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="rolemp-general" role="tabpanel"><div id="rolemp_general_body"></div></div>
                    <div class="tab-pane fade" id="rolemp-prov" role="tabpanel"><div id="rolemp_prov_body"></div></div>
                    <div class="tab-pane fade" id="rolemp-asiento" role="tabpanel"><div id="rolemp_asiento_body"></div></div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>
