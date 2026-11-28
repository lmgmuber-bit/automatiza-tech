<?php
/**
 * Estado del proyecto, Etapa 3: lo que dice el correo de cierre de hito y el texto de WhatsApp (spec §8). Funciones
 * puras: no tocan la base ni envían. El envío vive en cierre-correo.php.
 */

/**
 * Estado de envío de un cierre, con la forma del spec §3: lo que el cierre ya trae (correo, whatsapp) sobre los valores
 * por defecto. Lo vacío o inválido queda en null (fechas) o '' (mensaje, destinatario).
 */
function at_av_envio_de_cierre(array $cierre): array {
	$fecha = fn($v): ?string => (is_string($v) && trim($v) !== '') ? $v : null;
	$texto = fn($v): string => is_string($v) ? $v : '';
	$co = is_array($cierre['correo'] ?? null) ? $cierre['correo'] : [];
	$wa = is_array($cierre['whatsapp'] ?? null) ? $cierre['whatsapp'] : [];
	return [
		'correo' => [
			'enviado_at' => $fecha($co['enviado_at'] ?? null), 'prueba_at' => $fecha($co['prueba_at'] ?? null),
			'mensaje' => $texto($co['mensaje'] ?? ''), 'enviando' => $fecha($co['enviando'] ?? null), 'a' => $texto($co['a'] ?? ''),
		],
		'whatsapp' => ['abierto_at' => $fecha($wa['abierto_at'] ?? null)],
	];
}

function at_av_cierre_de_avance(array $avance, string $id): ?array {
	foreach ((array) ($avance['cierres'] ?? []) as $c) {
		if (is_array($c) && ($c['id'] ?? '') === $id) {
			return $c;
		}
	}
	return null;
}

/** «Lo teníamos planificado para el X y quedó listo el Y (N días antes).» Sin fecha planificada, solo cuándo quedó. */
function at_av_frase_plan_real(string $plan_hasta, string $fecha_real): string {
	$real = at_pt_fecha_larga($fecha_real);
	$plan = at_pt_fecha_larga($plan_hasta);
	if ($real === '') {
		return '';
	}
	if ($plan === '') {
		return "Quedó listo el {$real}.";
	}
	$dias = (int) round((strtotime($plan_hasta . ' 12:00:00 UTC') - strtotime($fecha_real . ' 12:00:00 UTC')) / 86400);
	if ($dias === 0) {
		return "Quedó listo el {$real}, el día planificado.";
	}
	$n = abs($dias);
	$cuando = $n . ($n === 1 ? ' día ' : ' días ') . ($dias > 0 ? 'antes' : 'después');
	return "Lo teníamos planificado para el {$plan} y quedó listo el {$real} ({$cuando}).";
}

/** Primera frase de un texto (hasta el primer punto, signo o salto de línea), con su punto. */
function at_av_primera_frase(string $t): string {
	$t = trim($t);
	if ($t === '') {
		return '';
	}
	if (preg_match('/^(.+?[.!?])(\s|$)/u', $t, $m)) {
		return trim($m[1]);
	}
	return trim((string) strtok($t, "\n"));
}

