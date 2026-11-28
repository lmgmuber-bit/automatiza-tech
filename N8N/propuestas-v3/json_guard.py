"""Lectura del JSON que devuelve GPT-4o en los flujos v3 (Borrador y Cambios): estricta, con UNA tolerancia.

El nodo OpenAI de n8n que usan esos flujos no fuerza JSON. Medido el 2026-09-26 en «2 Cambios» (ejecución
408862): el cambio pedido estaba bien aplicado, pero la respuesta terminaba con una llave `}` de más y
`JSON.parse` la rechazó, así que la propuesta quedó en «error».

`leerJsonModelo(raw)` hace lo mismo que antes (`JSON.parse` tras quitar un bloque ```json) y solo agrega:
si el texto empieza con `{`, trae un objeto completo y después de él quedan únicamente llaves `}` y espacios,
lee ese objeto. Cualquier otra cosa (texto antes o después, dos objetos, respuesta cortada, comillas sin
escapar) sigue siendo error, y el flujo avisa a Luis como siempre.

Por qué tan acotado (2026-09-26): dos versiones anteriores de este mismo día buscaban la propuesta en
cualquier parte del texto; dos rondas de revisión adversarial mostraron que así se puede tomar algo que no
es la propuesta (una nota, la versión «antes», la plantilla del prompt) y guardarlo como cambio correcto.
Los casos quedaron en probar_json.py.

`CLAVES_PROPUESTA` y `esObjetoPlano` los usa «2 Cambios» para rechazar respuestas que no son la propuesta ni
un pedazo de ella (claves ajenas, un arreglo, null), sin dejar de aceptar respuestas parciales.
"""

JS_LEER_JSON = r"""
const CLAVES_PROPUESTA = ['client_name', 'company_name', 'challenge_title', 'challenge_text', 'solution_title', 'solution_text',
  'benefits', 'how_it_works', 'extra_slides', 'pricing_rows', 'pricing_note', 'next_steps', 'correo_cliente', 'image_briefs', 'unique_id'];
function esObjetoPlano(v) { return v !== null && typeof v === 'object' && !Array.isArray(v); }
function leerJsonModelo(raw) {
  if (raw !== null && typeof raw === 'object') return raw;
  const s = String(raw == null ? '' : raw).trim().replace(/^```(?:json)?\s*/i, '').replace(/```\s*$/, '').trim();
  try {
    return JSON.parse(s);
  } catch (error) {
    // Única tolerancia: llaves «}» de más después de un objeto completo (ejecución 408862).
    if (s[0] === '{') {
      let prof = 0, enTexto = false, escape = false, fin = -1;
      for (let i = 0; i < s.length; i++) {
        const ch = s[i];
        if (enTexto) {
          if (escape) escape = false;
          else if (ch === '\\') escape = true;
          else if (ch === '"') enTexto = false;
          continue;
        }
        if (ch === '"') enTexto = true;
        else if (ch === '{') prof++;
        else if (ch === '}') { prof--; if (prof === 0) { fin = i; break; } }
      }
      if (fin > 0 && /^[\s}]*$/.test(s.slice(fin + 1))) return JSON.parse(s.slice(0, fin + 1));
    }
    throw error;
  }
}
"""
