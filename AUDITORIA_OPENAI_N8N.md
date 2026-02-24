# Auditoría de Consumo OpenAI en Flujos N8N
Fecha: 24 Enero 2026
Estado: ✅ Decisión Tomada

## Decisión Final
- **Flujos existentes:** NO se modifican. Siguen funcionando como hasta ahora.
- **Flujos nuevos:** Deben usar el template con tracking para registrar consumo por cliente.

## Archivos del Sistema de Tracking

| Archivo | Propósito |
|---------|-----------|
| `openai-controller.php` | Clase PHP con lógica de costos y registro |
| `api-chat-proxy.php` | Endpoint para N8N (reemplaza nodo OpenAI) |
| `admin-ai-dashboard.php` | Panel visual de consumo |
| `sql/setup_ai_usage.sql` | SQL para crear la tabla |
| `N8N/TEMPLATES/GUIA_TRACKING_CONSUMO_OPENAI.md` | Guía completa de implementación |
| `N8N/TEMPLATES/node-openai-proxy-template.json` | Template listo para copiar en N8N |

---

## 1. Flujos Críticos (Nivel Producción)
Estos archivos están en `N8N/PROD/` y son los más urgentes de intervenir.

| Archivo | Tipo de Conexión | Nodos Afectados | Acción Requerida |
|---------|------------------|-----------------|------------------|
| **WhatsApp_Tech_Principal.json** | LangChain Model | `Cerebro GPT-4o` (lmChatOpenAi) | Cambiar Base URL en Credencial |
| **propuesta-gamma-workflow.json** | Direct Request | `OpenAI (Cerebro)`, `OpenAI (Personalidad Bot)` | **Reemplazar por Nodo HTTP Proxy** |
| **Tech_Agente_Web_OPTIMIZADO.json**| LangChain Model | `Cerebro` (lmChatOpenAi) | Cambiar Base URL en Credencial |
| **WhatsApp_Tech_Buffer_Agent.json**| LangChain Model | `OpenAI Chat Model` | Cambiar Base URL en Credencial |
| **Argos detección de Errores N8N.json**| LangChain Model | `Chat OpenAI` | Cambiar Base URL en Credencial |

## 2. Flujos en Desarrollo / Root
Estos archivos están en la raíz `N8N/` y parecen ser versiones de desarrollo o backups.

- `agente-ia-demo-dinamico-v2.json`
- `agente-ia-demo-dinamico.json`
- `CM-Batalla-Estilos-Automatizatech.json`
- `Tech_Agente_Web_OPTIMIZADO.json`
- `WhatsApp_Tech_Principal-Respaldo.json`

## 3. Plantillas
- `TEMPLATES/kellscapilar/...`
- `TEMPLATES/WhatsApp_Bot_Demo_Template.json` (Este tiene nodos grandes de LangChain)

---

## Análisis Técnico y Soluciones

### CASO A: Nodos "OpenAI" Directos (Ej. Propuesta Gamma)
**El Problema:** Usan el nodo básico "OpenAI" para una tarea única (ej. "Resume esto").
**La Solución:** Se debe eliminar ese nodo y poner un nodo **HTTP Request** que apunte a `https://tu-dominio/api-chat-proxy.php`.
**Archivos Afectados:** `propuesta-gamma-workflow.json`.

### CASO B: Nodos "LangChain Model" (Ej. Agentes WhatsApp)
**El Problema:** Estos nodos (`lmChatOpenAi`) no hacen la petición ellos mismos, sino que "alimentan" a un Agente Inteligente. Son más complejos de reemplazar por un HTTP Request simple porque el Agente espera un objeto de modelo, no un JSON.
**La Solución (Menos Invasiva):**
En lugar de editar 15 archivos, la solución correcta es **editar la Credencial en N8N**.
1. En N8N, ve a **Credentials** > **OpenAi account** (usada por todos estos flujos).
2. Busca la opción **Ignore SSL Issues** (si es servidor local) o **Base URL**.
3. Si N8N permite cambiar la `Base URL` en la credencial, cámbiala a: `https://tu-dominio/api-chat-proxy-v1`.
4. *Nota:* Para esto, tu PHP debe comportarse exactamente como la API de OpenAI (rutas `/v1/chat/completions`).

### Recomendación Inmediata
Haremos el cambio manual hoy solo en **`propuesta-gamma-workflow.json`** ya que usa el método directo y es fácil de arreglar ya mismo vía edición de archivo.

Para los Agentes de WhatsApp (LangChain), recomiendo ajustar la credencial en el panel de N8N en lugar de editar los JSON, para evitar romper la lógica interna de los agentes.
