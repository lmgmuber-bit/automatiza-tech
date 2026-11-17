"""Prueba de rechazo de Turnstile en «Agente Tech Web (PROD)», después de aplicar: una cita sin token y otra con un token
inventado deben recibir HTTP 403 y no guardarse. No crea citas ni usa claves (el webhook es público y ya está en el tema).

Uso: python -P N8N/web-turnstile/probar.py

La prueba positiva (una persona real pasa la casilla y la cita se guarda) se hace con una agenda real desde
automatizatech.cl: Turnstile retiene al navegador automatizado normal (ver la guía de GT Baseball en CLAUDE.md).
"""
import json
import sys
import urllib.error
import urllib.request

sys.stdout.reconfigure(encoding='utf-8')
WEBHOOK = 'https://n8n-n8n.kchiba.easypanel.host/webhook/becd5a16-7b3a-4961-8a2c-e86ca01d069e'
LEAD = {'name': 'Prueba Turnstile', 'email': 'prueba-turnstile@automatizatech.cl', 'phone': '+56900000000',
        'scheduled_date': '2030-01-02', 'scheduled_time': '10:00'}


def enviar(cuerpo):
    req = urllib.request.Request(WEBHOOK, data=json.dumps(cuerpo).encode(), method='POST',
                                 headers={'Content-Type': 'application/json', 'Origin': 'https://automatizatech.cl'})
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            return r.status, r.read().decode('utf-8', 'replace')[:200]
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')[:200]


casos = [
    ('sin token', {'action': 'saveLead', 'sessionId': 'prueba-ts', 'leadData': LEAD}),
    ('token inventado', {'action': 'saveLead', 'sessionId': 'prueba-ts', 'turnstileToken': 'XXXX.FALSO.XXXX', 'leadData': LEAD}),
]
fallas = 0
for nombre, cuerpo in casos:
    codigo, texto = enviar(cuerpo)
    ok = codigo == 403 and 'turnstile' in texto
    fallas += not ok
    print(('ok    ' if ok else 'FALLA ') + f'{nombre}: HTTP {codigo} {texto}')
print('TODO OK' if not fallas else f'{fallas} FALLA(S)')
sys.exit(1 if fallas else 0)
