"""Correos de marca de los flujos de propuestas v3 (Borrador, Cambios, Final).

Cada función devuelve el código de un nodo Code que arma {asunto, html}; el nodo de correo solo usa
={{ $json.asunto }} y ={{ $json.html }}. Nunca enlazar *.easypanel.host (Hostinger lo rechaza como spam).
"""
from email_tpl import boton, caja, etiqueta, js_correo, nota, parrafo

VER = 'https://automatizatech.cl/ver-presentacion.php?id='
PANEL = 'https://automatizatech.cl/wp-admin/admin.php?page=automatiza-proposals&edit_id='


def correo_borrador():
    prep = """const a = $('Armar payload').first().json;
const c = $('Crear en WordPress').first().json;
const v = $('Vista previa (sin fotos)').first().json;
const ok = !!v.view_url;"""
    aviso = caja('<strong>La vista previa no se pudo generar.</strong> La propuesta quedó creada en borrador; '
                 'en el panel, «Pedir cambios» (aunque sea sin comentario) la vuelve a generar.', 'aviso')
    cuerpo = ("${ok ? '' : `" + aviso + "`}"
              + parrafo('<strong>Lo que pidió el cliente</strong>')
              + caja('${br(a.resumen) || "Sin resumen."}')
              + parrafo('Los precios dicen <strong>«Por confirmar»</strong> hasta que los escribas en el panel. '
                        'No se ha gastado nada en fotos.')
              + '<div style="margin:6px 0 4px;">'
              + boton(VER + '${esc(c.unique_id)}', '👀 Ver vista previa')
              + boton('${esc(c.panel_url)}', '✏️ Revisar y aprobar', primario=False)
              + '</div>')
    return js_correo(prep, "(ok ? '' : '⚠️ ') + a.payload.company_name + ' · borrador listo para revisar'",
                     'Borrador listo: ${esc(a.payload.company_name)}', etiqueta('Borrador', 'borrador'), cuerpo)


def correo_cambios_ok():
    prep = """const est = $('Leer estado').first().json.body;
const pf = $('Payload final').first().json;
const v = $('Vista previa').first().json;
const ok = !!v.view_url;"""
    aviso = caja('<strong>La nueva vista previa no se pudo generar.</strong> Los cambios quedaron guardados; '
                 '«Pedir cambios» sin comentario la vuelve a generar.', 'aviso')
    cuerpo = ("${ok ? '' : `" + aviso + "`}"
              + parrafo('<strong>Tu comentario</strong>')
              + caja('${br(est.ultimo_comentario) || "(sin comentario: solo cambiaste precios)"}')
              + parrafo('Tus precios se mantienen tal como los dejaste en el panel. No se ha gastado nada en fotos.')
              + '<div style="margin:6px 0 4px;">'
              + boton(VER + '${esc(pf.unique_id)}', '👀 Ver la nueva vista previa')
              + boton(PANEL + '${esc(pf.id)}', '✏️ Seguir revisando o aprobar', primario=False)
              + '</div>')
    return js_correo(prep, "(ok ? '' : '⚠️ ') + pf.company + ' · cambios aplicados'",
                     'Cambios aplicados: ${esc(pf.company)}', etiqueta('Cambios aplicados', 'cambios'), cuerpo)


def correo_cambios_error():
    prep = """const m = $('Motivo del error').first().json;
const me = $('Marcar error').first().json;
const marcado = me.statusCode === 200;"""
    marcado = parrafo('La propuesta quedó en estado <strong>error</strong>. Desde el panel puedes volver a '
                      '«Pedir cambios» o aprobarla.')
    no_marcado = caja('<strong>Tampoco se pudo marcar como error</strong> (HTTP ${esc(me.statusCode)}): puede haber '
                      'quedado en «ajustando». Revísala en el panel.', 'error')
    cuerpo = (caja('${br(m.reason)}', 'error')
              + "${marcado ? `" + marcado + "` : `" + no_marcado + "`}"
              + '<div style="margin:6px 0 4px;">' + boton(PANEL + '${esc(m.id)}', '✏️ Abrir en el panel') + '</div>'
              + nota('Ejecución de n8n: ${esc(m.exec)}'))
    return js_correo(prep, "'⚠️ ' + m.company + ' · no se pudieron aplicar los cambios'",
                     'No se pudieron aplicar los cambios: ${esc(m.company)}', etiqueta('Error', 'error'), cuerpo)


