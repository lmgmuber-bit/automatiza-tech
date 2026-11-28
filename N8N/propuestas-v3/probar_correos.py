"""Prueba local de los correos v3: ejecuta el código de cada nodo con datos de ejemplo y guarda el HTML.

Uso: python N8N/propuestas-v3/probar_correos.py <carpeta_salida>
No toca n8n ni envía correos.
"""
import json, os, subprocess, sys
from correos import correo_borrador, correo_cambios_ok, correo_cambios_error, correo_final, correo_error_flujo

out = sys.argv[1] if len(sys.argv) > 1 else '.'
os.makedirs(out, exist_ok=True)

RESUMEN = 'Orly quiere reestructurar su sitio actual en una sola página, con botones de llamada.\nPresupuesto inicial ~$400.000 CLP; campañas solo en la Región Metropolitana.'
casos = {
    'borrador': (correo_borrador(), {
        'Armar payload': {'payload': {'company_name': '[PRUEBA] Funerarias Amor de Dios'}, 'resumen': RESUMEN},
        'Crear en WordPress': {'unique_id': 'NOlyRnH3HcmI', 'panel_url': 'https://automatizatech.cl/wp-admin/admin.php?page=automatiza-proposals&edit_id=47'},
        'Vista previa (sin fotos)': {'view_url': 'x'}}),
    'borrador-sin-vista': (correo_borrador(), {
        'Armar payload': {'payload': {'company_name': 'Funerarias <Amor> & Dios'}, 'resumen': ''},
        'Crear en WordPress': {'unique_id': 'abc', 'panel_url': 'https://automatizatech.cl/wp-admin/x'},
        'Vista previa (sin fotos)': {}}),
    'cambios-ok': (correo_cambios_ok(), {
        'Leer estado': {'body': {'ultimo_comentario': 'Cambia el título del desafío.'}},
        'Payload final': {'company': '[PRUEBA] Funerarias Amor de Dios', 'unique_id': 'Ek5dgBrvmxew', 'id': 46},
        'Vista previa': {'view_url': 'x'}}),
    'cambios-error': (correo_cambios_error(), {
        'Motivo del error': {'company': 'propuesta 999999', 'reason': 'No se pudo leer la propuesta en WordPress (HTTP 404)', 'id': 999999, 'exec': '403710'},
        'Marcar error': {'statusCode': 404}}),
    'final-ok': (correo_final(), {
        'Verificar': {'ok': True, 'company': '[PRUEBA] Funerarias Amor de Dios', 'unique_id': 'NOlyRnH3HcmI', 'id': 47,
                      'fotos_locales': 7, 'fotos_pedidas': 7, 'problemas': [], 'chat_respuesta': 'Lamento mucho su pérdida. Puede llamarnos al +56 9 9911 9030.'},
        'Guardar resultado': {'statusCode': 200}, 'Revisar fotos': {'fotos_reemplazadas': 6}}),
    'final-error': (correo_final(), {
        'Verificar': {'ok': False, 'company': '[PRUEBA] Funerarias Amor de Dios', 'unique_id': 'NOlyRnH3HcmI', 'id': 47,
                      'fotos_locales': 4, 'fotos_pedidas': 7, 'problemas': ['fotos que no se generaron: how_it_works, pricing, next_steps'], 'chat_respuesta': ''},
        'Guardar resultado': {'statusCode': 200}, 'Revisar fotos': {'fotos_reemplazadas': 6}}),
    'error-borrador': (correo_error_flujo(), {'$json': {
        'workflow': {'name': 'Propuestas v3 · 1 Borrador'},
        'execution': {'id': '403900', 'lastNodeExecuted': 'Armar payload', 'error': {'message': 'Unexpected token < in JSON'},
                      'url': 'https://n8n-n8n.kchiba.easypanel.host/workflow/x/executions/403900'}}}),
    'error-final': (correo_error_flujo(), {'$json': {
        'workflow': {'name': 'Propuestas v3 · 3 Final'},
        'execution': {'id': '403901', 'lastNodeExecuted': 'Verificar', 'error': {'message': "Cannot read properties of undefined <b>"}}}}),
}

for nombre, (codigo, datos) in casos.items():
    js = ('const DATA = ' + json.dumps(datos, ensure_ascii=False) + ';\n'
          "const $ = (n) => ({ isExecuted: n in DATA, first: () => ({ json: DATA[n] }) });\n"
          "const $json = DATA.$json || {};\n"
          "const salida = (function () {\n" + codigo + "\n})();\n"
          "process.stdout.write(JSON.stringify(salida[0].json));")
    r = subprocess.run(['node', '-e', js], capture_output=True, text=True, encoding='utf-8')
    if r.returncode != 0:
        print(nombre, 'ERROR', r.stderr[-600:]); sys.exit(1)
    res = json.loads(r.stdout)
    open(os.path.join(out, nombre + '.html'), 'w', encoding='utf-8').write(res['html'])
    print(f"{nombre}: asunto={res['asunto']!r} html={len(res['html'])} bytes easypanel={'easypanel' in res['html']}")
