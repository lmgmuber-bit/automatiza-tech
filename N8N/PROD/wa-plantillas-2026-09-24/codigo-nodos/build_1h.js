const items = $input.all();
const results = [];

// Meta rechaza parametros vacios o con saltos de linea: siempre texto de una sola linea
const txt = (v, porDefecto) => String(v ?? '').replace(/\s+/g, ' ').trim() || porDefecto;

// Función para obtener hora actual en Chile (UTC-3 o UTC-4 según horario de verano)
function getChileNow() {
  const now = new Date();
  // Usar toLocaleString para obtener la hora en Chile y reconvertirla
  const chileStr = now.toLocaleString('en-US', { timeZone: 'America/Santiago' });
  return new Date(chileStr);
}

for (const item of items) {
  const data = item.json;

  // Limpiar el + del teléfono para la API de WhatsApp
  const phone = (data.phone || '').replace(/^\+/, '');

  const meetLink = txt(data.meet_link, 'Te llegó por correo');

  // Calcular tiempo restante hasta la cita
  // scheduled_date: "09-01-2026" (DD-MM-YYYY), scheduled_time: "12:00" o "12:00:00"
  const dateParts = data.scheduled_date.split('-');
  const day = parseInt(dateParts[0], 10);
  const month = parseInt(dateParts[1], 10) - 1; // Mes es 0-indexado
  const year = parseInt(dateParts[2], 10);

  const timeParts = data.scheduled_time.split(':');
  const hour = parseInt(timeParts[0], 10);
  const minute = parseInt(timeParts[1], 10);

  // Obtener hora actual EN CHILE
  const nowChile = getChileNow();

  // Crear fecha de la cita (también en hora Chile, ya que los datos vienen en hora local)
  const appointmentDate = new Date(year, month, day, hour, minute, 0);

  // Calcular diferencia en minutos
  const diffMs = appointmentDate.getTime() - nowChile.getTime();
  const diffMinutes = Math.floor(diffMs / 60000);

  // Debug info mejorado
  const debugInfo = {
    scheduled_date: data.scheduled_date,
    scheduled_time: data.scheduled_time,
    parsed: { year, month: month + 1, day, hour, minute },
    appointmentDate: appointmentDate.toString(),
    nowChile: nowChile.toString(),
    nowChileHour: nowChile.getHours(),
    nowChileMinute: nowChile.getMinutes(),
    serverNow: new Date().toISOString(),
    diffMs,
    diffMinutes
  };

  // Tiempo restante para el encabezado de la plantilla: "⏰ ¡Tu cita es en {{1}}!"
  let timeMessage = '';
  if (diffMinutes >= 115) {
    timeMessage = 'casi 2 horas';
  } else if (diffMinutes >= 90) {
    const hours = Math.floor(diffMinutes / 60);
    const mins = diffMinutes % 60;
    timeMessage = `${hours} hora y ${mins} minutos`;
  } else if (diffMinutes >= 75) {
    timeMessage = '1 hora y media';
  } else if (diffMinutes >= 55) {
    timeMessage = '1 hora';
  } else if (diffMinutes >= 30) {
    timeMessage = `${diffMinutes} minutos`;
  } else if (diffMinutes > 0) {
    timeMessage = 'menos de 30 minutos';
  } else {
    timeMessage = 'pocos minutos';
  }

  // Plantilla aprobada por Meta: llega aunque el cliente nunca haya escrito al numero.
  // Con type 'interactive' Meta la descartaba (error 131047) a todo lead que agendaba por la web.
  const messageBody = {
    messaging_product: 'whatsapp',
    recipient_type: 'individual',
    to: phone,
    type: 'template',
    template: {
      name: 'recordatorio_cita_1h',
      language: { code: 'es' },
      components: [
        {
          type: 'body',
          parameters: [
            { type: 'text', text: timeMessage },
            { type: 'text', text: txt(data.name, 'cliente') },
            { type: 'text', text: txt(data.scheduled_date, '-') },
            { type: 'text', text: txt(data.scheduled_time, '-') },
            { type: 'text', text: meetLink }
          ]
        },
        // Mismos ids de siempre: el bot los enruta igual que los botones interactive
        { type: 'button', sub_type: 'quick_reply', index: '0', parameters: [{ type: 'payload', payload: `btn_reminder_confirm_${data.id}` }] },
        { type: 'button', sub_type: 'quick_reply', index: '1', parameters: [{ type: 'payload', payload: `btn_reminder_no_${data.id}` }] }
      ]
    }
  };

  results.push({
    json: {
      ...data,
      debugInfo: debugInfo,
      diffMinutes: diffMinutes,
      timeMessage: timeMessage,
      whatsappBody: JSON.stringify(messageBody)
    }
  });
}

return results;
