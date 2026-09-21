# `propuesta-renderer`: qué es, cómo se despliega y cómo se verifica

Escrito el 2026-09-20 después de redesplegarlo. Antes no estaba documentado y
costó reconstruir el contexto: leer esto entero antes de tocar el servicio.

## 1. Qué es y quién lo llama

- Microservicio Node.js (Express + Playwright) que arma la presentación de una
  propuesta comercial de AutomatizaTech: HTML público + PDF, con una foto por
  lámina generada por IA.
- Lo llama n8n: workflow **"Propuesta AutomatizaTech (Con Tasa CLP)"**
  (`APuTGmusbjLAJ74w`), nodo **"Renderizar Propuesta"** (HTTP POST) hacia
  `https://n8n-propuesta-renderer.kchiba.easypanel.host/render`. El nodo
  siguiente, "Guardar Presentación", escribe `view_url` y `pdf_url` en la
  tabla `automatiza_propuestas` de WordPress.
- Ejemplo real: la propuesta "Academia de Béisbol X / Jeffer García" del
  2026-08-16 fue el caso de prueba original (ver
  `Docs/superpowers/plans/2026-08-16-propuesta-renderer.md`).

## 2. Dónde vive el código

- Carpeta `renderer/` de este repositorio, **solo en las ramas**
  `feat/propuesta-renderer` (base) y `claude/renderer-soul2` (Soul 2). Ninguna
  está en GitHub: si se pierde el clon local, la fuente de verdad es el zip
  que hay en Easypanel.
- Archivos: `src/index.js` (arranque), `src/server.js` (`GET /health`,
  `POST /render`, estáticos en `/p/`), `src/higgsfield.js` (cliente de
  imágenes), `src/render.js` y `src/template.js` (HTML y PDF), `src/schema.js`
  (validación del payload). Pruebas en `test/` con `npm test` (node:test).

## 3. Imágenes: Higgsfield por API REST, no por MCP

- Endpoint: `https://api.higgsfield.ai/higgsfield-ai/soul/v2/standard` (Soul 2,
  desde el 2026-09-20; antes `platform.higgsfield.ai/higgsfield-ai/soul/standard`,
  Soul Standard). Cabecera `Authorization: Key <ID>:<SECRET>`. Cuerpo
  `{prompt, aspect_ratio: "16:9", resolution: "720p"}`.
- Precio de lista en `open.higgsfield.ai/pricing`: Soul 2 $0,0032 por imagen;
  Soul Standard $0,0938.
- Credenciales: par `HF_API_KEY_ID` / `HF_API_KEY_SECRET`. En el archivo de
  claves de Luis (fuera del repo) están bajo el bloque **"API N8N"**. Ojo: el
  archivo tiene **otro** par de Higgsfield, rotulado "API KEY Higgsfield +
  N8N", que también autentica pero no es el que usa el contenedor. Las dos
  cuentas se administran en `open.higgsfield.ai` (antes `cloud.`), y el saldo
  solo se ve ahí con sesión: la API no tiene endpoint de saldo.
- Una imagen a la vez (`CONCURRENCY = 1`), 2 intentos por lámina, 90 s de
  espera por imagen y 210 s de presupuesto total: la lámina que no alcanza
  queda con el degradado de marca. Ver los comentarios de `src/higgsfield.js`.

## 4. Cómo se despliega (Easypanel)

- Consola: `https://kchiba.easypanel.host`, proyecto `n8n`, servicio
  `propuesta-renderer` (tipo App). Easypanel v2.24.0 al 2026-09-20.
- **Source = Upload** (zip arrastrado a la consola), **Build = Dockerfile**.
  No hay conexión a GitHub ni a un registro de imágenes.
- Generar el zip desde git para no arrastrar `node_modules` ni `.env`:

  ```bash
  git archive --format=zip -o propuesta-renderer-<commit>.zip <commit>:renderer
  ```

  El zip lleva `Dockerfile`, `package.json`, `src/`, `.dockerignore` en la raíz.
- Pasos: Source → Upload → soltar el zip → Easypanel construye y despliega
  solo (aparece una fila nueva en Deployments). Si no arranca, botón **Deploy**.
- Un agente **no puede** soltar el zip por automatización de navegador (el
  clasificador de Claude Code lo bloquea): se le entrega el zip a Luis y lo
  suelta él.
- Variables de entorno (pestaña Environment): `PORT=3000`,
  `PUBLIC_DIR=/app/public`, `BASE_URL`, `HF_API_KEY_ID`, `HF_API_KEY_SECRET`.
  Después de cambiarlas hay que pulsar Deploy para que el contenedor las tome.
- Rollback: subir el zip del commit anterior por el mismo camino.

## 5. Cómo se verifica

1. `curl https://n8n-propuesta-renderer.kchiba.easypanel.host/health` →
   `{"status":"ok"}`.
2. Logs en Overview: debe aparecer `propuesta-renderer listening on :3000`
   sin líneas `higgsfield ... failed` nuevas.
3. Prueba real de una imagen (cuesta ≈ $0,003):

   ```bash
   curl -s -m 300 -X POST -H "Content-Type: application/json" \
     https://n8n-propuesta-renderer.kchiba.easypanel.host/render \
     -d '{"unique_id":"prueba-<fecha>","client_name":"Prueba","company_name":"AutomatizaTech QA",
          "challenge_title":"t","challenge_text":"t","solution_title":"t","solution_text":"t",
          "benefits":[{"title":"t","text":"t"}],"how_it_works":[{"step_title":"t","step_text":"t"}],
          "pricing_rows":[{"service":"t","price_usd":0,"price_clp":0}],"pricing_note":"t",
          "next_steps":["t"],"image_briefs":[{"slide":"cover","prompt":"<brief fotográfico>"}]}'
   ```

   Devuelve `view_url` y `pdf_url`. Abrir `view_url` y comprobar que la
   portada tenga `url('https://d3u0tzju9qaucj.cloudfront.net/…')` y no solo
   `linear-gradient(...)`: si es degradado, la imagen falló y el motivo está en
   los logs.

## 6. Historial

- 2026-08-31, commit `588219d`: Soul Standard. En septiembre los logs muestran
  `higgsfield polling timed out` e `image phase budget exhausted` en todas las
  láminas: las propuestas salían sin fotos.
- 2026-09-20, commit `65ae8b3` (+ esta nota, `bae23b1`): Soul 2 en
  `api.higgsfield.ai`. Zip `propuesta-renderer-SOUL2-65ae8b3.zip` subido por
  Luis; `/health` 200; prueba real `prueba-soul2-20260920` respondió 200 en
  85 s con foto PNG de 2,7 MB en la portada. La página de esa prueba sigue
  publicada en `/p/prueba-soul2-20260920/`.
- Pendiente: medir cuánto tarda Soul 2 por imagen dentro del contenedor. Si una
  lámina ronda los 60 s, seis en serie no caben en los 210 s del presupuesto y
  las últimas seguirán cayendo al degradado; ahí conviene subir `CONCURRENCY`
  o el presupuesto, con medición previa.
