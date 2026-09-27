"""Construye (y con --publicar crea/actualiza y activa) el flujo n8n «GT Baseball · Inscripciones v2».

Formulario de demos/gt-baseball (v2) → este webhook → validar y limitar envíos → foto y planilla PDF a Drive →
fila en el Google Sheet → correo a la academia (resumen, planilla y foto) → correo al apoderado (resumen y
planilla, respuesta a la academia) → responder {ok, id, correo_apoderado}.

La v1 vive en otro flujo («GT Baseball · Inscripciones (prototipo)», webhook gt-baseball-inscripcion) y queda
congelada con la etiqueta git gt-baseball-v1 y su JSON en los respaldos. Este archivo solo construye la v2.

Los destinatarios, los ids de Drive/Sheet y el WhatsApp NO están en el repo (es público): se leen de un JSON local.
    python N8N/gt-baseball/build.py <ruta>/gt-config.json            # solo escribe el JSON del flujo
    python N8N/gt-baseball/build.py <ruta>/gt-config.json --publicar # además lo publica y lo activa
gt-config.json: {"folder_id", "sheet_id", "sheet_url", "destinatarios": [...], "destinatarios_prueba": [...],
                 "responder_a": "<correo de la academia>", "whatsapp": "<dígitos>"}
El JSON del flujo se escribe junto a gt-config.json, fuera del repo. Solo referencia credenciales por id.
"""
import json, os, sys

from n8n_api import call

NOMBRE = 'GT Baseball · Inscripciones v2'
PATH = 'gt-baseball-inscripcion-v2'
ORIGENES = 'https://automatizatech.cl,http://localhost:8771'
DRIVE = {'googleDriveOAuth2Api': {'id': 'fXrMhILaDWpAj8Ue', 'name': 'Google Drive account'}}
SHEETS = {'googleSheetsOAuth2Api': {'id': 'xWQj9WmGzqGKwQtb', 'name': 'Google Sheets PROD'}}
SMTP = {'smtp': {'id': 'dyhVFWmjRNC45ccA', 'name': 'SMTP account PROD'}}
POSICIONES = ['Lanzador', 'Receptor', 'Primera base', 'Segunda base', 'Tercera base', 'Campocorto',
              'Jardinero izquierdo', 'Jardinero central', 'Jardinero derecho', 'Utility', 'Aún no definida']
# Límites contra el uso del formulario para mandar correos a terceros: por IP y hora, y por correo y día.
LIMITE_IP_HORA = 10
LIMITE_CORREO_DIA = 6

