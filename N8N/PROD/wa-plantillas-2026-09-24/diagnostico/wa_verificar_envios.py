"""Solo lectura: que enviaron los 7 flujos de WhatsApp y que informo Meta despues por el webhook del bot
(sent/delivered/read/failed, con el cobro), mas los botones que tocaron los clientes.
Uso: python wa_verificar_envios.py [desde_ISO]   (sin argumento: desde que cada flujo usa plantilla)
n8n guarda ~5 dias de ejecuciones. No imprime telefonos, nombres ni wamid completos (el wamid codifica el numero)."""
import hashlib
import io
import json
import re
import sys
import time
import urllib.error
import urllib.request
from collections import Counter, defaultdict
from datetime import datetime, timezone

sys.stdout.reconfigure(encoding="utf-8")
KEY = json.load(io.open(r"C:/Users/luis_/.claude.json", encoding="utf-8"))["mcpServers"]["n8n-mcp"]["env"]["N8N_API_KEY"]
API = "https://n8n-n8n.kchiba.easypanel.host/api/v1"
BOT = "bBcNlFgBzQ0766Mq"
FLUJOS = {
    "KFqFTgc0o4bOe0xv": ("Citas 72h", "2026-09-25T16:42:00Z"),
    "cbFi7tpGGn7pEDnr": ("Citas 24h", "2026-09-25T16:42:00Z"),
    "ljNN7NedbUHzytwY": ("Citas 1h", "2026-09-25T16:42:00Z"),
    "MchisrkbuXDOqKvQ": ("Seguimiento 8AM", "2026-09-25T17:13:00Z"),
    "uU39aWmvJ4gGj8T2": ("Seguimiento 8PM", "2026-09-25T17:13:00Z"),
    "daSz1OJSeaQckDVy5q0uS": ("Followup", "2026-09-25T17:13:00Z"),
    "p7ISUf0J5GycHscc": ("ARGOS", "2026-09-25T18:25:00Z"),
}


def get(path):
    for intento in range(4):
        try:
            req = urllib.request.Request(API + path, headers={"X-N8N-API-KEY": KEY, "accept": "application/json"})
            with urllib.request.urlopen(req, timeout=180) as r:
                return json.load(r)
        except urllib.error.URLError:
            if intento == 3:
                raise
            time.sleep(3 * (intento + 1))


def ts(s):
    return datetime.fromisoformat(s.replace("Z", "+00:00"))


def hw(w):
    return "w-" + hashlib.sha1(w.encode()).hexdigest()[:8]


ALIAS = {}


def alias(num):
    num = str(num or "")
    if not num:
        return "?"
    if num not in ALIAS:
        ALIAS[num] = "dest-" + hashlib.sha1(num.encode()).hexdigest()[:5]
    return ALIAS[num]


def limpio(t):
    t = re.sub(r"wamid\.[A-Za-z0-9=_+/-]+", "<wamid>", str(t))
    return re.sub(r"\d{8,}", "<num>", t)[:260]


def ejecuciones(wid, desde, lote=50):
    cursor = None
    while True:
        q = f"/executions?workflowId={wid}&limit={lote}&includeData=true" + (f"&cursor={cursor}" if cursor else "")
        d = get(q)
        for e in d.get("data", []):
            st = e.get("startedAt")
            if st and ts(st) < ts(desde):
                return
            yield e
        cursor = d.get("nextCursor")
        if not cursor:
            return


def plantilla_de(j):
    wb = j.get("whatsappBody")
    if isinstance(wb, str):
        try:
            wb = json.loads(wb)
        except Exception:
            wb = None
    for cand in (wb, j):
        if isinstance(cand, dict) and isinstance(cand.get("template"), dict):
            return cand["template"].get("name"), cand.get("to")
    return None, None


DESDE = sys.argv[1] if len(sys.argv) > 1 else None
if DESDE:
    FLUJOS = {k: (v[0], DESDE) for k, v in FLUJOS.items()}

ENVIOS = {}
print("=== 1. Envios de los 7 flujos desde que usan plantilla ===")
for wid, (label, desde) in FLUJOS.items():
    n = 0
    con_envio = 0
    errores = []
    armadas = Counter()
    for e in ejecuciones(wid, desde):
        n += 1
        rd = (((e.get("data") or {}).get("resultData") or {}).get("runData") or {})
        envio_aqui = False
        for nodo, runs in rd.items():
            for run in runs or []:
                if run.get("error"):
                    er = run["error"]
                    errores.append((e["startedAt"], nodo, limpio(er.get("message")), limpio(er.get("description") or "")))
                for idx, out in enumerate(((run.get("data") or {}).get("main") or [])):
                    for it in out or []:
                        j = it.get("json") or {}
                        nombre, _to = plantilla_de(j)
                        if nombre:
                            armadas[nombre] += 1
                        msgs = j.get("messages")
                        if isinstance(msgs, list) and msgs and str(msgs[0].get("id", "")).startswith("wamid."):
                            envio_aqui = True
                            w = msgs[0]["id"]
                            wa = (j.get("contacts") or [{}])[0].get("wa_id")
                            ENVIOS[w] = {"flujo": label, "exec": e["id"], "hora": e["startedAt"], "nodo": nodo,
                                         "dest": alias(wa), "message_status": msgs[0].get("message_status")}
                        elif j.get("error"):
                            errores.append((e["startedAt"], f"{nodo}[salida {idx}]", limpio(j["error"]), ""))
        con_envio += envio_aqui
    print(f"{label:<16} ejecuciones={n:<4} con_envio={con_envio:<3} plantillas_armadas={dict(armadas)}")
    for er in errores[:8]:
        print(f"     ERROR {er}")

