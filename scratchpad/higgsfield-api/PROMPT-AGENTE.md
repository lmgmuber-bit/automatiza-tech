# Prompt para que un agente use Higgsfield por API

Copiar y pegar en Claude, Codex, OpenCode o Copilot al inicio de una tarea de imagen o video.

```text
Necesito generar imágenes/videos con Higgsfield. Reglas para esta tarea:

1. NO uses el MCP ni el conector de Higgsfield: el plan no tiene créditos. Usa la API REST
   (api.higgsfield.ai) con el cliente del repo automatiza-tech:
   python scratchpad/higgsfield-api/hf_api.py <comando>
   Lee primero scratchpad/higgsfield-api/README.md y, si tienes la skill higgsfield-studio,
   references/api-rest.md (mapa de modelos MCP -> API, precios, límites).

2. Cuenta: la del rótulo "API KEY Higgsfield + N8N" del archivo de claves (--cuenta n8n, es
   la opción por defecto). No uses el par HF_API_KEY_ID del bloque "API N8N": es otra cuenta
   y está en $0. Nunca copies, imprimas ni pegues las claves en ningún archivo, log o chat.

3. Antes de gastar: elige el modelo con `models --filtro <texto>`, calcula el costo con
   `estimar <slug> --segundos N` o `--imagenes N` (precio de lista, la cuenta tiene 15 % de
   descuento) y muéstrame el total del plan completo. Corre `generar <slug> --prompt "..."
   --param k=v` SIN --si para que la API valide el cuerpo gratis. Solo cuando yo diga "ok"
   corres el mismo comando con --si. Un "ok" cubre ese plan, no lo que descubras después.

4. Modelos por defecto: imagen -> higgsfield-ai/soul/v2/standard ($0,0032/img, 16:9 o 9:16,
   720p/1080p); video -> kling-video/v3.0/std/image-to-video ($0,063/s) partiendo de una
   imagen aprobada, nunca iterando en video. Sin texto ni logos dentro de la generación:
   se agregan en post (ffmpeg). Todo texto visible en español de Chile y el logo AT como
   marca de agua pequeña arriba a la izquierda, según las reglas de video de AutomatizaTech.

5. Después de generar: `bajar <request_id> --a <archivo>` de inmediato, porque los archivos
   duran 7 días en el CDN. Guarda el request_id y el costo estimado en el reporte final.

6. Límites que no debes intentar rodear: la API no tiene saldo consultable ni get_cost, y no
   tiene Nano Banana, Veo, 3D, voces, lipsync, upscale ni quitar fondo. Si la tarea necesita
   algo de eso, dímelo y proponme budgetpixel o ElevenLabs en vez de improvisar. Un 403
   "not_enough_credits" es saldo cero: detente y avísame. Un 403 "error code: 1010" es
   Cloudflare por el User-Agent, no un problema de claves.
```
