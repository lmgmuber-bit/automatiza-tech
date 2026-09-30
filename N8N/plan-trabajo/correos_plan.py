"""Correos de marca a Luis de los flujos n8n del plan de trabajo (1 Borrador, 2 Cambios y 3 Render).

Reutiliza la plantilla de las propuestas (N8N/propuestas-v3/email_tpl.py) y cambia solo el rótulo del encabezado
y el pie. Cada función devuelve el código de un nodo Code que arma {asunto, html} (el del render, además,
{enviar}); el nodo de correo usa ={{ $json.asunto }} y ={{ $json.html }}.
Solo enlaza al panel en automatizatech.cl: nunca a *.easypanel.host, que el SMTP de Hostinger rechaza como spam
(554 5.7.1, 2026-09-24). La vista previa del plan se abre desde el panel.
Nada de esto le llega al cliente: en la Etapa 1 los correos van solo a Luis.
"""
import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'propuestas-v3'))
from email_tpl import JS_ESC, boton, caja, etiqueta, marco, nota, parrafo  # noqa: E402

ROTULOS = [
    ('Propuestas · aviso interno', 'Plan de trabajo · aviso interno'),
    # En la Etapa 1 no hay envío al cliente: el pie de las propuestas («el envío lo haces tú desde el panel») no aplica.
    ('Aviso automático del flujo de propuestas de AutomatizaTech. Nada de esto se envía al cliente: el envío lo haces tú '
     'desde el panel.', 'Aviso automático del plan de trabajo de AutomatizaTech. Nada de esto le llega al cliente.'),
]

# Comienzo del aviso que agrega POST /plan/{id}/borrador (Task 7) cuando guardó el plan pero no pudo pedirle la vista
# previa a «3 Render»: 'No se pudo pedir la vista previa: <motivo>'.
AVISO_SIN_VISTA = 'No se pudo pedir la vista previa'

# Enlace a la pestaña del plan en la ficha del cliente (at_pt_url_ficha en WordPress). Si el flujo no conoce el
# id de la ficha del CRM, abre la lista de clientes: nunca un enlace roto.
JS_PANEL = r"""const PANEL_BASE = 'https://automatizatech.cl/wp-admin/admin.php?page=';
function urlPanel(crm, plan) {
  const c = parseInt(crm, 10);
  const p = parseInt(plan, 10);
  if (!(c > 0)) return PANEL_BASE + 'automatiza-crm-clientes';
  return PANEL_BASE + 'automatiza-crm-ficha&id=' + c + (p > 0 ? '&pt=' + p : '') + '#tab-plan';
}
"""


def marco_plan(titulo, etiqueta_html, cuerpo):
    html = marco(titulo, etiqueta_html, cuerpo)
    for viejo, nuevo in ROTULOS:
        if html.count(viejo) != 1:
            raise SystemExit(f'email_tpl.marco cambió: no encuentro «{viejo}» (revisar correos_plan.ROTULOS)')
        html = html.replace(viejo, nuevo)
    return html


def js_correo_plan(preparacion, asunto, titulo, etiqueta_html, cuerpo, extra=''):
    """Como email_tpl.js_correo, con el rótulo del plan, urlPanel() y campos extra en la salida."""
    html = marco_plan(titulo, etiqueta_html, cuerpo)
    return (JS_ESC + JS_PANEL + preparacion + '\nconst html = `' + html + '`;\n'
            + 'return [{ json: { asunto: ' + asunto + ', html' + (', ' + extra if extra else '') + ' } }];')


