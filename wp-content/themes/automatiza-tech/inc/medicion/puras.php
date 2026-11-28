<?php
/**
 * Medición de automatizatech.cl con Google Analytics 4 y Google Ads, sin WordPress
 * (prueba: tests/medicion/puras-test.php). Decisión de Luis del 13-nov-2026: dejar GA4 y la
 * conversión listos antes de cualquier campaña.
 *
 * Regla de privacidad: la etiqueta de Google solo se carga si la persona aceptó las cookies
 * (cookie `at_consentimiento=si`, la del aviso de wp-content/mu-plugins/at-consentimiento.php).
 * Se lee en el navegador porque LiteSpeed guarda la misma página para todos.
 */

function at_med_id_ga4_valido(string $id): bool {
	return preg_match('/^G-[A-Z0-9]{6,12}$/D', $id) === 1;
}

function at_med_id_ads_valido(string $id): bool {
	return preg_match('/^AW-[0-9]{6,12}$/D', $id) === 1;
}

/** IDs válidos en el orden en que se configuran: GA4 primero (es el que carga gtag.js), Ads después. */
function at_med_ids(string $ga4, string $ads): array {
	$ids = [];
	if (at_med_id_ga4_valido($ga4)) { $ids[] = $ga4; }
	if (at_med_id_ads_valido($ads)) { $ids[] = $ads; }
	return $ids;
}

/** Script del <head>: sin permiso no carga nada; con permiso carga gtag.js y configura cada ID. '' si no hay IDs. */
function at_med_script_cabecera(array $ids): string {
	if (!$ids) { return ''; }
	$lista = json_encode(array_values($ids), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	return "<script>(function(){"
		. "if(!/(?:^|;\\s*)at_consentimiento=si(?:;|$)/.test(document.cookie)){return;}"
		. "var ids={$lista};"
		. "var s=document.createElement('script');s.async=true;"
		. "s.src='https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(ids[0]);"
		. "document.head.appendChild(s);"
		. "window.dataLayer=window.dataLayer||[];"
		. "window.gtag=window.gtag||function(){window.dataLayer.push(arguments);};"
		. "gtag('js',new Date());"
		. "for(var i=0;i<ids.length;i++){gtag('config',ids[i]);}"
		. "})();</script>\n";
}

/**
 * Script del pie: envuelve window.atLead (lo define at-consentimiento.php y lo llaman la agenda del
 * inicio y los formularios del pie) para mandar también `generate_lead` a GA4. Sin gtag cargado
 * (sin permiso) no manda nada; el aviso a Meta sigue igual.
 */
function at_med_script_lead(): string {
	return "<script>(function(){"
		. "var original=window.atLead;"
		. "window.atLead=function(origen){"
		. "try{if(typeof window.gtag==='function'){window.gtag('event','generate_lead',{lead_source:String(origen||'web')});}}catch(e){}"
		. "if(typeof original==='function'){return original.apply(this,arguments);}"
		. "};"
		. "})();</script>\n";
}
