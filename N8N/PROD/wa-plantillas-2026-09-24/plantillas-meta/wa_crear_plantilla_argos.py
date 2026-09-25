"""Crea en Meta las 3 plantillas de recordatorio aprobadas por Luis el 2026-09-24,
usando la credencial 'WhatsApp Tech' de n8n a traves de un workflow temporal que se borra siempre.
No imprime tokens."""
import io
import json
import secrets
import sys
import time
import uuid
import urllib.error
import urllib.request

sys.stdout.reconfigure(encoding="utf-8")

KEY = json.load(io.open(r"C:/Users/luis_/.claude.json", encoding="utf-8"))["mcpServers"]["n8n-mcp"]["env"]["N8N_API_KEY"]
N8N = "https://n8n-n8n.kchiba.easypanel.host"
API = N8N + "/api/v1"
WABA = "1294451839384217"
CRED = {"whatsAppApi": {"id": "SH8OXr93p852Ll6m", "name": "WhatsApp Tech"}}
MEET_EJ = "https://meet.google.com/abc-defg-hij"

# Meta rechaza emojis en botones de plantilla (error 100/2388060); Luis aprobo quitarlos el 2026-09-24
from plantillas_3 import PLANTILLAS


def api(method, path, body=None):
    data = json.dumps(body).encode("utf-8") if body is not None else None
    req = urllib.request.Request(API + path, data=data, method=method,
                                 headers={"X-N8N-API-KEY": KEY, "content-type": "application/json",
                                          "accept": "application/json"})
    with urllib.request.urlopen(req, timeout=90) as r:
        raw = r.read()
        return json.loads(raw) if raw else {}


path = "tmp-wa-crear-" + secrets.token_hex(12)
js_plantillas = json.dumps(PLANTILLAS, ensure_ascii=False)
wf = {
    "name": "TEMP Claude - crear plantillas WA (borrar)",
    "nodes": [
        # sin webhookId, n8n activa el workflow pero no registra la ruta (404) al crearlo por la API REST
        {"id": "wh", "name": "Webhook", "type": "n8n-nodes-base.webhook", "typeVersion": 2, "position": [0, 0],
         "webhookId": str(uuid.uuid4()),
         "parameters": {"httpMethod": "POST", "path": path, "responseMode": "lastNode",
                        "responseData": "allEntries", "options": {}}},
        {"id": "tp", "name": "Plantillas", "type": "n8n-nodes-base.code", "typeVersion": 2, "position": [240, 0],
         "parameters": {"jsCode": "const tpls = " + js_plantillas + ";\nreturn tpls.map(t => ({ json: { tpl: t } }));"}},
        {"id": "ht", "name": "Crear en Meta", "type": "n8n-nodes-base.httpRequest", "typeVersion": 4.2,
         "position": [480, 0], "credentials": CRED,
         "parameters": {"method": "POST",
                        "url": f"https://graph.facebook.com/v22.0/{WABA}/message_templates",
                        "authentication": "predefinedCredentialType", "nodeCredentialType": "whatsAppApi",
                        "sendBody": True, "specifyBody": "json",
                        "jsonBody": "={{ JSON.stringify($json.tpl) }}",
                        "options": {"response": {"response": {"neverError": True}}}}},
        {"id": "rs", "name": "Resumen", "type": "n8n-nodes-base.code", "typeVersion": 2, "position": [720, 0],
         "parameters": {"jsCode": "const t = $('Plantillas').all();\n"
                                  "return $input.all().map((it, i) => ({ json: { name: t[i].json.tpl.name, meta: it.json } }));"}},
    ],
    "connections": {
        "Webhook": {"main": [[{"node": "Plantillas", "type": "main", "index": 0}]]},
        "Plantillas": {"main": [[{"node": "Crear en Meta", "type": "main", "index": 0}]]},
        "Crear en Meta": {"main": [[{"node": "Resumen", "type": "main", "index": 0}]]},
    },
    "settings": {"executionOrder": "v1", "saveDataSuccessExecution": "none", "saveDataErrorExecution": "none"},
}

# comprobacion previa: las connections deben ser array de arrays
assert isinstance(wf["connections"]["Webhook"]["main"][0], list)

wid = None
try:
    wid = api("POST", "/workflows", wf)["id"]
    print("workflow temporal creado:", wid)
    act = api("POST", f"/workflows/{wid}/activate")
    print("activo:", act.get("active"))
    # n8n tarda unos segundos en registrar la ruta del webhook tras activar.
    # Solo se reintenta ante 404 (el workflow no corrio), asi no hay riesgo de duplicar.
    out = None
    for intento in range(1, 11):
        req = urllib.request.Request(f"{N8N}/webhook/{path}", data=b"{}", method="POST",
                                     headers={"content-type": "application/json"})
        try:
            with urllib.request.urlopen(req, timeout=120) as r:
                out = json.load(r)
            break
        except urllib.error.HTTPError as e:
            if e.code != 404:
                raise
            print(f"  webhook aun no registrado (intento {intento}), reintento en 2 s")
            time.sleep(2)
    if out is None:
        raise SystemExit("el webhook nunca quedo registrado; no se creo nada")
    for x in out:
        m = x.get("meta", {})
        if "error" in m:
            e = m["error"]
            print(f"  {x['name']:<24} ERROR {e.get('code')}/{e.get('error_subcode')}: "
                  f"{e.get('error_user_title') or ''} {e.get('error_user_msg') or e.get('message')}")
        else:
            print(f"  {x['name']:<24} id={m.get('id')} status={m.get('status')} category={m.get('category')}")
finally:
    if wid:
        api("DELETE", f"/workflows/{wid}")
        print("workflow temporal borrado:", wid)
