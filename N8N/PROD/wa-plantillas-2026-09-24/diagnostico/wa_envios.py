"""Busca en el historial de n8n las ejecuciones de los recordatorios WhatsApp
que llegaron a enviar algo, y muestra que respondio Meta.
No imprime nombres ni telefonos completos."""
import io
import json
import sys
import urllib.request

sys.stdout.reconfigure(encoding="utf-8")

KEY = json.load(io.open(r"C:/Users/luis_/.claude.json", encoding="utf-8"))["mcpServers"]["n8n-mcp"]["env"]["N8N_API_KEY"]
BASE = "https://n8n-n8n.kchiba.easypanel.host/api/v1"

WFS = {
    "1h": "ljNN7NedbUHzytwY",
    "24h": "cbFi7tpGGn7pEDnr",
    "72h": "KFqFTgc0o4bOe0xv",
}


def get(path):
    req = urllib.request.Request(BASE + path, headers={"X-N8N-API-KEY": KEY, "accept": "application/json"})
    with urllib.request.urlopen(req, timeout=90) as r:
        return json.load(r)


def mask(p):
    p = str(p or "")
    return ("***" + p[-4:]) if len(p) > 4 else p


def items_of(rd, node):
    runs = rd.get(node) or []
    if not runs:
        return None
    main = ((runs[0].get("data") or {}).get("main") or [[]])
    return main[0] or []


for label, wid in WFS.items():
    cursor = None
    pages = 0
    total = 0
    oldest = None
    envios = []
    while pages < 40 and len(envios) < 4:
        q = f"/executions?workflowId={wid}&limit=100&includeData=true"
        if cursor:
            q += "&cursor=" + cursor
        d = get(q)
        pages += 1
        for e in d.get("data", []):
            total += 1
            oldest = e.get("startedAt")
            rd = ((e.get("data") or {}).get("resultData") or {}).get("runData") or {}
            send = [k for k in rd if k.lower().startswith("send")]
            if not send:
                continue
            leads = [k for k in rd if k.lower().startswith("get leads")]
            n_leads = len(items_of(rd, leads[0]) or []) if leads else "?"
            resp = []
            for it in items_of(rd, send[0]) or []:
                j = it.get("json", {})
                msgs = j.get("messages") or []
                contacts = j.get("contacts") or []
                resp.append({
                    "wamid": (msgs[0].get("id", "")[:18] + "...") if msgs else None,
                    "message_status": msgs[0].get("message_status") if msgs else None,
                    "wa_id": mask(contacts[0].get("wa_id")) if contacts else None,
                    "error": j.get("error"),
                })
            envios.append({"id": e["id"], "inicio": e.get("startedAt"), "status": e.get("status"),
                           "leads": n_leads, "respuesta_meta": resp})
        cursor = d.get("nextCursor")
        if not cursor:
            break
    print(f"=== Recordatorio {label} ({wid}) ===")
    print(f"  ejecuciones revisadas: {total}  |  historial hasta: {oldest}")
    if not envios:
        print("  NINGUNA ejecucion llego a enviar en todo ese historial")
    for x in envios:
        print(f"  envio exec {x['id']} @ {x['inicio']} status={x['status']} leads={x['leads']}")
        for r in x["respuesta_meta"]:
            print(f"     -> {r}")
    print()
