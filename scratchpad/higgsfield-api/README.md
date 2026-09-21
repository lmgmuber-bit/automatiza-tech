# Higgsfield por API REST (opcion cuando el MCP no tiene saldo)

Desde el 2026-09-21 la via para generar imagen y video en Higgsfield es la **API REST**
(`api.higgsfield.ai`, consola `open.higgsfield.ai`), porque los creditos del plan que usa el
MCP estan en cero. Cliente: `hf_api.py` (ver su docstring). Detalle completo, precios, mapa de
modelos MCP -> API y limites en `~/.claude/skills/higgsfield-studio/references/api-rest.md`
y en `Docs/ORCHESTRATION/CONEXIONES-Y-CREDENCIALES.md`.

Reglas: estimar con `estimar` o con `generar` sin `--si`, mostrar el costo a Luis, y recien
con su ok correr `generar ... --si`. La cuenta con saldo es la del rotulo
"API KEY Higgsfield + N8N" (`--cuenta n8n`, por defecto). El par del bloque "API N8N"
(`--cuenta api-n8n`) es otra cuenta y esta en $0.
