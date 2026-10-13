"""Pruebas locales del aplicador (sin n8n ni red): python pruebas/test_aplicar_anotar.py
- agrega una sola rama, no toca nada mas, es idempotente y dibuja el nodo donde corresponde;
- las expresiones, evaluadas en Node con datos de ejemplo, arman el cuerpo que espera WordPress;
- los tipos que mandan los flujos son los mismos que acepta whatsapp-recordatorios.php."""
import copy
import importlib.util
import json
import os
import re
import subprocess
import sys

sys.stdout.reconfigure(encoding="utf-8")
AQUI = os.path.dirname(os.path.abspath(__file__))
RAIZ = os.path.abspath(os.path.join(AQUI, "..", "..", "..", ".."))
spec = importlib.util.spec_from_file_location("aplicador", os.path.join(AQUI, "..", "aplicar_anotar_envio.py"))
A = importlib.util.module_from_spec(spec)
spec.loader.exec_module(A)

fallas = 0


def ok(cond, msg):
    global fallas
    print(("ok   " if cond else "FALLA ") + msg)
    fallas += 0 if cond else 1


def n(nombre, x, y):
    return {"id": nombre, "name": nombre, "type": "n8n-nodes-base.noOp", "typeVersion": 1, "position": [x, y], "parameters": {}}


def c(*destinos):
    return [{"node": d, "type": "main", "index": 0} for d in destinos]


# Estructura de cada flujo como esta publicada (nombres, salidas y posiciones), sin datos de PROD.
FIXTURES = {
    "Citas 72h": ([n("Build WhatsApp Body", 224, 0), n("Send WhatsApp 72h", 448, 0), n("Mark 72h Sent", 672, 0), n("Mark 72h Sent (fallido)", 672, 180)],
                  {"Build WhatsApp Body": {"main": [c("Send WhatsApp 72h")]}, "Send WhatsApp 72h": {"main": [c("Mark 72h Sent"), c("Mark 72h Sent (fallido)")]}}),
    "Citas 24h": ([n("Build WhatsApp Body", -32, 640), n("Send WhatsApp 24h", 192, 640), n("Mark 24h Sent", 416, 640), n("Mark 24h Sent (fallido)", 416, 820)],
                  {"Build WhatsApp Body": {"main": [c("Send WhatsApp 24h")]}, "Send WhatsApp 24h": {"main": [c("Mark 24h Sent"), c("Mark 24h Sent (fallido)")]}}),
    "Citas 1h": ([n("Build WhatsApp Body", 2016, 1104), n("Send WhatsApp 1h", 2240, 1104), n("Mark 1h Sent", 2464, 1104), n("Mark 1h Sent (fallido)", 2464, 1284)],
                 {"Build WhatsApp Body": {"main": [c("Send WhatsApp 1h")]}, "Send WhatsApp 1h": {"main": [c("Mark 1h Sent"), c("Mark 1h Sent (fallido)")]}}),
    "Seguimiento 8AM": ([n("Split In Batches", 384, 160), n("Prepare WA Data", 608, 160), n("Send WhatsApp", 832, 160), n("Mark as Sent", 1040, 160), n("Wait 5s Rate Limit", 1264, 160)],
                        {"Split In Batches": {"main": [[], c("Prepare WA Data")]}, "Prepare WA Data": {"main": [c("Send WhatsApp")]},
                         "Send WhatsApp": {"main": [c("Mark as Sent")]}, "Mark as Sent": {"main": [c("Wait 5s Rate Limit")]},
                         "Wait 5s Rate Limit": {"main": [c("Split In Batches")]}}),
    "Seguimiento 8PM": ([n("Split In Batches", 768, 112), n("Prepare WA Data", 992, 112), n("Send WhatsApp", 1216, 112), n("Mark as Sent", 1424, 112), n("Wait 5s Rate Limit", 1648, 112)],
                        {"Split In Batches": {"main": [[], c("Prepare WA Data")]}, "Prepare WA Data": {"main": [c("Send WhatsApp")]},
                         "Send WhatsApp": {"main": [c("Mark as Sent")]}, "Mark as Sent": {"main": [c("Wait 5s Rate Limit")]},
                         "Wait 5s Rate Limit": {"main": [c("Split In Batches")]}}),
    "Followup": ([n("Prepare WA Message", -528, 256), n("Send WhatsApp", -80, 160), n("WA Sent OK?", 144, 160), n("Mark WA Sent in WP", 368, 64),
                  n("Respond Error", 368, 256), n("Respond Success", 592, 64)],
                 {"Send WhatsApp": {"main": [c("WA Sent OK?")]}, "WA Sent OK?": {"main": [c("Mark WA Sent in WP"), c("Respond Error")]},
                  "Mark WA Sent in WP": {"main": [c("Respond Success")]}}),
}