def correo_error_plan(flujo):
    """Correo de «1 Borrador» o «2 Cambios» cuando algo falló: el plan quedó (o debió quedar) en «error»."""
    if flujo == 'borrador':
        que, trabado = 'no se pudo generar el borrador del plan', 'generando'
        titulo = 'No se pudo generar el borrador del plan: ${esc(m.proyecto)}'
        siguiente = ('El plan quedó en <strong>error</strong>. En la pestaña «Plan de trabajo» de la ficha del cliente, '
                     '«Reintentar borrador» lo vuelve a generar.')
    elif flujo == 'cambios':
        que, trabado = 'no se pudieron aplicar los cambios al plan', 'cambios'
        titulo = 'No se pudieron aplicar los cambios al plan: ${esc(m.proyecto)}'
        siguiente = ('El plan quedó en <strong>error</strong>. «Volver al borrador» recupera el último borrador guardado; '
                     'también puedes volver a pedir cambios.')
    else:
        raise ValueError(flujo)
    prep = """const m = $('Motivo del error').first().json;
const me = $('Marcar error').first().json;
// POST /error contesta 200 aunque no cambie el estado (solo pasa a «error» un plan «generando» o «cambios»; en otro estado
// deja la nota) y devuelve {ok, estado}: el correo solo afirma «error» si el estado que devolvió WordPress es «error».
const estadoWp = me.body && typeof me.body.estado === 'string' ? me.body.estado : '';
const marcado = me.statusCode === 200 && estadoWp === 'error';
const soloNota = me.statusCode === 200 && estadoWp !== '' && estadoWp !== 'error';
const panel = urlPanel(m.crm, m.id);"""
    no_marcado = caja('<strong>Tampoco se pudo marcar el plan como error</strong> '
                      '(${esc(me.statusCode ? "HTTP " + me.statusCode : "sin respuesta")}): puede haber quedado en «'
                      + trabado + '». Revísalo en el panel y usa «Destrabar».', 'error')
    solo_nota = caja('<strong>El plan no pasó a error:</strong> sigue en «${esc(estadoWp)}» y n8n solo dejó el motivo como nota '
                     '(el aviso es de una corrida vieja o repetida). Revísalo en el panel.', 'aviso')
    cuerpo = (caja('${br(m.reason)}', 'error')
              + "${marcado ? `" + parrafo(siguiente) + "` : (soloNota ? `" + solo_nota + "` : `" + no_marcado + "`)}"
              + '<div style="margin:6px 0 4px;">' + boton('${esc(panel)}', '✏️ Abrir el plan en el panel') + '</div>'
              + nota('Ejecución de n8n: ${esc(m.exec)}. Nada de esto le llegó al cliente.'))
    return js_correo_plan(prep, "'⚠️ ' + m.proyecto + ' · " + que + "'", titulo, etiqueta('Error', 'error'), cuerpo)


def correo_sin_vista(flujo):
    """Correo de «1 Borrador» o «2 Cambios» cuando WordPress guardó el plan pero no pudo pedir la vista previa.

    Sin este aviso Luis esperaría el correo «borrador listo» de «3 Render», que no va a llegar. El plan está bien
    (en «borrador»): no se marca error.
    """
    if flujo == 'borrador':
        que, lo_guardado = 'borrador del plan guardado sin vista previa', 'El borrador del plan quedó guardado'
    elif flujo == 'cambios':
        que, lo_guardado = 'cambios del plan guardados sin vista previa', 'El plan con tus cambios quedó guardado'
    else:
        raise ValueError(flujo)
    prep = """const ctx = $('Leer contexto').first().json.body || {};
const hook = $('Webhook').first().json.body || {};
const id = parseInt(hook.id, 10) || 0;
const g = $('Guardar borrador').first().json.body || {};
const avisos = (Array.isArray(g.avisos) ? g.avisos : []).map(String).filter((a) => a.startsWith(""" + repr(AVISO_SIN_VISTA) + """));
const proyecto = String(ctx.proyecto || ctx.empresa || ('plan ' + id));
const panel = urlPanel(ctx.crm_cliente_id, id);
const exec = String($execution.id);"""
    cuerpo = (caja('${avisos.map(esc).join("<br>")}', 'aviso')
              + parrafo(lo_guardado + ' en WordPress, pero la vista previa no se pidió, así que no te va a llegar el correo '
                        '«borrador listo». En la pestaña «Plan de trabajo» de la ficha del cliente, «Guardar y recalcular» '
                        'la vuelve a pedir.')
              + '<div style="margin:6px 0 4px;">' + boton('${esc(panel)}', '✏️ Abrir el plan en el panel') + '</div>'
              + nota('Ejecución de n8n: ${esc(exec)}. Nada de esto le llegó al cliente.'))
    return js_correo_plan(prep, "'⚠️ ' + proyecto + ' · " + que + "'", 'Vista previa sin pedir: ${esc(proyecto)}',
                          etiqueta('Borrador', 'borrador'), cuerpo)



