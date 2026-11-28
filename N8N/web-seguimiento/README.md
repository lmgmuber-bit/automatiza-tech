# Pestaña «Seguimiento» en el formulario de agenda de automatizatech.cl

Pedido de Luis (17-nov-2026): el modal de agenda tiene ahora tres pestañas, **Diagnóstico | Demo en vivo | Seguimiento**. La tercera es para que los clientes agenden su reunión de seguimiento. Luis decidió también qué pasa con quien no es cliente: n8n revisa si el teléfono es de un cliente o de alguien con propuesta. Si no lo es, la cita se agenda como diagnóstico y queda como lead.

## Cómo funciona

1. **Navegador (`at-home.js`):** la pestaña escribe `intent=seguimiento` y manda `appointmentType: 'Seguimiento'` al webhook de «Agente Tech Web (PROD)».
2. **«Limpiar sesión»** deja el `session_id` como `web-…`.
3. **Subflujo «Agendar Reunión AutomatizaTech - Cliente (PROD)»** (`PejBgr2LZQSAbbBy`, el mismo del bot de WhatsApp):
   - consulta `wa-contexto` por teléfono;
   - si viene de la web y la `situacion` no es `cliente` ni `prospecto`, o si la ficha trae un correo distinto del que se escribió, cambia el tipo a `Demo` (diagnóstico): queda como lead en `/leads`, con el correo de siempre;
   - si es cliente, guarda la cita en `/followup-meetings` con `source='web'` y la invitación «Reunión de Seguimiento».
4. **«Respond Lead»** devuelve `appointmentType`:
   - con `Seguimiento`, el navegador muestra «¡Reunión de seguimiento agendada!» y **no** avisa a Meta ni a Google como lead;
   - con `Demo`, muestra «¡Te agendamos un diagnóstico!» y sí cuenta como lead;
   - sin tipo, no cuenta como lead.

Lo que llega por WhatsApp no cambia, porque su `session_id` no empieza con `web-`.

## Por qué también se mira el correo

Exigir que el correo calce cuando la ficha lo trae evita dos cosas: que alguien que solo conoce el teléfono de un cliente agende a su nombre, y que el formulario sirva para averiguar si un número es cliente de AT. Si el teléfono tiene varias fichas, la ficha no trae un correo único y basta el teléfono.

## Aplicar

```bash
python -P N8N/web-seguimiento/aplicar.py --ver
```

```bash
python -P N8N/web-seguimiento/aplicar.py
```

- Respalda los dos flujos en `~/respaldos/n8n/web-seguimiento/`.
- Sube primero el subflujo; solo, todavía no cambia nada para nadie.
- Republica los flujos que estaban activos y verifica la `activeVersion`.
- El tema debe subirse **después** de n8n: si sube antes, una cita de seguimiento llega sin tipo de vuelta y se trata como no confirmada (no cuenta como lead).

## Archivos del tema

- `assets/home-premium/at-home.js`: la pestaña, el envío del tipo, el mensaje final y el conteo de leads. Además, la intro animada queda solo en la portada (decisión de Luis, 17-nov, propuesta de Faro).
- `assets/home-premium/at-home.css`: bajo 420 px las pestañas van sin ícono, para que las tres quepan en una línea.
- La tercera pestaña, los íconos de las tres y la opción «Reunión de seguimiento (ya soy cliente)» están en el `body.html` de la portada, en los de las siete landings y en `assets/guias/shell-bottom.html`.
