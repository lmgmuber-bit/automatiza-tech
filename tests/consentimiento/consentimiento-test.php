<?php
/**
 * Pruebas del aviso de cookies (mu-plugin at-consentimiento.php), sin WordPress:
 * stubs mínimos de add_filter/add_action para probar la regla del Pixel y lo que se dibuja.
 * Uso: php tests/consentimiento/consentimiento-test.php
 */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['filtros'] = [];
$GLOBALS['acciones'] = [];
$GLOBALS['es_admin'] = false;
function add_filter($h, $cb, $p = 10, $n = 1) { $GLOBALS['filtros'][$h][] = $cb; return true; }
function add_action($h, $cb, $p = 10, $n = 1) { $GLOBALS['acciones'][$h][] = $cb; return true; }
function apply_filters($h, $v) { foreach ($GLOBALS['filtros'][$h] ?? [] as $cb) { $v = $cb($v); } return $v; }
function __return_true() { return true; }
function is_admin() { return $GLOBALS['es_admin']; }
function wp_doing_ajax() { return false; }
function is_feed() { return false; }
function esc_url($u) { return $u; }
function home_url($p = '') { return 'https://automatizatech.cl' . $p; }

require __DIR__ . '/../../wp-content/mu-plugins/at-consentimiento.php';

$fallas = 0;
function ok($c, $m) { global $fallas; echo ($c ? 'ok   ' : 'FALLA ') . $m . "\n"; if (!$c) { $fallas++; } }
function dibujo() { ob_start(); foreach ($GLOBALS['acciones']['wp_footer'] ?? [] as $cb) { $cb(); } return ob_get_clean(); }

