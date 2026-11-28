const items = $input.all();
const results = [];

// Meta rechaza parametros vacios o con saltos de linea: siempre texto de una sola linea
const txt = (v, porDefecto) => String(v ?? '').replace(/\s+/g, ' ').trim() || porDefecto;

for (const item of items) {
  const data = item.json;

  // Limpiar el + del teléfono para la API de WhatsApp
  const phone = (data.phone || '').replace(/^\+/, '');

  // Plantilla aprobada por Meta: llega aunque el cliente nunca haya escrito al numero.
  // Con type 'interactive' Meta la descartaba (error 131047) a todo lead que agendaba por la web.
  const messageBody = {
    messaging_product: 'whatsapp',
    recipient_type: 'individual',
    to: phone,
    type: 'template',
    template: {
      name: 'recordatorio_cita_72h',
      language: { code: 'es' },
      components: [
        {
          type: 'body',
          parameters: [
            { type: 'text', text: txt(data.name, 'cliente') },
            { type: 'text', text: txt(data.scheduled_date, '-') },
            { type: 'text', text: txt(data.scheduled_time, '-') }
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
      whatsappBody: JSON.stringify(messageBody)
    }
  });
}

return results;
