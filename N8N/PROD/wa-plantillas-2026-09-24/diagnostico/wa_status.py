"""Para los wamid que enviaron los recordatorios, busca en el webhook de WhatsApp
(WhatsApp Tech - Principal) el status que Meta informo despues: sent/delivered/read/failed.
No imprime telefonos ni wamid completos."""
import io
import json
import sys
import urllib.request
from datetime import datetime, timedelta

sys.stdout.reconfigure(encoding="utf-8")

KEY = json.load(io.open(r"C:/Users/luis_/.claude.json", encoding="utf-8"))["mcpServers"]["n8n-mcp"]["env"]["N8N_API_KEY"]
BASE = "https://n8n-n8n.kchiba.easypanel.host/api/v1"
WEBHOOK_WF = "bBcNlFgBzQ0766Mq"  # WhatsApp Tech - Principal (PROD)
ENVIOS = {"24h": "401744", "1h": "402708"}


def get(path):
    req = urllib.request.Request(BASE + path, headers={"X-N8N-API-KEY": KEY, "accept": "application/json"})
    with urllib.request.urlopen(req, timeout=90) as r:
        return json.load(r)


def ts(s):
    return datetime.fromisoformat(s.replace("Z", "+00:00"))


def short(w):
    return (w[:14] + "..." + w[-6:]) if w else w


# 1) wamid completos y hora de cada envio
objetivos = {}
for label, eid in ENVIOS.items():
    e = get(f"/executions/{eid}?includeData=true")
    rd = e["data"]["resultData"]["runData"]
    send = [k for k in rd if k.lower().startswith("send")][0]
    for it in rd[send][0]["data"]["main"][0]:
        for m in it["json"].get("messages", []):
            objetivos[m["id"]] = {"label": label, "enviado": e["startedAt"], "statuses": []}

print("Mensajes a rastrear:")
for w, o in objetivos.items():
    print(f"  {o['label']}: {short(w)} enviado {o['enviado']}")
print()

# 2) ventana: desde el primer envio hasta 3 horas despues del ultimo
t_min = min(ts(o["enviado"]) for o in objetivos.values())
t_max = max(ts(o["enviado"]) for o in objetivos.values()) + timedelta(hours=3)

# 3) listar ejecuciones del webhook (sin data) hasta cubrir la ventana
candidatas = []
cursor = None
revisadas = 0
while True:
    q = f"/executions?workflowId={WEBHOOK_WF}&limit=250"
    if cursor:
        q += "&cursor=" + cursor
    d = get(q)
    stop = False
    for e in d.get("data", []):
        revisadas += 1
        st = e.get("startedAt")
        if not st:
            continue
        t = ts(st)
        if t < t_min - timedelta(minutes=2):
            stop = True
            break
        if t <= t_max:
            candidatas.append(e["id"])
    cursor = d.get("nextCursor")
    if stop or not cursor:
        break

print(f"Ejecuciones del webhook revisadas: {revisadas}; en la ventana: {len(candidatas)}")

# 4) en cada candidata, buscar statuses de nuestros wamid en el payload del webhook
hallados = 0
for eid in candidatas:
    e = get(f"/executions/{eid}?includeData=true")
    blob = json.dumps(e.get("data", {}))
    if not any(w in blob for w in objetivos):
        continue
    rd = e["data"]["resultData"]["runData"]
    for node, runs in rd.items():
        for run in runs:
            for out in ((run.get("data") or {}).get("main") or []):
                for it in out or []:
                    j = it.get("json", {})
                    body = j.get("body", j)
                    for entry in (body.get("entry") or []):
                        for ch in entry.get("changes", []):
                            for s in (ch.get("value", {}).get("statuses") or []):
                                w = s.get("id")
                                if w in objetivos:
                                    rec = {"status": s.get("status"),
                                           "hora": datetime.utcfromtimestamp(int(s.get("timestamp", 0))).isoformat() + "Z",
                                           "errores": [{"code": x.get("code"), "title": x.get("title"),
                                                        "detalle": (x.get("error_data") or {}).get("details")}
                                                       for x in (s.get("errors") or [])]}
                                    if rec not in objetivos[w]["statuses"]:
                                        objetivos[w]["statuses"].append(rec)
                                        hallados += 1
print(f"Status encontrados: {hallados}")
print()
for w, o in objetivos.items():
    print(f"=== Recordatorio {o['label']} ({short(w)}) enviado {o['enviado']} ===")
    if not o["statuses"]:
        print("  Meta NO informo ningun status en el webhook para este mensaje")
    for s in o["statuses"]:
        print(f"  {s['hora']}  {s['status']}  {s['errores'] or ''}")
    print()
