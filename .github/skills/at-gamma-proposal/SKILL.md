---
name: at-gamma-proposal
description: Arma la propuesta comercial AT con la plantilla única del propuesta-renderer (JSON de láminas + fotos + system prompt del chatbot). Ya no genera prompts de Gamma.
triggers:
  - generar propuesta gamma
  - nueva propuesta cliente
  - propuesta gamma automatizatech
  - crear presentación gamma
  - crear propuesta automatizatech
---

# at-gamma-proposal (GitHub Copilot)

> **Regla (2026-09-23):** toda propuesta usa la plantilla del `propuesta-renderer`, la misma de la
> propuesta de Jeffer García (id 42). **No generar prompts para Gamma.** El nombre de la skill se
> mantiene solo por los disparadores.

Guía completa, con el procedimiento de punta a punta: `Docs/METODO_AT/PROPUESTAS-PLANTILLA-UNICA.md`.

## Entrada

Transcripción o notas de la reunión, sitio y redes del cliente, catálogo si existe, y los precios que
decida Luis (proponer, nunca darlos por aprobados).

## Salida 1: JSON del renderer

Campos obligatorios (`renderer/src/schema.js`): `unique_id`, `client_name`, `company_name`,
`challenge_title`, `challenge_text`, `solution_title`, `solution_text`, `benefits[]`,
`how_it_works[]`, `pricing_rows[]`, `next_steps[]`. Opcionales: `extra_slides[]` (máx. 2),
`pricing_note`, `image_briefs[]`, `images{}`.

- Precios en CLP con `price_label`; `price_usd: 0`.
- Fotos sin texto, logos ni rostros; costo mostrado y "ok" de Luis antes de generar.
- Previsualizar gratis con `renderProposalHtml` antes de gastar o de tocar PROD.

## Salida 2: system prompt del chatbot

Con datos reales del cliente (teléfono, sucursales, servicios, precios públicos) y reglas de
derivación a un humano. Webhook demo: `https://n8n-n8n.kchiba.easypanel.host/webhook/demo-dinamico/chat`
(busca el prompt por `sessionId` = `unique_id`).

## Después

Crear la fila con `api-save-proposal.php`, renderizar con ese `unique_id`, y en el panel
(`wp-admin/admin.php?page=automatiza-proposals`) completar y **Guardar**: si no, `n8n_chat_url` queda
vacío y el demo muestra "Demo en Configuración". Para ajustar una propuesta existente, se edita el JSON
y se vuelve a renderizar con el mismo `unique_id` (ver `at-proposal-refiner`).

## Salida 3: prompt de diseño (opcional)

Si la propuesta incluye un sitio nuevo, el prompt de prototipo para Claude Design / Open Design sigue
como antes: ver `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\30-Agent-Protocols\automatizatech-pipeline.md`.