// JS de la salida real de PHP, sin dependencias ni archivos temporales:
// php tests/consentimiento/consentimiento-test.php --whatsapp-js | node
if (in_array('--whatsapp-js', $argv ?? [], true)) {
    preg_match_all('~<script>([\s\S]*?)</script>~', dibujo(), $scripts);
    $principal = array_values(array_filter($scripts[1], function ($s) { return strpos($s, 'window.atLead') !== false; }));
    if (count($principal) !== 1) { fwrite(STDERR, "Se esperaba un script principal\n"); exit(1); }
    echo 'const script = ' . json_encode($principal[0], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ";\n";
    echo <<<'JS'
const vm = require('node:vm');
const assert = require('node:assert/strict');
let pruebas = 0;
function test(nombre, fn) { fn(); pruebas++; console.log('ok   ' + nombre); }
// DOM acotado para eventos, parentesco, selectores y estilos; no acredita un navegador real.
class Elemento {
  constructor(tag, attrs = {}, padre = null) {
    this.tagName = tag.toUpperCase(); this.nodeType = 1; this.attrs = attrs;
    this.parentElement = padre; this.children = []; this.hidden = true;
    if (padre) padre.children.push(this);
  }
  getAttribute(k) { return this.attrs[k] ?? null; }
  matches(selector) {
    return selector.split(',').some(s => {
      s = s.trim();
      const attr = s.match(/^\[([^=*\]]+)(\*?=)?["']?([^"'\]]*)["']?\]$/);
      if (attr) { const v = this.getAttribute(attr[1]); return v !== null && (!attr[2] || (attr[2] === '*=' ? v.includes(attr[3]) : v === attr[3])); }
      return s.startsWith('#') ? this.getAttribute('id') === s.slice(1) : this.tagName.toLowerCase() === s;
    });
  }
  closest(s) { for (let n = this; n; n = n.parentElement) if (n.matches(s)) return n; return null; }
  contains(el) { for (let n = el; n; n = n.parentElement) if (n === this) return true; return false; }
}
function mundo({ cookie = 'at_consentimiento=si', path = '/', search = '', gtag = true } = {}) {
  const body = new Elemento('body'), main = new Elemento('main', {}, body);
  const hero = new Elemento('section', { id: 'top' }, main);
  const contacto = new Elemento('section', { id: 'contacto' }, main);
  const footer = new Elemento('footer', {}, body);
  const llamadas = [], listeners = [], datos = new Map();
  const document = {
    cookie, body, documentElement: body, getElementById: () => null,
    addEventListener(type, fn, capture) { listeners.push({ type, fn, capture }); },
    querySelector(selector) {
      const all = []; function visit(n) { all.push(n); n.children.forEach(visit); } visit(body);
      return all.find(n => selector.split(',').some(s => {
        s = s.trim(); return s === 'main section' ? n.matches('section') && n.parentElement?.closest('main') : n.matches(s);
      })) || null;
    }
  };
  const window = { getComputedStyle: el => ({ position: el.attrs.position || 'static' }) };
  if (gtag) window.gtag = (...args) => llamadas.push(args);
  const localStorage = { getItem: k => datos.get(k) ?? null, setItem: (k,v) => datos.set(k,v), removeItem: k => datos.delete(k) };
  const location = { protocol: 'https:', hostname: 'automatizatech.cl', pathname: path, search, href: 'https://automatizatech.cl' + path + search, reload() {} };
  const ctx = vm.createContext({ window, document, localStorage, location, URL, URLSearchParams });
  const cargar = () => vm.runInContext(script, ctx);
  cargar();
  function click(target, bubbles = true) {
    const e = { target, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, stopPropagation() { throw Error('No debe cortar propagacion'); } };
    // Un listener del destino puede impedir burbujeo: captura sigue ejecutandose.
    listeners.filter(l => l.type === 'click' && (l.capture === true || l.capture?.capture)).forEach(l => l.fn(e));
    if (bubbles) listeners.filter(l => l.type === 'click' && !l.capture).forEach(l => l.fn(e));
    assert.equal(e.defaultPrevented, false, 'El clic debe seguir navegando'); return llamadas;
  }
  return { body, main, hero, contacto, footer, window, document, llamadas, listeners, click, cargar };
}
const wa = 'https://wa.me/56000000000?text=Persona%20privada'; // solo fixture ficticio
function evento(m, target) { m.click(target); assert.equal(m.llamadas.length, 1); const [cmd, name, p] = m.llamadas[0]; assert.equal(cmd, 'event'); assert.equal(name, 'clic_whatsapp'); return p; }
for (const cookie of ['', 'at_consentimiento=no', 'at_consentimiento=SI', 'at_consentimiento=sino', 'otro_at_consentimiento=si']) {
  test('Sin permiso exacto: ' + (cookie || 'sin cookie'), () => { const m = mundo({ cookie }); m.click(new Elemento('a', { href: wa }, m.hero)); assert.equal(m.llamadas.length, 0); });
}
test('Cookie entre otras cookies: hero, portada y beacon', () => {
  const m = mundo({ cookie: 'otra=1; at_consentimiento=si; otra2=2' });
  const p = evento(m, new Elemento('a', { href: wa }, m.hero));
  assert.equal(p.ubicacion, 'hero'); assert.equal(p.servicio, 'portada'); assert.equal(p.transport_type, 'beacon');
  assert.deepEqual(Object.keys(p).sort(), ['servicio', 'transport_type', 'ubicacion']);
  assert.ok(!JSON.stringify(p).includes('56000000000')); assert.ok(!JSON.stringify(p).includes('Persona'));
});
test('Reevalua consentimiento al clic: retirar y aceptar', () => {
  const m = mundo(), a = new Elemento('a', { href: wa }, m.hero);
  m.document.cookie = 'at_consentimiento=no'; m.click(a); assert.equal(m.llamadas.length, 0);
  m.document.cookie = 'at_consentimiento=si'; m.click(a); assert.equal(m.llamadas.length, 1);
});
for (const url of [wa, 'https://api.whatsapp.com/send?phone=56000000000', 'whatsapp://send?phone=56000000000', '//wa.me/56000000000', 'HTTPS://WA.ME/56000000000']) {
  test('Destino WhatsApp valido ' + url.split('?')[0].replace(/\d/g, 'x'), () => { const m = mundo(); evento(m, new Elemento('a', { href: url }, m.hero)); });
}
for (const url of ['https://wa.me.ejemplo.cl/', 'https://api.whatsapp.com.ejemplo.cl/', 'https://wa.me@ejemplo.cl/', 'https://ejemplo.cl/?next=https://wa.me/1', '/wa.me/123', 'javascript:open("https://wa.me/1")', 'mailto:persona@ejemplo.cl', '#']) {
  test('No confunde otro destino con WhatsApp: ' + url, () => { const m = mundo(); m.click(new Elemento('a', { href: url }, m.hero)); assert.equal(m.llamadas.length, 0); });
}
test('Button data-href, icono SVG y captura sin burbujeo', () => {
  const m = mundo(), b = new Elemento('button', { 'data-href': wa }, m.contacto), svg = new Elemento('svg', {}, b);
  m.click(svg, false); assert.equal(m.llamadas.length, 1); assert.equal(m.llamadas[0][2].ubicacion, 'seccion:contacto');
});
for (const onclick of ["window.open('" + wa + "', '_blank')", "location.href='" + wa + "'", "window.location='" + wa + "'", "location.assign('" + wa + "')"]) {
  test('Button onclick con URL literal de navegacion', () => { const m = mundo(); evento(m, new Elemento('button', { onclick }, m.hero)); });
}
test('No ejecuta onclick ni cuenta una URL que solo se imprime', () => {
  const m = mundo(); m.click(new Elemento('button', { onclick: "console.log('" + wa + "')" }, m.hero)); assert.equal(m.llamadas.length, 0);
});
test('Un clic anidado button+a y script duplicado: un evento; dos clics: dos', () => {
  const m = mundo(), a = new Elemento('a', { href: wa }, m.hero), b = new Elemento('button', { 'data-href': wa }, a);
  m.cargar(); m.click(new Elemento('span', {}, b)); assert.equal(m.llamadas.length, 1);
  m.click(b); assert.equal(m.llamadas.length, 2);
});
test('Button sin URL dentro de enlace: encuentra enlace exterior', () => {
  const m = mundo(), a = new Elemento('a', { href: wa }, m.hero); evento(m, new Elemento('button', {}, a));
});
test('Pie', () => { const m = mundo(); assert.equal(evento(m, new Elemento('a', { href: wa }, m.footer)).ubicacion, 'pie'); });
for (const attrs of [{ class: 'whatsapp-float' }, { position: 'fixed' }]) {
  test('Flotante por clase o estilo de ancestro, aun dentro del pie', () => {
    const m = mundo(), flotante = new Elemento('div', attrs, m.footer);
    assert.equal(evento(m, new Elemento('a', { href: wa }, flotante)).ubicacion, 'flotante');
  });
}
test('data-ubicacion conocida tiene prioridad', () => {
  const m = mundo(), box = new Elemento('div', { 'data-ubicacion': 'hero', position: 'fixed' }, m.footer);
  assert.equal(evento(m, new Elemento('a', { href: wa }, box)).ubicacion, 'hero');
});
test('No envia data-ubicacion, ID, clase o ruta con datos personales', () => {
  const m = mundo({ path: '/persona-privada-56000000000/' }), box = new Elemento('section', { id: 'persona-privada', class: 'persona-privada', 'data-ubicacion': 'persona@ejemplo.cl' }, m.main);
  const p = evento(m, new Elemento('a', { href: wa }, box)); assert.equal(p.ubicacion, 'seccion'); assert.equal(p.servicio, 'sitio');
});
test('Seccion conocida por clase', () => {
  const m = mundo(), box = new Elemento('section', { class: 'contacto' }, m.main);
  assert.equal(evento(m, new Elemento('a', { href: wa }, box)).ubicacion, 'seccion:contacto');
});
test('Hero por clase fuera de primera seccion', () => {
  const m = mundo(), box = new Elemento('div', { class: 'landing-hero' }, m.main);
  assert.equal(evento(m, new Elemento('a', { href: wa }, box)).ubicacion, 'hero');
});
test('Primera seccion sin id sigue siendo hero', () => {
  const m = mundo(); m.hero.attrs = {};
  assert.equal(evento(m, new Elemento('a', { href: wa }, m.hero)).ubicacion, 'hero');
});
for (const path of ['/automatizacion-negocios-whatsapp-crm/', '/asistentes-inteligentes-ia-whatsapp/', '/sitios-web-premium-chile/', '/aplicaciones-web-a-medida/', '/sistemas-a-medida-chile/', '/portal-omnicliente-precios/']) {
  test('Slug de servicio ' + path, () => { const m = mundo({ path }); assert.equal(evento(m, new Elemento('a', { href: wa }, m.hero)).servicio, path.split('/')[1]); });
}
for (const path of ['/guias/', '/guias/guia-prueba/']) {
  test('Guias sin enviar slug de articulo ' + path, () => { const m = mundo({ path }); assert.equal(evento(m, new Elemento('a', { href: wa }, m.footer)).servicio, 'guias'); });
}
for (const search of ['?ga_debug=1', '?otro=2&ga_debug=1', '?ga_debug=0', '?ga_debug=11', '?otro=ga_debug=1', '']) {
  test('Depuracion solo para parametro exacto: ' + search, () => {
    const m = mundo({ search }), p = evento(m, new Elemento('a', { href: wa }, m.hero));
    assert.equal(p.debug_mode, new URLSearchParams(search).get('ga_debug') === '1' ? true : undefined);
  });
}
test('Sin gtag no rompe navegacion ni carga Analytics', () => { const m = mundo({ gtag: false }); m.click(new Elemento('a', { href: wa }, m.hero)); assert.equal(m.llamadas.length, 0); });
test('gtag que falla no rompe navegacion', () => { const m = mundo(); m.window.gtag = () => { throw Error('bloqueado'); }; m.click(new Elemento('a', { href: wa }, m.hero)); });
test('Clic fuera de enlace, sin target y nodo texto no rompen', () => {
  const m = mundo(); m.click(m.body); m.click(null, false); assert.equal(m.llamadas.length, 0);
  evento(m, { nodeType: 3, parentElement: new Elemento('a', { href: wa }, m.hero) });
});
console.log('\n' + pruebas + ' pruebas JS OK (DOM simulado, sin solicitudes de red)');
JS;
    exit;
}

