"""Lectura tolerante del JSON que devuelve GPT-4o en los flujos v3 (Borrador y Cambios).

El nodo OpenAI de n8n que usan esos flujos no fuerza JSON: el modelo a veces agrega algo alrededor del
objeto. Medido el 2026-09-26 en «2 Cambios» (ejecución 408862): el cambio pedido estaba bien aplicado,
pero la respuesta terminaba con una llave `}` de más y `JSON.parse` la rechazó, así que la propuesta quedó
en «error». Antes (ejecución 403689) había devuelto el objeto envuelto en {propuesta: {...}}.

`leerJsonModelo(raw)`:
  1. si ya es un objeto, lo devuelve;
  2. quita un bloque ```json ... ``` y prueba `JSON.parse` tal cual;
  3. si falla, busca el primer objeto `{...}` completo (contando llaves fuera de los textos entre comillas,
     con sus escapes) y lo lee; si ese no se puede leer, prueba con el siguiente objeto que empiece
     después de él (nunca con uno de adentro, que sería un pedazo de la propuesta);
  4. si no hay ningún objeto completo (respuesta cortada, vacía), lanza un error.
Nunca inventa ni completa datos: solo descarta lo que sobra alrededor de un objeto válido.
"""

JS_LEER_JSON = r"""
function leerJsonModelo(raw) {
  if (raw && typeof raw === 'object') return raw;
  const s = String(raw == null ? '' : raw).trim().replace(/^```(?:json)?\s*/i, '').replace(/```\s*$/, '').trim();
  try { return JSON.parse(s); } catch (e) { /* se busca el primer objeto completo */ }
  let desde = s.indexOf('{');
  while (desde >= 0) {
    let prof = 0, enTexto = false, escape = false, fin = -1;
    for (let i = desde; i < s.length; i++) {
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
    if (fin < 0) break;
    try {
      const o = JSON.parse(s.slice(desde, fin + 1));
      if (o && typeof o === 'object' && !Array.isArray(o)) return o;
    } catch (e) { /* se prueba con el siguiente objeto */ }
    // Nunca desde dentro del objeto que falló: un pedazo interno (p. ej. correo_cliente) no es la propuesta.
    desde = s.indexOf('{', fin + 1);
  }
  throw new Error('la respuesta del modelo no trae un objeto JSON completo');
}
"""
