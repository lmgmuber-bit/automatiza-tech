<?php
/**
 * Entregables con notas y versiones: funciones sin WordPress (se prueban con php tests/entregables/puras-test.php).
 * Spec: Docs/superpowers/specs/2026-10-05-entregables-notas-design.md
 */
if (!defined('AT_EN_MAX_TEXTO')) {
	define('AT_EN_MAX_TEXTO', 3000);
	define('AT_EN_MAX_NOMBRE', 80);
	define('AT_EN_MAX_MENSAJE', 6000);
	define('AT_EN_MAX_IMAGENES', 3);
	define('AT_EN_MAX_BYTES', 5242880);
	define('AT_EN_MAX_LADO_ENTRADA', 12000);
	define('AT_EN_MAX_PIXELES', 40000000);
	define('AT_EN_LADO_FINAL', 2000);
	define('AT_EN_NOTAS_POR_HORA', 10);
}

function at_en_codigo_valido(string $c): bool {
	return (bool) preg_match('/^[A-Za-z0-9]{12}\z/', $c);
}

/** Enlace público del entregable; '' sin sitio o con código inválido. */
function at_en_url_pagina(string $base, string $codigo): string {
	$base = rtrim(trim($base), '/');
	return ($base === '' || !at_en_codigo_valido($codigo)) ? '' : $base . '/ver-entregable.php?id=' . $codigo;
}

function at_en_nombre_imagen_valido(string $n): bool {
	return (bool) preg_match('/^[a-z0-9]{24}\.(jpg|png)\z/', $n);
}

function at_en_url_imagen(string $base, string $codigo, string $nombre): string {
	$p = at_en_url_pagina($base, $codigo);
	return ($p === '' || !at_en_nombre_imagen_valido($nombre)) ? '' : $p . '&img=' . $nombre;
}

/** Token del formulario de notas para el día $dia (días desde 1970). */
function at_en_token(string $codigo, int $dia, string $sal): string {
	return substr(hash_hmac('sha256', 'at-en-nota|' . $codigo . '|' . $dia, $sal), 0, 24);
}

/** Vale el día en que se emitió y el siguiente. */
function at_en_token_valido(string $token, string $codigo, int $hoy, string $sal): bool {
	if (!preg_match('/^[a-f0-9]{24}\z/', $token)) {
		return false;
	}
	return hash_equals(at_en_token($codigo, $hoy, $sal), $token) || hash_equals(at_en_token($codigo, $hoy - 1, $sal), $token);
}

/** Saltos \r\n → \n y sin caracteres de control (salvo tab y salto de línea). */
function at_en_limpiar_texto(string $t): string {
	$t = str_replace(["\r\n", "\r"], "\n", mb_convert_encoding($t, 'UTF-8', 'UTF-8')); // descarta bytes UTF-8 inválidos
	return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $t);
}

/** Valida una nota (del cliente o de AT). El texto se guarda tal cual: se escapa al mostrar. */
function at_en_validar_nota(string $nombre, string $texto): array {
	$nombre = trim((string) preg_replace('/\s+/u', ' ', at_en_limpiar_texto($nombre)));
	$texto = trim(at_en_limpiar_texto($texto));
	$r = ['ok' => false, 'error' => '', 'nombre' => $nombre, 'texto' => $texto];
	if ($nombre === '') {
		$r['error'] = 'nombre_vacio';
	} elseif (mb_strlen($nombre, 'UTF-8') > AT_EN_MAX_NOMBRE) {
		$r['error'] = 'nombre_largo';
	} elseif ($texto === '') {
		$r['error'] = 'nota_vacia';
	} elseif (mb_strlen($texto, 'UTF-8') > AT_EN_MAX_TEXTO) {
		$r['error'] = 'nota_larga';
	} else {
		$r['ok'] = true;
	}
	return $r;
}

/** Enlace de una versión: http(s), sin espacios y nunca a easypanel (Hostinger rechaza esos correos). '' si no sirve. */
function at_en_url_version_valida(string $u): string {
	$u = trim($u);
	return (preg_match('#^https?://[^\s<>"]+$#i', $u) && stripos($u, 'easypanel') === false) ? $u : '';
}

function at_en_validar_mensaje(string $m): array {
	$m = trim(at_en_limpiar_texto($m));
	if (mb_strlen($m, 'UTF-8') > AT_EN_MAX_MENSAJE) {
		return ['ok' => false, 'error' => 'mensaje_largo', 'mensaje' => $m];
	}
	return ['ok' => true, 'error' => '', 'mensaje' => $m];
}

/**
 * Revisa una imagen subida con datos ya medidos: indice (1..3), error (UPLOAD_ERR_*), size (bytes), tipo (MIME real del
 * contenido, '' si no es imagen), ancho y alto. '' si sirve; si no, el mensaje para el cliente.
 */