# ---------- 1) Transformacion ----------
for t in A.TRABAJOS:
    nodes, con = FIXTURES[t["label"]]
    antes_n, antes_c = copy.deepcopy(nodes), copy.deepcopy(con)
    r = A.agregar(nodes, con, t)
    ok(r is not None, f"{t['label']}: agrega la rama")
    nn, cc = r
    ok(nodes == antes_n and con == antes_c, f"{t['label']}: no modifica lo que recibe")
    nuevos = [x for x in nn if x["name"] == A.NOMBRE]
    ok(len(nuevos) == 1 and len(nn) == len(nodes) + 1, f"{t['label']}: un solo nodo nuevo")
    ok([x for x in nn if x["name"] != A.NOMBRE] == nodes, f"{t['label']}: los demas nodos quedan iguales")
    esperado = copy.deepcopy(con)
    salidas = esperado.setdefault(t["desde"], {}).setdefault("main", [])
    while len(salidas) <= t["salida"]:
        salidas.append([])
    salidas[t["salida"]] = (salidas[t["salida"]] or []) + c(A.NOMBRE)
    ok(cc == esperado, f"{t['label']}: solo se agrega la conexion {t['desde']}[{t['salida']}] -> {A.NOMBRE}")
    ok(not cc.get(A.NOMBRE), f"{t['label']}: la rama nueva termina en el nodo nuevo")
    junto = next(x for x in nodes if x["name"] == t["junto"])["position"]
    pos = nuevos[0]["position"]
    if t["donde"] == "arriba":
        ok(pos[0] == junto[0] and pos[1] < junto[1], f"{t['label']}: va arriba de '{t['junto']}' (corre primero en orden v1)")
    else:
        ok(pos[1] == junto[1] and pos[0] > junto[0], f"{t['label']}: va despues de '{t['junto']}'")
    nodo = nuevos[0]
    ok(nodo.get("onError") == "continueRegularOutput" and nodo["parameters"]["options"]["response"]["response"]["neverError"] is True,
       f"{t['label']}: si WordPress falla, el flujo sigue (onError + neverError)")
    ok(nodo["parameters"]["url"] == "https://automatizatech.cl/wp-json/at/v1/whatsapp-envio" and nodo["credentials"] == A.CREDENCIAL,
       f"{t['label']}: ruta de WordPress y credencial de cabecera")
    ok(A.agregar(nn, cc, t) is None, f"{t['label']}: idempotente (la segunda vez no agrega nada)")

