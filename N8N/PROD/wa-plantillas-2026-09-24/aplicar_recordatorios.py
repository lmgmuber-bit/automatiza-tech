"""Aplica el arreglo de los recordatorios de WhatsApp (2026-09-24).

  python aplicar_recordatorios.py            -> REVISAR: solo lee y muestra lo que haria (no escribe nada)
  python aplicar_recordatorios.py aplicar    -> escribe en n8n PROD

Orden: 1) bot principal (entiende el boton de plantilla, retrocompatible)
       2) recordatorios 72h/24h/1h (solo si Meta ya aprobo las tres plantillas)
Cada workflow: respaldo -> cambio -> PUT -> publicar -> verificar la version PUBLICADA.
Idempotente: si un cambio ya esta, lo salta. No imprime tokens.
"""
import copy
import io
import json
import os
import secrets
import sys
import time
import uuid
import urllib.error
import urllib.request

sys.stdout.reconfigure(encoding="utf-8")
APLICAR = len(sys.argv) > 1 and sys.argv[1] == "aplicar"
AQUI = os.path.dirname(os.path.abspath(__file__))
KEY = json.load(io.open(r"C:/Users/luis_/.claude.json", encoding="utf-8"))["mcpServers"]["n8n-mcp"]["env"]["N8N_API_KEY"]
N8N = "https://n8n-n8n.kchiba.easypanel.host"
API = N8N + "/api/v1"
WABA = "1294451839384217"
CRED_WA = {"whatsAppApi": {"id": "SH8OXr93p852Ll6m", "name": "WhatsApp Tech"}}
BOT = "bBcNlFgBzQ0766Mq"
RECORDATORIOS = {"72h": "KFqFTgc0o4bOe0xv", "24h": "cbFi7tpGGn7pEDnr", "1h": "ljNN7NedbUHzytwY"}
SELLO = time.strftime("%Y%m%d-%H%M%S")
# claves de settings que esta version de n8n rechaza al escribir (ver reference_n8n_api_settings_blocker)
SETTINGS_RECHAZADOS = {"timeSavedMode", "availableInMCP", "binaryMode"}  # el servidor conserva los omitidos


