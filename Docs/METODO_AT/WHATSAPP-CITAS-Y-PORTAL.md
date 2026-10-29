# Tech WhatsApp: citas con botones y enlace al portal del cliente

Pedido de Luis del 29-oct-2026, después de su prueba real. Al cancelar, el bot le pidió nombre, correo, fecha y hora en cuatro líneas. Luis las escribió en una sola línea, el bot las rechazó, y además le mandó un mensaje confuso. Como dijo Luis, mientras más tiene que escribir la persona, más difícil es que se entienda con el asistente.

Diseño: `Docs/superpowers/specs/2026-10-29-whatsapp-citas-botones-design.md`. Plan: `Docs/superpowers/plans/2026-10-29-whatsapp-citas-botones.md`. Detalle técnico de n8n: `N8N/wa-citas/README.md`. Base: `N8N/wa-reconoce/README.md` (el bot reconoce quién escribe por su número).

## Qué hace

- **Cancelar.** El bot busca las citas futuras del número (diagnósticos y seguimientos, por el teléfono y por los correos de la ficha) y se las muestra:
  - con una cita: «Tienes esta cita: … ¿La cancelamos?», con los botones «Sí, cancelar» y «No, mantenerla»;
  - con dos o tres: un botón por cita y después la confirmación;
  - con más: una lista.
  
  Al confirmar, WordPress vuelve a revisar que la cita siga siendo de ese número. La deja cancelada (nunca la borra), quita el evento de Calendar, le manda un correo a la persona y te avisa a ti. El bot dice «cancelada» solo si WordPress lo confirma.
- **Reprogramar.** Funciona igual: la persona elige, confirma y le llega el calendario. La cita vieja se cancela recién cuando la nueva quedó agendada. Si abandona a medio camino, o el horario ya está ocupado, la vieja sigue en pie. Tampoco se le pide el nombre: se usan los de la cita vieja.
  - Lo mismo pasa con los botones de reprogramar de los recordatorios.
  - Durante el cambio, la cita que se mueve no cuenta para el límite de 2 reuniones activas. Eso solo vale si lo pide n8n con su clave.
- **Sin citas.** El bot ofrece agendar y un botón «Tengo una cita», que te avisa a ti, porque pudo agendar con otro número u otro correo.
- **Si WordPress no responde,** el bot dice que no pudo revisar las citas y te avisa. Nunca pide datos por chat.
- **Enlace al portal.** Si escribe un cliente real y pregunta por su proyecto, Tech le manda el enlace firmado a su ficha, donde ve la línea de tiempo y le puede preguntar a MAXTECH.
  - Solo sale si el teléfono de la ficha del CRM es el mismo desde el que escribe.
  - Si ese número está en varias fichas, manda un enlace por ficha.
- **Tipo de cambio.** WordPress guarda la tasa 6 horas. Los nodos que la consultan siguen aunque se caiga, y el bot da los precios solo en USD cuando no hay tasa; nunca «$0 CLP».

## Límite conocido

Si al reprogramar la persona escribe a mano otra fecha («otra fecha»), la cita vieja no se cancela sola: queda activa y te toca cancelarla a ti. Nunca se pierde una cita.

## Cómo se aplica

1. **WordPress por SSH,** con respaldo: lo nuevo y lo cambiado en `inc/wa-contexto/`, más `inc/admin-followup-meetings.php`, `inc/api-endpoints.php` e `inc/tipo-cambio-cache.php`.
2. **n8n:** `python -I -B -X utf8 N8N/wa-citas/aplicar.py --subir` sube la agenda, WhatsApp y la web, y verifica la versión publicada. Antes hay que avisarle a la sesión de Instagram, porque la agenda la comparte.
3. **Personalidad de WhatsApp:** `python -I -X utf8 N8N/cerebro-at/sincronizar.py --subir`.

Todo se sube solo con tu OK. Para volver atrás, ver `N8N/wa-citas/README.md`.
