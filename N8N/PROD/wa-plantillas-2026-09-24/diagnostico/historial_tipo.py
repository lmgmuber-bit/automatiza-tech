"""Para cada version en git de los exports de recordatorios WhatsApp, dice que tipo de mensaje enviaban."""
import json
import re
import subprocess
import sys

sys.stdout.reconfigure(encoding="utf-8")
REPO = r"C:/wamp64/www/automatiza-tech"


def git(*args):
    return subprocess.run(["git", "-C", REPO, *args], capture_output=True, text=True, encoding="utf-8",
                          errors="replace").stdout


def tipo_envio(texto_json):
    try:
        wf = json.loads(texto_json)
    except Exception:
        return "json invalido"
    tipos = set()
    for n in wf.get("nodes", []):
        p = n.get("parameters", {})
        blob = (p.get("jsCode") or "") + (p.get("jsonBody") or "") + json.dumps(p.get("bodyParameters") or {})
        if "messaging_product" not in blob and "graph.facebook.com" not in str(p.get("url", "")):
            continue
        for t in re.findall(r"type['\"]?\s*:\s*['\"](template|interactive|text)['\"]", blob):
            tipos.add(t)
    return sorted(tipos) or "sin envio a WhatsApp"


archivos = git("log", "--all", "--name-only", "--format=", "--", "N8N/PROD/").split()
archivos = sorted({a for a in archivos if re.search(r"(?i)remind|recordatorio", a) and a.endswith(".json")})
for f in archivos:
    print(f"=== {f}")
    for linea in git("log", "--all", "--format=%h %ad", "--date=short", "--", f).splitlines():
        h, fecha = linea.split()
        contenido = git("show", f"{h}:{f}")
        print(f"   {fecha} {h}: {tipo_envio(contenido) if contenido else '(borrado en este commit)'}")

print()
print("=== commit 19b71ce (2025-12-17), primera aparicion de 'recordatorio_24h' ===")
print(git("show", "--stat", "--format=%h %ad %s", "--date=short", "19b71ce")[:1500])
