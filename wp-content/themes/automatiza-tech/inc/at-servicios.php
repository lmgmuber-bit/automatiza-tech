<?php
/**
 * Lista única de las páginas de servicio de automatizatech.cl y piezas de pie compartidas por las
 * landings (bloque «Otros servicios» y enlace «Preferencias de cookies»). Las direcciones salen de los
 * canonical de cada plantilla template-landing-*.php y del sitemap; si cambia una, cambia aquí.
 */
if (!defined('ABSPATH')) { exit; }

/** Servicios con página propia. Clave = slug de la página en WordPress. */
function at_servicios_lista(): array {
    $ic = 'width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="var(--accent-2)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    return array(
        'asistentes-inteligentes-ia-whatsapp' => array(
            'nombre' => 'Asistentes inteligentes',
            'texto'  => 'Responden, califican y agendan 24/7 en WhatsApp, Instagram y web.',
            'icono'  => '<svg ' . $ic . '><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/><path d="M9.5 10.5l1.2 1.2L13.5 9"/></svg>',
        ),
        'automatizacion-negocios-whatsapp-crm' => array(
            'nombre' => 'Automatización de negocios',
            'texto'  => 'WhatsApp, CRM, agenda, pagos y reportes conectados sin trabajo manual.',
            'icono'  => '<svg ' . $ic . '><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0115-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 01-15 6.7L3 16"/></svg>',
        ),
        'sitios-web-premium-chile' => array(
            'nombre' => 'Sitios web premium',
            'texto'  => 'Diseño de alto nivel, rápido y pensado para vender.',
            'icono'  => '<svg ' . $ic . '><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M7 6.5h.01M10 6.5h.01"/></svg>',
        ),
        'aplicaciones-web-a-medida' => array(
            'nombre' => 'Aplicaciones web a medida',
            'texto'  => 'Paneles, portales y dashboards para gestionar clientes, ventas y operación.',
            'icono'  => '<svg ' . $ic . '><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>',
        ),
        'sistemas-a-medida-chile' => array(
            'nombre' => 'Sistemas a medida',
            'texto'  => 'El software exacto que tu operación necesita cuando lo estándar no alcanza.',
            'icono'  => '<svg ' . $ic . '><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0018 0V5M3 12a9 3 0 0018 0"/></svg>',
        ),
        'portal-omnicliente-precios' => array(
            'nombre' => 'Portal OmniCliente',
            'texto'  => 'WhatsApp, Instagram y web en un solo panel, con planes y precios.',
            'icono'  => '<svg ' . $ic . '><path d="M4 5h16v11H8l-4 4z"/><path d="M8 9h8M8 12h5"/></svg>',
        ),
    );
}

/** Dirección (ruta absoluta con dominio) de un servicio por su slug. */
function at_servicio_url(string $slug): string {
    return esc_url(home_url('/' . $slug . '/'));
}

/**
 * Bloque «Otros servicios» para el final de cada landing. $actual = slug de la página donde se
 * dibuja (se omite de la lista). Usa las variables CSS del diseño de la portada.
 */