JS_VALIDAR = r"""
const entrada = $input.first().json;
const body = entrada.body || {};
const d = (body.datos && typeof body.datos === 'object') ? body.datos : {};
const v2 = body.v === 2;
const errores = [];
const s = (v, max) => (typeof v === 'string' ? v.trim().replace(/\s+/g, ' ') : '').slice(0, max);
const req = (k, max, etiqueta) => { const v = s(d[k], max); if (!v) errores.push(etiqueta); return v; };
if (body.hp) errores.push('trampa');
const id = (typeof body.id === 'string' && /^GT-[A-Z0-9]{6,16}$/.test(body.id)) ? body.id : '';
if (!id) errores.push('id');
const x = {
  nombre: req('nombre', 120, 'nombre'),
  fecha_nac: req('fecha_nac', 10, 'fecha de nacimiento'),
  documento: s(d.documento, 30),
  nacionalidad: req('nacionalidad', 60, 'nacionalidad'),
  telefono: s(d.telefono, 30),
  correo: v2 ? req('correo', 120, 'correo') : s(d.correo, 120),
  direccion: req('direccion', 250, 'dirección'),
  rep_nombre: req('rep_nombre', 120, 'representante'),
  rep_telefono: req('rep_telefono', 30, 'teléfono del representante'),
  liga: s(d.liga, 120),
  posicion: req('posicion', 40, 'posición'),
  batea: req('batea', 10, 'batea'),
  lanza: req('lanza', 10, 'lanza'),
  estatura: s(d.estatura, 20),
  peso: s(d.peso, 20),
  millas: s(d.millas, 10),
  anio_firma: s(d.anio_firma, 10),
};
if (x.posicion && !__POSICIONES__.includes(x.posicion)) errores.push('posición');
if (x.batea && !['Derecha', 'Izquierda', 'Ambas'].includes(x.batea)) errores.push('batea');
if (x.lanza && !['Derecha', 'Izquierda'].includes(x.lanza)) errores.push('lanza');
if (x.correo && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(x.correo)) errores.push('correo');
// Mismas reglas que el formulario (app.js): el navegador se puede saltar.
const LETRAS = /^[A-Za-zÁÉÍÓÚÜÑáéíóúüñÀ-ÿ' .-]+$/;
const dig = t => (String(t).match(/\d/g) || []).length;
const telOk = t => /^[0-9+()\-\s.]+$/.test(t) && dig(t) >= 10 && dig(t) <= 15;
const persona = t => LETRAS.test(t) && t.split(' ').filter(Boolean).length >= 2;
const num = t => { const q = String(t).replace(',', '.').match(/\d+(\.\d+)?/); return q ? parseFloat(q[0]) : NaN; };
if (v2) {
  if (x.nombre && !persona(x.nombre)) errores.push('nombre');
  if (x.rep_nombre && !persona(x.rep_nombre)) errores.push('representante');
  if (x.nacionalidad && !(LETRAS.test(x.nacionalidad) && x.nacionalidad.length >= 3)) errores.push('nacionalidad');
  if (x.direccion && !(x.direccion.length >= 8 && /[A-Za-zÁÉÍÓÚÑáéíóúñ]/.test(x.direccion))) errores.push('dirección');
  if (x.rep_telefono && !telOk(x.rep_telefono)) errores.push('teléfono del representante');
  if (x.telefono && !telOk(x.telefono)) errores.push('teléfono');
  if (x.documento) {
    const l = x.documento.replace(/[\s.\-]/g, '');
    if (!/^[VEJPGvejpg]?\d{6,9}$/.test(l) && !/^[A-Za-z0-9]{6,12}$/.test(l)) errores.push('documento');
  }
  if (x.millas && !(/^\d{2,3}([.,]\d)?$/.test(x.millas) && num(x.millas) >= 20 && num(x.millas) <= 110)) errores.push('millas');
  if (x.anio_firma && !(/^\d{4}$/.test(x.anio_firma) && +x.anio_firma >= new Date().getFullYear() - 1 && +x.anio_firma <= new Date().getFullYear() + 20)) errores.push('año de firma');
} else {
  if (x.rep_telefono && !/^[0-9+()\-\s.]{6,30}$/.test(x.rep_telefono)) errores.push('teléfono del representante');
  if (x.telefono && !/^[0-9+()\-\s.]{6,30}$/.test(x.telefono)) errores.push('teléfono');
}
if (d.acepta !== true) errores.push('aceptación');

// Hora de Venezuela para la fecha de envío y la edad.
const ahora = new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Caracas', year: 'numeric', month: '2-digit',
  day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date());
const [hy, hm, hd] = ahora.slice(0, 10).split('-').map(Number);
let edad = '';
const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(x.fecha_nac);
if (!m) { if (x.fecha_nac) errores.push('fecha de nacimiento'); }
else {
  const [y, mo, da] = [Number(m[1]), Number(m[2]), Number(m[3])];
  let e = hy - y; if (hm < mo || (hm === mo && hd < da)) e--;
  if (e < 3 || e > 30) errores.push('edad'); else edad = e;
}

const b64 = v => (typeof v === 'string' ? v.replace(/^data:[^,]*,/, '') : '');
const foto = Buffer.from(b64(body.foto), 'base64');
const pdf = Buffer.from(b64(body.pdf), 'base64');
if (foto.length < 1000 || foto.length > 2000000 || foto[0] !== 0xFF || foto[1] !== 0xD8) errores.push('foto');
if (pdf.length < 1000 || pdf.length > 4000000 || pdf.subarray(0, 5).toString('latin1') !== '%PDF-') errores.push('planilla');

if (errores.length) return [{ json: { ok: false, errores } }];

// Límite de envíos. Se guardan huellas (no la IP ni el correo) por 24 horas en los datos del flujo.
const huella = (t) => { let h = 5381; for (const ch of String(t)) h = ((h << 5) + h + ch.codePointAt(0)) >>> 0; return h.toString(36); };
const sd = $getWorkflowStaticData('global');
const ahoraMs = Date.now();
sd.envios = (Array.isArray(sd.envios) ? sd.envios : []).filter(e => ahoraMs - e.t < 86400000).slice(-500);
const cab = entrada.headers || {};
const ip = String(cab['x-forwarded-for'] || cab['x-real-ip'] || '').split(',')[0].trim();
const kIp = ip ? huella('ip:' + ip) : '';
const kCo = x.correo ? huella('co:' + x.correo.toLowerCase()) : '';
const porIp = kIp ? sd.envios.filter(e => e.i === kIp && ahoraMs - e.t < 3600000).length : 0;
const porCorreo = kCo ? sd.envios.filter(e => e.c === kCo).length : 0;
if (porIp >= __LIMITE_IP__ || porCorreo >= __LIMITE_CORREO__) return [{ json: { ok: false, errores: ['limite'] } }];
sd.envios.push({ t: ahoraMs, i: kIp, c: kCo });

const prueba = body.prueba === true;
const [fy, fm, fd] = x.fecha_nac.split('-');
const base = `${id} - ${x.nombre}`.replace(/[\\/:*?"<>|]/g, '');
const item = { json: { ok: true, v2, prueba, id, edad, fecha: ahora, fecha_nac_txt: `${fd}/${fm}/${fy}`, ...x }, binary: {} };
item.binary.foto = await this.helpers.prepareBinaryData(foto, `${base} - foto.jpg`, 'image/jpeg');
item.binary.planilla = await this.helpers.prepareBinaryData(pdf, `${base} - planilla.pdf`, 'application/pdf');
return [item];
"""

