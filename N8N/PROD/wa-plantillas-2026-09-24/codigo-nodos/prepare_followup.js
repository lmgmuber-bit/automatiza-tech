// Validar y preparar datos recibidos desde WordPress CRM
// Soporta 3 tipos:
//   type='demo'                  → 1ra reunión con prospecto
//   type='seguimiento_prospecto' → Prospecto que ya tuvo demo (sin ficha/MAXTECH)
//   type='seguimiento'           → Cliente convertido (con ficha/portal/MAXTECH)
// Se envía con plantilla aprobada por Meta: llega aunque el cliente no haya escrito en las últimas 24 h
// (con type 'interactive' Meta la descartaba con el error 131047).
try {
  const data = $input.first().json.body || $input.first().json;

  // Meta rechaza parametros vacios o con saltos de linea: siempre texto de una sola linea
  const txt = (v, porDefecto) => String(v ?? '').replace(/\s+/g, ' ').trim() || porDefecto;

  const phone = (data.phone || '').toString().replace(/^\+/, '').replace(/\s/g, '');
  const meetingId = data.meeting_id;
  const clientName = txt(data.client_name, 'Cliente');
  const companyName = txt(data.company_name, '');
  const formattedDate = txt(data.formatted_date || data.meeting_date, '-');
  const formattedTime = txt(data.formatted_time || data.meeting_time, '-');
  const meetingSubject = txt(data.meeting_subject, 'Reunión de Seguimiento');
  const fichaUrl = txt(data.ficha_url, '');
  const meetingType = data.type || 'seguimiento';
  const context = data.context || 'new';
  const prospectTimelineUrl = txt(data.prospect_timeline_url, '');
  const esReagendada = context === 'reschedule';

  // Validar campos requeridos
  if (!meetingId || !phone || phone.length < 8) {
    return {
      json: {
        error: true,
        errorMessage: `Datos incompletos: meeting_id=${meetingId}, phone=${phone} (len=${phone.length})`,
        meeting_id: meetingId || 0,
        phone: phone || 'N/A'
      }
    };
  }

  const saludo = companyName ? `${clientName} de ${companyName}` : clientName;
  const porCorreo = 'te lo enviaremos por correo';

  let plantilla, params, ids;
  if (meetingType === 'demo') {
    // Una demo es un lead: se usan las acciones que el bot ya tiene para leads
    // (antes los botones btn_demo_* no tenian ruta en el bot y no hacian nada)
    plantilla = 'aviso_demo';
    params = [esReagendada ? 'reprogramada' : 'agendada', saludo, formattedDate, formattedTime];
    ids = [`btn_reminder_confirm_${meetingId}`, `btn_reprogramar_lead_${meetingId}`, `btn_cancelar_lead_${meetingId}`];
  } else if (meetingType === 'seguimiento_prospecto') {
    // Misma tabla que las reuniones de seguimiento: sirven las acciones btn_followup_* del bot
    // (antes los botones btn_prospect_* no tenian ruta en el bot y no hacian nada)
    plantilla = 'aviso_reunion_prospecto';
    params = [esReagendada ? 'reagendada' : 'agendada', saludo, meetingSubject, formattedDate, formattedTime, prospectTimelineUrl || porCorreo];
    ids = [`btn_followup_confirm_${meetingId}`, `btn_followup_reschedule_${meetingId}`, `btn_followup_cancel_${meetingId}`];
  } else {
    plantilla = 'aviso_reunion_seguimiento';
    params = [esReagendada ? 'reagendada' : 'agendada', saludo, meetingSubject, formattedDate, formattedTime, fichaUrl || porCorreo];
    ids = [`btn_followup_confirm_${meetingId}`, `btn_followup_reschedule_${meetingId}`, `btn_followup_cancel_${meetingId}`];
  }

  const whatsappBody = JSON.stringify({
    messaging_product: 'whatsapp',
    recipient_type: 'individual',
    to: phone,
    type: 'template',
    template: {
      name: plantilla,
      language: { code: 'es' },
      components: [
        { type: 'body', parameters: params.map(text => ({ type: 'text', text })) },
        ...ids.map((payload, i) => ({ type: 'button', sub_type: 'quick_reply', index: String(i), parameters: [{ type: 'payload', payload }] }))
      ]
    }
  });

  return {
    json: {
      error: false,
      meeting_id: meetingId,
      phone: phone,
      clientName: clientName,
      companyName: companyName,
      meetingType: meetingType,
      btnConfirmId: ids[0],
      btnRescheduleId: ids[1],
      btnCancelId: ids[2],
      whatsappBody: whatsappBody
    }
  };
} catch (err) {
  return {
    json: {
      error: true,
      errorMessage: `Error en Prepare WA Message: ${err.message}`,
      meeting_id: 0,
      phone: 'N/A'
    }
  };
}
