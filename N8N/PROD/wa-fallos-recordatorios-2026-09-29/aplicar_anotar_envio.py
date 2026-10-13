"""Anota en WordPress el wamid de cada recordatorio de WhatsApp (29-sep-2026, decision de Luis).

Agrega a los 6 flujos que le escriben al cliente con plantilla un nodo "Anotar envio en WP" que manda a
at/v1/whatsapp-envio el wamid que devolvio Meta, el tipo de mensaje y el id de la cita o de la reunion.
Si despues Meta avisa que no lo entrego, WordPress le manda un correo a Luis
(wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp-recordatorios.php).

El nodo va en una rama propia que termina ahi, con onError continueRegularOutput y neverError: si
WordPress no responde, el recordatorio sigue igual que hoy. En 8 AM y 8 PM (bucle de a una reunion con
espera de 5 s) va arriba de "Mark as Sent" para correr primero en cada vuelta (orden v1: de arriba hacia
abajo). En el Followup va despues de "Respond Success", para no demorar la respuesta a WordPress.

Uso:
  python aplicar_anotar_envio.py            REVISAR: solo lee y muestra que haria
  python aplicar_anotar_envio.py aplicar    escribe en PROD: respaldo antes, verificacion de la version publicada despues
Antes de escribir comprueba que la ruta de WordPress existe (401 sin clave); si da 404, no toca nada.
La clave de la API de n8n se lee de ~/.claude.json; ningun secreto vive en este archivo.
"""
import copy
import io
import json
import os
import sys
import time
import urllib.error
import urllib.request
import uuid

sys.stdout.reconfigure(encoding="utf-8")
AQUI = os.path.dirname(os.path.abspath(__file__))
N8N = "https://n8n-n8n.kchiba.easypanel.host"
API = N8N + "/api/v1"
RUTA_WP = "https://automatizatech.cl/wp-json/at/v1/whatsapp-envio"
SETTINGS_RECHAZADOS = {"timeSavedMode", "availableInMCP", "binaryMode"}  # el servidor conserva los omitidos
NOMBRE = "Anotar envío en WP"
CREDENCIAL = {"httpHeaderAuth": {"id": "1NI0sJKc0kC430pb", "name": "AT REST Secret (header)"}}
SELLO = time.strftime("%Y%m%d-%H%M%S")

WAMID = "($json.messages && $json.messages[0] && $json.messages[0].id) || ''"
SEND_FOLLOWUP = "$('Send WhatsApp').item.json"
PREP_FOLLOWUP = "$('Prepare WA Message').item.json"

# desde/salida: de donde sale la rama nueva. junto: nodo al lado del cual se dibuja (arriba o a la derecha).
TRABAJOS = [
    {"wid": "KFqFTgc0o4bOe0xv", "label": "Citas 72h", "desde": "Send WhatsApp 72h", "salida": 0, "junto": "Mark 72h Sent",
     "donde": "arriba", "wamid": WAMID, "tipo": "'cita_72h'", "id": "$('Build WhatsApp Body').item.json.id"},
    {"wid": "cbFi7tpGGn7pEDnr", "label": "Citas 24h", "desde": "Send WhatsApp 24h", "salida": 0, "junto": "Mark 24h Sent",
     "donde": "arriba", "wamid": WAMID, "tipo": "'cita_24h'", "id": "$('Build WhatsApp Body').item.json.id"},
    {"wid": "ljNN7NedbUHzytwY", "label": "Citas 1h", "desde": "Send WhatsApp 1h", "salida": 0, "junto": "Mark 1h Sent",
     "donde": "arriba", "wamid": WAMID, "tipo": "'cita_1h'", "id": "$('Build WhatsApp Body').item.json.id"},
    {"wid": "MchisrkbuXDOqKvQ", "label": "Seguimiento 8AM", "desde": "Send WhatsApp", "salida": 0, "junto": "Mark as Sent",
     "donde": "arriba", "wamid": WAMID, "tipo": "'seguimiento_8am'", "id": "$('Prepare WA Data').item.json.meeting_id"},
    {"wid": "uU39aWmvJ4gGj8T2", "label": "Seguimiento 8PM", "desde": "Send WhatsApp", "salida": 0, "junto": "Mark as Sent",
     "donde": "arriba", "wamid": WAMID, "tipo": "'seguimiento_8pm'", "id": "$('Prepare WA Data').item.json.meeting_id"},
    {"wid": "daSz1OJSeaQckDVy5q0uS", "label": "Followup", "desde": "Respond Success", "salida": 0, "junto": "Respond Success",
     "donde": "derecha",
     "wamid": f"({SEND_FOLLOWUP}.messages && {SEND_FOLLOWUP}.messages[0] && {SEND_FOLLOWUP}.messages[0].id) || ''",
     "tipo": (f"({PREP_FOLLOWUP}.meetingType === 'demo' ? 'reunion_demo' : "
              f"({PREP_FOLLOWUP}.meetingType === 'seguimiento_prospecto' ? 'reunion_prospecto' : 'reunion_seguimiento'))"),
     "id": f"{PREP_FOLLOWUP}.meeting_id"},
]