def correo_error_flujo():
    """Correo del workflow de errores: cualquier flujo v3 que se caiga sin llegar a su propio aviso."""
    prep = """const w = $json.workflow || {};
const x = $json.execution || {};
const err = x.error || {};
const nombre = String(w.name || 'flujo de propuestas');
const esBorrador = /borrador/i.test(nombre);"""
    borrador = caja('<strong>Puede que una reunión no haya generado su borrador.</strong> La transcripción sigue en '
                    'Drive › Transcripciones: para reintentar, vuelve a subirla (una copia) o pídemelo.', 'aviso')
    otros = caja('<strong>Si era una propuesta en «ajustando» o «generando», puede haber quedado trabada.</strong> '
                 'Revísala en el panel de propuestas.', 'aviso')
    cuerpo = ("${esBorrador ? `" + borrador + "` : `" + otros + "`}"
              + parrafo('<strong>Qué falló</strong>')
              + caja('${esc(err.message || "Sin mensaje de error")}', 'error')
              + nota('Flujo: ${esc(nombre)} · paso: ${esc(x.lastNodeExecuted || "desconocido")} · '
                     'ejecución de n8n: ${esc(x.id || "")}'))
    return js_correo(prep, "'⚠️ Falló ' + nombre", 'Falló un flujo de propuestas: ${esc(nombre)}',
                     etiqueta('Error', 'error'), cuerpo)


def correo_final():
    prep = """const v = $('Verificar').isExecuted ? $('Verificar').first().json : $('Motivo lectura').first().json;
const g = $('Guardar resultado').first().json;
const rev = $('Revisar fotos').isExecuted ? $('Revisar fotos').first().json.fotos_reemplazadas : 0;
const ok = v.ok;
const li = (t) => `<li style="margin:0 0 6px;">${t}</li>`;"""
    no_guardado = caja('<strong>No se pudo guardar el resultado en WordPress</strong> (HTTP ${esc(g.statusCode)}): '
                       'la propuesta puede seguir en «generando». Revísala en el panel.', 'error')
    lista_ok = caja('<ul style="margin:0;padding-left:18px;">'
                    '${li("Presentación publicada y accesible")}'
                    '${li("PDF generado")}'
                    '${li(esc(v.fotos_locales) + " de " + esc(v.fotos_pedidas) + " fotos guardadas junto a la presentación")}'
                    '${rev ? li(esc(rev) + " descripciones de fotos reemplazadas por el filtro (pedían pantallas, texto o personas)") : ""}'
                    '${li("El chatbot de demo respondió")}'
                    '</ul>', 'ok')
    lista_mal = (caja('<ul style="margin:0;padding-left:18px;">${(v.problemas || []).map((p) => li(esc(p))).join("")}</ul>', 'error')
                 + parrafo('La propuesta quedó en <strong>error</strong>: desde el panel puedes volver a aprobarla o pedir cambios.'))
    chat = parrafo('<strong>El chatbot respondió a «Hola»:</strong>') + caja('${br(v.chat_respuesta)}')
    botones = ('<div style="margin:6px 0 4px;">'
               + "${v.unique_id ? `" + boton(VER + '${esc(v.unique_id)}', '📊 Ver la presentación final') + "` : ''}"
               + boton(PANEL + '${esc(v.id)}', '✏️ Abrir en el panel', primario=False)
               + '</div>')
    cuerpo = ("${g.statusCode === 200 ? '' : `" + no_guardado + "`}"
              + "${ok ? `" + lista_ok + "` : `" + lista_mal + "`}"
              + "${v.chat_respuesta ? `" + chat + "` : ''}"
              + botones
              + nota('El PDF se descarga desde la última lámina. El envío al cliente lo haces tú desde el panel, '
                     'con la casilla «Enviar correo».'))
    etiquetas = "${ok ? `" + etiqueta('Lista para enviar', 'lista') + "` : `" + etiqueta('Con problemas', 'error') + "`}"
    return js_correo(prep, "(ok ? '✅ ' : '⚠️ ') + v.company + (ok ? ' · lista para enviar' : ' · la versión final tiene problemas')",
                     "${ok ? 'Lista para enviar' : 'La versión final tiene problemas'}: ${esc(v.company)}",
                     etiquetas, cuerpo)