JS_PASAR_PLANILLA = "return [{ json: {}, binary: $('Validar').first().binary }];"

JS_ARMAR = r"""
const x = $('Validar').first().json;
const link = id => `https://drive.google.com/file/d/${id}/view`;
const fotoUrl = link($('Subir foto').first().json.id);
const pdfUrl = link($('Subir planilla').first().json.id);
const values = [[x.fecha, x.prueba ? 'PRUEBA' : 'Inscripción', x.nombre, x.fecha_nac_txt, String(x.edad), x.documento,
  x.nacionalidad, x.telefono, x.correo, x.direccion, x.rep_nombre, x.rep_telefono, x.liga, x.posicion, x.batea,
  x.lanza, x.estatura, x.peso, x.millas, x.anio_firma, fotoUrl, pdfUrl, x.id]];

const esc = t => String(t ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const fila = (k, v) => `<tr><td style="padding:6px 10px;color:#6b7280;font-size:13px;white-space:nowrap;vertical-align:top">${esc(k)}</td><td style="padding:6px 10px;font-size:14px;color:#111827">${esc(v) || '<span style="color:#9ca3af">Sin dato</span>'}</td></tr>`;
const seccion = t => `<tr><td colspan="2" style="padding:14px 10px 4px;font-size:12px;letter-spacing:.08em;color:#b45309;font-weight:bold">${esc(t)}</td></tr>`;
const cabecera = t => `<div style="background:#111418;padding:18px 20px;border-radius:10px 10px 0 0">
<img src="https://automatizatech.cl/demos/gt-baseball/assets/logo-gt-256.jpg" width="56" height="56" alt="GT Baseball Academy" style="border-radius:8px;vertical-align:middle">
<span style="color:#fbbf24;font-size:18px;font-weight:bold;margin-left:12px;vertical-align:middle">${t}</span></div>`;
const caja = c => `<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#111827">${c}</div>`;
const cuerpo = c => `<div style="border:1px solid #e5e7eb;border-top:0;border-radius:0 0 10px 10px;padding:8px 10px 16px">${c}</div>`;
const hojaUrl = __SHEET_URL__;
const whatsapp = __WHATSAPP__;

// Correo a la academia: todo el detalle, con la planilla y la foto.
const html = caja(cabecera(`Nueva inscripción${x.prueba ? ' (PRUEBA)' : ''}`) + cuerpo(`
<table style="border-collapse:collapse;width:100%">
${seccion('ATLETA')}${fila('Nombre completo', x.nombre)}${fila('Fecha de nacimiento', x.fecha_nac_txt)}${fila('Edad', x.edad + ' años')}
${fila('Cédula o pasaporte', x.documento)}${fila('Nacionalidad', x.nacionalidad)}${fila('Teléfono del atleta', x.telefono)}
${fila('Dirección', x.direccion)}
${seccion('REPRESENTANTE')}${fila('Nombre y apellido', x.rep_nombre)}${fila('Teléfono', x.rep_telefono)}${fila('Correo', x.correo)}
${seccion('BÉISBOL')}${fila('Liga o programa', x.liga)}${fila('Posición', x.posicion)}${fila('Batea', x.batea)}
${fila('Lanza', x.lanza)}${fila('Estatura', x.estatura)}${fila('Peso', x.peso)}${fila('Referencia millas', x.millas)}${fila('Año de firma', x.anio_firma)}
</table>
<p style="font-size:13px;color:#374151;margin:16px 10px 0">Adjuntos: la planilla lista para imprimir (PDF) y la foto del atleta. Al representante le llega una confirmación con la planilla; si responde, su respuesta llega a este correo.</p>
${x.prueba ? `<p style="font-size:13px;margin:8px 10px 0"><a href="${esc(hojaUrl)}">Ver todas las inscripciones en el Google Sheet</a></p>` : ''}
<p style="font-size:11px;color:#9ca3af;margin:16px 10px 0">Inscripción ${esc(x.id)}, enviada el ${esc(x.fecha)} (hora de Venezuela). Formulario hecho por AutomatizaTech.</p>`));

// Correo al apoderado: confirmación con el resumen y la planilla adjunta.
const waLink = whatsapp ? `https://wa.me/${whatsapp}?text=${encodeURIComponent('Hola, inscribí a ' + x.nombre + ' en GT Baseball Academy y tengo una consulta.')}` : '';
const htmlApoderado = caja(cabecera('Inscripción recibida') + cuerpo(`
<p style="font-size:15px;margin:14px 10px 6px">Hola ${esc(x.rep_nombre)}:</p>
<p style="font-size:15px;line-height:1.5;margin:0 10px 10px">GT Baseball Academy recibió la inscripción de <strong>${esc(x.nombre)}</strong>. Te adjuntamos la planilla en PDF para que la guardes o la imprimas.</p>
<table style="border-collapse:collapse;width:100%">
${seccion('ATLETA')}${fila('Nombre completo', x.nombre)}${fila('Fecha de nacimiento', x.fecha_nac_txt)}${fila('Edad', x.edad + ' años')}
${fila('Posición', x.posicion)}${fila('Batea', x.batea)}${fila('Lanza', x.lanza)}
${seccion('REPRESENTANTE')}${fila('Nombre y apellido', x.rep_nombre)}${fila('Teléfono', x.rep_telefono)}${fila('Correo', x.correo)}
</table>
${waLink ? `<p style="margin:18px 10px 4px"><a href="${esc(waLink)}" style="display:inline-block;background:#25d366;color:#0a2e1a;font-weight:bold;font-size:14px;text-decoration:none;padding:11px 18px;border-radius:8px">Escríbenos por WhatsApp</a></p>` : ''}
<p style="font-size:13px;color:#374151;margin:12px 10px 0">Si tienes dudas, también puedes responder este correo.</p>
<p style="font-size:11px;color:#9ca3af;margin:16px 10px 0">Inscripción ${esc(x.id)}, recibida el ${esc(x.fecha)} (hora de Venezuela). Recibiste este correo porque inscribiste a ${esc(x.nombre)} en GT Baseball Academy. Formulario hecho por AutomatizaTech.</p>`));

const destinoAcademia = (x.prueba ? __PRUEBA__ : __DESTINATARIOS__).join(',');
return [{ json: {
  values, html, para: destinoAcademia, id: x.id,
  asunto: `${x.prueba ? '[PRUEBA] ' : ''}Nueva inscripción: ${x.nombre} · GT Baseball Academy`,
  html_apoderado: htmlApoderado, para_apoderado: x.correo,
  asunto_apoderado: `${x.prueba ? '[PRUEBA] ' : ''}Inscripción recibida: ${x.nombre} · GT Baseball Academy`,
  responder_a: x.prueba ? __PRUEBA__[0] : __RESPONDER_A__,
} }];
"""