def expresion(t):
    return "={{ JSON.stringify({ wamid: " + t["wamid"] + ", tipo: " + t["tipo"] + ", id: " + t["id"] + " }) }}"


def nodo_nuevo(t, posicion):
    return {
        "id": str(uuid.uuid4()),
        "name": NOMBRE,
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.2,
        "position": posicion,
        "onError": "continueRegularOutput",
        "credentials": copy.deepcopy(CREDENCIAL),
        "parameters": {
            "method": "POST",
            "url": RUTA_WP,
            "authentication": "genericCredentialType",
            "genericAuthType": "httpHeaderAuth",
            "sendBody": True,
            "specifyBody": "json",
            "jsonBody": expresion(t),
            "options": {"timeout": 15000, "response": {"response": {"neverError": True}}},
        },
    }


def agregar(nodes, connections, t):
    """Devuelve (nodes, connections) con la rama nueva, sin tocar lo que ya habia. None si ya estaba."""
    nombres = {n["name"]: n for n in nodes}
    if NOMBRE in nombres:
        return None
    for requerido in (t["desde"], t["junto"]):
        if requerido not in nombres:
            raise RuntimeError(f"{t['label']}: no esta el nodo '{requerido}'")
    x, y = nombres[t["junto"]]["position"]
    posicion = [x, y - 180] if t["donde"] == "arriba" else [x + 224, y]
    nodes = copy.deepcopy(nodes) + [nodo_nuevo(t, posicion)]
    connections = copy.deepcopy(connections)
    salidas = connections.setdefault(t["desde"], {}).setdefault("main", [])
    while len(salidas) <= t["salida"]:
        salidas.append([])
    salidas[t["salida"]] = (salidas[t["salida"]] or []) + [{"node": NOMBRE, "type": "main", "index": 0}]
    return nodes, connections


# ------------------------------------------------------------------ n8n y WordPress
def _clave():
    return json.load(io.open(os.path.expanduser("~/.claude.json"), encoding="utf-8"))["mcpServers"]["n8n-mcp"]["env"]["N8N_API_KEY"]