// --- Regla del Pixel: sin «Aceptar» no se activa ninguno
$_COOKIE = [];
ok(apply_filters('pys_disable_by_gdpr', false) === true, 'sin decisión, PixelYourSite queda desactivado');
$_COOKIE = ['at_consentimiento' => 'no'];
ok(apply_filters('pys_disable_by_gdpr', false) === true, 'con «Rechazar», sigue desactivado');
$_COOKIE = ['at_consentimiento' => 'si'];
ok(apply_filters('pys_disable_by_gdpr', false) === false, 'con «Aceptar», se activa');
$_COOKIE = ['at_consentimiento' => 'SI'];
ok(apply_filters('pys_disable_by_gdpr', false) === true, 'un valor distinto de «si» no cuenta como permiso');
$_COOKIE = ['at_consentimiento' => ['si']];
ok(apply_filters('pys_disable_by_gdpr', false) === true, 'una cookie con forma rara no cuenta como permiso');
$_COOKIE = ['at_consentimiento' => 'si'];
ok(apply_filters('pys_disable_by_gdpr', true) === true, 'si otro filtro ya lo desactivó, no lo reactivamos');
ok(apply_filters('pys_gdpr_ajax_enabled', false) === true, 'pide a PixelYourSite consultar el permiso por AJAX (la caché de página no lo congela)');

