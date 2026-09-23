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

## 🔴 Trampas medidas el 2026-09-21 (image-to-video)

- **La API encola cualquier cuerpo que traiga las claves obligatorias, aunque los valores sean basura.**
  Kling 3.0 i2v con `image_url="validacion"` y con `aspect_ratio=abc` + `duration=7` entraron como
  trabajos reales (el primero terminó `failed`, sin cobro; del segundo se perdió el `request_id`
  porque el cliente no lo imprimía, ya corregido). **Kling no se deja cancelar** (`400 Request cannot
  be canceled`). Para validar gratis: omitir un campo obligatorio (`prompt` o `image_url`) → 400/422;
  para conocer el tipo de un campo, mandar otro tipo (`duration=abc` → 400 sin encolar). El cliente
  ya quita `prompt` y toda clave `*url*` cuando falta `--si`.
- **La imagen con los personajes de la temática spidey (`fondo-banner.jpg`, figuras tipo juguete) rebota
  `nsfw` a los 11 s** con dos prompts distintos (con y sin "kids"/"hero"): es la imagen, no el texto.
  Las imágenes sin personajes (`fondo-juego-ciudad.jpg`, telaraña de Soul sobre negro) pasan a la
  primera. Regla: tras 2 rechazos de una misma imagen, cambiar de fuente (video ya existente de la
  temática, imagen sin personajes), no reescribir el prompt. Los rechazos no se cobran.
- Kling 3.0 std por API entrega **720×1280 a 24 fps** (5,04 s); Soul 2 1080p 9:16 entrega 1152×2048.
- Las URL de `cumpleclick.com/app/themes/...` sirven como `image_url` (200 con cualquier User-Agent).
