"""Llamadas a la API pública del n8n de AT, reutilizando la lectura de la API key de propuestas-v3/deploy.py.

La key se lee de ~/.claude.json (config del conector n8n-mcp) y nunca se imprime.
"""
import os, sys

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'propuestas-v3'))
from deploy import api, call, AT_N8N_HOST  # noqa: E402,F401

WEBHOOK_BASE = f'https://{AT_N8N_HOST}/webhook/'
