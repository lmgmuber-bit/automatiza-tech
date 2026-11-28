// Preparar datos para envío de WhatsApp.
// Plantilla aprobada por Meta: llega aunque el cliente no haya escrito en las últimas 24 h
// (con type 'interactive' Meta la descartaba con el error 131047).
const meeting = $input.first().json;

// Meta rechaza parametros vacios o con saltos de linea: siempre texto de una sola linea
const txt = (v, porDefecto) => String(v ?? '').replace(/\s+/g, ' ').trim() || porDefecto;

// Limpiar teléfono
const phone = (meeting.phone || '').replace(/^\+/, '');

// Información personalizada
const clientName = txt(meeting.name, 'Cliente');
const companyName = txt(meeting.company_name, '');
const projectInfo = txt(meeting.meeting_subject, 'Reunión de Seguimiento');
const saludo = companyName ? `${clientName} de ${companyName}` : clientName;

const btnConfirmId = `btn_followup_confirm_${meeting.id}`;
const btnRescheduleId = `btn_followup_reschedule_${meeting.id}`;
const btnCancelId = `btn_followup_cancel_${meeting.id}`;
const boton = (i, payload) => ({ type: 'button', sub_type: 'quick_reply', index: String(i), parameters: [{ type: 'payload', payload }] });

const whatsappBody = JSON.stringify({
  messaging_product: 'whatsapp',
  recipient_type: 'individual',
  to: phone,
  type: 'template',
  template: {
    name: 'recordatorio_seguimiento_hoy',
    language: { code: 'es' },
    components: [
      {
        type: 'body',
        parameters: [
          { type: 'text', text: saludo },
          { type: 'text', text: projectInfo },
          { type: 'text', text: txt(meeting.scheduled_date, '-') },
          { type: 'text', text: txt(meeting.scheduled_time, '-') },
          { type: 'text', text: txt(meeting.meet_link, 'Te llegará por correo') }
        ]
      },
      boton(0, btnConfirmId),
      boton(1, btnRescheduleId),
      boton(2, btnCancelId)
    ]
  }
});

return {
  json: {
    meeting_id: meeting.id,
    phone: phone,
    clientName: clientName,
    companyName: companyName,
    projectInfo: projectInfo,
    btnConfirmId: btnConfirmId,
    btnRescheduleId: btnRescheduleId,
    btnCancelId: btnCancelId,
    whatsappBody: whatsappBody
  }
};