def correo_render():
    """Correo de «3 Render»: vista previa lista (solo si WordPress pidió aviso), versión final lista o con problemas.

    Además de {asunto, html} devuelve {enviar}: sin aviso, una vista previa que salió bien y quedó guardada no manda
    correo (Luis está mirando el panel, por ejemplo tras «Guardar y recalcular»). Solo dice «listo» si WordPress guardó
    el resultado y dejó el plan en «listo» (POST /vista responde {ok, estado}).
    """
    prep = """const v = $('Resultado del render').first().json;
const g = $('Guardar vista').first().json;
const guardado = g.statusCode === 200;
const ok = v.ok === true;
const final = v.modo === 'final';
// Una versión final que salió bien deja el plan «listo» solo si seguía «aprobando»: si Luis lo destrabó mientras
// tanto, WordPress guarda los enlaces pero el plan sigue en «error». Sin «estado» en la respuesta, se asume «listo».
const estadoWp = g.body && typeof g.body.estado === 'string' ? g.body.estado : '';
const quedoListo = !final || estadoWp === '' || estadoWp === 'listo';
const bien = ok && guardado && quedoListo;
const enviar = v.aviso === true || final || !ok || !guardado;
const panel = urlPanel(v.crm, v.id);
const que = !guardado ? 'el resultado del plan no quedó guardado en WordPress'
  : final ? (!ok ? 'la versión final del plan tiene problemas'
    : (quedoListo ? 'plan de trabajo listo' : 'la versión final salió, pero el plan quedó en «' + estadoWp + '»'))
  : (ok ? 'borrador del plan listo para revisar' : 'la vista previa del plan no se pudo generar');
const titular = que.charAt(0).toUpperCase() + que.slice(1).replace(' para revisar', '');
const li = (t) => `<li style="margin:0 0 6px;">${t}</li>`;
const lista = (l, pre) => (Array.isArray(l) ? l : []).map((t) => li(pre + esc(t))).join('');"""
    ul = '<ul style="margin:0;padding-left:18px;">'
    no_guardado = caja('<strong>No se pudo guardar el resultado en WordPress</strong> '
                       '(${esc(g.statusCode ? "HTTP " + g.statusCode : "sin respuesta")}).'
                       '${final ? " El plan puede haber quedado en «aprobando»: revísalo en el panel y usa «Destrabar»." : ""}',
                       'error')
    no_quedo_listo = caja('La versión final salió bien, pero WordPress dejó el plan en «${esc(estadoWp)}» (por ejemplo, si '
                          'lo destrabaste mientras se generaba): no quedó «listo». Revísalo en el panel: puedes volver al '
                          'borrador o aprobarlo de nuevo, y las fotos que ya salieron no se vuelven a pagar.', 'aviso')
    final_ok = (caja(ul + '${lista(v.resumen, "")}${lista(v.avisos, "⚠️ ")}</ul>', 'ok')
                + "${!guardado ? `" + parrafo('La presentación salió bien, pero WordPress no registró el resultado.')
                + "` : (quedoListo ? `" + parrafo('El plan quedó <strong>listo</strong>. Revísalo en el panel: nada se le '
                                                  'envió al cliente.')
                + "` : `" + no_quedo_listo + "`)}")
    final_mal = (caja(ul + '${lista(v.problemas, "")}${lista(v.avisos, "⚠️ ")}</ul>', 'error')
                 + parrafo('El plan quedó en <strong>error</strong>. Desde el panel puedes volver al borrador o aprobarlo de '
                           'nuevo: las fotos que ya salieron no se vuelven a pagar.'))
    borrador_ok = (parrafo('${guardado ? "La vista previa del plan (sin fotos) quedó lista." : "La vista previa del plan '
                           '(sin fotos) se generó, pero el panel no la tiene: «Guardar y recalcular» la vuelve a pedir."} '
                           'En la pestaña «Plan de trabajo» de la ficha del cliente ves las actividades, los días y las '
                           'fechas: ahí los editas, pides cambios o lo apruebas.')
                   + "${(v.avisos || []).length ? `" + caja(ul + '${lista(v.avisos, "⚠️ ")}</ul>', 'aviso') + "` : ''}"
                   + parrafo('Las fotos nuevas se generan solo al «Aprobar»; su cantidad y su costo aparecen en el panel antes.'))
    borrador_mal = (caja(ul + '${lista(v.problemas, "")}</ul>', 'error')
                    + parrafo('El plan sigue en borrador. «Guardar y recalcular» en el panel vuelve a generar la vista previa.'))
    cuerpo = ("${guardado ? '' : `" + no_guardado + "`}"
              + "${ok ? (final ? `" + final_ok + "` : `" + borrador_ok + "`) : (final ? `" + final_mal + "` : `"
              + borrador_mal + "`)}"
              + '<div style="margin:6px 0 4px;">' + boton('${esc(panel)}', '✏️ Abrir el plan en el panel') + '</div>'
              + nota('Ejecución de n8n: ${esc(v.exec)}. La presentación y el PDF se abren desde el panel. '
                     'Nada de esto le llegó al cliente.'))
    etiquetas = ("${bien ? (final ? `" + etiqueta('Listo', 'lista') + "` : `" + etiqueta('Borrador', 'borrador') + "`) : `"
                 + etiqueta('Con problemas', 'error') + "`}")
    asunto = "(bien ? (final ? '✅ ' : '') : '⚠️ ') + v.proyecto + ' · ' + que"
    titulo = '${esc(titular)}: ${esc(v.proyecto)}'
    return js_correo_plan(prep, asunto, titulo, etiquetas, cuerpo, extra='enviar')