/** Todo lo que el correo y el WhatsApp necesitan de un cierre. [] si no existe. */
function at_av_resumen_cierre(array $plan, array $avance, string $id): array {
	$c = at_av_cierre_de_avance($avance, $id);
	if ($c === null) {
		return [];
	}
	$tres = fn($l) => array_slice(array_values(array_filter(array_map('strval', (array) $l), 'strlen')), 0, 3);
	$plan_hasta = '';
	$sigue = [];
	foreach (at_av_estado_bloques($plan, $avance) as $b) {
		if (in_array($b['nombre'], (array) ($c['bloques'] ?? []), true) && (string) $b['plan_hasta'] > $plan_hasta) {
			$plan_hasta = (string) $b['plan_hasta'];
		}
		if ($b['estado'] !== 'listo' && count($sigue) < 3) {
			$sigue[] = ['nombre' => (string) $b['nombre'], 'cuando' => $b['estado'] === 'en_curso' ? 'En curso' : 'Planificado del ' . at_pt_fecha_larga((string) $b['plan_desde']) . ' al ' . at_pt_fecha_larga((string) $b['plan_hasta'])];
		}
	}
	$cifras = [];
	$videos = [];
	$urls = [];
	$ev_despues = (array) ($c['despues']['evidencias'] ?? []);
	foreach (array_unique(array_merge((array) ($c['antes']['evidencias'] ?? []), $ev_despues)) as $eid) {
		$e = $avance['evidencias'][$eid] ?? null;
		if (!is_array($e)) {
			continue;
		}
		foreach (['url', 'poster'] as $k) {
			if ((string) ($e[$k] ?? '') !== '') {
				$urls[] = (string) $e[$k];
			}
		}
		if (($e['tipo'] ?? '') === 'cifra') {
			$cifras[] = ['titulo' => (string) $e['titulo'], 'valor' => (string) $e['valor'], 'unidad' => (string) $e['unidad'], 'fuente' => (string) $e['fuente'], 'fecha' => (string) $e['fecha'],
				'parte' => in_array($eid, $ev_despues, true) ? 'despues' : 'antes'];
		} elseif (($e['tipo'] ?? '') === 'video' && (string) ($e['url'] ?? '') !== '') {
			$videos[] = ['titulo' => (string) $e['titulo'], 'url' => (string) $e['url']];
		}
	}
	$despues = $tres($c['despues']['puntos'] ?? []);
	return [
		'titulo' => (string) $c['titulo'], 'fecha_real' => (string) $c['fecha_real'],
		'frase' => at_av_frase_plan_real($plan_hasta, (string) $c['fecha_real']),
		'antes' => $tres($c['antes']['puntos'] ?? []),
		'durante' => $tres(array_column((array) ($c['durante']['actividades'] ?? []), 'nombre')),
		'despues' => $despues,
		'cifras' => $cifras,
		'linea_despues' => $despues[0] ?? at_av_primera_frase((string) ($c['despues']['texto'] ?? '')),
		'sigue' => $sigue,
		'necesitamos' => array_values(array_map(fn($x) => (string) $x['texto'], array_filter((array) ($avance['necesitamos'] ?? []), fn($x) => is_array($x) && ($x['estado'] ?? '') !== 'resuelto'))),
		'videos' => $videos,
		'urls' => array_values(array_unique($urls)),
	];
}

/** D8: las URL que apuntan al servidor interno (Easypanel); el correo no sale con ninguna. */
function at_av_urls_internas(array $urls): array {
	return array_values(array_unique(array_filter(array_map('strval', $urls), fn($u) => stripos($u, 'easypanel') !== false)));
}

/**
 * Correo «Terminamos: …» (spec §8). $v: nombre, proyecto, logo, resumen (at_av_resumen_cierre), mensaje (texto de Luis,
 * opcional), url_estado (ver-estado.php), con_pdf. Escapa todo; un enlace del servidor interno no se dibuja (D8).
 */
