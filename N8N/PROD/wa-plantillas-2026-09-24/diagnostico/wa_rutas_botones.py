"""Traza en el bot publicado que hace cada salida de 'Button Action' (endpoints que llama),
y que marca el Followup segun el tipo de reunion. Solo lectura del respaldo."""
import io
import json
import re
import sys
from collections import deque

sys.stdout.reconfigure(encoding="utf-8")
raw = json.loads(io.open("bot_publicado_ahora.json", "rb").read())
wf = raw.get("activeVersion") or raw
nodes = {n["name"]: n for n in wf["nodes"]}
con = wf["connections"]
ba = nodes["Button Action"]
reglas = ba["parameters"]["rules"]["values"]
salidas = con["Button Action"]["main"]


def fix(s):
    try:
        return s.encode("utf-16", "surrogatepass").decode("utf-16")
    except Exception:
        return s


def resumen(n):
    p = n.get("parameters", {})
    t = n["type"].split(".")[-1]
    if t == "httpRequest":
        url = str(p.get("url", ""))
        body = fix(str(p.get("jsonBody", "")))
        return f"{p.get('method', 'GET')} {url[:120]}" + (f"  body={body[:140]}" if "graph.facebook" not in url and body else "")
    if t == "code":
        c = fix(p.get("jsCode", ""))
        urls = re.findall(r"https?://[^\s'\"`]+", c)
        return "code " + (f"urls={urls[:2]}" if urls else c[:100].replace("\n", " "))
    return t


interes = ["Confirmar Followup", "Reagendar Followup", "Cancelar Followup", "Nueva Fecha Followup",
           "Confirmar Recordatorio", "Cancelar Lead", "Reprogramar Lead"]
for i, r in enumerate(reglas):
    clave = r.get("outputKey", "")
    pref = r["conditions"]["conditions"][0]["rightValue"]
    if not any(k.lower() in clave.lower() for k in ["followup", "recordatorio", "lead", "reminder"]):
        continue
    print(f"\n### salida {i}: '{clave}'  (prefijo {pref})")
    vistos = set()
    q = deque([(x["node"], 1) for x in (salidas[i] if i < len(salidas) else [])])
    while q:
        nombre, prof = q.popleft()
        if nombre in vistos or prof > 7:
            continue
        vistos.add(nombre)
        n = nodes[nombre]
        print(f"   {'  ' * (prof - 1)}{nombre}  ->  {resumen(n)}")
        for out in con.get(nombre, {}).get("main", []):
            for c in out or []:
                q.append((c["node"], prof + 1))

fb = ba["parameters"].get("options", {}).get("fallbackOutput")
print(f"\nfallback de Button Action: {fb}; salidas totales conectadas: {len(salidas)} de {len(reglas)} reglas"
      f" -> {'la salida extra existe: ' + str([c['node'] for c in salidas[len(reglas)]]) if len(salidas) > len(reglas) else 'NO hay salida extra conectada (se pierde)'}")

# que marca el Followup segun el tipo
fu = json.loads(io.open("respaldo_daSz1OJSeaQckDVy5q0uS.json", "rb").read())
mk = [n for n in fu["nodes"] if n["name"] == "Mark WA Sent in WP"][0]
print("\nFollowup 'Mark WA Sent in WP' url:", mk["parameters"].get("url"))
