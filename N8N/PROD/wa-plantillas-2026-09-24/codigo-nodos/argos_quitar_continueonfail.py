"""Quita el 'continueOnFail' heredado de "Buscar Historial BD" en ARGOS: ya tiene onError=continueRegularOutput
(mismo comportamiento) y n8n no admite las dos a la vez. Respaldo -> cambio -> PUT -> publicar -> verificar."""
import copy
import io
import json
import sys
import time
import urllib.error
import urllib.request

sys.stdout.reconfigure(encoding="utf-8")
KEY = json.load(io.open(r"C:/Users/luis_/.claude.json", encoding="utf-8"))["mcpServers"]["n8n-mcp"]["env"]["N8N_API_KEY"]
API = "https://n8n-n8n.kchiba.easypanel.host/api/v1"
WID = "p7ISUf0J5GycHscc"
RECHAZADOS = {"timeSavedMode", "availableInMCP", "binaryMode"}


def api(method, path, body=None):
    data = json.dumps(body).encode("utf-8") if body is not None else None
    req = urllib.request.Request(API + path, data=data, method=method,
                                 headers={"X-N8N-API-KEY": KEY, "content-type": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=120) as r:
            raw = r.read()
            return json.loads(raw) if raw else {}
    except urllib.error.HTTPError as e:
        raise RuntimeError(f"{method} {path} -> HTTP {e.code}: {e.read().decode('utf-8', 'replace')[:400]}")


wf = api("GET", f"/workflows/{WID}")
assert wf.get("versionId") == wf.get("activeVersionId"), "borrador distinto de lo publicado: no se toca"
antes = copy.deepcopy(wf)
nodo = next(n for n in wf["nodes"] if n["name"] == "Buscar Historial BD")
print("antes:", {k: nodo.get(k) for k in ("continueOnFail", "onError")})
if "continueOnFail" not in nodo:
    print("ya no tiene continueOnFail: sin cambios")
    sys.exit(0)
assert nodo.get("onError") == "continueRegularOutput", "sin onError no se puede quitar continueOnFail"

sello = time.strftime("%Y%m%d-%H%M%S")
io.open(f"respaldo_{WID}_{sello}.json", "w", encoding="utf-8").write(json.dumps(antes, ensure_ascii=False, indent=1))
print(f"respaldo: respaldo_{WID}_{sello}.json")

del nodo["continueOnFail"]
settings = {k: v for k, v in (wf.get("settings") or {}).items() if k not in RECHAZADOS}
api("PUT", f"/workflows/{WID}", {"name": wf["name"], "nodes": wf["nodes"], "connections": wf["connections"], "settings": settings})
api("POST", f"/workflows/{WID}/activate")

leido = api("GET", f"/workflows/{WID}")
pub = leido.get("activeVersion") or leido
assert leido.get("versionId") == leido.get("activeVersionId"), "no quedo publicado"
assert (leido.get("settings") or {}) == (antes.get("settings") or {}), "cambiaron los ajustes"
b = next(n for n in pub["nodes"] if n["name"] == "Buscar Historial BD")
assert "continueOnFail" not in b and b.get("onError") == "continueRegularOutput"


def sin(ns, quitar_cof):
    out = []
    for n in ns:
        n = {k: v for k, v in n.items() if not (quitar_cof and n["name"] == "Buscar Historial BD" and k == "continueOnFail")}
        out.append(json.dumps(n, sort_keys=True))
    return sorted(out)


assert sin(pub["nodes"], False) == sin(antes["nodes"], True), "cambio algo mas que continueOnFail"
assert json.dumps(pub["connections"], sort_keys=True) == json.dumps(antes["connections"], sort_keys=True)
print("OK publicado: solo se quito continueOnFail; nodos, conexiones y ajustes identicos")