function at_av_correo_cierre_html(array $v): string {
	$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	$url = function ($u): string {
		$u = trim((string) $u);
		return (preg_match('#^https://\S+$#i', $u) && stripos($u, 'easypanel') === false) ? $u : '';
	};
	$r = is_array($v['resumen'] ?? null) ? $v['resumen'] : [];
	$nombre = trim((string) ($v['nombre'] ?? ''));
	$logo = $url($v['logo'] ?? '');
	$estado = $url($v['url_estado'] ?? '');
	$lista = function (string $titulo, array $items) use ($h): string {
		if (!$items) {
			return '';
		}
		return '<h3 style="margin:18px 0 6px;font-size:15px;color:#1e40af">' . $h($titulo) . '</h3><ul style="margin:0;padding-left:20px">'
			. implode('', array_map(fn($x) => '<li>' . $h($x) . '</li>', $items)) . '</ul>';
	};
	$cifras = '';
	foreach ((array) ($r['cifras'] ?? []) as $x) {
		$origen = array_values(array_filter([(string) $x['fuente'], at_pt_fecha_larga((string) $x['fecha'])], 'strlen'));
		$cifras .= '<li>' . ($x['parte'] === 'antes' ? 'Antes' : 'Después') . ' · <strong>' . $h($x['titulo']) . ':</strong> ' . $h(trim($x['valor'] . ' ' . $x['unidad']))
			. ($origen ? ' <span style="color:#64748b">(' . $h(implode(', ', $origen)) . ')</span>' : '') . '</li>';
	}
	$mensaje = trim((string) ($v['mensaje'] ?? ''));
	$sigue = array_map(fn($s) => $s['nombre'] . ' — ' . $s['cuando'], (array) ($r['sigue'] ?? []));
	return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f4f6fb;margin:0;padding:0;color:#222">'
		. '<div style="max-width:600px;margin:24px auto;background:#ffffff;border-radius:10px;overflow:hidden">'
		. '<div style="background:#1e40af;color:#ffffff;text-align:center;padding:26px 20px">'
		. ($logo !== '' ? '<img src="' . $h($logo) . '" alt="AutomatizaTech" style="max-height:64px"><br>' : '')
		. '<h1 style="margin:12px 0 0;font-size:22px">Terminamos: ' . $h($r['titulo'] ?? '') . '</h1></div>'
		. '<div style="padding:26px;line-height:1.6">'
		. '<p>' . ($nombre !== '' ? 'Hola <strong>' . $h($nombre) . '</strong>,' : 'Hola,') . '</p>'
		. '<p>Terminamos <strong>' . $h($r['titulo'] ?? '') . '</strong>' . (trim((string) ($v['proyecto'] ?? '')) !== '' ? ' de ' . $h($v['proyecto']) : '') . '. ' . $h($r['frase'] ?? '') . '</p>'
		. ($mensaje !== '' ? '<div style="background:#f0f6fc;border-left:3px solid #1e40af;padding:10px 14px;margin:14px 0">' . nl2br($h($mensaje), false) . '</div>' : '')
		. $lista('Cómo estaba', (array) ($r['antes'] ?? []))
		. $lista('Qué hicimos', (array) ($r['durante'] ?? []))
		. $lista('Cómo quedó', (array) ($r['despues'] ?? []))
		. ($cifras !== '' ? '<h3 style="margin:18px 0 6px;font-size:15px;color:#1e40af">En números</h3><ul style="margin:0;padding-left:20px">' . $cifras . '</ul>' : '')
		. (!empty($v['con_pdf']) ? '<p>Va adjunto en PDF el estado completo del proyecto; también puedes verlo en línea.</p>' : '')
		. ($estado !== '' ? '<p style="text-align:center;margin:22px 0"><a href="' . $h($estado) . '" style="display:inline-block;background:#1e40af;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 26px;border-radius:24px">Ver el estado del proyecto</a></p>' : '')
		. $lista('Lo que sigue', $sigue)
		. $lista('Lo que necesitamos de ti', (array) ($r['necesitamos'] ?? []))
		. '<p style="margin-top:22px">Cualquier duda, responde este correo.</p><p>Un abrazo,<br><strong>El equipo de AutomatizaTech</strong></p></div>'
		. '<div style="background:#f1f1f1;color:#777;text-align:center;font-size:12px;padding:14px">© ' . date('Y') . ' AutomatizaTech · automatizatech.cl</div>'
		. '</div></body></html>';
}

/** Texto para el WhatsApp de Luis (spec §8). */
function at_av_texto_whatsapp_cierre(string $nombre, array $resumen, string $url_estado): string {
	$saludo = trim($nombre) !== '' ? 'Hola ' . trim($nombre) : 'Hola';
	$linea = rtrim(trim((string) ($resumen['linea_despues'] ?? '')), '.');
	$video = !empty($resumen['videos']) ? ' y el video' : '';
	return $saludo . ', terminamos ' . trim((string) ($resumen['titulo'] ?? '')) . ' 🎉' . ($linea !== '' ? ' ' . $linea . '.' : '')
		. ' Te dejamos el detalle' . $video . ' aquí: ' . $url_estado;
}
