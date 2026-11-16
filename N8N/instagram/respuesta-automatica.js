// Filtro de respuestas automáticas de otros negocios (16-nov-2026). Lo inyecta build_agente_instagram.py en el nodo
// «Validar»: si el mensaje es la respuesta automática de otro bot, el asistente no contesta (si no, quedan dos bots
// hablándose, como pasó en la conversación 29). Es conservador a propósito: una sola señal nunca alcanza, porque una
// persona real puede preguntar por «respuestas automáticas» o contar su horario de atención.
// <<RESPUESTA AUTOMATICA: inicio>>
function esRespuestaAutomatica(texto) {
  const t = String(texto || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  // A: se declara automático.
  const A = /\b(respuesta|mensaje|contestacion) automatic[oa]\b|\bauto-?(reply|respuesta)\b|\bautomated (reply|message|response)\b|\bthis is an automated\b|\bgenerado automaticamente\b/.test(t);
  // B: agradece el contacto (no una respuesta puntual).
  const B = /\bgracias por (contactar|escribir|comunicar|ponerte en contacto|ponerse en contacto|tu mensaje|su mensaje|tu consulta|su consulta|preferirnos|elegirnos)|\bthank(s| you) for (your message|contacting|reaching out|writing)/.test(t);
  // C: promete responder después o informa el horario del negocio.
  const C = /\b(te|le|les) (responderemos|contestaremos|atenderemos|contactaremos|escribiremos)\b|\b(pronto|en breve|a la brevedad|lo antes posible|lo mas pronto posible)\b[^.!?\n]{0,40}\b(responder|contestar|atender)|\b(responderemos|contestaremos|atenderemos)\b[^.!?\n]{0,40}\b(pronto|en breve|a la brevedad|lo antes posible)|\b(nuestro|el) horario de atencion\b|\bfuera (del|de) horario\b|\bno estamos disponibles\b|\bwe will (get back|reply|respond)\b|\bas soon as possible\b/.test(t);
  // D: menú de opciones en varias líneas (1️⃣ / 1. / 1) al comienzo de al menos dos líneas, o «escribe 1 / responde con».
  const lineasMenu = t.split('\n').filter(l => /^\s*(\d️?⃣|\d{1,2}[.)-]\s|\d{1,2}\s+(para|si)\b)/.test(l)).length;
  const D = lineasMenu >= 2 || (/\b(escribe|responde con|digita|marca|envia|elige|selecciona)\b[^.!?\n]{0,30}\b(1|una opcion|el numero)\b/.test(t) && t.includes('\n'));
  // E: acusa recibo con voz de negocio («hemos recibido tu mensaje», «agradecemos tu interés»), como la conversación 29.
  const E = /\b(hemos recibido|recibimos) (tu|su) (mensaje|consulta|solicitud)\b|\bagradecemos (tu|su) (interes|mensaje|preferencia|consulta)\b/.test(t);
  // F: publicidad masiva de plantilla (conversación 27): título en negrita al comienzo, saludo con el nombre vacío
  // («Hola , ¿cómo estás?») y texto largo con viñetas. Hacen falta dos de las tres señales.
  const titulo = /^\s*\*[^*\n]{5,120}\*/.test(t);
  const saludoVacio = /\b(hola|buenas|buenos dias|buenas tardes|buenas noches|estimad[oa]s?)\s+,/.test(t);
  const largoConVinetas = t.length > 300 && t.split('\n').filter(l => /^\s*([•·▪➤✔✅-]|\d{1,2}[.)])\s*\S/.test(l)).length >= 3;
  const F = (titulo ? 1 : 0) + (saludoVacio ? 1 : 0) + (largoConVinetas ? 1 : 0) >= 2;
  return (B && (C || E)) || (A && (B || C || D || E)) || (D && (A || B || C)) || F;
}
// <<RESPUESTA AUTOMATICA: fin>>
if (typeof module !== 'undefined') module.exports = { esRespuestaAutomatica };