function at_en_revisar_imagen(array $i): string {
	$n = (int) ($i['indice'] ?? 1);
	if ((int) ($i['error'] ?? 0) !== 0) {
		return "La imagen {$n} no se pudo subir. Inténtalo de nuevo.";
	}
	if (!in_array((string) ($i['tipo'] ?? ''), ['image/jpeg', 'image/png'], true)) {
		return "La imagen {$n} no es JPG ni PNG.";
	}
	if ((int) ($i['size'] ?? 0) > AT_EN_MAX_BYTES) {
		return "La imagen {$n} pesa más de 5 MB.";
	}
	$ancho = (int) ($i['ancho'] ?? 0);
	$alto = (int) ($i['alto'] ?? 0);
	if ($ancho < 1 || $alto < 1) { // getimagesize no pudo leerla
		return "La imagen {$n} no es JPG ni PNG.";
	}
	if ($ancho > AT_EN_MAX_LADO_ENTRADA || $alto > AT_EN_MAX_LADO_ENTRADA) {
		return "La imagen {$n} es demasiado grande (más de 12.000 px de lado).";
	}
	if ($ancho * $alto > AT_EN_MAX_PIXELES) {
		return "La imagen {$n} es demasiado grande (más de 40 megapíxeles).";
	}
	return '';
}

/** Escapa y convierte las URL en enlaces. */
function at_en_enlazar(string $t): string {
	$e = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
	return (string) preg_replace_callback('#https?://[^\s<>"\']+#i', function ($m) {
		$u = $m[0];
		return '<a href="' . $u . '" style="color:#1e3a8a;">' . $u . '</a>';
	}, $e);
}

/** Mensaje de Luis → HTML seguro: párrafos por línea en blanco, «- » forma lista, URL a enlace. Sin HTML libre. */
function at_en_mensaje_html(string $m): string {
	$m = trim(at_en_limpiar_texto($m));
	if ($m === '') {
		return '';
	}
	$html = '';
	foreach (preg_split("/\n{2,}/", $m) as $bloque) {
		$lineas = array_values(array_filter(array_map('trim', explode("\n", $bloque)), 'strlen'));
		if (!$lineas) {
			continue;
		}
		$texto = [];
		$items = [];
		foreach ($lineas as $l) {
			if (strpos($l, '- ') === 0) {
				$items[] = '<li>' . at_en_enlazar(substr($l, 2)) . '</li>';
			} else {
				if ($items) {
					$texto[] = '<ul style="padding-left:20px;margin:0 0 14px;">' . implode('', $items) . '</ul>';
					$items = [];
				}
				$texto[] = '<p>' . at_en_enlazar($l) . '</p>';
			}
		}
		if ($items) {
			$texto[] = '<ul style="padding-left:20px;margin:0 0 14px;">' . implode('', $items) . '</ul>';
		}
		$html .= implode('', $texto);
	}
	return $html;
}

function at_en_extracto(string $t, int $max = 400): string {
	$t = trim($t);
	return mb_strlen($t, 'UTF-8') > $max ? mb_substr($t, 0, $max, 'UTF-8') . '…' : $t;
}

function at_en_texto_whatsapp_version(string $nombre, int $n, string $titulo, string $url): string {
	$saludo = trim($nombre) !== '' ? 'Hola ' . trim($nombre) : 'Hola';
	return $saludo . ', te escribe Luis de AutomatizaTech. Te envié la versión ' . $n . ' de ' . trim($titulo) . '. Puedes verla y dejarme tus notas aquí: ' . $url;
}

function at_en_texto_whatsapp_respuesta(string $nombre, int $n, string $titulo, string $url): string {
	$saludo = trim($nombre) !== '' ? 'Hola ' . trim($nombre) : 'Hola';
	return $saludo . ', te respondí tu nota sobre la versión ' . $n . ' de ' . trim($titulo) . ': ' . $url;
}

/** wa.me al teléfono del cliente con el texto; '' sin teléfono válido. */
function at_en_url_wa(string $telefono, string $texto): string {
	$n = at_pt_telefono_wa($telefono);
	return $n === '' ? '' : 'https://wa.me/' . $n . '?text=' . rawurlencode($texto);
}

/** Lo que ven el cliente (página) y Luis (panel) por cada clave. */
function at_en_mensajes(): array {
	return [
		'no_disponible'   => 'Este enlace no está disponible.',
		'cerrado'         => 'Este entregable ya está cerrado. Si necesitas algo, escríbenos por WhatsApp.',
		'nota_ok'         => 'Recibimos tu nota. Te responderemos antes de la reunión.',
		'nota_vacia'      => 'Escribe tu nota antes de enviarla.',
		'nota_larga'      => 'La nota es muy larga: el máximo es 3.000 caracteres. Puedes dividirla en dos.',
		'nombre_vacio'    => 'Escribe tu nombre.',
		'nombre_largo'    => 'El nombre es muy largo (máximo 80 caracteres).',
		'sesion_vencida'  => 'La página quedó abierta mucho rato. Recárgala y vuelve a enviar tu nota.',
		'muchos_intentos' => 'Enviaste muchas notas seguidas. Espera un rato o escríbenos por WhatsApp.',
		'imagen'          => 'Una de las imágenes no sirve.',
		'muchas_imagenes' => 'Puedes adjuntar hasta 3 imágenes por nota.',
		'no_guardo'       => 'No pudimos guardar tu nota. Inténtalo de nuevo o escríbenos por WhatsApp.',
	];
}
