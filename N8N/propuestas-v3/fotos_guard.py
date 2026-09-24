"""Filtro de descripciones de fotos que comparten los flujos v3 (Borrador y Final).

Regla de Luis (2026-09-24): las fotos son del RUBRO de cada cliente, como las de Jeffer (béisbol) y Orly
(funeraria): su gente, sus clientes, sus productos y lugares, con personas en acción. Lo medido con Soul 2
a 720p ese día:
  - Pantallas, letreros, carteles, marcadores, documentos o una polera con letras => el modelo inventa texto,
    aunque el prompt diga "no text" (3 de 3 fotos de béisbol con estadio de fondo; 3 de 4 en la prueba 47).
  - Portada en primer plano con el fondo desenfocado => limpia (2 de 2, funeraria).
  - Celular "con la pantalla hacia el otro lado" => limpio (desafío de Orly).

Por eso el filtro:
  1. reemplaza la descripción si pide algo que trae texto o contenido en pantalla;
  2. deja pasar productos con etiqueta o pantalla (botellas, cajas, celulares, libros) solo si la descripción
     dice que van sin etiqueta, de espaldas, apagados o desenfocados;
  3. en la portada, reemplaza si pide un fondo con estructuras (estadio, fachada, calle, muro) y siempre
     agrega primer plano con fondo desenfocado;
  4. siempre agrega el cierre de prohibiciones.
Los reemplazos son escenas NEUTRAS que sirven para cualquier rubro, nunca escenas de otro cliente.
Nunca prohíbe personas: lo que hizo buenas las fotos de Jeffer fueron los jugadores y los niños.
"""

JS_LIMPIAR_FOTOS = r"""
const PROHIBIDO = /\b(websites?|web ?pages?|web ?design|interfaces?|dashboards?|apps?|charts?|graphs?|flow ?charts?|diagrams?|infographics?|whiteboards?|spreadsheets?|clipboards?|checklists?|documents?|papers?|forms?|signs?|signage|posters?|banners?|billboards?|scoreboards?|menus?|newspapers?|magazines?|text|letters?|lettering|words?|numbers?|meetings?|office)\b/i;
const CON_ETIQUETA = /\b(screens?|monitors?|displays?|laptops?|computers?|tablets?|phones?|smartphones?|cell ?phones?|bottles?|cans?|boxes?|packag\w*|books?|labels?|jerseys?|t-?shirts?)\b/i;
const NEUTRALIZADO = /(facing away|closed|turned off|switched off|dark screen|screen off|unlabell?ed|without (a |any )?labels?|no labels?|plain|blank|out of focus|blurred|from behind|seen from the back)/i;
const FONDO_PORTADA = /\b(stadiums?|facades?|storefronts?|shop ?fronts?|exteriors?|buildings?|streets?|walls?|billboards?|bleachers?|grandstands?)\b/i;
const PRIMER_PLANO = /(close-?up|macro|shallow depth of field|bokeh|blurred background|background (completely )?blurred)/i;
const CIERRE = 'no signs, no labels, no text, no lettering, no logos, no watermarks';
const SEGURAS = {
  cover: 'close-up of warm morning light falling across a textured wooden surface with a small green plant, background completely blurred into soft golden bokeh, shallow depth of field',
  challenge: 'quiet empty room at night lit by a single warm lamp, rain drops on a large window, calm pensive mood',
  solution: 'bright calm space with natural light through tall windows and a green plant, soft morning light',
  benefits: 'wide open landscape of the Chilean central valley at sunrise with the Andes in the distance, soft golden light',
  how_it_works: 'close-up of hands arranging small wooden blocks in a neat row on a light table, soft daylight, shallow depth of field',
  pricing: 'close-up of a single green sprout growing from rich dark soil in soft morning light, shallow depth of field',
  next_steps: 'open country road through green hills at sunrise, soft mist, hopeful calm atmosphere',
  extra: 'soft pattern of light and shadow on a textured surface next to a small plant, calm minimal composition',
};
function motivoFoto(slide, tema) {
  if (!tema) return 'vacía';
  if (PROHIBIDO.test(tema)) return 'texto o pantalla';
  if (CON_ETIQUETA.test(tema) && !NEUTRALIZADO.test(tema)) return 'producto con etiqueta o pantalla visible';
  if (slide === 'cover' && FONDO_PORTADA.test(tema)) return 'portada con estructuras de fondo';
  return '';
}
function limpiarFotos(briefs) {
  let reemplazadas = 0;
  const limpias = (briefs || []).filter((b) => b && b.slide).map((b) => {
    // Se descarta la lista de prohibiciones que haya escrito el modelo: el cierre lo pone siempre el filtro.
    let tema = String(b.prompt || '').split(/,\s*no (?:people facing camera|screens?|signs?|labels?|text)\b/i)[0].trim();
    if (motivoFoto(b.slide, tema)) {
      reemplazadas++;
      tema = SEGURAS[b.slide] || SEGURAS.extra;
    }
    if (b.slide === 'cover' && !PRIMER_PLANO.test(tema)) {
      tema += ', close-up, shallow depth of field, background completely blurred';
    }
    return { slide: b.slide, prompt: `${tema}, ${CIERRE}` };
  });
  return { limpias, reemplazadas };
}
"""
