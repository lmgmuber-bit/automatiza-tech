# Propuestas comerciales: una sola plantilla

> Regla de Luis (2026-09-23): **toda propuesta de cliente se arma con la plantilla del
> `propuesta-renderer`**, con la misma estructura y los mismos controles que la propuesta de
> referencia (cuál es: bóveda privada, nota de Propuestas v3). No se vuelve a Gamma ni se diseña una presentación nueva: por cliente cambian los
> textos, los precios y las fotos, nada más. Canva/Gamma quedan como respaldo solo si Luis lo pide.

**Camino normal desde el 2026-09-24: el flujo automático v3** (Meet → «1 Borrador» → panel →
«3 Final» → envío desde el panel), en `Docs/METODO_AT/PROPUESTAS-FLUJO-V3.md`. Esta guía es el camino
**manual**, para cuando ese flujo no aplica, y la referencia de los campos del JSON.

Aplica a Claude, Codex, OpenCode y Copilot. La skill `at-gamma-proposal` conserva su nombre histórico
por los disparadores (`at-propuesta` es su alias), pero su salida es el JSON de esta guía, no un prompt
para Gamma. Desde el 2026-10-10 hay una sola copia de la skill: `C:\Users\luis_\.agents\skills\at-gamma-proposal`
(enlazada desde Claude, Codex y OpenCode) y su gemela `.github/skills/at-gamma-proposal/SKILL.md`.

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
3. **Fotos.** Una por lámina, del rubro del cliente con personas en acción, sin texto, sin logos y
   sin rostros reconocibles (`image_briefs[]` con `slide` = `cover`, `challenge`, `solution`,
   `benefits`, `how_it_works`, `extra_1`, `extra_2`, `pricing`, `next_steps`). Reglas completas en
   «Fotos por rubro» de `PROPUESTAS-FLUJO-V3.md`. Modelo: Soul 2 por API, ≈ US$0,0032 por foto.
   **Mostrar el costo y esperar el "ok" de Luis** antes de mandar `image_briefs` al `/render`.
4. **Alojamiento de las fotos (automático desde el 2026-09-24).** El renderer guarda cada foto en
   `/p/<unique_id>/img/` junto a la presentación y la reutiliza en renders siguientes si su
   descripción no cambió (`img/manifest.json`), así que una foto ya guardada no se vuelve a pagar.
   Lo que llegue en el campo `images` (`{ "cover": "https://…" }`) gana sobre lo generado. Ya no hace
   falta bajar las fotos a mano: el problema del CDN de Higgsfield (en la propuesta de referencia, 1 de 7 ya no
   cargaba el 2026-09-23) quedó resuelto con este cambio.
5. **Crear la propuesta.** `POST https://automatizatech.cl/api-save-proposal.php` con
   `client_email`, `transcript`, `gamma_prompt` (resumen del contenido; el nombre del campo es
   histórico) y `system_prompt` (el del chatbot). Devuelve `unique_id`. Es una escritura en PROD:
   con autorización de Luis.
6. **Renderizar.** `POST https://n8n-propuesta-renderer.kchiba.easypanel.host/render` con el JSON y
   `unique_id` igual al del paso 5. Desde el 2026-09-25 exige la cabecera `X-AT-Render-Key` (sin
   ella responde 401). El valor vive solo en la credencial de n8n y en la variable `RENDER_KEY` de
   Easypanel: nunca se escribe en un documento, un prompt ni el chat. Tarda ~1 min sin fotos por generar.
7. **Completar en el panel.** `wp-admin → Propuestas → editar`: nombre, empresa, *URL Iframe Gamma* =
   `https://n8n-propuesta-renderer.kchiba.easypanel.host/p/<unique_id>/index.html`, y **Guardar**.
   El campo *URL Webhook n8n* aparece lleno aunque la base esté vacía: si no se guarda,
   `ver-demo.php` muestra "Demo en Configuración" (pasó con una propuesta real el 2026-08-31).
8. **Verificar desde afuera** `ver-presentacion.php?id=…` y `ver-demo.php?id=…` (el chat debe
   responder) antes de enviarla al cliente.

## Chatbot de la propuesta

El `system_prompt` lleva datos reales del cliente (teléfono, sucursales, servicios, precios si son
públicos) y reglas de derivación a un humano. El webhook es
`https://n8n-n8n.kchiba.easypanel.host/webhook/demo-dinamico/chat` y busca el prompt por
`sessionId` = `unique_id`.

## Precios y enlaces

Cómo presentar la inversión (tres bloques con su total), la tabla densa sobre 6 filas, las URL que el
renderer vuelve enlace y por qué recargar el panel antes de Aprobar: sección «Precios, reaprobación y
enlaces» de `PROPUESTAS-FLUJO-V3.md`.