JS_ADJUNTOS = "return [{ json: $('Armar fila y correo').first().json, binary: $('Validar').first().binary }];"


def node(id_, name, type_, version, pos, params, **extra):
    n = {'id': id_, 'name': name, 'type': type_, 'typeVersion': version, 'position': pos, 'parameters': params}
    n.update(extra)
    return n


def code(id_, name, pos, js):
    return node(id_, name, 'n8n-nodes-base.code', 2, pos, {'jsCode': js.strip()})


def subir(id_, name, pos, campo, folder_id):
    return node(id_, name, 'n8n-nodes-base.googleDrive', 3, pos, {
        'name': f'={{{{ $binary.{campo}.fileName }}}}',
        'driveId': {'__rl': True, 'mode': 'list', 'value': 'My Drive'},
        'folderId': {'__rl': True, 'mode': 'id', 'value': folder_id},
        'inputDataFieldName': campo, 'options': {}}, credentials=DRIVE)


def construir(cfg):
    for k in ('folder_id', 'sheet_id', 'sheet_url', 'destinatarios', 'destinatarios_prueba', 'responder_a'):
        if not cfg.get(k):
            raise SystemExit(f'Falta {k} en gt-config.json')
    validar = (JS_VALIDAR.replace('__POSICIONES__', json.dumps(POSICIONES, ensure_ascii=False))
               .replace('__LIMITE_IP__', str(LIMITE_IP_HORA)).replace('__LIMITE_CORREO__', str(LIMITE_CORREO_DIA)))
    armar = (JS_ARMAR.replace('__SHEET_URL__', json.dumps(cfg['sheet_url']))
             .replace('__WHATSAPP__', json.dumps(str(cfg.get('whatsapp') or '')))
             .replace('__PRUEBA__', json.dumps(cfg['destinatarios_prueba']))
             .replace('__DESTINATARIOS__', json.dumps(cfg['destinatarios']))
             .replace('__RESPONDER_A__', json.dumps(cfg['responder_a'])))
    remitente = 'GT Baseball Academy <contacto@automatizatech.cl>'
    nodes = [
        node('g1', 'Webhook', 'n8n-nodes-base.webhook', 2, [0, 0],
             {'httpMethod': 'POST', 'path': PATH, 'responseMode': 'responseNode',
              'options': {'allowedOrigins': ORIGENES}}, webhookId='8b1d2c4e-3f5a-4c6b-9d7e-2a1f0c9b8e71'),
        code('g2', 'Validar', [220, 0], validar),
        node('g3', '¿Válido?', 'n8n-nodes-base.if', 2, [440, 0], {
            'conditions': {'options': {'caseSensitive': True, 'leftValue': '', 'typeValidation': 'strict'},
                           'conditions': [{'id': 'ok', 'leftValue': '={{ $json.ok }}', 'rightValue': True,
                                           'operator': {'type': 'boolean', 'operation': 'true', 'singleValue': True}}],
                           'combinator': 'and'}, 'options': {}}),
        node('g4', 'Responder error', 'n8n-nodes-base.respondToWebhook', 1.1, [660, 200],
             {'respondWith': 'json', 'responseBody': '={{ JSON.stringify({ ok: false, errores: $json.errores }) }}',
              'options': {'responseCode': 400}}),
        subir('g5', 'Subir foto', [660, -100], 'foto', cfg['folder_id']),
        code('g6', 'Pasar planilla', [880, -100], JS_PASAR_PLANILLA),
        subir('g7', 'Subir planilla', [1100, -100], 'planilla', cfg['folder_id']),
        code('g8', 'Armar fila y correo', [1320, -100], armar),
        node('g9', 'Agregar fila', 'n8n-nodes-base.httpRequest', 4.2, [1540, -100], {
            'method': 'POST',
            'url': f"https://sheets.googleapis.com/v4/spreadsheets/{cfg['sheet_id']}/values/Inscripciones!A1:append"
                   '?valueInputOption=RAW&insertDataOption=INSERT_ROWS',
            'authentication': 'predefinedCredentialType', 'nodeCredentialType': 'googleSheetsOAuth2Api',
            'sendBody': True, 'specifyBody': 'json', 'jsonBody': '={{ JSON.stringify({ values: $json.values }) }}',
            'options': {}}, credentials=SHEETS),
        code('g10', 'Recuperar adjuntos', [1760, -100], JS_ADJUNTOS),
        node('g11', 'Correo', 'n8n-nodes-base.emailSend', 2.1, [1980, -100], {
            'fromEmail': remitente, 'toEmail': '={{ $json.para }}', 'subject': '={{ $json.asunto }}',
            'emailFormat': 'html', 'html': '={{ $json.html }}',
            'options': {'attachments': 'planilla,foto', 'appendAttribution': False}}, credentials=SMTP),
        code('g12', 'Adjunto para el apoderado', [2200, -100], JS_ADJUNTOS),
        node('g13', 'Correo apoderado', 'n8n-nodes-base.emailSend', 2.1, [2420, -100], {
            'fromEmail': remitente, 'toEmail': '={{ $json.para_apoderado }}', 'subject': '={{ $json.asunto_apoderado }}',
            'emailFormat': 'html', 'html': '={{ $json.html_apoderado }}',
            'options': {'attachments': 'planilla', 'appendAttribution': False, 'replyTo': '={{ $json.responder_a }}'}},
            credentials=SMTP, onError='continueRegularOutput'),
        node('g14', 'Responder ok', 'n8n-nodes-base.respondToWebhook', 1.1, [2640, -100], {
            'respondWith': 'json',
            'responseBody': "={{ JSON.stringify({ ok: true, id: $('Validar').first().json.id, "
                            "correo_apoderado: !!($('Correo apoderado').first().json.accepted || []).length }) }}",
            'options': {'responseCode': 200}}),
    ]

    enlaces = [('Webhook', 0, 'Validar'), ('Validar', 0, '¿Válido?'), ('¿Válido?', 0, 'Subir foto'),
               ('¿Válido?', 1, 'Responder error'), ('Subir foto', 0, 'Pasar planilla'),
               ('Pasar planilla', 0, 'Subir planilla'), ('Subir planilla', 0, 'Armar fila y correo'),
               ('Armar fila y correo', 0, 'Agregar fila'), ('Agregar fila', 0, 'Recuperar adjuntos'),
               ('Recuperar adjuntos', 0, 'Correo'), ('Correo', 0, 'Adjunto para el apoderado'),
               ('Adjunto para el apoderado', 0, 'Correo apoderado'), ('Correo apoderado', 0, 'Responder ok')]
    connections = {}
    for a, salida, b in enlaces:
        main = connections.setdefault(a, {'main': []})['main']
        while len(main) <= salida:
            main.append([])
        main[salida].append({'node': b, 'type': 'main', 'index': 0})
    return {'name': NOMBRE, 'nodes': nodes, 'connections': connections,
            'settings': {'executionOrder': 'v1', 'timezone': 'America/Caracas',
                         # Cada envío trae la foto de un menor en base64: no guardar las ejecuciones exitosas.
                         'saveDataSuccessExecution': 'none'}}


