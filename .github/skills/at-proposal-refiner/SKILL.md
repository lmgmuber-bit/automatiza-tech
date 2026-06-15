---
name: at-proposal-refiner
description: Valida y refina propuestas Automatizatech usando historial de llamada, presentación Gamma y API. Genera el prompt de diseño visual final.
triggers:
  - refinar propuesta
  - mejorar prompt gamma
  - actualizar prompts propuesta
  - at proposal refiner
---

# at-proposal-refiner (GitHub Copilot)

## Purpose

Valida una propuesta existente contra la reunión real y la presentación Gamma.
Refina prompts vía API si hace falta. Genera el prompt de diseño visual (Output 3).

Referencia completa: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\30-Agent-Protocols\automatizatech-pipeline.md`

---

## Paso 1 — Recopilar contexto

Pedir al usuario:
1. Historial de la llamada con el cliente (.md o texto)
2. Presentación Gamma generada (link embed, PDF o screenshot)

---

## Paso 2 — Consultar API

Pedir el edit_id de la propuesta.

```
GET https://automatizatech.cl/?rest_route=/automatiza-tech/v1/proposal/{ID}/prompts
Header: X-AT-Secret: <secret>
```

Secret en wp-config.php del servidor. No exponer. Confirmar empresa + cliente con usuario.

---

## Paso 3 — Evaluar y refinar

Cruzar historial + Gamma + prompts de la API.
Si hay gaps → mostrar diff → pedir aprobación → POST:

```
POST https://automatizatech.cl/?rest_route=/automatiza-tech/v1/proposal/{ID}/prompts
Header: X-AT-Secret: <secret>
Body: { "gamma_prompt_text": "...", "system_prompt_text": "..." }
```

Reglas: nunca inventar datos | solo mejorar redacción | no cambiar alcance comercial

---

## Paso 4 — Output 3: Prompt de Diseño

Inputs adicionales:
- Logo del cliente (imagen o URL)
- Visitar Instagram + Facebook + sitio web → extraer identidad visual real

Generar prompt para Open Design / Claude Design con:
- 7 frames detallados (Hero → Feature estrella → Catálogo → Historia → Prueba social → Contacto → Footer)
- Animaciones específicas por frame
- Transiciones globales (fade + translateY, 400ms ease-out)
- Requisitos: mobile-first, WhatsApp flotante, skeleton loaders, contraste AA

Ver plantilla completa en:
`C:\Users\luis_\Documents\Codex\AI-Memory-Vault\30-Agent-Protocols\automatizatech-pipeline.md`

---

## Endpoint REST

| Método | URL |
|--------|-----|
| GET | `https://automatizatech.cl/?rest_route=/automatiza-tech/v1/proposal/{ID}/prompts` |
| POST | `https://automatizatech.cl/?rest_route=/automatiza-tech/v1/proposal/{ID}/prompts` |

Auth: `X-AT-Secret` header | Body POST: solo campos modificados