function at_otros_servicios_html(string $actual): string {
    $html = '';
    foreach (at_servicios_lista() as $slug => $s) {
        if ($slug === $actual) { continue; }
        $html .= '<a href="' . at_servicio_url($slug) . '" class="at-otros__item" style="display:flex;flex-direction:column;gap:10px;background:var(--surface);border:1px solid var(--bd2);border-radius:14px;padding:22px;transition:border-color .25s,transform .25s" data-hover="border-color:rgba(45,212,191,.45);transform:translateY(-2px)">'
            . '<span style="width:44px;height:44px;border-radius:12px;background:rgba(45,212,191,.08);border:1px solid rgba(45,212,191,.18);display:flex;align-items:center;justify-content:center">' . $s['icono'] . '</span>'
            . '<span style="font-family:\'Space Grotesk\';font-weight:600;font-size:17px;color:var(--text)">' . esc_html($s['nombre']) . '</span>'
            . '<span style="font-size:13.5px;line-height:1.55;color:var(--text-3)">' . esc_html($s['texto']) . '</span>'
            . '<span style="margin-top:auto;display:inline-flex;align-items:center;gap:7px;font-family:\'Space Grotesk\';font-size:13.5px;font-weight:600;color:var(--accent-2)">Ver servicio <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>'
            . '</a>';
    }
    return '<section id="otros-servicios" aria-labelledby="otros-servicios-titulo" style="position:relative;border-top:1px solid var(--ov2);background:var(--bg-alt);padding:clamp(56px,7vw,92px) 0">'
        . '<div style="max-width:1200px;margin:0 auto;padding:0 28px">'
        . '<span style="display:inline-flex;align-items:center;gap:12px;font-family:\'JetBrains Mono\';font-size:12px;font-weight:500;letter-spacing:.18em;color:#2dd4bf"><span style="width:24px;height:1px;background:#2dd4bf"></span>MÁS DE LO QUE HACEMOS</span>'
        . '<h2 id="otros-servicios-titulo" style="margin-top:18px;font-family:\'Space Grotesk\';font-weight:600;font-size:clamp(26px,3.2vw,38px);line-height:1.12;letter-spacing:-.025em;color:var(--text)">Otros servicios</h2>'
        . '<div style="margin-top:34px;display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,250px),1fr));gap:14px">' . $html . '</div>'
        . '</div></section>';
}

/** Enlace «Preferencias de cookies» para el pie: el aviso (mu-plugin at-consentimiento) lo reabre con data-at-cookies. */
function at_preferencias_cookies_html(): string {
    return '<a href="' . esc_url(home_url('/cookies/')) . '" data-at-cookies style="display:inline-flex;align-items:center;gap:8px;font-size:14px;color:var(--text-3)" data-hover="color:var(--text)">'
        . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1111.2 3a4 4 0 004.3 4.5 4 4 0 005.5 5.3z"/><path d="M8.5 11.5h.01M12 16h.01M15.5 12.5h.01"/></svg>'
        . 'Preferencias de cookies</a>';
}

/**
 * Enlace «Guías» (16-nov-2026): va junto al menú «Servicios» en el encabezado y en el menú del celular
 * de la portada y de todas las landings, porque las dos funciones de abajo lo agregan a su salida.
 * El ícono va en SVG en línea, con el mismo trazo que los del menú «Servicios»: así ninguna página baja la fuente de
 * íconos solo por este enlace (Phosphor queda para las páginas de guías).
 */
function at_guias_url(): string {
    return esc_url(home_url('/guias/'));
}

function at_menu_guias_html(): string {
    return '<a href="' . at_guias_url() . '" class="at-guias-enlace" style="display:inline-flex;align-items:center;gap:6px;font-size:14px;font-weight:500;color:var(--text-3);transition:color .2s" data-hover="color:var(--text)">'
        . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z"/></svg>Guías</a>';
}

function at_menu_guias_movil_html(): string {
    return '<a href="' . at_guias_url() . '" data-act="closeMenu" style="display:flex;align-items:center;gap:10px;padding:13px 12px;font-weight:600;color:var(--text-2);border-radius:8px">'
        . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z"/></svg>Guías</a>';
}

/** Enlace «Guías» del pie (marcador {{GUIAS_PIE}} en el body.html de cada página). */
function at_guias_pie_html(): string {
    return '<a href="' . at_guias_url() . '" style="display:inline-flex;align-items:center;gap:8px;font-size:14px;color:var(--text-3)" data-hover="color:var(--text)">'
        . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z"/></svg>Guías</a>';
}

/**
 * Menú «Servicios» del encabezado (12-nov-2026, pedido de Luis): reemplaza el enlace «Soluciones».
 * Escritorio: lista desplegable con <details> (funciona sin JS y con teclado). $actual marca la página abierta.
 */
