/* Anexo Transaccional Simplificado (ATS) */
(function () {
  'use strict';

  const base   = window.BASE_URL || '';
  const modulo = window.R_MODULO || 'modulos/anexo-ats';

  const form       = document.getElementById('form-ats');
  const mesSel      = document.getElementById('ats-mes');
  const semestWrap  = document.getElementById('ats-semestral-wrap');
  const semestChk   = document.getElementById('ats-semestral');
  const ventasChk   = document.getElementById('ats-ventas');
  const btn         = document.getElementById('ats-generar');
  const resultado   = document.getElementById('ats-resultado');

  // El semestre RIMPE solo aplica a junio (06) y diciembre (12)
  function toggleSemestral() {
    const m = mesSel.value;
    const aplica = (m === '06' || m === '12');
    semestWrap.style.display = aplica ? '' : 'none';
    if (!aplica) semestChk.checked = false;
  }
  mesSel.addEventListener('change', toggleSemestral);
  toggleSemestral();

  function alerta(tipo, html) {
    resultado.innerHTML =
      '<div class="alert alert-' + tipo + ' mb-0">' + html + '</div>';
  }

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // Lista colapsable de errores/advertencias (máx. 50 visibles, salvo 'todos')
  function listaValidacion(titulo, items, color, abierta, todos) {
    if (!items || items.length === 0) return '';
    const visibles = todos ? items : items.slice(0, 50);
    let h = '<details class="mt-2"' + (abierta ? ' open' : '') + '><summary class="text-' + color + ' fw-semibold">' +
            titulo + ' (' + items.length + ')</summary>' +
            '<ul class="small mb-0 mt-1">';
    visibles.forEach(function (it) { h += '<li class="text-' + color + '">' + esc(it) + '</li>'; });
    if (items.length > visibles.length) {
      h += '<li class="text-muted">… y ' + (items.length - visibles.length) + ' más</li>';
    }
    h += '</ul></details>';
    return h;
  }

  let enCurso = false;

  function datosForm(revisar) {
    const fd = new FormData();
    fd.append('mes', mesSel.value);
    fd.append('anio', document.getElementById('ats-anio').value);
    fd.append('semestral', semestChk.checked ? '1' : '0');
    fd.append('ventas', ventasChk.checked ? '1' : '0');
    if (revisar) fd.append('revisar', '1');
    return fd;
  }

  function pedir(fd) {
    return fetch(base + '/' + modulo + '/generarAjax', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); });
  }

  function ocupado(texto) {
    enCurso = true;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + texto;
  }

  function libre() {
    enCurso = false;
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-cogs me-1"></i> Generar';
  }

  // Paso 1: revisar el periodo. Si hay errores se muestran y NO se genera,
  // salvo que el usuario lo pida expresamente; sin errores, se genera directo.
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (enCurso) return;

    ocupado('Revisando...');
    alerta('info', 'Revisando los documentos del periodo antes de generar el anexo...');

    pedir(datosForm(true))
      .then(function (res) {
        const j = res.j || {};
        if (!res.ok || !j.ok) {
          alerta('danger', '<strong>Error:</strong> ' + (j.mensaje || 'No se pudo revisar el periodo.'));
          libre();
          return;
        }
        const errores = j.errores || [];
        if (errores.length === 0) {
          generar();
          return;
        }
        let html = '<div><i class="fas fa-exclamation-triangle text-danger me-1"></i> La revisión encontró <strong>' +
                   errores.length + '</strong> error(es) que el SRI rechazaría. Corríjalos en los documentos o fichas ' +
                   'indicados y vuelva a pulsar <strong>Generar</strong>. El anexo todavía no se generó.</div>';
        html += listaValidacion('Errores', errores, 'danger', true, true);
        html += listaValidacion('Advertencias', j.advertencias || [], 'warning', false, true);
        html += '<div class="mt-3"><button type="button" class="btn btn-outline-danger btn-sm" id="ats-generar-igual">' +
                '<i class="fas fa-cogs me-1"></i> Generar de todas formas</button></div>';
        resultado.innerHTML = '<div class="alert alert-light border mb-0">' + html + '</div>';
        document.getElementById('ats-generar-igual').addEventListener('click', function () {
          if (enCurso) return;
          generar();
        });
        libre();
      })
      .catch(function () {
        alerta('danger', 'Error de comunicación al revisar el periodo.');
        libre();
      });
  });

  // Paso 2: generar el XML/ZIP.
  function generar() {
    ocupado('Generando...');
    alerta('info', 'Generando Anexo ATS, espere por favor...');

    pedir(datosForm(false))
      .then(function (res) {
        const j = res.j || {};
        if (!res.ok || !j.ok) {
          alerta('danger', '<strong>Error:</strong> ' + (j.mensaje || 'No se pudo generar el anexo.'));
          return;
        }
        const errores = j.errores || [];
        const advert  = j.advertencias || [];

        let html = '<div class="d-flex flex-column gap-2">';
        var ambienteBadge = j.ambiente
          ? ' <span class="badge ' + (j.ambiente === 'Producción' ? 'bg-danger' : 'bg-secondary') +
            '">Ambiente: ' + j.ambiente + '</span>'
          : '';
        // Qué módulos entraron: el de ventas es opcional y conviene que quede a la
        // vista, para que nadie presente sin ventas sin darse cuenta (ni al revés).
        var detalleVentas = j.incluye_ventas === false
          ? ' <span class="badge bg-warning text-dark">Sin módulo de ventas</span>'
          : ' y <strong>' + (j.ventas || 0) + '</strong> de ventas';

        if (errores.length === 0) {
          html += '<div><i class="fas fa-check-circle text-success me-1"></i> Anexo generado con <strong>' +
                  (j.registros || 0) + '</strong> registro(s) de compras' + detalleVentas + '.' + ambienteBadge +
                  ' <span class="text-success">Validación sin errores.</span></div>';
        } else {
          html += '<div><i class="fas fa-exclamation-triangle text-danger me-1"></i> Anexo generado con <strong>' +
                  (j.registros || 0) + '</strong> registro(s) de compras' + detalleVentas + '.' + ambienteBadge +
                  ' La validación encontró <strong>' + errores.length + '</strong> error(es). Corrija antes de cargar al SRI.</div>';
        }
        html += '<div class="d-flex gap-2 flex-wrap">';
        if (j.url_zip) {
          html += '<a class="btn btn-success btn-sm" href="' + j.url_zip +
                  '"><i class="fas fa-file-archive me-1"></i> Descargar ' + j.zip + ' (para el SRI)</a>';
        }
        html += '<a class="btn btn-outline-secondary btn-sm" href="' + j.url_xml +
                '"><i class="fas fa-file-code me-1"></i> Descargar ' + j.xml + '</a>';
        if (j.url_excel) {
          html += '<a class="btn btn-outline-success btn-sm" href="' + j.url_excel +
                  '"><i class="fas fa-file-excel me-1"></i> Descargar detalle (Excel)</a>';
        }
        html += '</div>';
        html += listaValidacion('Errores', errores, 'danger');
        html += listaValidacion('Advertencias', advert, 'warning');
        html += '</div>';
        resultado.innerHTML = '<div class="alert alert-light border mb-0">' + html + '</div>';
      })
      .catch(function () {
        alerta('danger', 'Error de comunicación al generar el anexo.');
      })
      .finally(libre);
  }
})();
