# Despliegue de `propuesta-renderer` en Easypanel

Verificado en la consola el 2026-09-20 (Easypanel v2.24.0). No estaba documentado.

## Servicio

- Proyecto `n8n`, servicio `propuesta-renderer`, tipo App.
- URL interna que usa n8n: `https://n8n-propuesta-renderer.kchiba.easypanel.host/render`
  (nodo "Renderizar Propuesta" del workflow `APuTGmusbjLAJ74w`).
- Salud: `GET /health` -> `{"status":"ok"}`.

## Cómo se sube el código

- **Source = Upload** (zip arrastrado a la consola), **Build = Dockerfile**.
  No está conectado a GitHub ni a un registro de imágenes: cada versión se
  sube a mano como zip. La rama `feat/propuesta-renderer` solo existe en local.
- El zip lleva el contenido de `renderer/` en la raíz (Dockerfile, package.json,
  src/, .dockerignore). Se genera desde git para no arrastrar `node_modules`
  ni `.env`:

  ```bash
  git archive --format=zip -o propuesta-renderer-<commit>.zip <commit>:renderer
  ```

- Pasos: Source -> Upload -> soltar el zip -> Easypanel construye y despliega.
  Si no arranca solo, botón **Deploy**. Luego mirar Deployments y los logs del
  Overview hasta ver `propuesta-renderer listening on :3000`.
- Variables de entorno (pestaña Environment): `PORT`, `PUBLIC_DIR`, `BASE_URL`,
  `HF_API_KEY_ID`, `HF_API_KEY_SECRET`. Al cambiarlas hay que volver a pulsar
  Deploy para que el contenedor las reciba.

## Rollback

Subir el zip del commit anterior por el mismo camino. El de la versión que
corrió del 2026-08-31 al 2026-09-20 es `588219d` (Soul Standard).

## Historial

- 2026-08-31 `588219d`: Soul Standard en `platform.higgsfield.ai`. Los logs de
  septiembre muestran `higgsfield polling timed out` y `image phase budget
  exhausted` en todas las láminas: las propuestas salían sin fotos.
- 2026-09-20 `65ae8b3`: Soul 2 en `api.higgsfield.ai` ($0,0032/imagen en vez
  de $0,0938). Pendiente de subir al cierre de esta nota.