print()
for w, inf in sorted(ENVIOS.items(), key=lambda x: x[1]["hora"]):
    print(f"  enviado {inf['hora']}  {inf['flujo']:<16} exec {inf['exec']}  nodo={inf['nodo']!r}  {hw(w)}  a {inf['dest']}  message_status={inf['message_status']}")

print()
print("=== 2. Webhook del bot: statuses y mensajes entrantes desde el 25-sep ===")
STATUS = defaultdict(list)
ENTRANTES = []
N_BOT = 0
vistos = set()
for e in ejecuciones(BOT, DESDE or "2026-09-25T00:00:00Z", lote=10):
    N_BOT += 1
    res = ((e.get("data") or {}).get("resultData") or {})
    rd = res.get("runData") or {}
    ultimo = res.get("lastNodeExecuted")
    for nodo, runs in rd.items():
        for run in runs or []:
            for out in ((run.get("data") or {}).get("main") or []):
                for it in out or []:
                    j = it.get("json") or {}
                    body = j.get("body") if isinstance(j.get("body"), dict) else j
                    if not isinstance(body, dict):
                        continue
                    for entry in body.get("entry") or []:
                        for ch in entry.get("changes", []) or []:
                            v = ch.get("value") or {}
                            for s in v.get("statuses") or []:
                                k = (s.get("id"), s.get("status"), s.get("timestamp"))
                                if k in vistos:
                                    continue
                                vistos.add(k)
                                pr = s.get("pricing")
                                STATUS[s.get("id")].append({
                                    "status": s.get("status"),
                                    "hora": datetime.fromtimestamp(int(s.get("timestamp", 0)), timezone.utc).strftime("%Y-%m-%d %H:%M:%SZ"),
                                    "dest": alias(s.get("recipient_id")),
                                    "err": [(x.get("code"), x.get("title")) for x in (s.get("errors") or [])],
                                    "pricing": {k2: pr.get(k2) for k2 in ("billable", "category", "type", "pricing_model")} if pr else None,
                                    "exec": e["id"]})
                            for m in v.get("messages") or []:
                                k = ("msg", m.get("id"))
                                if k in vistos:
                                    continue
                                vistos.add(k)
                                p = None
                                if m.get("type") == "button":
                                    p = (m.get("button") or {}).get("payload")
                                elif m.get("type") == "interactive":
                                    p = ((m.get("interactive") or {}).get("button_reply") or {}).get("id")
                                ENTRANTES.append((e["startedAt"], m.get("type"), p, alias(m.get("from")), e["id"], e.get("status"), ultimo))
print(f"ejecuciones del bot revisadas: {N_BOT}")
print()
print("-- statuses de los mensajes enviados por los 7 flujos --")
if not ENVIOS:
    print("  (no hubo envios)")
for w, inf in sorted(ENVIOS.items(), key=lambda x: x[1]["hora"]):
    print(f"  {inf['flujo']} {hw(w)} enviado {inf['hora']}:")
    for s in sorted(STATUS.get(w, []), key=lambda x: x["hora"]):
        print(f"      {s['hora']} {s['status']:<9} err={s['err']} pricing={s['pricing']}")
    if not STATUS.get(w):
        print("      (Meta no informo ningun status en el webhook para este mensaje)")
print()
print("-- todos los statuses del webhook desde el 25-sep (por mensaje) --")
for w, lst in STATUS.items():
    lst.sort(key=lambda x: x["hora"])
    origen = ENVIOS.get(w, {}).get("flujo", "otro flujo")
    cadena = " -> ".join(f"{s['status']}@{s['hora'][5:16]}{s['err'] or ''}" for s in lst)
    print(f"  {hw(w)} ({origen}) a {lst[0]['dest']}: {cadena}  pricing={lst[-1]['pricing']}")
print()
print("-- mensajes entrantes al bot --")
for b in ENTRANTES:
    print(f"  {b[0]} tipo={b[1]} payload={b[2]} de {b[3]} exec {b[4]} status={b[5]} ultimo_nodo={b[6]!r}")
print()
print("destinos vistos:", sorted(set(ALIAS.values())))
