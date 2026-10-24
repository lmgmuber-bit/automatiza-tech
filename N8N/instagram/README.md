# Asistente de mensajes directos de Instagram (n8n)

Diseño: `Docs/superpowers/specs/2026-10-22-asistente-instagram-design.md` (con los ajustes del 24-oct). Guía para Luis:
`Docs/METODO_AT/ASISTENTE-INSTAGRAM.md`.

## Flujos

| Flujo | Id | Qué hace |
|---|---|---|
| Agente Tech Instagram (PROD) | `nDOSJhtVUPEGYrv3` | Lo llama el Portal (`forward_to_n8n`) con cada directo del canal 2. Valida, junta las ráfagas, corre el agente y responde por `callback_url`. |
| Agente Tech Instagram · Herramientas | `9qMlHNKITu0OWUfU` | Sub-workflow de `ver_disponibilidad` y `agendar`. `agendar` llama a `PejBgr2LZQSAbbBy` (no se modifica). n8n solo lo ejecuta si está **activo**. |

Recorrido del principal: Webhook → Validar (canal 2, token de 64 hex, `callback_url` de automatizatech.cl) → ¿texto?
→ ráfaga en Redis (`ig:rafaga:<IGSID>`, espera 6 s, solo sigue el último mensaje) → tipo de cambio → agente
(gpt-4o-mini, memoria Redis `ig:<IGSID>` de 30 mensajes y 30 días) → Preparar respuesta → callback al Portal.
Si no es texto, responde una vez cada 10 minutos «Por ahora leo solo texto».

- **Prompt:** el del agente web (`3A5Y1NPBxpTjhHdE`), leído en vivo por `aplicar.py`, sin el formulario `<<SHOW_DEMO_FORM>>`, más `prompt-canal-instagram.md` y, si la persona llegó desde un comentario, el bloque «De dónde viene esta persona» con la palabra y el privado que le enviamos (`contexto` del Portal).
- **Derivar:** la herramienta `derivar_a_persona` solo deja constancia; «Preparar respuesta» la detecta en los pasos del agente y manda un único callback `action: 'escalate'` con `motivo` y la despedida en `content`.
- **Agendar:** el agente consulta la agenda real (`/appointments-config` + `/check-availability`, horas en punto con 2 h de anticipación), confirma día, hora y correo, y agenda solo después del «sí». El sub-workflow de PROD vuelve a revisar WordPress y Google Calendar, crea el evento con Meet, guarda el lead (`session_id = ig:<IGSID>`, `source = instagram`) y manda el correo.

## Archivos

- `build_agente_instagram.py`: genera los dos JSON, sin red y sin secretos.
- `agente-tech-instagram.json`, `agente-tech-instagram-herramientas.json`: lo generado, con los marcadores `RUTA_WEBHOOK`, `ID_HERRAMIENTAS` y `PROMPT_AGENTE_WEB`.
- `aplicar.py`: crea o actualiza los dos flujos, siempre **inactivos**. La ruta del webhook y los ids quedan fuera del repo, en `~/respaldos/deploy-scripts/2026-10-24-asistente-instagram/estado.json`.
- `probar.py`: casos con JSON falso, en modo prueba (`prueba: true`: no llama al Portal y `agendar` responde como el de PROD sin crear la cita). Activa los dos flujos solo mientras corre.

```bash
python N8N/instagram/build_agente_instagram.py
python N8N/instagram/aplicar.py
python N8N/instagram/probar.py
```

## Pruebas (24-oct-2026)

`probar.py` completo: 10 casos y 23 comprobaciones, todas en verde. Casos: básico, precio (USD y CLP), fuera de tema, pedir
una persona, derivar en medio de un agendamiento (deriva y no agenda), feriado del 31-oct (no agenda), ráfaga de 3
mensajes (una sola respuesta con los tres), no texto (un solo aviso), agenda simulada (confirma antes de agendar y agenda una
vez) y quien llega desde el comentario MÉTODO.

Lo que corrigieron las pruebas:
- n8n no ejecuta un sub-workflow inactivo («Workflow is not active»): el de herramientas va activo en PROD.
- En la primera versión el agente agendó sin esperar el «sí»: la regla 4 del bloque de Instagram ahora lo prohíbe.
- Un texto de cita simulada distinto al real confundía al agente; la simulada copia el mensaje de éxito de PROD.

Costo medido en las pruebas: 49 llamadas a gpt-4o-mini, unos 2.600 tokens de entrada por llamada (US$0,02 en total).
