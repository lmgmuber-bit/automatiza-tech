---
name: at-gamma-proposal
description: Genera el prompt Gamma 8-slides + system prompt chatbot + prompt de diseño visual para propuestas Automatizatech
triggers:
  - generar propuesta gamma
  - nueva propuesta cliente
  - propuesta gamma automatizatech
  - crear presentación gamma
---

# at-gamma-proposal (GitHub Copilot)

## Purpose

Generar los assets de propuesta para un cliente nuevo de Automatizatech:
1. Gamma Presentation Prompt (8 slides)
2. Chatbot System Prompt (demo dinámico)
3. Design Prototype Prompt (para Open Design / Claude Design)

Panel admin: `https://automatizatech.cl/wp-admin/admin.php?page=automatiza-proposals`
Referencia completa: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\30-Agent-Protocols\automatizatech-pipeline.md`

## Required Input

```
CLIENTE: nombre, empresa, rubro, email, teléfono, redes sociales
DIAGNÓSTICO: descripción del negocio, problema principal, objetivo
SOLUCIÓN: servicios incluidos, beneficio clave
PRECIOS: tabla USD + CLP, ofertas especiales
IDENTIDAD: colores corporativos, logo, estilo visual
```

## Output 1: Gamma Prompt

```
Crea una presentación profesional de 7 a 10 slides para AutomatizaTech presentando
una propuesta a {{NOMBRE_EMPRESA}}. Estilo: Fotográfico profesional, cinemático y tecnológico.

Slide 1: Portada — "Transformación Digital para {{NOMBRE_EMPRESA}}"
         Logo AT URL centrado pequeño.
Slide 2: El Desafío Actual — {{NOMBRE_CLIENTE}} enfrenta {{DESAFIO}}.
Slide 3: Nuestra Solución — AutomatizaTech propone {{SOLUCION}}.
Slide 4: Beneficios Clave — lista de 4 beneficios específicos.
Slide 5: ¿Cómo Funciona? — 3 pasos de implementación.
Slide 6: Inversión — tabla de precios USD + CLP.
Slide 7: Próximos Pasos — Aprobación → Kick-off → Implementación → Entrega.
Slide 8: Contacto — Logo AT + contacto@automatizatech.cl / automatizatech.cl / +56 9 2700 2984

Logo AT: https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech+slogan.png
```

## Output 2: Chatbot System Prompt

```
Eres un asistente virtual de {{NOMBRE_EMPRESA}}, una {{DESCRIPCION}}.
FUNCIONES: responder sobre productos, precios, pedidos, derivar a {{NOMBRE_CLIENTE}}.
CONTACTO: {{TELEFONO}} / {{INSTAGRAM}}
TONO: Amigable, profesional. IDIOMA: Español.
```

Webhook demo n8n: `https://n8n-n8n.kchiba.easypanel.host/webhook/demo-dinamico/chat`

## Paso Intermedio (antes de Output 3)

Pedir historial de la llamada + presentación Gamma generada.
Evaluar coherencia. Si hay gaps → ejecutar flujo `at-proposal-refiner`
(ver `.github/skills/at-proposal-refiner/SKILL.md`).

## Output 3: Prompt de Diseño (requiere logo + redes)

1. Pedir logo al usuario (imagen o URL)
2. Visitar Instagram + Facebook + sitio web del cliente
3. Extraer: paleta real, tono, catálogo, historia, ubicación, público

Generar prompt detallado con 7 frames, animaciones, transiciones y requisitos técnicos.
Ver plantilla completa en: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\30-Agent-Protocols\automatizatech-pipeline.md`

## Flujo del Pipeline

```
Reunión → Output 1+2 → guardar en /wp-admin → edit_id
       → at-proposal-refiner (validar) → Output 3 (diseño)
       → segunda reunión → ejecución → cliente definitivo
```
