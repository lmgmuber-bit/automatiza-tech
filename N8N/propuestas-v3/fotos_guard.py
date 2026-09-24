"""Filtro de descripciones de fotos que comparten los flujos v3 (Borrador y Final).

GPT-4o ignora la regla del prompt: en la propuesta de prueba 47 (2026-09-24) 6 de 7 descripciones pedían
pantallas, sitios web, gráficos, pizarras, tablas o checklists, que es justo donde el modelo de imagen
inventa texto. Este filtro no le pide nada al modelo: revisa cada descripción contra temas prohibidos y,
si cae en uno, la reemplaza por una escena segura para esa lámina. Siempre agrega el cierre de prohibiciones.
"""

JS_LIMPIAR_FOTOS = r"""
const PROHIBIDO = /\b(screens?|monitors?|displays?|laptops?|computers?|tablets?|phones?|smartphones?|websites?|web ?pages?|web ?design|interfaces?|dashboards?|apps?|charts?|graphs?|flow ?charts?|diagrams?|infographics?|whiteboards?|tables?|spreadsheets?|clipboards?|checklists?|documents?|papers?|forms?|signs?|signage|posters?|banners?|text|letters?|lettering|words?|numbers?|meetings?|people|persons?|man|men|woman|women|team|consultants?|director|office)\b/i;
const CIERRE = 'no people facing camera, no screens, no phones, no computers, no papers, no signs, no text, no lettering, no logos, no watermarks';
const SEGURAS = {
  cover: 'elegant exterior of the business at golden hour, fresh flowers in the foreground, warm cinematic light',
  challenge: 'quiet empty room at night lit by a single warm lamp, rain drops on a large window, calm melancholic mood',
  solution: 'bright calm interior with natural light through tall windows and fresh flowers on a wooden sideboard',
  benefits: 'aerial view of Santiago de Chile at dusk with the Andes mountains behind, city lights turning on',
  how_it_works: 'minimal light wooden surface by a bright window with a small potted plant and a ceramic cup, soft morning light',
  pricing: 'elegant bouquet of white flowers tied with a gold ribbon on a white marble surface, soft warm light',
  next_steps: 'peaceful garden path at sunrise with green lawn, tall trees and soft mist, hopeful calm atmosphere',
  extra: 'two white lilies side by side on white linen with soft golden bokeh, minimal elegant still life',
};
function limpiarFotos(briefs) {
  let reemplazadas = 0;
  const limpias = (briefs || []).filter((b) => b && b.slide).map((b) => {
    const tema = String(b.prompt || '').split(/,?\s*no people facing camera/i)[0].trim();
    if (!tema || PROHIBIDO.test(tema)) {
      reemplazadas++;
      return { slide: b.slide, prompt: `${SEGURAS[b.slide] || SEGURAS.extra}, ${CIERRE}` };
    }
    return { slide: b.slide, prompt: `${tema}, ${CIERRE}` };
  });
  return { limpias, reemplazadas };
}
"""
