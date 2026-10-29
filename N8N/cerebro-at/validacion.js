// Validación común de los datos para agendar (nombre, correo y WhatsApp) de los asistentes Tech.
// Fuente única: sincronizar.py copia el tramo marcado al nodo «Check Email Booking» de WhatsApp, y el asistente
// de Instagram usa las mismas funciones en «Validar cita». No cambiar las reglas en n8n a mano: cambiarlas aquí.
// <<VALIDACION AT: inicio>>
// Palabras con que la gente responde sin dar su nombre. Un texto se rechaza si EMPIEZA con un saludo o una
// confirmación, o si TODAS sus palabras están en la lista; así pasan apellidos reales como Claro, Bueno o Prueba.
const AT_NO_NOMBRE_INICIO = new Set(['hola', 'holi', 'ola', 'buenas', 'buenos', 'buena', 'si', 'sí', 'no', 'ok', 'oka',
  'okey', 'dale', 'gracias', 'listo', 'perfecto', 'claro', 'bueno', 'quiero', 'agendar', 'cita', 'demo', 'nombre',
  'correo', 'email']);
const AT_NO_NOMBRES = new Set([...AT_NO_NOMBRE_INICIO, 'dias', 'días', 'tardes', 'noches', 'agenda',
  'reunion', 'reunión', 'diagnostico', 'diagnóstico', 'apellido', 'whatsapp', 'test', 'prueba', 'tech', 'bot']);

// Nombre y apellido: al menos dos palabras de dos letras o más, solo letras (con tildes, ñ, ü), guion o apóstrofo.
// Acepta que la persona lo presente: «Soy Ana Pérez», «me llamo Ana Pérez», «mi nombre es Ana Pérez».
function atValidarNombre(texto) {
  const limpio = String(texto || '').replace(/\s+/g, ' ').trim()
    .replace(/^(hola[,!.]?\s+)?(me llamo|mi nombre es|soy)\s+/i, '').replace(/[.!]+$/, '').trim();
  if (!limpio) return { ok: false, motivo: 'vacio' };
  if (limpio.length > 60) return { ok: false, motivo: 'largo' };
  if (/[0-9@]/.test(limpio)) return { ok: false, motivo: 'caracteres' };
  if (!/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü' -]+$/.test(limpio)) return { ok: false, motivo: 'caracteres' };
  const palabras = limpio.split(' ').filter(p => p.replace(/['-]/g, '').length > 0);
  const minus = palabras.map(p => p.toLowerCase());
  if (AT_NO_NOMBRE_INICIO.has(minus[0]) || minus.every(p => AT_NO_NOMBRES.has(p))) return { ok: false, motivo: 'no_es_nombre' };
  if (palabras.length < 2 || palabras.filter(p => p.replace(/['-]/g, '').length >= 2).length < 2) {
    return { ok: false, motivo: 'falta_apellido' };
  }
  const nombre = palabras.map(p => p.split('-').map(s => s ? s[0].toUpperCase() + s.slice(1).toLowerCase() : s).join('-')).join(' ');
  return { ok: true, valor: nombre };
}

// Errores de tipeo frecuentes en dominios de correo: se rechazan y se sugiere el correcto.
const AT_DOMINIOS_MAL = {
  'gmial.com': 'gmail.com', 'gmai.com': 'gmail.com', 'gmal.com': 'gmail.com', 'gamil.com': 'gmail.com',
  'gmaill.com': 'gmail.com', 'gmail.co': 'gmail.com', 'gmail.con': 'gmail.com', 'gmail.cm': 'gmail.com',
  'gmail.om': 'gmail.com', 'gmail.cl': 'gmail.com', 'gmail.comm': 'gmail.com',
  'hotmial.com': 'hotmail.com', 'hotmal.com': 'hotmail.com', 'hotamil.com': 'hotmail.com', 'hotmai.com': 'hotmail.com',
  'hotmail.con': 'hotmail.com', 'hotmail.co': 'hotmail.com', 'hotmail.cm': 'hotmail.com',
  'outlok.com': 'outlook.com', 'outllok.com': 'outlook.com', 'outlook.con': 'outlook.com', 'outlook.co': 'outlook.com',
  'yaho.com': 'yahoo.com', 'yahoo.con': 'yahoo.com', 'yahooo.com': 'yahoo.com',
  'iclod.com': 'icloud.com', 'icloud.con': 'icloud.com', 'iclud.com': 'icloud.com',
};

function atValidarCorreo(texto) {
  const correo = String(texto || '').trim().toLowerCase().replace(/^mailto:/, '');
  if (!correo) return { ok: false, motivo: 'vacio' };
  if (correo.length > 254 || /\s/.test(correo)) return { ok: false, motivo: 'formato' };
  const m = correo.match(/^([a-z0-9._%+-]+)@([a-z0-9-]+(?:\.[a-z0-9-]+)+)$/);
  if (!m) return { ok: false, motivo: 'formato' };
  const [, local, dominio] = m;
  if (/^\.|\.$|\.\./.test(local) || dominio.split('.').some(e => !e || /^-|-$/.test(e))) return { ok: false, motivo: 'formato' };
  if (!/\.[a-z]{2,}$/.test(dominio)) return { ok: false, motivo: 'formato' };
  const sugerido = AT_DOMINIOS_MAL[dominio] || (/\.(con|cmo|cpm|comm|vom)$/.test(dominio) ? dominio.replace(/\.[a-z]+$/, '.com') : '');
  if (sugerido) return { ok: false, motivo: 'tipeo', sugerencia: local + '@' + sugerido };
  return { ok: true, valor: correo };
}

// WhatsApp con código de país, en formato +<código><número>. Un celular chileno sin código se completa con +56.
// En Chile el celular es +56 9 y ocho dígitos. Cualquier otro número sin «+» (o «00») se rechaza: no se adivina el
// país, porque un venezolano que escribe 4141234567 quedaría como +41 (Suiza) y el recordatorio iría a otra persona.
function atValidarWhatsApp(texto) {
  let tel = String(texto || '').replace(/[^\d+]/g, '');
  if (/^00\d/.test(tel)) tel = '+' + tel.slice(2);
  if (/^9\d{8}$/.test(tel)) tel = '+56' + tel;
  else if (/^569\d{8}$/.test(tel)) tel = '+' + tel;
  if (/^\d+$/.test(tel)) return { ok: false, motivo: tel.length >= 8 ? 'falta_codigo' : 'formato' };
  if (!/^\+[1-9]\d{7,14}$/.test(tel)) return { ok: false, motivo: 'formato' };
  if (tel.startsWith('+56') && !/^\+569\d{8}$/.test(tel)) return { ok: false, motivo: 'chile' };
  return { ok: true, valor: tel };
}
// <<VALIDACION AT: fin>>

if (typeof module !== 'undefined' && module.exports) {
  module.exports = { atValidarNombre, atValidarCorreo, atValidarWhatsApp };
}
