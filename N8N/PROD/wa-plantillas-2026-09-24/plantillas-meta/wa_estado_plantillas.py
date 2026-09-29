"""Consulta en Meta el estado de las plantillas de recordatorio, usando la credencial 'WhatsApp Tech'
de n8n a traves de un workflow temporal de solo lectura que se borra siempre. No imprime tokens."""
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


def api(method, path, body=None):
    data = json.dumps(body).encode("utf-8") if body is not None else None
    req = urllib.request.Request(API + path, data=data, method=method,
                                 headers={"X-N8N-API-KEY": KEY, "content-type": "application/json"})
    with urllib.request.urlopen(req, timeout=90) as r:
        raw = r.read()
        return json.loads(raw) if raw else {}


path = "tmp-wa-estado-" + secrets.token_hex(12)
wf = {
    "name": "TEMP Claude - estado plantillas WA (borrar)",
    "nodes": [
        {"id": "wh", "name": "Webhook", "type": "n8n-nodes-base.webhook", "typeVersion": 2, "position": [0, 0],
         "webhookId": str(uuid.uuid4()),
         "parameters": {"httpMethod": "GET", "path": path, "responseMode": "lastNode", "options": {}}},
        {"id": "ht", "name": "Listar", "type": "n8n-nodes-base.httpRequest", "typeVersion": 4.2, "position": [240, 0],
         "credentials": {"whatsAppApi": {"id": "SH8OXr93p852Ll6m", "name": "WhatsApp Tech"}},
         "parameters": {"method": "GET", "url": f"https://graph.facebook.com/v22.0/{WABA}/message_templates",
                        "authentication": "predefinedCredentialType", "nodeCredentialType": "whatsAppApi",
                        "sendQuery": True,
                        "queryParameters": {"parameters": [
                            {"name": "fields", "value": "name,status,category,language,rejected_reason,quality_score"},
                            {"name": "limit", "value": "100"}]},
                        "options": {"response": {"response": {"neverError": True}}}}},
    ],
    "connections": {"Webhook": {"main": [[{"node": "Listar", "type": "main", "index": 0}]]}},
    "settings": {"executionOrder": "v1", "saveDataSuccessExecution": "none", "saveDataErrorExecution": "none"},
}

wid = None
try:
    wid = api("POST", "/workflows", wf)["id"]
    api("POST", f"/workflows/{wid}/activate")
    out = None
    for _ in range(10):
        try:
            with urllib.request.urlopen(f"{N8N}/webhook/{path}", timeout=60) as r:
                out = json.load(r)
            break
        except urllib.error.HTTPError as e:
            if e.code != 404:
                raise
            time.sleep(2)
    if out is None:
        raise SystemExit("el webhook no quedo registrado")
    d = out[0] if isinstance(out, list) else out
    if "error" in d:
        print("ERROR de Meta:", d["error"].get("code"), d["error"].get("message"))
    for t in d.get("data", []):
        if t["name"].startswith(("recordatorio_", "aviso_", "alerta_")):
            extra = f"  rechazo={t.get('rejected_reason')}" if t.get("rejected_reason") not in (None, "NONE") else ""
            print(f"  {t['name']:<24} {t['status']:<10} {t['category']:<9} {t['language']}{extra}")
finally:
    if wid:
        api("DELETE", f"/workflows/{wid}")
        print("(workflow temporal borrado)")