function at_menu_servicios_html(string $actual = ''): string {
    $items = '';
    foreach (at_servicios_lista() as $slug => $s) {
        $cur = $slug === $actual ? ' aria-current="page"' : '';
        $items .= '<a href="' . at_servicio_url($slug) . '"' . $cur . ' style="display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:10px;color:var(--text-2);font-size:14px;font-weight:500;transition:background .2s,color .2s" data-hover="background:var(--ov2);color:var(--text)">'
            . '<span style="flex:none;width:34px;height:34px;border-radius:9px;background:rgba(45,212,191,.08);border:1px solid rgba(45,212,191,.18);display:flex;align-items:center;justify-content:center">' . str_replace('width="23" height="23"', 'width="18" height="18"', $s['icono']) . '</span>'
            . '<span>' . esc_html($s['nombre']) . '</span></a>';
    }
    $todas = '<a href="' . esc_url(home_url('/')) . '#soluciones" style="display:flex;align-items:center;gap:8px;margin-top:4px;padding:10px 12px;border-top:1px solid var(--bd1);font-size:13px;font-weight:600;color:var(--accent-2)">Ver todas las soluciones <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>';
    return '<details class="at-ms" style="position:relative">'
        . '<summary style="list-style:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:14px;font-weight:500;color:var(--text-3)">'
        . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>'
        . 'Servicios <svg class="at-ms-flecha" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></summary>'
        . '<div style="position:absolute;top:calc(100% + 14px);left:50%;transform:translateX(-50%);width:300px;padding:8px;background:var(--surface);border:1px solid var(--bd2);border-radius:14px;box-shadow:0 24px 60px -24px rgba(0,0,0,.6);z-index:70">'
        . $items . $todas . '</div></details>'
        . at_menu_guias_html();
}

/** Menú del celular: grupo «Servicios» desplegable dentro del menú hamburguesa. */
function at_menu_servicios_movil_html(string $actual = ''): string {
    $items = '';
    foreach (at_servicios_lista() as $slug => $s) {
        $cur = $slug === $actual ? ' aria-current="page"' : '';
        $items .= '<a href="' . at_servicio_url($slug) . '"' . $cur . ' style="display:flex;align-items:center;gap:12px;padding:11px 12px;border-radius:8px;font-weight:600;color:var(--text-2)">'
            . str_replace('width="23" height="23"', 'width="18" height="18"', $s['icono']) . esc_html($s['nombre']) . '</a>';
    }
    return '<details class="at-ms-movil"><summary style="list-style:none;display:flex;align-items:center;justify-content:space-between;padding:13px 12px;font-weight:600;color:var(--text-2);border-radius:8px;cursor:pointer">Servicios'
        . '<svg class="at-ms-flecha" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></summary>'
        . '<div style="padding:2px 0 8px 10px;border-left:1px solid var(--bd2);margin-left:12px">' . $items . '</div></details>'
        . at_menu_guias_movil_html();
}

/** Estilos y cierre del menú (clic afuera o Escape). Va al final del <body>, fuera de los sc-if. */
function at_menu_servicios_extras(): string {
    return '<style>.at-ms summary::-webkit-details-marker,.at-ms-movil summary::-webkit-details-marker{display:none}'
        . '.at-ms[open] .at-ms-flecha,.at-ms-movil[open] .at-ms-flecha{transform:rotate(180deg)}.at-ms-flecha{transition:transform .2s}'
        . '.at-ms summary:hover,.at-ms[open] summary{color:var(--text)}.at-ms a[aria-current="page"],.at-ms-movil a[aria-current="page"]{color:var(--accent-2)!important}'
        . '.at-ms summary:focus-visible{outline:2px solid #2dd4bf;outline-offset:4px;border-radius:6px}</style>'
        . '<script>(function(){function c(e){document.querySelectorAll("details.at-ms[open]").forEach(function(d){if(!e||!d.contains(e.target)){d.removeAttribute("open");}});}'
        . 'document.addEventListener("click",function(e){c(e);});document.addEventListener("keydown",function(e){if(e.key==="Escape"){c(null);}});})();</script>';
}
