---
name: at-proposal-refiner
description: Valida una propuesta AutomatizaTech ya creada contra la reunión real y la presentación del propuesta-renderer; propone los ajustes por el camino correcto (panel «Pedir cambios» en las v3, endpoint /prompts en las antiguas) y deja listo el prompt de diseño. No usa Gamma.
triggers:
  - refinar propuesta
  - refinar prompts propuesta
  - at proposal refiner
  - actualizar prompts propuesta
  - refinar propuesta automatizatech
  - mejorar prompt gamma
---

# at-proposal-refiner

Copia única compartida por Claude Code, Codex, OpenCode y GitHub Copilot (conciliada el 2026-10-10,
ticket AT-INT-PROP-002). Fuente: `C:\Users\luis_\.agents\skills\at-proposal-refiner`; los hosts la leen
por enlace. En el repo `automatiza-tech` hay una copia idéntica en
`.github/skills/at-proposal-refiner/SKILL.md`. Se cambian las dos a la vez.

Trabaja junto a `at-gamma-proposal` (la plantilla única del propuesta-renderer). Guías:
`Docs/METODO_AT/PROPUESTAS-FLUJO-V3.md` y `Docs/METODO_AT/PROPUESTAS-PLANTILLA-UNICA.md`.

## Reglas

- La fuente de verdad es la reunión: la transcripción o el historial, más lo que confirme Luis. Nunca inventar precios,
  servicios ni datos. Refinar es mejorar redacción, estructura y coherencia; **no cambia el alcance
  comercial**. Los precios los decide Luis.
- Toda escritura en PROD (panel, endpoint, re-render) se hace con el diff antes/después a la vista y el
  "ok" explícito de Luis para esa propuesta.
- Los secretos (`X-AT-Secret`, `X-AT-Render-Key`) nunca se escriben en el chat, un archivo, un commit ni un
  log. Se cargan en memoria y se pasan por variable de entorno o stdin.

## Paso 1: reunir el contexto real

Pedir:
1. El historial de la llamada (`.md`, texto pegado o transcripción).
2. La presentación generada: `https://automatizatech.cl/ver-presentacion.php?id=<unique_id>` o su PDF.
3. El `edit_id` de la propuesta (está en `wp-admin/admin.php?page=automatiza-proposals`).

## Paso 2: leer la propuesta

Saber primero si es **v3** (`flujo = 'v3'`, todas las nuevas desde el 2026-09-24) o **antigua**.

| Lectura | Endpoint (cabecera `X-AT-Secret`) | Devuelve |
|---|---|---|
| Completa (preferida) | `GET https://automatizatech.cl/?rest_route=/automatiza-tech/v1/proposal/{ID}/state` | `flujo`, `status`, `status_note`, `payload` (el JSON de láminas ya parseado), `system_prompt` |
| Solo prompts | `GET …/proposal/{ID}/prompts` | `gamma_prompt_text` (en las v3 es el JSON de láminas como texto), `system_prompt_text`, `status` |

Mostrar empresa, cliente, `flujo` y `status`, y confirmar que es la propuesta correcta.

**Secreto:** la constante `AT_REST_SECRET` del `wp-config.php` de PROD. Se lee **por su etiqueta exacta** en el
archivo de claves de Luis, con un script que no la imprime. Si no se conoce la etiqueta, preguntarla a Luis: no
adivinar ni recorrer el archivo. 🔴 La variable de usuario `AT_REST_SECRET_PROD` de Windows quedó **vencida** con
la rotación del 2026-08-26 (responde 403, comprobado el 2026-10-10): no usarla.

Errores: `401` sin cabecera · `403` secreto incorrecto o vencido · `404` la propuesta no existe ·
`500 at_rest_misconfigured` falta la constante en el servidor.

## Paso 3: evaluar

| Criterio | ¿Refinar? |
|---|---|
| Las láminas no reflejan algo relevante de la llamada | Sí |
| Precios o servicios distintos de lo acordado | Sí (a Luis, que decide) |
| El tono del chatbot no calza con el rubro | Sí |
| El desafío (lámina 2) no captura el dolor real | Sí |
| Contactos del chatbot incorrectos o genéricos | Sí |
| Todo alineado con la reunión y la presentación | No: pasar al paso 5 |

Mostrar siempre el diff antes/después de cada campo.

## Paso 4: aplicar el ajuste por el camino correcto

**Propuesta v3** (el endpoint `POST /prompts` responde `409` a propósito):
- **Camino normal:** escribir los comentarios para que Luis los pegue en el panel, pestaña «Revisión y precios», y apriete
  **Pedir cambios**. El flujo «2 Cambios» aplica solo esos comentarios, no toca los precios (WordPress los
  restaura) y rehace la vista previa. Para cambiar una foto, nombrar la lámina por su número
  (1 portada, 2 desafío, 3 solución, 4 beneficios, 5 cómo funciona, extras, inversión, próximos pasos).
- **Precios:** los escribe Luis en el panel. «Guardar» no guarda precios: solo «Pedir cambios» o «Aprobar».
- **Excepción** (solo con el "ok" expreso de Luis): `POST …/proposal/{ID}/state` con `payload` parcial
  (se fusiona con lo guardado y se valida). Respaldar antes el payload en `~/respaldos/propuesta-<id>-antes-*.json`
  y avisar a Luis que **recargue el panel antes de Aprobar**, porque «Aprobar» manda las filas que están en
  pantalla. El envío al cliente (`sent`) nunca va por API: solo desde el panel.

**Propuesta antigua** (`flujo` vacío):
- `POST …/proposal/{ID}/prompts` con `{ "gamma_prompt_text"?, "system_prompt_text"? }`, solo los campos que
  cambian. Confirmar con la respuesta.
- La presentación se rehace editando el JSON y re-renderizando con el mismo `unique_id` (paso 6 de
  `PROPUESTAS-PLANTILLA-UNICA.md`; `/render` exige `X-AT-Render-Key`).

Para UTF-8 y JSON, usar Python con `urllib` en vez de `curl`.

## Paso 5: prompt de diseño (opcional)

Si la propuesta incluye un sitio nuevo, generar la **Salida 3 de `at-gamma-proposal`** (logo, redes del
cliente y plantilla de frames) con todo lo reunido: reunión, presentación, datos de la API y ajustes.
La plantilla vive solo en esa skill, para no tener dos copias que diverjan.

## Después: entrega a desarrollo

Prototipo presentado al cliente → si lo aprueba («le gustó el diseño», «quedamos en arrancar», «firmó»,
«ya pagó»), sugerir `at-dev-kickoff` para definir el alcance técnico. Si pide cambios, ajustar el prompt y
regenerar. Hoy el cierre formal del cliente va por la página de respuesta de la propuesta (sección «Cierre de
cliente» de `PROPUESTAS-FLUJO-V3.md`).

## Referencia técnica

- Rutas en `wp-content/themes/automatiza-tech/inc/rest-proposals.php`. Todas usan la misma autenticación
  `X-AT-Secret` = `AT_REST_SECRET`.
- `GET|POST /proposal/{id}/prompts` (el POST da 409 en las v3) · `POST /proposal` (alta v3, la usa n8n) ·
  `GET|POST /proposal/{id}/state` (estado y payload; transiciones en `PROPUESTAS-FLUJO-V3.md`).
- Tabla `wp_automatiza_propuestas`: `gamma_prompt_text` (nombre histórico: en las v3 guarda el JSON de
  láminas), `system_prompt_text`, `flujo`, `status`, `status_note`.
- El endpoint ya está en PROD. La guía antigua de despliegue manual se retiró el 2026-10-10.