// --- Lo que se dibuja
$_COOKIE = [];
$h = dibujo();
ok(strpos($h, 'id="at-consent"') !== false && strpos($h, 'hidden') !== false, 'el aviso se dibuja oculto: lo muestra el JS según la cookie (la página puede venir de caché)');
ok(strpos($h, 'data-at-consent="si"') !== false && strpos($h, 'data-at-consent="no"') !== false, 'trae «Aceptar» y «Rechazar»');
ok(strpos($h, '/cookies/') !== false && strpos($h, '/privacidad/') !== false, 'enlaza la Política de Cookies y la de Privacidad');
ok(strpos($h, 'window.atLead') !== false && strpos($h, "fbq('track', 'Lead'") !== false, 'expone window.atLead, que envía «Lead» a Meta');
ok(strpos($h, "leer() !== 'si'") !== false, 'atLead no envía nada sin permiso');
ok(strpos($h, 'at_cookie_consent') !== false, 'respeta la decisión guardada por el aviso anterior (localStorage)');
ok(strpos($h, 'role="dialog"') !== false && strpos($h, 'aria-labelledby') !== false, 'accesible: rol de diálogo con título');
ok(strpos($h, "window.gtag('event', 'clic_whatsapp', params)") !== false, 'dibuja clic_whatsapp para GA4');
ok((bool) preg_match("/leer\(\) !== 'si' \|\| typeof window\.gtag !== 'function'/", $h), 'WhatsApp exige consentimiento y gtag cargado');
ok(strpos($h, "transport_type: 'beacon'") !== false, 'WhatsApp usa beacon');
ok(strpos($h, 'wa.me') !== false && strpos($h, 'api.whatsapp.com') !== false && strpos($h, 'whatsapp:') !== false, 'reconoce los tres destinos WhatsApp');
ok(strpos($h, 'debug_mode = true') !== false && strpos($h, 'ga_debug') !== false, 'incluye depuración optativa');
$GLOBALS['es_admin'] = true;
ok(dibujo() === '', 'en wp-admin no se dibuja');
$GLOBALS['es_admin'] = false;

echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n";
exit($fallas ? 1 : 0);