def api(method, path, body=None):
    data = json.dumps(body).encode("utf-8") if body is not None else None
    req = urllib.request.Request(API + path, data=data, method=method,
                                 headers={"X-N8N-API-KEY": KEY, "content-type": "application/json",
                                          "accept": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=120) as r:
            raw = r.read()
            return json.loads(raw) if raw else {}
    except urllib.error.HTTPError as e:
        raise RuntimeError(f"{method} {path} -> HTTP {e.code}: {e.read().decode('utf-8', 'replace')[:400]}")


def nodo(wf, nombre):
    for n in wf["nodes"]:
        if n["name"] == nombre:
            return n
    raise KeyError(f"no existe el nodo '{nombre}' en '{wf['name']}'")


def respaldar(wf):
    # carpeta ignorada por git (.gitignore): el repo es publico; copiar despues a la boveda privada
    p = os.path.join(AQUI, "respaldos-antes-del-cambio", f"respaldo_{wf['id']}_{SELLO}.json")
    os.makedirs(os.path.dirname(p), exist_ok=True)
    io.open(p, "w", encoding="utf-8").write(json.dumps(wf, ensure_ascii=False, indent=1))
    return p


def escribir_y_publicar(wf):
    """PUT del borrador y publicacion; devuelve la version publicada leida de vuelta."""
    settings = {k: v for k, v in (wf.get("settings") or {}).items() if k not in SETTINGS_RECHAZADOS}
    api("PUT", f"/workflows/{wf['id']}",
        {"name": wf["name"], "nodes": wf["nodes"], "connections": wf["connections"], "settings": settings})
    api("POST", f"/workflows/{wf['id']}/activate")
    leido = api("GET", f"/workflows/{wf['id']}")
    if leido.get("versionId") != leido.get("activeVersionId"):
        raise RuntimeError(f"'{wf['name']}': el borrador no quedo publicado")
    if (leido.get("settings") or {}) != (wf.get("settings") or {}):
        raise RuntimeError(f"'{wf['name']}': cambiaron los ajustes al escribir: "
                           f"{wf.get('settings')} -> {leido.get('settings')}")
    return leido.get("activeVersion") or leido


# ---------------------------------------------------------------- 1) BOT
def bot():
    wf = api("GET", f"/workflows/{BOT}")
    mt = nodo(wf, "Message Type")
    pi = nodo(wf, "Process Interactive")
    reglas = mt["parameters"]["rules"]["rules"]
    valores = {v["name"]: v for v in pi["parameters"]["values"]["string"]}
    ya_regla = any(r.get("value2") == "button" for r in reglas)
    ya_payload = "button?.payload" in valores["buttonId"]["value"]
    print(f"[bot] regla 'button' en Message Type: {'ya esta' if ya_regla else 'FALTA'}; "
          f"button.payload en Process Interactive: {'ya esta' if ya_payload else 'FALTA'}")
    if ya_regla and ya_payload:
        return
    antes_con = json.dumps(wf["connections"], sort_keys=True)
    antes_n = len(wf["nodes"])
    if not ya_regla:
        reglas.append({"value2": "button", "output": 3})  # misma salida que 'interactive'
    m = "$node['WhatsApp Webhook'].json.body.entry[0].changes[0].value.messages[0]"
    valores["interactiveType"]["value"] = "={{ " + m + ".interactive?.type || " + m + ".type }}"
    valores["buttonId"]["value"] = ("={{ " + m + ".interactive?.button_reply?.id || " + m +
                                    ".interactive?.list_reply?.id || " + m + ".button?.payload || '' }}")
    valores["buttonTitle"]["value"] = ("={{ " + m + ".interactive?.button_reply?.title || " + m +
                                       ".interactive?.list_reply?.title || " + m + ".button?.text || '' }}")
    if not APLICAR:
        print("[bot] REVISAR: se agregaria la regla y se cambiarian 3 expresiones; conexiones sin cambios")
        return
    print("[bot] respaldo:", respaldar(api("GET", f"/workflows/{BOT}")))
    pub = escribir_y_publicar(wf)
    assert len(pub["nodes"]) == antes_n, "cambio la cantidad de nodos del bot"
    assert json.dumps(pub["connections"], sort_keys=True) == antes_con, "cambiaron conexiones del bot"
    assert any(r.get("value2") == "button" for r in nodo(pub, "Message Type")["parameters"]["rules"]["rules"])
    assert "button?.payload" in json.dumps(nodo(pub, "Process Interactive")["parameters"])
    print(f"[bot] OK publicado: {antes_n} nodos y conexiones identicas; entiende el boton de plantilla")


# ---------------------------------------------------------------- 2) PLANTILLAS APROBADAS?
def plantillas_aprobadas():
    """Consulta Meta con la credencial de n8n mediante un workflow temporal que se borra siempre."""
    path = "tmp-wa-estado-" + secrets.token_hex(12)
    wf = {"name": "TEMP Claude - estado plantillas WA (borrar)",
          "nodes": [{"id": "wh", "name": "Webhook", "type": "n8n-nodes-base.webhook", "typeVersion": 2,
                     "position": [0, 0], "webhookId": str(uuid.uuid4()),
                     "parameters": {"httpMethod": "GET", "path": path, "responseMode": "lastNode", "options": {}}},
                    {"id": "ht", "name": "Listar", "type": "n8n-nodes-base.httpRequest", "typeVersion": 4.2,
                     "position": [240, 0], "credentials": CRED_WA,
                     "parameters": {"method": "GET",
                                    "url": f"https://graph.facebook.com/v22.0/{WABA}/message_templates",
                                    "authentication": "predefinedCredentialType", "nodeCredentialType": "whatsAppApi",
                                    "sendQuery": True, "queryParameters": {"parameters": [
                                        {"name": "fields", "value": "name,status"}, {"name": "limit", "value": "100"}]},
                                    "options": {"response": {"response": {"neverError": True}}}}}],
          "connections": {"Webhook": {"main": [[{"node": "Listar", "type": "main", "index": 0}]]}},
          "settings": {"executionOrder": "v1", "saveDataSuccessExecution": "none", "saveDataErrorExecution": "none"}}
    wid = None
    try:
        wid = api("POST", "/workflows", wf)["id"]
        api("POST", f"/workflows/{wid}/activate")
        for _ in range(10):
            try:
                with urllib.request.urlopen(f"{N8N}/webhook/{path}", timeout=60) as r:
                    d = json.load(r)
                d = d[0] if isinstance(d, list) else d
                return {t["name"]: t["status"] for t in d.get("data", [])}
            except urllib.error.HTTPError as e:
                if e.code != 404:
                    raise
                time.sleep(2)
        raise RuntimeError("el webhook temporal no quedo registrado")
    finally:
        if wid:
            api("DELETE", f"/workflows/{wid}")


# ---------------------------------------------------------------- 3) RECORDATORIOS
def mensaje_error(tipo):
    e = f"$('Send WhatsApp {tipo}').item.json.error"
    return ("={{ 'Recordatorio WhatsApp " + tipo + " NO enviado al lead ' + $('Build WhatsApp Body').item.json.id"
            " + ': ' + ((x) => typeof x === 'string' ? x : (x?.description || x?.message || JSON.stringify(x)))(" + e + ")"
            " + '. Quedo marcado para no reintentar cada pocos minutos; revisar el numero o la plantilla en WhatsApp Manager.' }}")


def recordatorio(tipo, wid):
    wf = api("GET", f"/workflows/{wid}")
    build = nodo(wf, "Build WhatsApp Body")
    codigo = build["parameters"]["jsCode"]
    if "type: 'template'" in codigo:
        print(f"[{tipo}] ya usa plantilla: sin cambios")
        return
    if "type: 'interactive'" not in codigo:
        raise RuntimeError(f"[{tipo}] el nodo Build no esta como se esperaba; no se toca")
    nuevo = io.open(os.path.join(AQUI, "codigo-nodos", f"build_{tipo}.js"), encoding="utf-8").read()
    send = nodo(wf, f"Send WhatsApp {tipo}")
    mark = nodo(wf, f"Mark {tipo} Sent")
    x, y = mark["position"]

    build["parameters"]["jsCode"] = nuevo
    send.pop("continueOnFail", None)
    send["onError"] = "continueErrorOutput"   # salida 1 = error de Meta
    fallido = copy.deepcopy(mark)
    fallido["id"] = str(uuid.uuid4())
    fallido["name"] = f"Mark {tipo} Sent (fallido)"
    fallido["position"] = [x, y + 180]
    avisar = {"id": str(uuid.uuid4()), "name": f"Avisar fallo {tipo}", "type": "n8n-nodes-base.stopAndError",
              "typeVersion": 1, "position": [x + 224, y + 180],
              "parameters": {"errorType": "errorMessage", "errorMessage": mensaje_error(tipo)}}
    wf["nodes"] += [fallido, avisar]
    wf["connections"][send["name"]] = {"main": [
        [{"node": mark["name"], "type": "main", "index": 0}],
        [{"node": fallido["name"], "type": "main", "index": 0}]]}
    wf["connections"][fallido["name"]] = {"main": [[{"node": avisar["name"], "type": "main", "index": 0}]]}
    assert isinstance(wf["connections"][send["name"]]["main"][1], list)  # array de arrays (gotcha)

    if not APLICAR:
        print(f"[{tipo}] REVISAR: Build -> plantilla recordatorio_cita_{tipo}; Send con salida de error; "
              f"+ '{fallido['name']}' + '{avisar['name']}'")
        return
    print(f"[{tipo}] respaldo:", respaldar(api("GET", f"/workflows/{wid}")))
    pub = escribir_y_publicar(wf)
    assert "type: 'template'" in nodo(pub, "Build WhatsApp Body")["parameters"]["jsCode"]
    assert nodo(pub, f"Send WhatsApp {tipo}").get("onError") == "continueErrorOutput"
    nodo(pub, f"Mark {tipo} Sent (fallido)")
    nodo(pub, f"Avisar fallo {tipo}")
    salidas = pub["connections"][f"Send WhatsApp {tipo}"]["main"]
    assert salidas[1][0]["node"] == f"Mark {tipo} Sent (fallido)"
    print(f"[{tipo}] OK publicado: usa la plantilla y un fallo se marca una vez y avisa a ARGOS")


# ---------------------------------------------------------------- 4) 8 AM, 8 PM Y FOLLOWUP
def seguimiento(label, wid, prep_nombre, archivo):
    """Solo cambia 2 nodos: el que arma los datos (genera whatsappBody) y el Send (lo manda tal cual).
    Sin freno extra: 8 AM/8 PM corren una vez al dia sobre citas de hoy/manana (no hay reintento en bucle)
    y el Followup ya responde el error a WordPress."""
    wf = api("GET", f"/workflows/{wid}")
    prep = nodo(wf, prep_nombre)
    send = nodo(wf, "Send WhatsApp")
    if "type: 'template'" in prep["parameters"]["jsCode"] and "whatsappBody" in send["parameters"]["jsonBody"]:
        print(f"[{label}] ya usa plantilla: sin cambios")
        return
    if "messageBody" not in prep["parameters"]["jsCode"] or "interactive" not in send["parameters"]["jsonBody"]:
        raise RuntimeError(f"[{label}] los nodos no estan como se esperaba; no se toca")
    antes_con = json.dumps(wf["connections"], sort_keys=True)
    antes_n = len(wf["nodes"])
    on_error = send.get("onError")
    prep["parameters"]["jsCode"] = io.open(os.path.join(AQUI, "codigo-nodos", archivo), encoding="utf-8").read()
    send["parameters"]["jsonBody"] = "={{ $json.whatsappBody }}"
    if not APLICAR:
        print(f"[{label}] REVISAR: '{prep_nombre}' arma la plantilla; 'Send WhatsApp' la manda; conexiones sin cambios")
        return
    print(f"[{label}] respaldo:", respaldar(api("GET", f"/workflows/{wid}")))
    pub = escribir_y_publicar(wf)
    assert "type: 'template'" in nodo(pub, prep_nombre)["parameters"]["jsCode"]
    assert nodo(pub, "Send WhatsApp")["parameters"]["jsonBody"] == "={{ $json.whatsappBody }}"
    assert nodo(pub, "Send WhatsApp").get("onError") == on_error
    assert len(pub["nodes"]) == antes_n and json.dumps(pub["connections"], sort_keys=True) == antes_con
    print(f"[{label}] OK publicado: usa plantilla; mismos nodos y conexiones")


# ---------------------------------------------------------------- 5) ARGOS
ARGOS = "p7ISUf0J5GycHscc"
IF_ARGOS = "¿Primera vez de este error?"


def normal(s):
    try:
        return s.encode("utf-16", "surrogatepass").decode("utf-16")
    except Exception:
        return s


def argos():
    """Alertas de ARGOS por plantilla alerta_argos, WhatsApp una sola vez por error (correo siempre),
    un fallo del WhatsApp o del historial ya no corta el correo."""
    wf = api("GET", f"/workflows/{ARGOS}")
    prep = nodo(wf, "Preparar Datos")
    actual = normal(prep["parameters"]["jsCode"])
    if "alerta_argos" in actual:
        print("[ARGOS] ya usa plantilla: sin cambios")
        return
    base = io.open(os.path.join(AQUI, "codigo-nodos", "preparar_datos_actual.js"), encoding="utf-8").read()
    if actual != base:
        raise RuntimeError("[ARGOS] 'Preparar Datos' cambio desde que se preparo el arreglo; no se toca")
    hist = nodo(wf, "Buscar Historial BD")
    wa = nodo(wf, "WhatsApp Meta")
    antes_n = len(wf["nodes"])

    prep["parameters"]["jsCode"] = io.open(os.path.join(AQUI, "codigo-nodos", "argos_preparar_datos.js"), encoding="utf-8").read()
    hist["parameters"]["url"] = io.open(os.path.join(AQUI, "codigo-nodos", "argos_url_historial.txt"), encoding="utf-8").read().strip()
    # n8n no admite continueOnFail y onError a la vez (el validador lo marca como error): se deja solo onError
    for n in (hist, wa):
        n.pop("continueOnFail", None)
    hist["onError"] = "continueRegularOutput"   # sin historial igual se avisa (y no se pierde el correo)
    wa["onError"] = "continueRegularOutput"     # un fallo del WhatsApp nunca frena el correo ni el guardado
    x, y = wa["position"]
    wa["position"] = [x + 224, y]
    wf["nodes"].append({
        "id": str(uuid.uuid4()), "name": IF_ARGOS, "type": "n8n-nodes-base.if", "typeVersion": 2.3,
        "position": [x, y],
        "parameters": {"conditions": {
            "options": {"version": 2, "leftValue": "", "caseSensitive": True, "typeValidation": "loose"},
            "conditions": [{"id": "primera-vez", "leftValue": "={{ $json.enviar_whatsapp === true }}",
                            "rightValue": True, "operator": {"type": "boolean", "operation": "true", "singleValue": True}}],
            "combinator": "and"}}})
    salidas = wf["connections"]["Preparar Datos"]["main"][0]
    assert [c["node"] for c in salidas].count("WhatsApp Meta") == 1
    for c in salidas:
        if c["node"] == "WhatsApp Meta":
            c["node"] = IF_ARGOS
    wf["connections"][IF_ARGOS] = {"main": [[{"node": "WhatsApp Meta", "type": "main", "index": 0}], []]}

    if not APLICAR:
        print("[ARGOS] REVISAR: plantilla alerta_argos; WhatsApp solo la 1a vez de cada error (via "
              f"'{IF_ARGOS}'); historial tambien con trigger.error; correo y guardado siempre")
        return
    print("[ARGOS] respaldo:", respaldar(api("GET", f"/workflows/{ARGOS}")))
    pub = escribir_y_publicar(wf)
    assert len(pub["nodes"]) == antes_n + 1
    assert "alerta_argos" in normal(nodo(pub, "Preparar Datos")["parameters"]["jsCode"])
    assert "trigger?.error" in nodo(pub, "Buscar Historial BD")["parameters"]["url"]
    assert nodo(pub, "WhatsApp Meta").get("onError") == "continueRegularOutput"
    destinos = sorted(c["node"] for c in pub["connections"]["Preparar Datos"]["main"][0])
    assert destinos == sorted([IF_ARGOS, "Enviar Email", "Guardar en BD"]), destinos
    assert pub["connections"][IF_ARGOS]["main"][0][0]["node"] == "WhatsApp Meta"
    print("[ARGOS] OK publicado: usa la plantilla, WhatsApp una vez por error, correo y guardado siempre")


# cada workflow se aplica apenas esten APROBADAS sus propias plantillas
TRABAJOS = [
    ("72h", ["recordatorio_cita_72h"], lambda: recordatorio("72h", RECORDATORIOS["72h"])),
    ("24h", ["recordatorio_cita_24h"], lambda: recordatorio("24h", RECORDATORIOS["24h"])),
    ("1h", ["recordatorio_cita_1h"], lambda: recordatorio("1h", RECORDATORIOS["1h"])),
    ("8AM", ["recordatorio_seguimiento_hoy"],
     lambda: seguimiento("8AM", "MchisrkbuXDOqKvQ", "Prepare WA Data", "prepare_8am.js")),
    ("8PM", ["recordatorio_seguimiento_manana"],
     lambda: seguimiento("8PM", "uU39aWmvJ4gGj8T2", "Prepare WA Data", "prepare_8pm.js")),
    ("Followup", ["aviso_demo", "aviso_reunion_prospecto", "aviso_reunion_seguimiento"],
     lambda: seguimiento("Followup", "daSz1OJSeaQckDVy5q0uS", "Prepare WA Message", "prepare_followup.js")),
    ("ARGOS", ["alerta_argos"], argos),
]

print("MODO:", "APLICAR (escribe en PROD)" if APLICAR else "REVISAR (no escribe nada)")
bot()
estados = plantillas_aprobadas() if APLICAR else {}
pendientes = []
for label, requeridas, aplicar in TRABAJOS:
    if APLICAR:
        mal = {n: estados.get(n, "NO EXISTE") for n in requeridas if estados.get(n) != "APPROVED"}
        if mal:
            print(f"[{label}] esperando a Meta: {mal}")
            pendientes.append(label)
            continue
    aplicar()
print("PENDIENTES:", ",".join(pendientes) if pendientes else "ninguno")
