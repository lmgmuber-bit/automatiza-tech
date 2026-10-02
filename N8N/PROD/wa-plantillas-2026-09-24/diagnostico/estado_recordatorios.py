"""Muestra que tipo de mensaje usa la version PUBLICADA de cada workflow de WhatsApp."""
import io
import json
import sys
import urllib.request

sys.stdout.reconfigure(encoding="utf-8")
KEY = json.load(io.open(r"C:/Users/luis_/.claude.json", encoding="utf-8"))["mcpServers"]["n8n-mcp"]["env"]["N8N_API_KEY"]
API = "https://n8n-n8n.kchiba.easypanel.host/api/v1"
IDS = sys.argv[1:] or ["KFqFTgc0o4bOe0xv", "cbFi7tpGGn7pEDnr", "ljNN7NedbUHzytwY"]
for wid in IDS:
    d = json.load(urllib.request.urlopen(urllib.request.Request(f"{API}/workflows/{wid}", headers={"X-N8N-API-KEY": KEY}), timeout=60))
    pub = d.get("activeVersion") or d
    codigo = " ".join(n.get("parameters", {}).get("jsCode", "") for n in pub["nodes"])
    tipo = "template" if "type: 'template'" in codigo else ("interactive" if "interactive" in codigo else "?")
    print(f"  {d['name'][:60]:<60} publicado: {tipo}  (borrador==publicado: {d.get('versionId') == d.get('activeVersionId')})")
