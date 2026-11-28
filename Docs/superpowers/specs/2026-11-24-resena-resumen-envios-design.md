# Reseña · Resumen de envíos por correo

Fecha: 24-nov-2026 · Pedido de Luis en el chat · Rama `codex/resena-google-impl`

## Intención

Cuando termina una tanda de invitaciones a reseña, Luis quiere un correo con un cuadro: una fila por cliente, ✓ o ✗ en
Correo y en WhatsApp, para ver de una vez a quién le llegó y a quién no. El correo lo manda n8n cuando el proceso
completo terminó, es decir, después de esperar las confirmaciones de entrega de WhatsApp.

## Metas

- Un correo por tanda, tanto del envío diario de las 10:00 como de un «Enviar ahora» o «Volver a enviar» desde la ficha.
- Cuadro con cliente, proyecto y dos columnas: Correo y WhatsApp.
- Símbolos: ✓ llegó · ✗ no salió o Meta avisó que no se entregó · ⏳ Meta lo aceptó pero no confirmó la entrega en 15
  minutos · — no correspondía, con el motivo (sin permiso de WhatsApp, sin teléfono, sin correo, baja).
- Correo: ✓ cuando el servidor lo aceptó (no hay confirmación de entrega para el correo).
- WhatsApp: ✓ cuando Meta avisó entregado o leído.

## No metas

- No reenvía nada ni cambia los envíos: solo informa.
- No manda correo si la tanda no tuvo ningún envío (el diario sin pendientes no escribe).

## Enfoques considerados

- A · Dentro del flujo diario: el más simple, pero no cubre el «Enviar ahora» manual. Descartado.
- **B · Flujo aparte «Reseña · Resumen» (elegido por Luis).** Lo llaman el diario al terminar y el sitio tras un envío
  manual; espera 15 minutos, pide el resultado al sitio y manda el correo.
- C · Revisión cada hora: no depende de llamadas, pero tarda hasta una hora y hay que marcar lo ya resumido. Descartado.

## Cómo

- **Sitio (WordPress):** ruta nueva `GET /wp-json/<misma base de reseña>/resena/resumen?desde=<fecha ISO>`, con la misma
  autenticación `X-AT-Secret` que las otras rutas de reseña. Devuelve los intentos creados desde esa fecha (reales y
  pruebas, marcando cuáles son prueba), cada uno con cliente, proyecto y el estado y detalle de cada canal, y el campo
  `aviso_a` con la casilla de avisos del cierre (de la base, nunca escrita en el repo).
- **Sitio:** después de un «Enviar ahora» o «Volver a enviar» aceptado, avisa sin esperar al webhook nuevo
  `at-resena-resumen` con `{desde, origen: "manual"}`, con la misma cabecera que ya usa para el flujo de WhatsApp.
- **n8n «Reseña · Resumen» (nuevo):** webhook con cabecera → espera 15 minutos → pide `/resena/resumen` → arma el cuadro
  en HTML (tabla de correo, con los colores de AT) → lo envía a `aviso_a` con la credencial SMTP que ya usan los avisos
  de AT. Asunto: «Reseñas enviadas: N de M con todo OK» (o similar).
- **n8n «Reseña · Diario 10:00»:** al final, si hubo al menos un pendiente, llama al webhook con `{desde: hora de inicio
  de la corrida, origen: "diario"}`.
- Si dos envíos manuales caen dentro de 15 minutos, cada resumen cubre desde su propia hora: puede repetirse una fila.
  Se acepta por simple.

## Restricciones

- Repo público: sin correos, teléfonos, nombres de clientes ni claves.
- Cambios en n8n con respaldo previo y verificación de que la versión publicada es la nueva; un solo dueño por flujo.
- Despliegue a PROD con el OK de Luis, respaldo y verificación desde afuera.

## Qué queda para quien construya

- Nombre exacto de la credencial SMTP de n8n y de la constante del sitio que guarda la URL o clave del webhook.
- Diseño fino del cuadro (debe verse bien en Gmail y en el celular).
- Pruebas: la ruta `/resena/resumen` (con y sin envíos, filtro `desde`, sin datos de más), el aviso tras envío manual
  (con el webhook falso) y una corrida de n8n con `prueba:true`.