def api(method, path, body=None):
    data = json.dumps(body).encode("utf-8") if body is not None else None
    req = urllib.request.Request(API + path, data=data, method=method,
                                 headers={"X-N8N-API-KEY": _clave(), "content-type": "application/json",
                                          "accept": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=120) as r:
            raw = r.read()
            return json.loads(raw) if raw else {}
    except urllib.error.HTTPError as e:
        raise RuntimeError(f"{method} {path} -> HTTP {e.code}: {e.read().decode('utf-8', 'replace')[:400]}")


def ruta_wp_existe():
    """401 = la ruta existe y pide clave (lo esperado). 404 = WordPress todavia no la tiene."""
    req = urllib.request.Request(RUTA_WP, data=b"{}", method="POST", headers={"content-type": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            return r.status
    except urllib.error.HTTPError as e:
        return e.code


def respaldar(wf):
    # carpeta ignorada por git (.gitignore): el repo es publico; despues se mueve a la boveda privada
    p = os.path.join(AQUI, "respaldos-antes-del-cambio", f"respaldo_{wf['id']}_{SELLO}.json")
    os.makedirs(os.path.dirname(p), exist_ok=True)
    io.open(p, "w", encoding="utf-8").write(json.dumps(wf, ensure_ascii=False, indent=1))
    return p


def firma_nodos(nodes, sin=None):
    return sorted(json.dumps(n, sort_keys=True) for n in nodes if n["name"] != sin)


def verificar(antes, pub, t):
    """La version publicada tiene la rama nueva y todo lo demas igual que antes."""
    nuevo = next((n for n in pub["nodes"] if n["name"] == NOMBRE), None)
    assert nuevo is not None, "no quedo el nodo nuevo"
    p = nuevo["parameters"]
    assert p["url"] == RUTA_WP and p["jsonBody"] == expresion(t) and nuevo.get("onError") == "continueRegularOutput", "parametros distintos"
    assert nuevo.get("credentials") == CREDENCIAL, "credencial distinta"
    assert p["options"]["response"]["response"]["neverError"] is True, "falta neverError"
    assert firma_nodos(pub["nodes"], NOMBRE) == firma_nodos(antes["nodes"]), "cambio otro nodo"
    esperadas = agregar(antes["nodes"], antes["connections"], t)[1]
    assert json.dumps(pub["connections"], sort_keys=True) == json.dumps(esperadas, sort_keys=True), "conexiones distintas a las esperadas"


def main():
    aplicar = len(sys.argv) > 1 and sys.argv[1] == "aplicar"
    estado_wp = ruta_wp_existe()
    print(f"Ruta de WordPress at/v1/whatsapp-envio sin clave: HTTP {estado_wp} ({'existe' if estado_wp == 401 else 'NO ESTA'})")
    if aplicar and estado_wp != 401:
        raise SystemExit("La ruta de WordPress no esta en PROD: primero se sube el PHP. No se toco n8n.")
    for t in TRABAJOS:
        wf = api("GET", f"/workflows/{t['wid']}")
        if wf.get("versionId") != wf.get("activeVersionId"):
            print(f"[{t['label']}] el borrador no es lo publicado: no se toca")
            continue
        r = agregar(wf["nodes"], wf["connections"], t)
        if r is None:
            print(f"[{t['label']}] ya tiene '{NOMBRE}': sin cambios")
            continue
        nodes, connections = r
        nuevo = nodes[-1]
        print(f"[{t['label']}] se agrega '{NOMBRE}' desde '{t['desde']}' (salida {t['salida']}) en {nuevo['position']}")
        if not aplicar:
            continue
        print(f"   respaldo: {respaldar(wf)}")
        antes = copy.deepcopy(wf)
        settings = {k: v for k, v in (wf.get("settings") or {}).items() if k not in SETTINGS_RECHAZADOS}
        api("PUT", f"/workflows/{t['wid']}", {"name": wf["name"], "nodes": nodes, "connections": connections, "settings": settings})
        api("POST", f"/workflows/{t['wid']}/activate")
        leido = api("GET", f"/workflows/{t['wid']}")
        assert leido.get("versionId") == leido.get("activeVersionId"), "el borrador no quedo publicado"
        assert (leido.get("settings") or {}) == (antes.get("settings") or {}), "cambiaron los ajustes"
        verificar(antes, leido.get("activeVersion") or leido, t)
        print("   OK publicado y verificado: solo se agrego la rama nueva")


if __name__ == "__main__":
    main()
