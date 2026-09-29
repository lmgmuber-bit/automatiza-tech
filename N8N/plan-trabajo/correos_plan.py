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
const marcado = me.statusCode === 200;
const panel = urlPanel(m.crm, m.id);"""
    no_marcado = caja('<strong>Tampoco se pudo marcar el plan como error</strong> '
                      '(${esc(me.statusCode ? "HTTP " + me.statusCode : "sin respuesta")}): puede haber quedado en «'
                      + trabado + '». Revísalo en el panel y usa «Destrabar».', 'error')
    cuerpo = (caja('${br(m.reason)}', 'error')
              + "${marcado ? `" + parrafo(siguiente) + "` : `" + no_marcado + "`}"
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