# ---------- 2) Expresiones evaluadas en Node ----------
JS = r"""
const casos = JSON.parse(process.argv[1]);
const salida = casos.map(k => {
  const $json = k.json;
  const $ = (nombre) => ({ item: { json: k.nodos[nombre] } });
  const cuerpo = k.expr.replace(/^=\{\{\s*/, '').replace(/\s*\}\}$/, '');
  try { return JSON.parse(eval(cuerpo)); } catch (e) { return { error: String(e) }; }
});
console.log(JSON.stringify(salida));
"""
enviado = {"messaging_product": "whatsapp", "contacts": [{"wa_id": "56900000000"}], "messages": [{"id": "wamid.PRUEBA123"}]}
por_label = {t["label"]: t for t in A.TRABAJOS}
casos = [
    ("Citas 72h", {"json": enviado, "nodos": {"Build WhatsApp Body": {"id": 99}}}, {"wamid": "wamid.PRUEBA123", "tipo": "cita_72h", "id": 99}),
    ("Citas 24h", {"json": enviado, "nodos": {"Build WhatsApp Body": {"id": "99"}}}, {"wamid": "wamid.PRUEBA123", "tipo": "cita_24h", "id": "99"}),
    ("Citas 1h", {"json": enviado, "nodos": {"Build WhatsApp Body": {"id": 7}}}, {"wamid": "wamid.PRUEBA123", "tipo": "cita_1h", "id": 7}),
    ("Citas 24h", {"json": {"error": "x"}, "nodos": {"Build WhatsApp Body": {"id": 7}}}, {"wamid": "", "tipo": "cita_24h", "id": 7}),
    ("Seguimiento 8AM", {"json": enviado, "nodos": {"Prepare WA Data": {"meeting_id": 12}}}, {"wamid": "wamid.PRUEBA123", "tipo": "seguimiento_8am", "id": 12}),
    ("Seguimiento 8PM", {"json": enviado, "nodos": {"Prepare WA Data": {"meeting_id": 12}}}, {"wamid": "wamid.PRUEBA123", "tipo": "seguimiento_8pm", "id": 12}),
    ("Followup", {"json": {"ok": True}, "nodos": {"Send WhatsApp": enviado, "Prepare WA Message": {"meetingType": "demo", "meeting_id": 5}}},
     {"wamid": "wamid.PRUEBA123", "tipo": "reunion_demo", "id": 5}),
    ("Followup", {"json": {}, "nodos": {"Send WhatsApp": enviado, "Prepare WA Message": {"meetingType": "seguimiento_prospecto", "meeting_id": 6}}},
     {"wamid": "wamid.PRUEBA123", "tipo": "reunion_prospecto", "id": 6}),
    ("Followup", {"json": {}, "nodos": {"Send WhatsApp": enviado, "Prepare WA Message": {"meetingType": "seguimiento", "meeting_id": 8}}},
     {"wamid": "wamid.PRUEBA123", "tipo": "reunion_seguimiento", "id": 8}),
    ("Followup", {"json": {}, "nodos": {"Send WhatsApp": {"error": "x"}, "Prepare WA Message": {"meeting_id": 9}}},
     {"wamid": "", "tipo": "reunion_seguimiento", "id": 9}),
]
entrada = [dict(expr=A.expresion(por_label[lab]), **ctx) for lab, ctx, _ in casos]
res = subprocess.run(["node", "-e", JS, json.dumps(entrada)], capture_output=True, text=True, encoding="utf-8")
obtenidos = json.loads(res.stdout) if res.returncode == 0 and res.stdout.strip() else [{"error": res.stderr[:300]}] * len(casos)
for (lab, _ctx, esperado), obtenido in zip(casos, obtenidos):
    ok(obtenido == esperado, f"expresion {lab}: {json.dumps(obtenido, ensure_ascii=False)}")

# ---------- 3) Los tipos coinciden con los de WordPress ----------
php = open(os.path.join(RAIZ, "wp-content", "themes", "automatiza-tech", "inc", "cierre-cliente", "whatsapp-recordatorios.php"), encoding="utf-8").read()
tipos_wp = set(re.findall(r"'([a-z0-9_]+)'\s*=>\s*\['tabla'", php))
tipos_flujos = {e["tipo"] for _l, _c, e in casos}
ok(tipos_wp == tipos_flujos and len(tipos_wp) == 8, f"los 8 tipos de los flujos son los que acepta WordPress ({sorted(tipos_wp)})")

print("\nTODO OK" if not fallas else f"\n{fallas} FALLAS")
sys.exit(1 if fallas else 0)
