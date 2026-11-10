/* ════════════════════════════════════════════════════════════════════════════
   PERSONALIZAR REPORTE (por empresa) — ventana común de los reportes del catálogo
   App\Helpers\ColumnasReporte. La abre el botón «Personalizar» del encabezado de
   filtros (solo administradores) con window.CMG_personalizarReporte().

   Al guardar o restablecer emite en document el evento `reportePersonalizado`
   ({ detail: { modulo, vistas } }, con `vistas` = la configuración ya guardada, igual que
   la entrega getPersonalizacionReporteAjax): el reporte lo escucha y se vuelve a dibujar.
   Manual: docs/manual/guias/personalizar-reportes.md
════════════════════════════════════════════════════════════════════════════ */
(function () {
    if (window.__CMG_personalizarReporte) return;
    window.__CMG_personalizarReporte = true;

    let vistas = [];        // estado en edición: [{vista, titulo, auto, orientacion, columnas:[…]}]
    let guardando = false;

    const el   = id => document.getElementById(id);
    const esc  = t => String(t == null ? '' : t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const base = () => (window.BASE_URL || '').replace(/\/$/, '');
    const url  = accion => base() + '/' + el('modalPersonalizarReporte').dataset.ruta + '/' + accion;
    const aviso = (titulo, texto, icono) => window.Swal ? Swal.fire(titulo, texto, icono) : alert(titulo + (texto ? '\n' + texto : ''));

    function vistaActual() {
        return vistas.find(v => v.vista === el('pr-vista').value) || null;
    }

    function pintarVista() {
        const v = vistaActual();
        const tbody = el('pr-columnas');
        if (!v) { tbody.innerHTML = ''; return; }

        const sel = el('pr-orientacion');
        sel.options[0].textContent = 'Automática (' + (v.auto_txt || (v.auto === 'P' ? 'vertical' : 'horizontal')) + ')';
        sel.value = v.orientacion;

        // Medios de la vista: [Pantalla y Excel, PDF] o [Pantalla, Excel, PDF].
        const medios = v.medios || [];
        el('pr-cabecera').innerHTML = '<th>Columna</th>' + medios.map(m =>
            `<th class="text-center" style="width:${m.titulo.length > 8 ? 120 : 72}px;">${esc(m.titulo)}</th>`).join('');

        tbody.innerHTML = v.columnas.map((c, i) => {
            const check = (campo) => (c.no || []).includes(campo)
                ? '<span class="text-muted" title="Esta columna no existe aquí">—</span>'
                : (c.fija
                    ? '<i class="bi bi-lock-fill text-muted" title="Siempre se muestra"></i>'
                    : `<input type="checkbox" class="form-check-input pr-check" data-i="${i}" data-campo="${campo}" ${c[campo] ? 'checked' : ''}>`);
            return `<tr>
                <td>${esc(c.titulo)}</td>
                ${medios.map(m => `<td class="text-center">${check(m.clave)}</td>`).join('')}
            </tr>`;
        }).join('');
    }

    function pedirVistas() {
        return fetch(url('getPersonalizacionReporteAjax'), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(res => {
                if (!res.ok) throw new Error(res.error || 'No se pudo cargar la personalización.');
                return res.data || [];
            });
    }

    function cargar() {
        el('pr-columnas').innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Cargando…</td></tr>';
        return pedirVistas()
            .then(data => {
                vistas = data;
                el('pr-vista').innerHTML = vistas.map(v =>
                    `<option value="${esc(v.vista)}">${esc(v.titulo)}${v.personalizada ? ' •' : ''}</option>`).join('');
                // Abre en la vista que el reporte tiene en pantalla, si el módulo la informa.
                const actual = typeof window.CMG_vistaReporteActual === 'function' ? window.CMG_vistaReporteActual() : '';
                if (actual && vistas.some(v => v.vista === actual)) el('pr-vista').value = actual;
                pintarVista();
            });
    }

    window.CMG_personalizarReporte = function () {
        const modalEl = el('modalPersonalizarReporte');
        if (!modalEl) return;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        cargar().catch(err => {
            el('pr-columnas').innerHTML = `<tr><td colspan="4" class="text-center text-danger py-3">${esc(err.message)}</td></tr>`;
        });
    };

    function enviar(cuerpo, mensaje) {
        if (guardando) return;
        guardando = true;
        const btnG = el('pr-btn-guardar'), btnR = el('pr-btn-restablecer');
        btnG.disabled = btnR.disabled = true;

        fetch(url('guardarPersonalizacionReporteAjax'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams(cuerpo).toString(),
        })
            .then(r => r.json())
            .then(res => {
                if (!res.ok) throw new Error(res.error || 'No se pudo guardar.');
                const modalEl = el('modalPersonalizarReporte');
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                // El reporte recibe la configuración tal como quedó guardada (también tras
                // restablecer); si no se pudo releer, igual se le avisa para que se actualice.
                const avisar = (data) => document.dispatchEvent(new CustomEvent('reportePersonalizado',
                    { detail: { modulo: modalEl.dataset.ruta, vistas: data } }));
                pedirVistas().then(avisar).catch(() => avisar(null));
                if (window.Swal) {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: mensaje, showConfirmButton: false, timer: 2500 });
                }
            })
            .catch(err => aviso('No se guardó', err.message, 'error'))
            .finally(() => {
                guardando = false;
                btnG.disabled = btnR.disabled = false;
            });
    }

    document.addEventListener('change', function (e) {
        if (e.target.id === 'pr-vista') { pintarVista(); return; }
        const v = vistaActual();
        if (!v) return;
        if (e.target.id === 'pr-orientacion') { v.orientacion = e.target.value; return; }
        if (e.target.classList && e.target.classList.contains('pr-check')) {
            v.columnas[+e.target.dataset.i][e.target.dataset.campo] = e.target.checked;
        }
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('#pr-btn-guardar')) {
            const datos = {};
            vistas.forEach(v => {
                datos[v.vista] = {
                    ocultas:       v.columnas.filter(c => !c.fija && !c.pantalla).map(c => c.clave),
                    ocultas_excel: v.columnas.filter(c => !c.fija && !c.excel).map(c => c.clave),
                    ocultas_pdf:   v.columnas.filter(c => !c.fija && !c.pdf).map(c => c.clave),
                    orientacion:   v.orientacion,
                };
            });
            enviar({ vistas: JSON.stringify(datos) }, 'Reporte personalizado');
            return;
        }
        if (e.target.closest('#pr-btn-restablecer')) {
            const seguir = () => enviar({ restablecer: '1' }, 'Reporte restablecido');
            if (!window.Swal) { if (confirm('¿Restablecer todas las vistas de este reporte?')) seguir(); return; }
            Swal.fire({
                title: '¿Restablecer el reporte?',
                text: 'Todas sus vistas vuelven a las columnas y la orientación originales, para toda la empresa.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, restablecer',
                cancelButtonText: 'Cancelar',
            }).then(r => { if (r.isConfirmed) seguir(); });
        }
    });
})();
