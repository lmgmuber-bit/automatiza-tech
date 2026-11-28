"""Plantilla de correo de marca AutomatizaTech para los avisos internos de los flujos de propuestas v3.

Estilos en línea y tablas: es lo que Gmail y Outlook respetan. Los textos pueden llevar expresiones n8n
({{ ... }}); quien arma el correo antepone el «=» que n8n exige al parámetro completo.
Nunca enlazar *.easypanel.host: el SMTP de Hostinger rechaza esos correos como spam (554 5.7.1, 2026-09-24).
"""

LOGO = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png'
NAVY = '#0d1b2a'
TEAL = '#00d9c0'
TEXTO = '#334155'
SUAVE = '#64748b'
FONDO = '#eef2f6'
# Sin comillas: el HTML termina dentro de literales de JavaScript.
FUENTE = 'font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;'

COLORES_ESTADO = {
    'borrador': ('#fef3c7', '#92400e'),
    'cambios': ('#dbeafe', '#1e40af'),
    'lista': ('#dcfce7', '#166534'),
    'error': ('#fee2e2', '#991b1b'),
}


def etiqueta(texto, estado):
    fondo, color = COLORES_ESTADO[estado]
    return (f'<span style="display:inline-block;background:{fondo};color:{color};{FUENTE}font-size:12px;'
            f'font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:5px 12px;border-radius:999px;">{texto}</span>')


def boton(url, texto, primario=True):
    if primario:
        estilo = f'background:{TEAL};color:{NAVY};border:2px solid {TEAL};'
    else:
        estilo = f'background:#ffffff;color:{NAVY};border:2px solid {NAVY};'
    return (f'<a href="{url}" style="display:inline-block;{estilo}{FUENTE}font-size:15px;font-weight:700;'
            f'text-decoration:none;padding:12px 22px;border-radius:10px;margin:6px 8px 6px 0;">{texto}</a>')


def parrafo(html):
    return f'<p style="margin:0 0 14px;{FUENTE}font-size:15px;line-height:1.55;color:{TEXTO};">{html}</p>'


def caja(html, tono='neutro'):
    fondos = {'neutro': ('#f8fafc', '#cbd5e1'), 'aviso': ('#fffbeb', '#f59e0b'), 'error': ('#fef2f2', '#ef4444'),
              'ok': ('#f0fdf4', '#22c55e')}
    fondo, borde = fondos[tono]
    return (f'<div style="background:{fondo};border-left:4px solid {borde};border-radius:8px;padding:14px 16px;'
            f'margin:0 0 16px;{FUENTE}font-size:14px;line-height:1.55;color:{TEXTO};">{html}</div>')


def nota(html):
    return f'<p style="margin:18px 0 0;{FUENTE}font-size:13px;line-height:1.5;color:{SUAVE};">{html}</p>'


def marco(titulo, etiqueta_html, cuerpo):
    """Correo completo: encabezado con logo, etiqueta de estado, título, cuerpo y pie."""
    return f"""<div style="margin:0;padding:24px 12px;background:{FONDO};">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(13,27,42,.08);">
<tr><td style="background:{NAVY};padding:18px 24px;">
<table role="presentation" cellpadding="0" cellspacing="0"><tr>
<td style="vertical-align:middle;"><img src="{LOGO}" width="44" height="37" alt="AutomatizaTech" style="display:block;border:0;"></td>
<td style="vertical-align:middle;padding-left:12px;{FUENTE}color:#ffffff;font-size:16px;font-weight:700;">AutomatizaTech<br><span style="color:{TEAL};font-size:12px;font-weight:600;letter-spacing:.04em;">Propuestas · aviso interno</span></td>
</tr></table>
</td></tr>
<tr><td style="height:4px;background:{TEAL};line-height:4px;font-size:0;">&nbsp;</td></tr>
<tr><td style="padding:26px 24px 8px;">
{etiqueta_html}
<h1 style="margin:14px 0 16px;{FUENTE}font-size:22px;line-height:1.3;color:{NAVY};">{titulo}</h1>
{cuerpo}
</td></tr>
<tr><td style="padding:14px 24px 22px;border-top:1px solid #e2e8f0;{FUENTE}font-size:12px;line-height:1.5;color:{SUAVE};">
Aviso automático del flujo de propuestas de AutomatizaTech. Nada de esto se envía al cliente: el envío lo haces tú desde el panel.
</td></tr>
</table>
</div>"""


JS_ESC = r"""const esc = (s) => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const br = (s) => esc(s).replace(/\n/g, '<br>');
"""


def js_correo(preparacion, asunto, titulo, etiqueta_html, cuerpo):
    """Código de un nodo Code que arma {asunto, html}. `titulo` y `cuerpo` pueden llevar ${...} de JS."""
    # Las partes condicionales van como plantillas de JS anidadas dentro de ${...}, que es válido.
    html = marco(titulo, etiqueta_html, cuerpo)
    return JS_ESC + preparacion + '\nconst html = `' + html + '`;\nreturn [{ json: { asunto: ' + asunto + ', html } }];'