def publicar(wf):
    existentes, cursor = [], None
    while True:
        resp = call('GET', '/workflows?limit=250' + (f'&cursor={cursor}' if cursor else ''))
        existentes += [w for w in resp.get('data', []) if w['name'] == wf['name']]
        cursor = resp.get('nextCursor')
        if not cursor:
            break
    if existentes:
        wid = existentes[0]['id']
        call('PUT', f'/workflows/{wid}', wf)
        accion = 'actualizado'
    else:
        wid = call('POST', '/workflows', wf)['id']
        accion = 'creado'
    call('POST', f'/workflows/{wid}/activate')
    w = call('GET', f'/workflows/{wid}')
    print(f"{accion}: {w['name']} id={wid} activo={w['active']} nodos={len(w['nodes'])}")


def main():
    if len(sys.argv) < 2:
        raise SystemExit(__doc__)
    cfg_path = os.path.abspath(sys.argv[1])
    wf = construir(json.load(open(cfg_path, encoding='utf-8')))
    out = os.path.join(os.path.dirname(cfg_path), 'gt-baseball-flujo-v2.json')
    json.dump(wf, open(out, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
    print('escrito', out, len(wf['nodes']), 'nodos')
    if '--publicar' in sys.argv:
        publicar(wf)


if __name__ == '__main__':
    main()
