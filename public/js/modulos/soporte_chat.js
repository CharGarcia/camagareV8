/* ============================================================================
 * Bandeja del equipo de soporte.
 *
 * Misma estrategia que la burbuja: polling adaptativo contra un endpoint de
 * "pulso" que devuelve solo un número de versión servido desde APCu. La lista
 * completa y los mensajes se piden únicamente cuando ese número cambia.
 * ========================================================================= */
(function () {
    'use strict';

    var BASE = window.SOC_BASE || '';

    var S = {
        idConversacion: 0,
        ultimoId: 0,
        versionBandeja: 0,
        versionChat: 0,
        timer: null,
        ultimaActividad: Date.now(),
        enVuelo: false,
        conversacion: null,
        sugerenciaUsada: false,  // el texto de la caja partió del copiloto
        rr: {}                   // respuestas rápidas cargadas, por id
    };

    // ── Utilidades ──────────────────────────────────────────────────────────

    function $(id) { return document.getElementById(id); }

    function esc(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function escMulti(str) { return esc(str).replace(/\n/g, '<br>'); }

    function fechaCorta(iso) {
        if (!iso) return '';
        var d = new Date(String(iso).replace(' ', 'T'));
        if (isNaN(d)) return '';
        var hoy = new Date();
        var hh = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        if (d.toDateString() === hoy.toDateString()) return hh;
        return String(d.getDate()).padStart(2, '0') + '-' +
               String(d.getMonth() + 1).padStart(2, '0') + '-' + d.getFullYear() + ' ' + hh;
    }

    function api(ruta, opciones) {
        return fetch(BASE + ruta, Object.assign({
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }, opciones || {})).then(function (r) { return r.json(); });
    }

    function post(ruta, datos) {
        return api(ruta, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(datos)
        });
    }

    function aviso(mensaje, icono) {
        if (window.Toast) {
            window.Toast.fire({ icon: icono || 'error', title: mensaje });
        } else {
            alert(mensaje);
        }
    }

    function marcarActividad() { S.ultimaActividad = Date.now(); }

    // ── Polling ─────────────────────────────────────────────────────────────

    function proximoDelay() {
        if (document.hidden) return 0;
        var inactivoMs = Date.now() - S.ultimaActividad;
        if (inactivoMs > 5 * 60 * 1000) return 30000;
        if (!document.hasFocus()) return 10000;
        return 3000;
    }

    function agendar() {
        if (S.timer) { clearTimeout(S.timer); S.timer = null; }
        var delay = proximoDelay();
        if (delay > 0) S.timer = setTimeout(ciclo, delay);
    }

    function ciclo() {
        if (S.enVuelo) { agendar(); return; }
        S.enVuelo = true;

        api('/modulos/soporte-chat/pulsoBandejaAjax')
            .then(function (r) {
                if (!r || !r.ok) return;
                if (r.v === S.versionBandeja) return;   // nada nuevo en todo el sistema

                S.versionBandeja = r.v;
                var tareas = [cargarLista()];
                if (S.idConversacion > 0) tareas.push(cargarMensajesNuevos());
                return Promise.all(tareas);
            })
            .catch(function () {})
            .finally(function () { S.enVuelo = false; agendar(); });
    }

    // ── Lista ───────────────────────────────────────────────────────────────

    function filtros() {
        var q = [];
        var estado = $('socEstado').value;
        if (estado) q.push('estado=' + encodeURIComponent(estado));
        var buscar = $('socBuscar').value.trim();
        if (buscar) q.push('buscar=' + encodeURIComponent(buscar));
        if ($('socSoloMias').checked)  q.push('solo_mias=1');
        if ($('socArchivadas').checked) q.push('archivadas=1');
        return q.length ? '?' + q.join('&') : '';
    }

    function cargarLista() {
        return api('/modulos/soporte-chat/bandejaAjax' + filtros()).then(function (r) {
            if (!r || !r.ok) {
                if (r && r.error) aviso(r.error);
                return;
            }
            pintarLista(r.data || []);
        });
    }

    var ESTADOS = {
        espera:     ['En espera', 'bg-warning text-dark'],
        atendiendo: ['Atendiendo', 'bg-info text-dark'],
        resuelta:   ['Resuelta', 'bg-success'],
        cerrada:    ['Cerrada', 'bg-secondary']
    };

    function pintarLista(items) {
        var cont = $('socLista');

        if (!items.length) {
            cont.innerHTML = '<div class="text-center text-muted py-5 small">No hay conversaciones con estos filtros.</div>';
            return;
        }

        cont.innerHTML = items.map(function (c) {
            var e = ESTADOS[c.estado] || ['', 'bg-secondary'];
            var sinLeer = parseInt(c.sin_leer_agente || 0, 10);
            var activo = (parseInt(c.id, 10) === S.idConversacion) ? ' activo' : '';
            return '' +
                '<div class="soc-item' + activo + '" data-id="' + c.id + '">' +
                  '<div class="d-flex align-items-center gap-2">' +
                    '<span class="fw-semibold small text-truncate flex-grow-1">' + esc(c.asunto || 'Consulta') + '</span>' +
                    // Gris, no rojo: informa de cuántos mensajes quedan por leer,
                    // no es una alarma. La urgencia ya la comunica el estado.
                    (sinLeer > 0 ? '<span class="badge rounded-pill bg-light text-dark border" title="' + sinLeer +
                                   ' mensaje(s) por leer">' + sinLeer + '</span>' : '') +
                  '</div>' +
                  '<div class="soc-item-prev">' +
                    '<i class="bi bi-building me-1"></i>' + esc(c.empresa_nombre || '—') +
                    ' · ' + esc(c.usuario_nombre || '—') +
                  '</div>' +
                  '<div class="soc-item-prev">' + esc(c.ultimo_mensaje || '') + '</div>' +
                  '<div class="d-flex align-items-center gap-2 mt-1">' +
                    '<span class="badge ' + e[1] + '" style="font-size:.62rem;">' + e[0] + '</span>' +
                    (c.agente_nombre ? '<small class="text-muted" style="font-size:.66rem;"><i class="bi bi-person-check me-1"></i>' + esc(c.agente_nombre) + '</small>' : '') +
                    '<small class="text-muted ms-auto" style="font-size:.66rem;">' + fechaCorta(c.ultimo_mensaje_at) + '</small>' +
                  '</div>' +
                '</div>';
        }).join('');

        Array.prototype.forEach.call(cont.querySelectorAll('.soc-item'), function (el) {
            el.addEventListener('click', function () {
                abrirConversacion(parseInt(el.dataset.id, 10), items);
            });
        });
    }

    // ── Conversación ────────────────────────────────────────────────────────

    function abrirConversacion(id, items) {
        S.idConversacion = id;
        S.ultimoId = 0;
        S.conversacion = (items || []).filter(function (c) { return parseInt(c.id, 10) === id; })[0] || null;

        marcarActividad();

        $('socVacio').classList.add('d-none');
        $('socHeader').classList.remove('d-none');
        $('socMensajes').classList.remove('d-none');
        $('socInputArea').classList.remove('d-none');
        $('socMensajes').innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div></div>';

        // Móvil: un panel a la vez
        $('socChatPanel').classList.add('soc-abierto');
        $('socListaPanel').classList.add('soc-oculto');

        pintarCabecera();

        Array.prototype.forEach.call(document.querySelectorAll('.soc-item'), function (el) {
            el.classList.toggle('activo', parseInt(el.dataset.id, 10) === id);
        });

        api('/modulos/soporte-chat/mensajesAjax?id=' + id).then(function (r) {
            if (!r || !r.ok) { aviso(r && r.error ? r.error : 'No se pudo abrir la conversación.'); return; }
            $('socMensajes').innerHTML = '';
            pintarMensajes(r.data || []);
            cargarLista(); // el contador de no leídos acaba de cambiar
        });
    }

    function pintarCabecera() {
        var c = S.conversacion;
        if (!c) return;

        $('socAsunto').textContent = c.asunto || 'Consulta';

        var meta = [];
        if (c.empresa_nombre) meta.push(c.empresa_nombre);
        if (c.usuario_nombre) meta.push(c.usuario_nombre);
        if (c.agente_nombre)  meta.push('Atiende: ' + c.agente_nombre);
        $('socMeta').textContent = meta.join(' · ');

        // El módulo desde donde se abrió la burbuja: contexto gratis para el agente.
        if (c.origen_modulo) {
            $('socOrigenTexto').textContent = c.origen_modulo;
            $('socOrigen').classList.remove('d-none');
        } else {
            $('socOrigen').classList.add('d-none');
        }
    }

    function cargarMensajesNuevos() {
        return api('/modulos/soporte-chat/mensajesAjax?id=' + S.idConversacion + '&desde=' + S.ultimoId)
            .then(function (r) {
                if (!r || !r.ok) return;
                pintarMensajes(r.data || []);
            });
    }

    function pintarMensajes(mensajes) {
        if (!mensajes.length) return;

        var cont = $('socMensajes');
        var pegadoAbajo = (cont.scrollHeight - cont.scrollTop - cont.clientHeight) < 60;

        mensajes.forEach(function (m) {
            var id = parseInt(m.id, 10);
            if (id > S.ultimoId) S.ultimoId = id;

            var div = document.createElement('div');
            div.className = 'soc-burbuja soc-burbuja-' + esc(m.rol);

            if (m.rol === 'sistema') {
                div.innerHTML = escMulti(m.contenido);
            } else {
                var autor = '<div class="fw-semibold" style="font-size:.7rem;opacity:.7;">' +
                            esc(m.autor_nombre || (m.rol === 'agente' ? 'Soporte' : 'Usuario')) + '</div>';
                div.innerHTML = autor + escMulti(m.contenido) + htmlAdjunto(m) +
                    '<div class="soc-hora">' + fechaCorta(m.created_at) + '</div>';
            }
            cont.appendChild(div);
        });

        if (pegadoAbajo) cont.scrollTop = cont.scrollHeight;
    }

    function enviar() {
        var input = $('socInput');
        var texto = input.value.trim();
        if (!texto || !S.idConversacion) return;

        input.value = '';
        input.style.height = 'auto';
        marcarActividad();

        var veniaDeIa = S.sugerenciaUsada;
        S.sugerenciaUsada = false;
        var avisoFuentes = $('socSugerenciaAviso');
        if (avisoFuentes) avisoFuentes.classList.add('d-none');

        post('/modulos/soporte-chat/enviarAjax', {
            id: S.idConversacion,
            contenido: texto,
            sugerida_por_ia: veniaDeIa,
            origen: 'bandeja'   // se escribe como soporte (el servidor lo valida)
        })
            .then(function (r) {
                if (!r || !r.ok) {
                    aviso(r && r.error ? r.error : 'No se pudo enviar la respuesta.');
                    input.value = texto;
                    S.sugerenciaUsada = veniaDeIa; // se recupera para el reintento
                    return;
                }
                return cargarMensajesNuevos().then(cargarLista);
            })
            .catch(function () {
                aviso('No se pudo enviar la respuesta.');
                input.value = texto;
            });
    }

    // ── Acciones sobre la conversación ──────────────────────────────────────

    function tomar() {
        if (!S.idConversacion) return;
        post('/modulos/soporte-chat/tomarAjax', { id: S.idConversacion }).then(function (r) {
            if (!r || !r.ok) { aviso(r && r.error ? r.error : 'No se pudo tomar la conversación.'); return; }
            aviso('Conversación asignada a ti.', 'success');
            cargarLista();
        });
    }

    function cambiarEstado(estado, mensajeOk) {
        if (!S.idConversacion) return;
        post('/modulos/soporte-chat/estadoAjax', { id: S.idConversacion, estado: estado }).then(function (r) {
            if (!r || !r.ok) { aviso(r && r.error ? r.error : 'No se pudo cambiar el estado.'); return; }
            aviso(mensajeOk, 'success');
            if (S.conversacion) S.conversacion.estado = estado;
            cargarLista();
        });
    }

    // ── Adjuntos ────────────────────────────────────────────────────────────

    /** HTML del adjunto de un mensaje: imagen en línea, el resto como enlace. */
    function htmlAdjunto(m) {
        if (!m.adjunto) return '';

        var url = BASE + '/modulos/soporte-chat/adjuntoVer?id=' + encodeURIComponent(m.id);
        var nombre = esc(m.adjunto_nombre || 'archivo');

        if (String(m.adjunto_mime || '').indexOf('image/') === 0) {
            return '<a href="' + url + '" target="_blank" rel="noopener">' +
                   '<img src="' + url + '" alt="' + nombre + '" ' +
                   'style="max-width:100%;max-height:220px;border-radius:8px;display:block;margin-top:4px;"></a>';
        }

        return '<a href="' + url + '" target="_blank" rel="noopener" ' +
               'class="d-inline-flex align-items-center gap-1 mt-1 text-decoration-none">' +
               '<i class="bi bi-paperclip"></i><span style="font-size:.8rem;">' + nombre + '</span></a>';
    }

    function subirArchivo(file) {
        if (!file || !S.idConversacion) return;

        var fd = new FormData();
        fd.append('id', S.idConversacion);
        fd.append('archivo', file);
        fd.append('texto', $('socInput').value.trim());
        fd.append('origen', 'bandeja');

        marcarActividad();
        aviso('Subiendo ' + file.name + '…', 'info');

        // Sin Content-Type: el navegador pone el boundary del multipart.
        fetch(BASE + '/modulos/soporte-chat/adjuntarAjax', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function (r) { return r.json(); })
        .then(function (r) {
            if (!r || !r.ok) { aviso(r && r.error ? r.error : 'No se pudo adjuntar el archivo.'); return; }
            $('socInput').value = '';
            return cargarMensajesNuevos().then(cargarLista);
        })
        .catch(function () { aviso('No se pudo adjuntar el archivo.'); });
    }

    // ── Respuestas rápidas ──────────────────────────────────────────────────

    function togglePanelRR(mostrar) {
        var panel = $('socRRPanel');
        if (!panel) return;
        var abrir = (mostrar === undefined) ? panel.style.display === 'none' : mostrar;
        panel.style.display = abrir ? 'flex' : 'none';
        if (abrir) cargarRR();
    }

    function cargarRR() {
        api('/modulos/soporte-chat/respuestasRapidasAjax').then(function (r) {
            if (!r || !r.ok) { aviso(r && r.error ? r.error : 'No se pudieron cargar las respuestas.'); return; }
            pintarRR(r.data || { empresa: [], personales: [] });
        });
    }

    function pintarRR(data) {
        var cont = $('socRRLista');
        var html = '';

        function grupo(titulo, items, icono) {
            if (!items.length) return '';
            var h = '<div class="px-3 py-1 bg-light text-muted fw-semibold" style="font-size:.72rem;">' +
                    '<i class="bi ' + icono + ' me-1"></i>' + titulo + '</div>';
            items.forEach(function (i) {
                h += '<div class="px-3 py-2 border-bottom soc-rr-item d-flex align-items-start gap-2" data-id="' + i.id + '">' +
                       '<div class="flex-grow-1" style="cursor:pointer;" data-usar="1">' +
                         '<div class="fw-semibold">' + esc(i.titulo) + '</div>' +
                         '<div class="text-muted soc-item-prev">' + esc(i.contenido) + '</div>' +
                       '</div>' +
                       '<button type="button" class="btn btn-sm btn-link p-0 text-secondary" data-editar="1" title="Editar">' +
                         '<i class="bi bi-pencil"></i></button>' +
                       '<button type="button" class="btn btn-sm btn-link p-0 text-danger" data-borrar="1" title="Eliminar">' +
                         '<i class="bi bi-trash"></i></button>' +
                     '</div>';
            });
            return h;
        }

        html += grupo('Del equipo', data.empresa || [], 'bi-building');
        html += grupo('Personales', data.personales || [], 'bi-person');

        if (html === '') {
            html = '<div class="text-center text-muted py-4 small">Sin respuestas guardadas.<br>Crea una con los botones de abajo.</div>';
        }
        cont.innerHTML = html;

        // Guardadas para poder editarlas sin volver a pedirlas.
        S.rr = {};
        (data.empresa || []).concat(data.personales || []).forEach(function (i) { S.rr[i.id] = i; });

        Array.prototype.forEach.call(cont.querySelectorAll('.soc-rr-item'), function (el) {
            var id = parseInt(el.dataset.id, 10);
            el.querySelector('[data-usar]').addEventListener('click', function () {
                var input = $('socInput');
                input.value = S.rr[id] ? S.rr[id].contenido : '';
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 110) + 'px';
                togglePanelRR(false);
                input.focus();
            });
            el.querySelector('[data-editar]').addEventListener('click', function () {
                var i = S.rr[id];
                if (!i) return;
                $('socRRId').value = id;
                $('socRRTitulo').value = i.titulo;
                $('socRRContenido').value = i.contenido;
                $('socRRForm').style.display = 'block';
            });
            el.querySelector('[data-borrar]').addEventListener('click', function () {
                if (!confirm('¿Eliminar esta respuesta rápida?')) return;
                post('/modulos/soporte-chat/eliminarRespuestaRapidaAjax', { id: id }).then(function (r) {
                    if (!r || !r.ok) { aviso(r && r.error ? r.error : 'No se pudo eliminar.'); return; }
                    cargarRR();
                });
            });
        });
    }

    function nuevaRR(tipo) {
        $('socRRId').value = '';
        $('socRRTipo').value = tipo;
        $('socRRTitulo').value = '';
        $('socRRContenido').value = $('socInput').value.trim(); // aprovecha lo ya escrito
        $('socRRForm').style.display = 'block';
        $('socRRTitulo').focus();
    }

    function guardarRR() {
        var datos = {
            id: parseInt($('socRRId').value || '0', 10),
            titulo: $('socRRTitulo').value.trim(),
            contenido: $('socRRContenido').value.trim(),
            tipo: $('socRRTipo').value
        };
        if (!datos.titulo || !datos.contenido) { aviso('Completa el título y el contenido.'); return; }

        post('/modulos/soporte-chat/guardarRespuestaRapidaAjax', datos).then(function (r) {
            if (!r || !r.ok) { aviso(r && r.error ? r.error : 'No se pudo guardar.'); return; }
            $('socRRForm').style.display = 'none';
            cargarRR();
        });
    }

    // ── Configuración ───────────────────────────────────────────────────────

    function cargarConfig() {
        api('/modulos/soporte-chat/configGet').then(function (r) {
            if (!r || !r.ok) { aviso(r && r.error ? r.error : 'No se pudo cargar la configuración.'); return; }
            var c = r.data || {};

            $('cfgActivo').checked = c.activo === true || c.activo === 't' || c.activo === '1';
            $('cfgCopiloto').checked = c.copiloto_activo === true || c.copiloto_activo === 't' || c.copiloto_activo === '1';
            $('cfgBienvenida').value = c.mensaje_bienvenida || '';
            $('cfgFueraHorario').value = c.mensaje_fuera_horario || '';
            $('cfgHoraInicio').value = String(c.hora_inicio || '08:00:00').substring(0, 5);
            $('cfgHoraFin').value = String(c.hora_fin || '18:00:00').substring(0, 5);
            $('cfgCorreo').value = c.correo_alertas || '';
            $('cfgMinutos').value = c.minutos_alerta_sin_atender != null ? c.minutos_alerta_sin_atender : 15;
            $('cfgDiasArchivar').value = c.dias_archivar_cerradas != null ? c.dias_archivar_cerradas : 90;

            var dias = String(c.dias_atencion || '').split(',');
            Array.prototype.forEach.call(document.querySelectorAll('.cfg-dia'), function (chk) {
                chk.checked = dias.indexOf(chk.value) !== -1;
            });
        });
    }

    function guardarConfig() {
        var dias = [];
        Array.prototype.forEach.call(document.querySelectorAll('.cfg-dia'), function (chk) {
            if (chk.checked) dias.push(chk.value);
        });

        post('/modulos/soporte-chat/configStore', {
            activo: $('cfgActivo').checked,
            copiloto_activo: $('cfgCopiloto').checked,
            mensaje_bienvenida: $('cfgBienvenida').value,
            mensaje_fuera_horario: $('cfgFueraHorario').value,
            dias_atencion: dias.join(','),
            hora_inicio: $('cfgHoraInicio').value,
            hora_fin: $('cfgHoraFin').value,
            correo_alertas: $('cfgCorreo').value,
            minutos_alerta_sin_atender: parseInt($('cfgMinutos').value || '0', 10),
            dias_archivar_cerradas: parseInt($('cfgDiasArchivar').value || '0', 10)
        }).then(function (r) {
            if (!r || !r.ok) { aviso(r && r.error ? r.error : 'No se pudo guardar.'); return; }
            aviso('Configuración guardada.', 'success');
        });
    }

    // ── Copiloto ────────────────────────────────────────────────────────────

    /**
     * Pide un borrador a la IA y lo deja en la caja de escritura. No envía nada:
     * el agente lo lee, lo corrige y decide. Si ya había texto escrito, pregunta
     * antes de pisarlo.
     */
    function sugerir() {
        var btn = $('socBtnSugerir');
        var input = $('socInput');
        if (!btn || !S.idConversacion) return;

        if (input.value.trim() && !confirm('Ya tienes texto escrito. ¿Reemplazarlo por la sugerencia?')) {
            return;
        }

        var htmlOriginal = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Redactando…';
        marcarActividad();

        post('/modulos/soporte-chat/sugerirAjax', { id: S.idConversacion })
            .then(function (r) {
                if (!r || !r.ok) {
                    aviso(r && r.error ? r.error : 'No se pudo generar la sugerencia.');
                    return;
                }
                input.value = r.data.contenido || '';
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 110) + 'px';
                input.focus();

                // Queda marcado para guardar sugerida_por_ia al enviar, aunque
                // el agente reescriba parte del texto.
                S.sugerenciaUsada = true;
                mostrarFuentes(r.data.fuentes || []);
            })
            .catch(function () { aviso('No se pudo generar la sugerencia.'); })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = htmlOriginal;
            });
    }

    /** Muestra en qué se basó el borrador, para que el agente pueda verificarlo. */
    function mostrarFuentes(fuentes) {
        var el = $('socSugerenciaAviso');
        if (!el) return;

        if (!fuentes.length) {
            el.textContent = 'Sin fuentes: revísala con cuidado antes de enviar.';
            el.classList.remove('d-none');
            return;
        }

        var nombres = fuentes.slice(0, 3).map(function (f) {
            return f.tipo === 'manual' && f.seccion ? f.titulo + ' › ' + f.seccion : f.titulo;
        });
        el.textContent = 'Basado en: ' + nombres.join(' · ') + (fuentes.length > 3 ? ' (+' + (fuentes.length - 3) + ')' : '');
        el.classList.remove('d-none');
    }

    function eliminar() {
        if (!S.idConversacion) return;

        var seguir = function () {
            post('/modulos/soporte-chat/eliminarAjax', { id: S.idConversacion }).then(function (r) {
                if (!r || !r.ok) { aviso(r && r.error ? r.error : 'No se pudo eliminar.'); return; }
                aviso('Conversación eliminada.', 'success');
                S.idConversacion = 0;
                S.conversacion = null;
                $('socHeader').classList.add('d-none');
                $('socMensajes').classList.add('d-none');
                $('socInputArea').classList.add('d-none');
                $('socVacio').classList.remove('d-none');
                cargarLista();
            });
        };

        if (window.Swal) {
            window.Swal.fire({
                icon: 'warning',
                title: '¿Eliminar esta conversación?',
                text: 'Se ocultará del sistema. El historial se conserva en la base de datos.',
                showCancelButton: true,
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then(function (res) { if (res.isConfirmed) seguir(); });
        } else if (confirm('¿Eliminar esta conversación?')) {
            seguir();
        }
    }

    // ── Arranque ────────────────────────────────────────────────────────────

    function debounce(fn, ms) {
        var t = null;
        return function () {
            clearTimeout(t);
            t = setTimeout(fn, ms);
        };
    }

    function init() {
        $('socBtnRefrescar').addEventListener('click', function () { marcarActividad(); cargarLista(); });
        $('socEstado').addEventListener('change', function () { marcarActividad(); cargarLista(); });
        $('socSoloMias').addEventListener('change', function () { marcarActividad(); cargarLista(); });
        $('socArchivadas').addEventListener('change', function () { marcarActividad(); cargarLista(); });
        $('socBuscar').addEventListener('input', debounce(function () { marcarActividad(); cargarLista(); }, 350));

        $('socForm').addEventListener('submit', function (e) { e.preventDefault(); enviar(); });

        $('socBtnTomar').addEventListener('click', tomar);
        $('socBtnResolver').addEventListener('click', function () { cambiarEstado('resuelta', 'Marcada como resuelta.'); });
        $('socBtnCerrar').addEventListener('click', function () { cambiarEstado('cerrada', 'Conversación cerrada.'); });

        // Solo existe si el usuario tiene permiso de eliminar (lo decide la vista).
        var btnEliminar = $('socBtnEliminar');
        if (btnEliminar) btnEliminar.addEventListener('click', eliminar);

        // Solo existe si el copiloto está activo y hay IA configurada.
        var btnSugerir = $('socBtnSugerir');
        if (btnSugerir) btnSugerir.addEventListener('click', sugerir);

        // Adjuntos
        $('socBtnAdjuntar').addEventListener('click', function () { $('socFile').click(); });
        $('socFile').addEventListener('change', function () {
            if (this.files && this.files.length) subirArchivo(this.files[0]);
            this.value = '';
        });

        // Respuestas rápidas
        $('socBtnRR').addEventListener('click', function () { togglePanelRR(); });
        $('socRRCerrar').addEventListener('click', function () { togglePanelRR(false); });
        $('socRRCancelar').addEventListener('click', function () { $('socRRForm').style.display = 'none'; });
        $('socRRGuardar').addEventListener('click', guardarRR);
        $('socRRNuevaEmpresa').addEventListener('click', function () { nuevaRR('empresa'); });
        $('socRRNuevaPersonal').addEventListener('click', function () { nuevaRR('personal'); });

        // Configuración: solo existe con permiso de actualizar.
        var modalConfig = document.getElementById('socModalConfig');
        if (modalConfig) {
            modalConfig.addEventListener('show.bs.modal', cargarConfig);
            $('cfgGuardar').addEventListener('click', guardarConfig);
        }

        $('socBtnVolver').addEventListener('click', function () {
            $('socChatPanel').classList.remove('soc-abierto');
            $('socListaPanel').classList.remove('soc-oculto');
        });

        var input = $('socInput');
        input.addEventListener('input', function () {
            marcarActividad();
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 110) + 'px';
            // Si vacía la caja, lo que escriba después ya no viene del copiloto.
            if (!this.value.trim()) {
                S.sugerenciaUsada = false;
                var av = $('socSugerenciaAviso');
                if (av) av.classList.add('d-none');
            }
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviar(); }
        });

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) { marcarActividad(); ciclo(); }
        });
        window.addEventListener('focus', function () { marcarActividad(); agendar(); });

        cargarLista();
        agendar();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
