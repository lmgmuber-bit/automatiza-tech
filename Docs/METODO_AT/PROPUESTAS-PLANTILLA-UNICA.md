# Propuestas comerciales: una sola plantilla

> Regla de Luis (2026-09-23): **toda propuesta de cliente se arma con la plantilla del
> `propuesta-renderer`**, con la misma estructura y los mismos controles que la de Jeffer García
> (propuesta 42, `ver-presentacion.php?id=EY2U5YW7zRO6`). No se vuelve a Gamma ni se diseña una
> presentación nueva: por cliente cambian los textos, los precios y las fotos, nada más.

Aplica a Claude, Codex, OpenCode y Copilot. La skill `at-gamma-proposal` conserva su nombre por los
disparadores, pero su salida es el JSON de esta guía, no un prompt para Gamma.

## Qué recibe el cliente

Una presentación web en `https://automatizatech.cl/ver-presentacion.php?id=<unique_id>` (un iframe
del renderer) con:

| # | Lámina | Campo del JSON |
|---|--------|----------------|
| 1 | Portada | `company_name`, `client_name` |
| 2 | El desafío actual | `challenge_title`, `challenge_text` |
| 3 | Nuestra solución | `solution_title`, `solution_text` |
| 4 | Beneficios clave | `benefits[]` (`title`, `text`) |
| 5 | Proceso de implementación | `how_it_works[]` (`step_title`, `step_text`) |
| 6–7 | Hasta 2 láminas extra (opcionales) | `extra_slides[]` (`eyebrow`, `title`, `bullets[]` o `text`) |
| — | Inversión | `pricing_rows[]`, `pricing_note` |
| — | Próximos pasos | `next_steps[]` |
| — | Cierre "Hablemos" | fijo: contactos AT y botón **Descargar la propuesta en PDF** |

Controles fijos (no se tocan por cliente): modo presentación con flechas y teclado, **pantalla
completa**, **Ver todo** (vista de lista), barra de progreso, aviso de girar el teléfono, PDF.

Código: carpeta `renderer/` (`src/template.js`, `src/schema.js`). Despliegue y claves:
`renderer/DEPLOY.md`.

## Campos que suelen equivocarse

- **Precios en pesos:** la columna muestra USD por defecto. Para CLP usar `price_label`
  (`"$250.000 en 2 pagos"`) y dejar `price_usd: 0`. `emphasis: true` marca la fila final en color;
  `strike: true` la tacha (descuentos).
- **`pricing_note`:** moneda, forma de pago y lo que NO incluye (por ejemplo, la inversión en
  anuncios que el cliente paga directo a Google).
- **Largo:** `challenge_text` y `solution_text` de ~70 palabras; 4 a 5 beneficios y 4 a 5 pasos. Si
  algo no cabe, la previsualización lo muestra (paso 2).
- **Tono:** español de Chile, tuteo salvo que el rubro pida usted. Los datos del desafío salen de la
  reunión y de una revisión real del sitio del cliente; no inventar cifras.

## Procedimiento

1. **Contenido.** Con la transcripción, armar el JSON. Los precios los decide Luis: proponer, nunca
   darlos por aprobados.
2. **Vista previa gratis.** Desde `renderer/`:
   ```bash
   node -e "const fs=require('fs');const {renderProposalHtml}=require('./src/template');const {validatePayload}=require('./src/schema');const d=JSON.parse(fs.readFileSync(process.argv[1],'utf8'));console.log(validatePayload(d));fs.writeFileSync(process.argv[2],renderProposalHtml(d,{}))" payload.json preview.html
   ```
   Sacar capturas de cada lámina con Playwright a 1920×1080 y mostrárselas a Luis.
3. **Fotos.** Una por lámina, sin texto, sin logos y sin rostros reconocibles (`image_briefs[]` con
   `slide` = `cover`, `challenge`, `solution`, `benefits`, `how_it_works`, `extra_1`, `extra_2`,
   `pricing`, `next_steps`). Modelo: Soul 2 por API (`scratchpad/higgsfield-api/hf_api.py`,
   US$0,0032 por imagen de lista). **Mostrar el costo y esperar el "ok" de Luis** antes de `--si`.
4. **Alojar las fotos.** No dejar las URL del CDN de Higgsfield dentro de la presentación: el renderer
   las enlaza tal cual y se caen (en la de Jeffer, 1 de 7 ya no cargaba el 2026-09-23). Bajar cada
   foto y publicarla en un lugar propio; pasarlas en el campo `images` (`{ "cover": "https://…" }`),
   que el renderer usa sin regenerar.
5. **Crear la propuesta.** `POST https://automatizatech.cl/api-save-proposal.php` con
   `client_email`, `transcript`, `gamma_prompt` (resumen del contenido; el nombre del campo es
   histórico) y `system_prompt` (el del chatbot). Devuelve `unique_id`. Es una escritura en PROD:
   con autorización de Luis.
6. **Renderizar.** `POST https://n8n-propuesta-renderer.kchiba.easypanel.host/render` con el JSON y
   `unique_id` igual al del paso 5. Tarda ~1 min sin fotos por generar.
7. **Completar en el panel.** `wp-admin → Propuestas → editar`: nombre, empresa, *URL Iframe Gamma* =
   `https://n8n-propuesta-renderer.kchiba.easypanel.host/p/<unique_id>/index.html`, y **Guardar**.
   El campo *URL Webhook n8n* aparece lleno aunque la base esté vacía: si no se guarda,
   `ver-demo.php` muestra "Demo en Configuración" (pasó con Jeffer el 2026-08-31).
8. **Verificar desde afuera** `ver-presentacion.php?id=…` y `ver-demo.php?id=…` (el chat debe
   responder) antes de enviarla al cliente.

## Chatbot de la propuesta

El `system_prompt` lleva datos reales del cliente (teléfono, sucursales, servicios, precios si son
públicos) y reglas de derivación a un humano. El webhook es
`https://n8n-n8n.kchiba.easypanel.host/webhook/demo-dinamico/chat` y busca el prompt por
`sessionId` = `unique_id`.

## Pendiente conocido

El renderer debería bajar las fotos a `/p/<unique_id>/` al generar, en vez de enlazar el CDN. Mientras
no se haga, el paso 4 es obligatorio.
