<?php
/**
 * Plan de trabajo: reglas sin WordPress. Fechas en días hábiles (Task 1); enumeraciones, tabla de tiempos,
 * validación, tabla -> días y marcas de origen (Task 2); cronograma (Task 3); estados, cuerpo del render y costo de
 * fotos (Task 4). Pruebas: php tests/plan/fechas-test.php, validacion-test.php, cronograma-test.php, render-test.php
 * Spec: Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md
 * Las fechas son cadenas 'Y-m-d' y se calculan con DateTimeImmutable en UTC: nada depende de la zona del servidor.
 */

/* ---------- Fechas en días hábiles (Task 1) ---------- */

/** 'Y-m-d' válida al inicio de la cadena (acepta 'Y-m-d H:i:s' de MySQL, como signed_at); '' si no es una fecha real. */
function at_pt_ymd(string $f): string {
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:$|[ T])/', trim($f), $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
		return '';
	}
	return $m[1] . '-' . $m[2] . '-' . $m[3];
}

/** Medianoche UTC de una fecha 'Y-m-d' ya validada con at_pt_ymd(). */
function at_pt_dia(string $ymd): DateTimeImmutable {
	return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
}

/** Feriados desde el texto del ajuste de la agenda (get_option('automatiza_chat_schedule')['holidays'], una fecha
 *  'AAAA-MM-DD' por línea; mismo corte que wp-content/mu-plugins/api-appointments-config.php:30-42). Sin fechas
 *  imposibles, sin repetir y en orden. */
function at_pt_feriados_de_texto(string $raw): array {
	$fechas = [];
	foreach (preg_split('/\r\n|\r|\n/', $raw) as $linea) {
		$f = trim($linea);
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && at_pt_ymd($f) !== '') {
			$fechas[$f] = true;
		}
	}
	$fechas = array_keys($fechas);
	sort($fechas);
	return $fechas;
}

/** Día hábil: de lunes a viernes y no feriado. Una fecha inválida no es hábil. */
function at_pt_es_habil(string $f, array $feriados): bool {
	$ymd = at_pt_ymd($f);
	if ($ymd === '') {
		return false;
	}
	return (int) at_pt_dia($ymd)->format('N') <= 5 && !in_array($ymd, $feriados, true);
}

/** Primer día hábil estrictamente después de $f; '' si $f no es una fecha. Termina siempre: la lista de feriados es finita. */
function at_pt_siguiente_habil(string $f, array $feriados): string {
	$ymd = at_pt_ymd($f);
	if ($ymd === '') {
		return '';
	}
	$d = at_pt_dia($ymd);
	do {
		$d = $d->modify('+1 day');
	} while (!at_pt_es_habil($d->format('Y-m-d'), $feriados));
	return $d->format('Y-m-d');
}

/** Día hábil n-ésimo contando $desde como el día 1; si $desde no es hábil, cuenta desde el hábil siguiente.
 *  $n menor que 1 vale 1. '' si $desde no es una fecha. */
function at_pt_sumar_habiles(string $desde, int $n, array $feriados): string {
	$ymd = at_pt_ymd($desde);
	if ($ymd === '') {
		return '';
	}
	$dia = at_pt_es_habil($ymd, $feriados) ? $ymd : at_pt_siguiente_habil($ymd, $feriados);
	for ($i = 1; $i < max(1, $n); $i++) {
		$dia = at_pt_siguiente_habil($dia, $feriados);
	}
	return $dia;
}

/** Inicio por defecto del plan: el primer lunes estrictamente posterior a la firma; si ese lunes es feriado, el hábil
 *  siguiente. Acepta la firma con hora ('Y-m-d H:i:s'). '' si la firma no es una fecha. */
function at_pt_inicio_por_defecto(string $fecha_firma, array $feriados): string {
	$ymd = at_pt_ymd($fecha_firma);
	if ($ymd === '') {
		return '';
	}
	$lunes = at_pt_dia($ymd)->modify('next monday')->format('Y-m-d');
	return at_pt_es_habil($lunes, $feriados) ? $lunes : at_pt_siguiente_habil($lunes, $feriados);
}

/** '2026-09-28' => '28 de septiembre de 2026' (acepta la fecha con hora); '' si no es una fecha. */
function at_pt_fecha_larga(string $ymd): string {
	$f = at_pt_ymd($ymd);
	if ($f === '') {
		return '';
	}
	$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
	[$a, $m, $d] = explode('-', $f);
	return (int) $d . ' de ' . $meses[(int) $m - 1] . ' de ' . $a;
}

/* ---------- Enumeraciones y tabla de tiempos (Task 2) ---------- */

/** Fases del plan en su orden fijo y con su título fijo (Método AT: lo que viene después de la firma). */
function at_pt_fases_validas(): array {
	return ['diseno_desarrollo' => 'Diseño y desarrollo', 'implementacion' => 'Implementación', 'soporte' => 'Soporte y mejora continua'];
}

/** Quién hace cada actividad. */
function at_pt_responsables(): array {
	return ['at' => 'AutomatizaTech', 'cliente' => 'Tú', 'ambos' => 'Ambos'];
}

/** Etapa de una actividad; '' es «otra» (no calza con la tabla de tiempos). */
function at_pt_etapas(): array {
	return ['arranque' => 'Arranque', 'diseno' => 'Diseño', 'desarrollo' => 'Desarrollo', 'pruebas' => 'Pruebas', 'implementacion' => 'Implementación', 'soporte' => 'Soporte', '' => 'Otra'];
}

/** De dónde sale la duración de una actividad: la tabla de tiempos, la IA (el panel la marca «revisar») o Luis a mano. */
function at_pt_origenes(): array {
	return ['tabla' => 'Tabla de tiempos', 'ia' => 'IA · revisar', 'luis' => 'Editado por Luis'];
}

/** Láminas del plan que pueden llevar foto (image_briefs[].slide), en el orden del documento. */
function at_pt_slides_foto(): array {
	return ['cover', 'metodo', 'gantt', 'fase_1', 'fase_2', 'fase_3', 'necesitamos', 'reuniones', 'portal', 'cierre'];
}

/** Etapas que son columnas de la tabla de tiempos. */
function at_pt_etapas_tabla(): array {
	return ['diseno', 'desarrollo', 'pruebas', 'implementacion'];
}

/** Entero exacto desde un número JSON o un texto de dígitos (5, '5', ' 12 ', 3.0); null si no lo es (3.5, '3,5', true, []). */
function at_pt_entero(mixed $v): ?int {
	if (is_int($v)) {
		return $v;
	}
	if (is_float($v) && is_finite($v) && floor($v) === $v && abs($v) < 1e9) {
		return (int) $v;
	}
	if (is_string($v) && preg_match('/^\s*-?\d{1,9}\s*$/', $v)) {
		return (int) trim($v);
	}
	return null;
}

/** Tabla de tiempos de referencia propuesta (spec §3, días hábiles por etapa). Luis la corrige en «Ajustes del plan». */
function at_pt_duraciones_defecto(): array {
	return [
		'sitio_una_pagina'   => ['nombre' => 'Sitio de una página',           'diseno' => 3, 'desarrollo' => 5,  'pruebas' => 2, 'implementacion' => 1],
		'sitio_web_tienda'   => ['nombre' => 'Sitio web o tienda',            'diseno' => 5, 'desarrollo' => 10, 'pruebas' => 3, 'implementacion' => 2],
		'asistente_basico'   => ['nombre' => 'Asistente básico',              'diseno' => 2, 'desarrollo' => 4,  'pruebas' => 2, 'implementacion' => 1],
		'asistente_avanzado' => ['nombre' => 'Asistente avanzado',            'diseno' => 3, 'desarrollo' => 8,  'pruebas' => 3, 'implementacion' => 2],
		'plataforma'         => ['nombre' => 'Plataforma o sistema a medida', 'diseno' => 8, 'desarrollo' => 20, 'pruebas' => 5, 'implementacion' => 3],
		'automatizacion_n8n' => ['nombre' => 'Automatización (flujo n8n)',    'diseno' => 2, 'desarrollo' => 5,  'pruebas' => 2, 'implementacion' => 1],
		'google_ads'         => ['nombre' => 'Google Ads (puesta en marcha)', 'diseno' => 2, 'desarrollo' => 3,  'pruebas' => 1, 'implementacion' => 1],
	];
}

/** Solo las filas válidas de una tabla de tiempos: clave [a-z0-9_]{2,40}, nombre de 1 a 60 caracteres y los cuatro
 *  números enteros de 0 a 60. Acepta 'clave' => fila o una lista de filas con 'clave' (el formulario de ajustes). Si
 *  una clave se repite, gana la primera. Quita los campos de más. */
function at_pt_normalizar_duraciones(array $tabla): array {
	$out = [];
	foreach ($tabla as $k => $fila) {
		if (!is_array($fila)) {
			continue;
		}
		$clave = trim(is_int($k) ? (is_string($fila['clave'] ?? null) ? $fila['clave'] : '') : (string) $k);
		if (!preg_match('/^[a-z0-9_]{2,40}$/', $clave) || isset($out[$clave])) {
			continue;
		}
		$nombre = is_string($fila['nombre'] ?? null) ? trim($fila['nombre']) : '';
		$largo = mb_strlen($nombre, 'UTF-8');
		if ($largo < 1 || $largo > 60) {
			continue;
		}
		$limpia = ['nombre' => $nombre];
		foreach (at_pt_etapas_tabla() as $etapa) {
			$n = at_pt_entero($fila[$etapa] ?? null);
			if ($n === null || $n < 0 || $n > 60) {
				continue 2;
			}
			$limpia[$etapa] = $n;
		}
		$out[$clave] = $limpia;
	}
	return $out;
}

/** Bloque fijo «Arranque» (spec §3): reunión de inicio y entrega de insumos del cliente (cláusula 4.2 del contrato:
 *  el plazo corre desde que recibimos el anticipo y los insumos). Siempre es el primer bloque del plan. */
function at_pt_arranque(): array {
	$actividad = static function (string $nombre, string $detalle, string $responsable, int $dias): array {
		return ['nombre' => $nombre, 'detalle' => $detalle, 'responsable' => $responsable, 'dias_habiles' => $dias,
			'servicio' => '', 'etapa' => 'arranque', 'origen' => 'tabla', 'en_paralelo' => false, 'desde' => '', 'hasta' => ''];
	};
	return [
		'nombre'      => 'Arranque',
		'entregable'  => '',
		'entrega'     => false,
		'actividades' => [
			$actividad('Reunión de inicio', 'Nos conocemos, revisamos este plan y acordamos cómo nos comunicamos.', 'ambos', 1),
			$actividad('Entrega de logo, textos y accesos', 'El plazo corre desde que recibimos el anticipo y estos insumos.', 'cliente', 3),
		],
	];
}

/* ---------- Topes, textos, JSON de la IA y días por bloque (Task 2) ---------- */

// Topes del plan (Review Focus 6). Con 14 bloques, el Arranque incluido, la carta Gantt tiene como máximo 28 barras
// (cada bloque más su «Tu revisión»); con 130 días hábiles, unas 26 semanas más los feriados (peor caso medido en
// tests/plan/cronograma-test.php: 27 barras y 130 días hábiles en 27 semanas). Es lo que el renderer debe dejar
// legible en 1920×1080 y en el PDF. Más que eso es un plan roto o un proyecto que hay que dividir. La tabla de tiempos
// puede subir los días: por eso el plan se valida otra vez después de aplicarla.
const AT_PT_MAX_BLOQUES = 14;
const AT_PT_MAX_ACTIVIDADES_BLOQUE = 10;
const AT_PT_MAX_ACTIVIDADES = 60;
const AT_PT_MAX_DIAS_PLAN = 130;
// Cláusula 6.1 del contrato de servicios (Docs/CONTRATO_SERVICIO_DESARROLLO.md:74): el cliente tiene 5 días hábiles
// para aprobar cada avance. Se suma una vez después de cada bloque con entrega.
const AT_PT_DIAS_REVISION = 5;

/** Un valor que mandó la IA, corto y legible para un mensaje de error. */
function at_pt_mostrar(mixed $v): string {
	if ($v === null || $v === '') {
		return 'vacío';
	}
	if (is_bool($v)) {
		return $v ? 'true' : 'false';
	}
	if (!is_scalar($v)) {
		return 'una lista';
	}
	$s = (string) $v;
	if (!mb_check_encoding($s, 'UTF-8')) {
		return 'texto ilegible';
	}
	$s = trim((string) preg_replace('/\s+/u', ' ', $s));
	return mb_strlen($s, 'UTF-8') > 40 ? mb_substr($s, 0, 39, 'UTF-8') . '…' : $s;
}

/** Nombre para comparar: sin espacios de más y en minúsculas («  Diseño » y «diseño» son el mismo bloque). */
function at_pt_clave_nombre(string $s): string {
	$t = preg_replace('/\s+/u', ' ', $s);
	return mb_strtolower(trim(is_string($t) ? $t : $s), 'UTF-8');
}

/** Sí o no de la IA o de un formulario: true, 1, '1', 'true', 'si', 'sí' u 'on' son sí; lo demás, no. */
function at_pt_booleano(mixed $v): bool {
	if (is_bool($v)) {
		return $v;
	}
	if (is_int($v)) {
		return $v === 1;
	}
	if (is_string($v)) {
		return in_array(mb_strtolower(trim($v), 'UTF-8'), ['1', 'true', 'si', 'sí', 'on'], true);
	}
	return false;
}

/** Texto limpio de un campo del plan: sin caracteres de control (el salto de línea se queda solo si $multilinea), sin
 *  espacios de más y con tope. Hasta 4 veces el tope se acorta con aviso; más que eso es un texto roto de la IA y es
 *  error, igual que un valor que no es texto ni número. $que dice dónde está el campo, para el mensaje. */
function at_pt_texto(mixed $v, int $max, string $que, array &$errores, array &$avisos, bool $multilinea = false): string {
	if ($v === null) {
		return '';
	}
	if (is_int($v) || is_float($v)) {
		$v = (string) $v;
	}
	if (!is_string($v)) {
		$errores[] = "{$que}: no es texto.";
		return '';
	}
	if (!mb_check_encoding($v, 'UTF-8')) {
		$errores[] = "{$que}: el texto no es UTF-8 válido.";
		return '';
	}
	$t = str_replace(["\r\n", "\r"], "\n", $v);
	if ($multilinea) {
		$t = (string) preg_replace('/[^\P{Cc}\n]/u', ' ', $t);
		$t = (string) preg_replace('/[ ]+/u', ' ', $t);
		$t = (string) preg_replace("/ ?\n ?/u", "\n", $t);
		$t = trim((string) preg_replace("/\n{3,}/u", "\n\n", $t));
	} else {
		$t = trim((string) preg_replace('/[\p{Cc}\s]+/u', ' ', $t));
	}
	$largo = mb_strlen($t, 'UTF-8');
	if ($largo > 4 * $max) {
		$errores[] = "{$que}: el texto es demasiado largo (" . number_format($largo, 0, ',', '.') . " caracteres; máximo {$max}).";
		return '';
	}
	if ($largo > $max) {
		$avisos[] = "{$que}: se acortó a {$max} caracteres.";
		$t = rtrim(mb_substr($t, 0, $max - 1, 'UTF-8')) . '…';
	}
	return $t;
}

/** El plan desde el texto de la IA: JSON puro, con cerco ```json o con texto alrededor (del primer «{» al último «}»).
 *  null si no hay un objeto JSON (texto suelto, JSON roto, una lista —aunque envuelva un objeto— o un objeto vacío). */
function at_pt_plan_de_json(string $texto): ?array {
	$t = trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $texto));
	$t = trim((string) preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $t));
	$intentos = [$t];
	$a = strpos($t, '{');
	$b = strrpos($t, '}');
	if ($a !== false && $b !== false && $b > $a && !str_starts_with($t, '[')) {
		$intentos[] = substr($t, $a, $b - $a + 1);
	}
	foreach ($intentos as $intento) {
		$d = json_decode($intento, true);
		if (is_array($d) && $d !== [] && !array_is_list($d)) {
			return $d;
		}
	}
	return null;
}

/** «Qué necesitamos de ti» cuando la IA no trae nada (cláusula 4.2: logo, textos y accesos). */
function at_pt_necesitamos_defecto(): array {
	return ['Logo y colores de tu marca', 'Textos e información de tu negocio', 'Accesos que el proyecto necesite (dominio, hosting o cuentas)'];
}

/** Reuniones cuando la IA no trae ninguna (spec §2). */
function at_pt_reuniones_defecto(): array {
	return [
		['nombre' => 'Reunión de inicio', 'detalle' => ''],
		['nombre' => 'Llamada de seguimiento del plan', 'detalle' => ''],
		['nombre' => 'Entrega y capacitación', 'detalle' => ''],
	];
}

/** Días hábiles que ocupa un bloque en secuencia (sin su revisión): la misma regla de at_pt_calcular_fechas(),
 *  contada en días hábiles (los feriados alargan el calendario, no los días hábiles). */
function at_pt_dias_bloque(array $bloque): int {
	$fin = -1;
	$inicio_anterior = 0;
	$primera = true;
	foreach (($bloque['actividades'] ?? []) as $a) {
		$inicio = $primera ? 0 : (!empty($a['en_paralelo']) ? $inicio_anterior : $fin + 1);
		$fin = max($fin, $inicio + max(1, (int) ($a['dias_habiles'] ?? 1)) - 1);
		$inicio_anterior = $inicio;
		$primera = false;
	}
	return $fin + 1;
}

/* ---------- Validación del plan (Task 2) ---------- */

/** Clave de una enumeración desde lo que mandó la IA: la clave misma («AT», « cliente ») o su etiqueta («Tú»,
 *  «Implementación», «Soporte y mejora continua»), sin distinguir mayúsculas ni espacios de más. '' si no calza.
 *  Así un valor inequívoco no deja el plan en «error». */
function at_pt_clave_de(mixed $v, array $opciones): string {
	if (!is_string($v)) {
		return '';
	}
	$buscado = at_pt_clave_nombre($v);
	foreach ($opciones as $clave => $etiqueta) {
		if ($buscado === at_pt_clave_nombre((string) $clave) || $buscado === at_pt_clave_nombre((string) $etiqueta)) {
			return (string) $clave;
		}
	}
	return '';
}

/** Una actividad normalizada (las diez claves, en orden), o null si no se puede usar (el motivo va a $errores). */
function at_pt_validar_actividad(mixed $a, string $donde, int $n, array &$errores, array &$avisos): ?array {
	if (!is_array($a)) {
		$errores[] = "{$donde}: la actividad {$n} no tiene la forma esperada.";
		return null;
	}
	$antes = count($errores);
	$nombre = at_pt_texto($a['nombre'] ?? null, 120, "{$donde} › actividad {$n}", $errores, $avisos);
	if ($nombre === '') {
		if (count($errores) === $antes) {
			$errores[] = "{$donde}: la actividad {$n} no tiene nombre.";
		}
		return null;
	}
	$aqui = "{$donde} › «{$nombre}»";
	$responsable = at_pt_clave_de($a['responsable'] ?? null, at_pt_responsables());
	if ($responsable === '') {
		$errores[] = "{$aqui}: responsable desconocido «" . at_pt_mostrar($a['responsable'] ?? null) . '» (debe ser at, cliente o ambos).';
		$responsable = 'ambos';
	}
	$dias = at_pt_entero($a['dias_habiles'] ?? null);
	if ($dias === null || $dias < 1 || $dias > 60) {
		$errores[] = $dias === null
			? "{$aqui}: los días hábiles no son un número entero («" . at_pt_mostrar($a['dias_habiles'] ?? null) . '»; deben ser de 1 a 60).'
			: "{$aqui}: dice {$dias} días hábiles (deben ser de 1 a 60).";
		$dias = 1;
	}
	$servicio = is_string($a['servicio'] ?? null) ? strtolower(trim($a['servicio'])) : '';
	$etapa = is_string($a['etapa'] ?? null) ? strtolower(trim($a['etapa'])) : '';
	$origen = is_string($a['origen'] ?? null) ? strtolower(trim($a['origen'])) : '';
	return [
		'nombre'       => $nombre,
		'detalle'      => at_pt_texto($a['detalle'] ?? null, 300, "{$aqui}: detalle", $errores, $avisos),
		'responsable'  => $responsable,
		'dias_habiles' => $dias,
		'servicio'     => preg_match('/^[a-z0-9_]{2,40}$/', $servicio) ? $servicio : '',
		'etapa'        => isset(at_pt_etapas()[$etapa]) ? $etapa : '',
		'origen'       => isset(at_pt_origenes()[$origen]) ? $origen : 'ia',
		'en_paralelo'  => at_pt_booleano($a['en_paralelo'] ?? false),
		'desde'        => '',
		'hasta'        => '',
	];
}

/** Un bloque normalizado, o null si no sirve: sin forma o sin nombre (error) o sin actividades (se quita con aviso). */
function at_pt_validar_bloque(mixed $b, string $fase, int $n, array &$errores, array &$avisos): ?array {
	if (!is_array($b)) {
		$errores[] = "{$fase}: el bloque {$n} no tiene la forma esperada.";
		return null;
	}
	$antes = count($errores);
	$nombre = at_pt_texto($b['nombre'] ?? null, 80, "{$fase} › bloque {$n}", $errores, $avisos);
	if ($nombre === '') {
		if (count($errores) === $antes) {
			$errores[] = "{$fase}: el bloque {$n} no tiene nombre.";
		}
		return null;
	}
	$donde = "{$fase} › {$nombre}";
	$bloque = [
		'nombre'      => $nombre,
		'entregable'  => at_pt_texto($b['entregable'] ?? null, 200, "{$donde}: entregable", $errores, $avisos),
		'entrega'     => at_pt_booleano($b['entrega'] ?? false),
		'actividades' => [],
	];
	$actividades = $b['actividades'] ?? [];
	if (!is_array($actividades)) {
		$errores[] = "{$donde}: las actividades no tienen la forma esperada.";
		return null;
	}
	if (count($actividades) > AT_PT_MAX_ACTIVIDADES_BLOQUE) {
		$errores[] = "{$donde}: trae " . count($actividades) . ' actividades (máximo ' . AT_PT_MAX_ACTIVIDADES_BLOQUE . ' por bloque).';
	}
	$k = 0;
	foreach ($actividades as $a) {
		$act = at_pt_validar_actividad($a, $donde, ++$k, $errores, $avisos);
		if ($act === null) {
			continue;
		}
		if ($bloque['actividades'] === []) {
			$act['en_paralelo'] = false; // la primera de un bloque nunca es paralela
		}
		$bloque['actividades'][] = $act;
	}
	if ($bloque['actividades'] === []) {
		if (count($errores) === $antes) {
			$avisos[] = "{$donde}: el bloque no trae actividades y se quitó.";
		}
		return null;
	}
	return $bloque;
}

/** Valida y normaliza el plan que manda la IA o el panel (forma en el esqueleto). Devuelve
 *  ['ok' => bool, 'errores' => string[], 'avisos' => string[], 'plan' => array]. Con errores, 'plan' es [] para que
 *  nunca se guarde un plan roto. Normaliza: títulos fijos por clave, fases en su orden (las repetidas se juntan),
 *  Arranque al inicio de la primera fase si falta, textos recortados, días enteros de 1 a 60, primera actividad de
 *  cada bloque no paralela, hitos solo sobre bloques que existen, un brief de foto por lámina válida; desde, hasta,
 *  fechas de hitos y cronograma quedan vacíos (los llena at_pt_calcular_fechas). Fase y responsable se aceptan por
 *  clave o por etiqueta. Hasta 25 errores y 25 avisos. Es idempotente: validar un plan ya validado da lo mismo, así
 *  que se vuelve a validar después de aplicar la tabla o devolver los días de Luis (pueden pasar el tope de días). */
function at_pt_validar_plan(array $plan): array {
	$errores = [];
	$avisos = [];
	if ($plan !== [] && array_is_list($plan)) {
		return ['ok' => false, 'errores' => ['El plan no tiene la forma esperada: llegó una lista y no un objeto con fases.'], 'avisos' => [], 'plan' => []];
	}
	$out = [
		'version'           => 1,
		'proyecto'          => at_pt_texto($plan['proyecto'] ?? null, 120, 'Nombre del proyecto', $errores, $avisos),
		'fecha_firma'       => is_string($plan['fecha_firma'] ?? null) ? at_pt_ymd($plan['fecha_firma']) : '',
		'fecha_inicio'      => is_string($plan['fecha_inicio'] ?? null) ? at_pt_ymd($plan['fecha_inicio']) : '',
		'fases'             => [],
		'hitos'             => [],
		'necesitamos_de_ti' => [],
		'reuniones'         => [],
		'soporte'           => ['garantia_meses' => 3, 'mensuales' => []],
		'image_briefs'      => [],
		'cronograma'        => [],
	];
	if ($out['proyecto'] === '') {
		$avisos[] = 'El plan no trae el nombre del proyecto.';
	}

	// Fases: conocidas, en su orden fijo y con su título fijo.
	$titulos = at_pt_fases_validas();
	$fases_in = $plan['fases'] ?? null;
	if (!is_array($fases_in) || $fases_in === []) {
		$errores[] = 'El plan no trae fases.';
		$fases_in = [];
	}
	$por_clave = [];
	$n = 0;
	foreach ($fases_in as $k => $f) {
		$n++;
		if (!is_array($f)) {
			$errores[] = "La fase {$n} no tiene la forma esperada.";
			continue;
		}
		$clave_in = $f['clave'] ?? (is_string($k) ? $k : null);
		$clave = at_pt_clave_de($clave_in, $titulos);
		if ($clave === '') {
			$errores[] = 'Fase desconocida: «' . at_pt_mostrar($clave_in) . '» (las válidas son diseno_desarrollo, implementacion y soporte).';
			continue;
		}
		$bloques = $f['bloques'] ?? [];
		if (!is_array($bloques)) {
			$errores[] = "{$titulos[$clave]}: los bloques no tienen la forma esperada.";
			$bloques = [];
		}
		if (isset($por_clave[$clave])) {
			$avisos[] = "La fase «{$titulos[$clave]}» venía repetida: se juntaron sus bloques.";
			$por_clave[$clave]['bloques'] = array_merge($por_clave[$clave]['bloques'], array_values($bloques));
			continue;
		}
		$por_clave[$clave] = ['descripcion' => $f['descripcion'] ?? null, 'bloques' => array_values($bloques)];
	}
	foreach ($titulos as $clave => $titulo) {
		if (!isset($por_clave[$clave])) {
			continue;
		}
		$fase = [
			'clave'       => $clave,
			'titulo'      => $titulo,
			'descripcion' => at_pt_texto($por_clave[$clave]['descripcion'], 600, "{$titulo}: descripción", $errores, $avisos, true),
			'bloques'     => [],
		];
		foreach ($por_clave[$clave]['bloques'] as $j => $b) {
			$bloque = at_pt_validar_bloque($b, $titulo, $j + 1, $errores, $avisos);
			if ($bloque !== null) {
				$fase['bloques'][] = $bloque;
			}
		}
		if ($fase['bloques'] === []) {
			$avisos[] = "La fase «{$titulo}» quedó sin bloques y se quitó.";
			continue;
		}
		$out['fases'][] = $fase;
	}

	// Arranque fijo al inicio de la primera fase, si no está.
	if ($out['fases'] !== []) {
		$tiene_arranque = false;
		foreach ($out['fases'][0]['bloques'] as $b) {
			$tiene_arranque = $tiene_arranque || at_pt_clave_nombre($b['nombre']) === 'arranque';
		}
		if (!$tiene_arranque) {
			array_unshift($out['fases'][0]['bloques'], at_pt_arranque());
		}
	}

	// Tamaño: actividades propias, bloques, actividades y días hábiles en secuencia.
	$n_bloques = 0;
	$n_actividades = 0;
	$n_propias = 0;
	$dias_plan = 0;
	foreach ($out['fases'] as $f) {
		foreach ($f['bloques'] as $b) {
			$n_bloques++;
			$n_actividades += count($b['actividades']);
			if (at_pt_clave_nombre($b['nombre']) !== 'arranque') {
				$n_propias += count($b['actividades']);
			}
			$dias_plan += at_pt_dias_bloque($b) + ($b['entrega'] ? AT_PT_DIAS_REVISION : 0);
		}
	}
	if ($n_propias === 0 && $errores === []) {
		$errores[] = 'El plan no trae actividades (aparte del arranque).';
	}
	if ($n_bloques > AT_PT_MAX_BLOQUES) {
		$errores[] = "El plan trae {$n_bloques} bloques contando el arranque (máximo " . AT_PT_MAX_BLOQUES . '): la carta Gantt no cabe legible. Junta bloques o divide el proyecto.';
	}
	if ($n_actividades > AT_PT_MAX_ACTIVIDADES) {
		$errores[] = "El plan trae {$n_actividades} actividades (máximo " . AT_PT_MAX_ACTIVIDADES . ').';
	}
	if ($dias_plan > AT_PT_MAX_DIAS_PLAN) {
		$errores[] = "El plan suma {$dias_plan} días hábiles con las revisiones (máximo " . AT_PT_MAX_DIAS_PLAN . ', unas 26 semanas): acórtalo o divide el proyecto.';
	}

	// Hitos: solo sobre un bloque que existe (el nombre exacto del bloque queda en despues_de).
	$bloques_por_nombre = [];
	foreach ($out['fases'] as $f) {
		foreach ($f['bloques'] as $b) {
			$bloques_por_nombre[at_pt_clave_nombre($b['nombre'])] ??= $b['nombre'];
		}
	}
	$hitos = is_array($plan['hitos'] ?? null) ? array_values($plan['hitos']) : [];
	foreach ($hitos as $i => $h) {
		$nombre = at_pt_texto(is_array($h) ? ($h['nombre'] ?? null) : null, 80, 'Hito ' . ($i + 1), $errores, $avisos);
		if ($nombre === '' || at_pt_clave_nombre($nombre) === 'entrega estimada') {
			continue; // sin nombre no se muestra; «Entrega estimada» la agrega siempre el cronograma
		}
		$despues = is_string($h['despues_de'] ?? null) ? at_pt_clave_nombre($h['despues_de']) : '';
		if (!isset($bloques_por_nombre[$despues])) {
			$avisos[] = "El hito «{$nombre}» se descartó: no existe el bloque «" . at_pt_mostrar($h['despues_de'] ?? null) . '».';
			continue;
		}
		if (count($out['hitos']) >= 10) {
			$avisos[] = "El hito «{$nombre}» se descartó: máximo 10 hitos.";
			continue;
		}
		$out['hitos'][] = ['nombre' => $nombre, 'despues_de' => $bloques_por_nombre[$despues], 'fecha' => ''];
	}

	// Qué necesitamos de ti (máximo 12).
	$necesitamos = is_array($plan['necesitamos_de_ti'] ?? null) ? array_values($plan['necesitamos_de_ti']) : [];
	foreach ($necesitamos as $i => $x) {
		$t = at_pt_texto(is_array($x) ? ($x['nombre'] ?? $x['texto'] ?? null) : $x, 160, 'Qué necesitamos de ti, punto ' . ($i + 1), $errores, $avisos);
		if ($t !== '' && count($out['necesitamos_de_ti']) < 12) {
			$out['necesitamos_de_ti'][] = $t;
		}
	}
	if ($out['necesitamos_de_ti'] === []) {
		$out['necesitamos_de_ti'] = at_pt_necesitamos_defecto();
		$avisos[] = '«Qué necesitamos de ti» venía vacío: se usó la lista de siempre (logo, textos y accesos).';
	}

	// Reuniones (máximo 8; cada una puede venir como texto o como {nombre, detalle}).
	$reuniones = is_array($plan['reuniones'] ?? null) ? array_values($plan['reuniones']) : [];
	foreach ($reuniones as $i => $r) {
		$q = 'Reunión ' . ($i + 1);
		$nombre = at_pt_texto(is_array($r) ? ($r['nombre'] ?? null) : $r, 80, $q, $errores, $avisos);
		$detalle = is_array($r) ? at_pt_texto($r['detalle'] ?? null, 200, "{$q}: detalle", $errores, $avisos) : '';
		if ($nombre !== '' && count($out['reuniones']) < 8) {
			$out['reuniones'][] = ['nombre' => $nombre, 'detalle' => $detalle];
		}
	}
	if ($out['reuniones'] === []) {
		$out['reuniones'] = at_pt_reuniones_defecto();
		$avisos[] = 'Las reuniones venían vacías: se usó la lista de siempre (inicio, seguimiento del plan, entrega y capacitación).';
	}

	// Soporte: garantía de 0 a 24 meses (3 por defecto, como garantia_meses_servicio en
	// inc/cierre-cliente/puras.php:428) y hasta 6 servicios mensuales como texto.
	$soporte = is_array($plan['soporte'] ?? null) ? $plan['soporte'] : [];
	$garantia = at_pt_entero($soporte['garantia_meses'] ?? 3);
	if ($garantia === null || $garantia < 0 || $garantia > 24) {
		$avisos[] = 'La garantía no era un número de 0 a 24 meses: quedó en 3.';
		$garantia = 3;
	}
	$out['soporte']['garantia_meses'] = $garantia;
	$mensuales = is_array($soporte['mensuales'] ?? null) ? array_values($soporte['mensuales']) : [];
	foreach ($mensuales as $i => $m) {
		$t = at_pt_texto(is_array($m) ? ($m['nombre'] ?? null) : $m, 160, 'Servicio mensual ' . ($i + 1), $errores, $avisos);
		if ($t !== '' && count($out['soporte']['mensuales']) < 6) {
			$out['soporte']['mensuales'][] = $t;
		}
	}

	// Fotos: una por lámina válida, con descripción de 1 a 1.200 caracteres (no se recorta: se perdería el cierre de
	// prohibiciones que agrega fotos_guard en n8n).
	$slides = at_pt_slides_foto();
	$vistos = [];
	$briefs = is_array($plan['image_briefs'] ?? null) ? array_values($plan['image_briefs']) : [];
	foreach ($briefs as $b) {
		$slide = is_array($b) && is_string($b['slide'] ?? null) ? trim($b['slide']) : '';
		$prompt = is_array($b) && is_string($b['prompt'] ?? null) && mb_check_encoding($b['prompt'], 'UTF-8')
			? trim((string) preg_replace('/\s+/u', ' ', $b['prompt'])) : '';
		if (!in_array($slide, $slides, true)) {
			$avisos[] = 'Se descartó la foto de la lámina «' . at_pt_mostrar(is_array($b) ? ($b['slide'] ?? null) : $b) . '»: esa lámina no existe en el plan.';
			continue;
		}
		if (isset($vistos[$slide])) {
			$avisos[] = "Se descartó una segunda foto para la lámina «{$slide}».";
			continue;
		}
		if ($prompt === '' || mb_strlen($prompt, 'UTF-8') > 1200) {
			$avisos[] = "Se descartó la foto de la lámina «{$slide}»: " . ($prompt === '' ? 'no trae descripción.' : 'la descripción pasa de 1.200 caracteres.');
			continue;
		}
		$vistos[$slide] = true;
		$out['image_briefs'][] = ['slide' => $slide, 'prompt' => $prompt];
	}

	// Una respuesta desbordada de la IA no llena la nota del plan (TEXT, 64 KB): hasta 25 mensajes de cada tipo.
	if (count($errores) > 25) {
		$resto = number_format(count($errores) - 25, 0, ',', '.');
		$errores = array_slice($errores, 0, 25);
		$errores[] = "… y {$resto} errores más.";
	}
	if (count($avisos) > 25) {
		$resto = number_format(count($avisos) - 25, 0, ',', '.');
		$avisos = array_slice($avisos, 0, 25);
		$avisos[] = "… y {$resto} avisos más.";
	}

	$ok = $errores === [];
	return ['ok' => $ok, 'errores' => $errores, 'avisos' => $avisos, 'plan' => $ok ? $out : []];
}

/** Lo que llega en «plan» a POST /plan/{id}/borrador: un objeto o el texto de la IA. Mismo resultado que
 *  at_pt_validar_plan(); si no hay un objeto JSON, error legible y plan vacío (Review Focus 2). Con $borrador true
 *  (un borrador nuevo de la IA) se quitan los bloques «Arranque» que traiga la IA, en cualquier fase, para que entre el
 *  fijo con la entrega de insumos de la cláusula 4.2; en «cambios» y en el panel se conserva el Arranque guardado, que
 *  Luis puede editar. */
function at_pt_validar_entrada(mixed $plan, bool $borrador = false): array {
	if (is_string($plan)) {
		$plan = at_pt_plan_de_json($plan);
	}
	if (!is_array($plan)) {
		return ['ok' => false, 'errores' => ['La IA no devolvió un plan en JSON válido (un objeto con fases).'], 'avisos' => [], 'plan' => []];
	}
	$quitados = 0;
	if ($borrador && is_array($plan['fases'] ?? null)) {
		foreach ($plan['fases'] as $k => $f) {
			if (!is_array($f) || !is_array($f['bloques'] ?? null)) {
				continue;
			}
			$quedan = array_values(array_filter($f['bloques'], static fn($b): bool => !(is_array($b) && is_string($b['nombre'] ?? null) && at_pt_clave_nombre($b['nombre']) === 'arranque')));
			$quitados += count($f['bloques']) - count($quedan);
			$plan['fases'][$k]['bloques'] = $quedan;
		}
	}
	$v = at_pt_validar_plan($plan);
	if ($quitados > 0) {
		array_unshift($v['avisos'], 'La IA mandó su propio bloque «Arranque»: se usó el fijo (reunión de inicio y entrega de logo, textos y accesos).');
	}
	return $v;
}

/* ---------- Tabla de tiempos -> días (Task 2) ---------- */

/** Reparte $total días entre actividades en proporción a $pesos (los días que propuso la IA): método del resto mayor
 *  con mínimo 1 por actividad. Se da a cada una la parte entera de su cuota (al menos 1); lo que falta va de a un día
 *  a las de mayor fracción (empate: la que va primero), sin contar las que subieron al mínimo de 1, que ya recibieron
 *  más que su cuota; si el mínimo de 1 hizo pasarse, se quita a la más pasada de su cuota. Así a la que la IA le
 *  estimó más nunca le tocan menos días que a otra. Si $total es menor que la cantidad de actividades, 1 a cada una.
 *  Cuentas en enteros: sin errores de coma. */
function at_pt_repartir(int $total, array $pesos): array {
	$pesos = array_map(static fn($p): int => max(1, (int) $p), array_values($pesos));
	$n = count($pesos);
	if ($n === 0) {
		return [];
	}
	if ($total <= $n) {
		return array_fill(0, $n, 1);
	}
	$suma = array_sum($pesos);
	$dias = [];
	$resto = [];
	foreach ($pesos as $i => $p) {
		$entero = intdiv($total * $p, $suma);
		$dias[$i] = max(1, $entero);
		// La que subió al mínimo de 1 ya recibió más que su cuota: no compite por los días que faltan.
		$resto[$i] = $entero >= 1 ? ($total * $p) % $suma : -1;
	}
	$falta = $total - array_sum($dias);
	if ($falta > 0) {
		$orden = array_keys($pesos);
		usort($orden, static fn(int $a, int $b): int => [$resto[$b], $a] <=> [$resto[$a], $b]);
		for ($k = 0; $k < $falta; $k++) {
			$dias[$orden[$k % $n]]++;
		}
	}
	while (array_sum($dias) > $total) {
		$quitar = null;
		$exceso_max = null;
		foreach ($dias as $i => $d) {
			$exceso = $d * $suma - $total * $pesos[$i]; // (días - cuota) × suma, en enteros
			if ($d > 1 && ($exceso_max === null || $exceso >= $exceso_max)) {
				$quitar = $i;
				$exceso_max = $exceso;
			}
		}
		$dias[$quitar]--;
	}
	return $dias;
}

/** Pone los días de la tabla de tiempos donde calza. Para cada par (servicio, etapa) con el servicio en la tabla y la
 *  etapa en diseño, desarrollo, pruebas o implementación, reparte el total de la tabla entre las actividades de ese
 *  par en todo el plan (at_pt_repartir, pesos = los días de la IA) y las marca 'tabla'. Si el par tiene alguna
 *  actividad 'luis', no se toca (Review Focus 3). Las que no calzan y no son 'luis' quedan 'ia'. Las de la etapa
 *  'arranque' (el bloque fijo) conservan su origen. */
function at_pt_aplicar_tabla(array $plan, array $tabla): array {
	$etapas_tabla = at_pt_etapas_tabla();
	$grupos = [];
	foreach (($plan['fases'] ?? []) as $fi => $fase) {
		foreach (($fase['bloques'] ?? []) as $bi => $bloque) {
			foreach (($bloque['actividades'] ?? []) as $ai => $a) {
				$servicio = (string) ($a['servicio'] ?? '');
				$etapa = (string) ($a['etapa'] ?? '');
				if ($etapa === 'arranque' && at_pt_clave_nombre((string) ($bloque['nombre'] ?? '')) === 'arranque') {
					continue; // el bloque fijo conserva su origen; fuera de él, «arranque» no es un par de la tabla
				}
				if ($servicio !== '' && in_array($etapa, $etapas_tabla, true) && is_array($tabla[$servicio] ?? null) && isset($tabla[$servicio][$etapa])) {
					$grupos[$servicio][$etapa][] = [$fi, $bi, $ai];
				} elseif (($a['origen'] ?? '') !== 'luis') {
					$plan['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['origen'] = 'ia';
				}
			}
		}
	}
	foreach ($grupos as $servicio => $por_etapa) {
		foreach ($por_etapa as $etapa => $lugares) {
			$pesos = [];
			$con_luis = false;
			foreach ($lugares as [$fi, $bi, $ai]) {
				$a = $plan['fases'][$fi]['bloques'][$bi]['actividades'][$ai];
				if (($a['origen'] ?? '') === 'luis') {
					$con_luis = true;
				}
				$pesos[] = (int) ($a['dias_habiles'] ?? 1);
			}
			if ($con_luis) {
				// El grupo tiene días de Luis: no se reparte ni se tocan sus días; lo demás lo estimó la IA.
				foreach ($lugares as [$fi, $bi, $ai]) {
					if (($plan['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['origen'] ?? '') !== 'luis') {
						$plan['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['origen'] = 'ia';
					}
				}
				continue;
			}
			$dias = at_pt_repartir((int) $tabla[$servicio][$etapa], $pesos);
			foreach ($lugares as $i => [$fi, $bi, $ai]) {
				$plan['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['dias_habiles'] = $dias[$i];
				$plan['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['origen'] = 'tabla';
			}
		}
	}
	return $plan;
}

/* ---------- Marcas de origen entre versiones del plan (Task 2) ---------- */

/** Lugar y clave de cada actividad del plan: [fase, bloque, actividad, clave]. La clave es el nombre normalizado y
 *  cuántas veces apareció antes ese nombre («revisión interna#1», «revisión interna#2»). No depende del bloque ni de la
 *  fase: mover una actividad de bloque no la hace nueva; renombrarla, sí (at_pt_luis_perdidas avisa si era de Luis).
 *  Límite conocido: si la IA inserta otra actividad con el mismo nombre ANTES de una de Luis, la marca y los días de
 *  Luis pasan a la primera de las dos (no hay otra forma de reconocerlas: la IA no conserva identificadores). */
function at_pt_recorrer_actividades(array $plan): array {
	$lugares = [];
	$vistas = [];
	foreach (($plan['fases'] ?? []) as $fi => $fase) {
		foreach (($fase['bloques'] ?? []) as $bi => $bloque) {
			foreach (($bloque['actividades'] ?? []) as $ai => $a) {
				$nombre = at_pt_clave_nombre((string) ($a['nombre'] ?? ''));
				$vistas[$nombre] = ($vistas[$nombre] ?? 0) + 1;
				$lugares[] = [$fi, $bi, $ai, $nombre . '#' . $vistas[$nombre]];
			}
		}
	}
	return $lugares;
}

/** Marca de origen de lo que cambió entre la versión guardada y la nueva: una actividad nueva o cuyos dias_habiles
 *  cambiaron queda con $marca; las demás conservan el origen guardado (no el que venga en el formulario o de la IA).
 *  $marca 'luis' (por defecto) al guardar desde el panel: lo cambió Luis a mano. $marca 'ia' en «Pedir cambios»: lo
 *  cambió o lo agregó la IA, así que el panel lo muestra «IA · revisar» y la IA lo puede volver a cambiar en la
 *  ronda siguiente. Cualquier otro valor de $marca vale 'luis'. */
function at_pt_marcar_ediciones(array $anterior, array $nuevo, string $marca = 'luis'): array {
	$marca = $marca === 'ia' ? 'ia' : 'luis';
	$guardadas = [];
	foreach (at_pt_recorrer_actividades($anterior) as [$fi, $bi, $ai, $clave]) {
		$guardadas[$clave] = $anterior['fases'][$fi]['bloques'][$bi]['actividades'][$ai];
	}
	foreach (at_pt_recorrer_actividades($nuevo) as [$fi, $bi, $ai, $clave]) {
		$a = $nuevo['fases'][$fi]['bloques'][$bi]['actividades'][$ai];
		$antes = $guardadas[$clave] ?? null;
		$cambio = $antes === null || (int) ($antes['dias_habiles'] ?? 0) !== (int) ($a['dias_habiles'] ?? 0);
		$nuevo['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['origen'] = $cambio ? $marca : (string) ($antes['origen'] ?? ($a['origen'] ?? 'ia'));
	}
	return $nuevo;
}

/** «Pedir cambios» con IA (Review Focus 3): las actividades que en $anterior eran 'luis' recuperan sus días y su marca
 *  aunque la IA los haya cambiado, y la IA no puede crear marcas 'luis' (las que no lo eran vuelven a 'ia'). Con
 *  $anterior vacío (borrador nuevo) solo baja a 'ia' las marcas 'luis' que invente la IA, para que la tabla de tiempos
 *  no se salte ese grupo. La ruta REST la llama después de validar y antes de at_pt_aplicar_tabla() (borrador) o de
 *  at_pt_marcar_ediciones(..., 'ia') (cambios). */
function at_pt_respetar_dias_luis(array $anterior, array $nuevo): array {
	$de_luis = [];
	foreach (at_pt_recorrer_actividades($anterior) as [$fi, $bi, $ai, $clave]) {
		$a = $anterior['fases'][$fi]['bloques'][$bi]['actividades'][$ai];
		if (($a['origen'] ?? '') === 'luis') {
			$de_luis[$clave] = (int) ($a['dias_habiles'] ?? 1);
		}
	}
	foreach (at_pt_recorrer_actividades($nuevo) as [$fi, $bi, $ai, $clave]) {
		$origen = (string) ($nuevo['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['origen'] ?? '');
		if (isset($de_luis[$clave])) {
			$nuevo['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['dias_habiles'] = $de_luis[$clave];
			$nuevo['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['origen'] = 'luis';
		} elseif ($origen === 'luis') {
			$nuevo['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['origen'] = 'ia';
		}
	}
	return $nuevo;
}

/** Actividades que eran 'luis' en $anterior y ya no están en $nuevo (la IA las renombró o las quitó al pedir cambios):
 *  sus días no se pueden devolver solos. La ruta de «cambios» (Task 7) suma estos textos a los avisos del plan. */
function at_pt_luis_perdidas(array $anterior, array $nuevo): array {
	$en_nuevo = [];
	foreach (at_pt_recorrer_actividades($nuevo) as [, , , $clave]) {
		$en_nuevo[$clave] = true;
	}
	$avisos = [];
	foreach (at_pt_recorrer_actividades($anterior) as [$fi, $bi, $ai, $clave]) {
		$a = $anterior['fases'][$fi]['bloques'][$bi]['actividades'][$ai];
		if (($a['origen'] ?? '') === 'luis' && !isset($en_nuevo[$clave])) {
			$n = (int) ($a['dias_habiles'] ?? 1);
			$avisos[] = 'La IA renombró o quitó «' . $a['nombre'] . '», que tenía ' . ($n === 1 ? '1 día hábil' : "{$n} días hábiles") . ' puestos por ti: revísala en el panel.';
		}
	}
	return $avisos;
}

/* ---------- Fechas y cronograma de la carta Gantt (Task 3) ---------- */

/** Semanas de calendario (de lunes a domingo) que toca el rango; 0 si una fecha es inválida o el rango está al revés. */
function at_pt_semanas(string $desde, string $hasta): int {
	$a = at_pt_ymd($desde);
	$b = at_pt_ymd($hasta);
	if ($a === '' || $b === '' || $b < $a) {
		return 0;
	}
	$lunes = static function (string $f): DateTimeImmutable {
		$d = at_pt_dia($f);
		return $d->modify('-' . ((int) $d->format('N') - 1) . ' days');
	};
	return intdiv((int) $lunes($a)->diff($lunes($b))->days, 7) + 1;
}

/** Llena desde y hasta de cada actividad, la fecha de cada hito, fecha_inicio y el cronograma de la carta Gantt.
 *  Secuencia: fases, bloques y actividades en su orden. La primera actividad de un bloque parte en el cursor; cada
 *  siguiente parte el hábil siguiente al mayor «hasta» del bloque, salvo en_paralelo, que parte el mismo día que la
 *  anterior. Un bloque con entrega suma «Tu revisión» (5 días hábiles, del cliente) desde el hábil siguiente a su fin,
 *  y el cursor sigue después de la revisión. Barras: una por bloque (responsable común o 'ambos') y una por revisión.
 *  Hitos: fin de la revisión del bloque despues_de (o su fin si no tiene entrega); se agrega siempre «Entrega
 *  estimada» = fin del último bloque de implementación (o fin del cronograma si no hay esa fase). Un $inicio que no es
 *  hábil parte el hábil siguiente (Review Focus 1). Con $inicio inválido devuelve el plan sin tocar. */
function at_pt_calcular_fechas(array $plan, string $inicio, array $feriados): array {
	$cursor = at_pt_sumar_habiles($inicio, 1, $feriados);
	if ($cursor === '' || !is_array($plan['fases'] ?? null)) {
		return $plan;
	}
	$plan['fecha_inicio'] = $cursor;
	$barras = [];
	$fecha_hito = [];
	$fin_implementacion = '';
	$fin = $cursor;
	foreach ($plan['fases'] as $fi => $fase) {
		$clave_fase = (string) ($fase['clave'] ?? '');
		foreach (($fase['bloques'] ?? []) as $bi => $bloque) {
			$actividades = $bloque['actividades'] ?? [];
			if (!is_array($actividades) || $actividades === []) {
				continue;
			}
			$inicio_bloque = $cursor;
			$fin_bloque = '';
			$desde_anterior = $cursor;
			$responsables = [];
			$primera = true;
			foreach ($actividades as $ai => $a) {
				if ($primera) {
					$desde = $cursor;
				} elseif (!empty($a['en_paralelo'])) {
					$desde = $desde_anterior;
				} else {
					$desde = at_pt_siguiente_habil($fin_bloque, $feriados);
				}
				$hasta = at_pt_sumar_habiles($desde, max(1, (int) ($a['dias_habiles'] ?? 1)), $feriados);
				$plan['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['desde'] = $desde;
				$plan['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['hasta'] = $hasta;
				$fin_bloque = max($fin_bloque, $hasta);
				$desde_anterior = $desde;
				$responsables[(string) ($a['responsable'] ?? 'ambos')] = true;
				$primera = false;
			}
			$barras[] = [
				'fase'        => $clave_fase,
				'etiqueta'    => (string) ($bloque['nombre'] ?? ''),
				'tipo'        => 'trabajo',
				'responsable' => count($responsables) === 1 ? (string) array_key_first($responsables) : 'ambos',
				'desde'       => $inicio_bloque,
				'hasta'       => $fin_bloque,
			];
			$termina = $fin_bloque;
			if (!empty($bloque['entrega'])) {
				$revision_desde = at_pt_siguiente_habil($fin_bloque, $feriados);
				$termina = at_pt_sumar_habiles($revision_desde, AT_PT_DIAS_REVISION, $feriados);
				$barras[] = ['fase' => $clave_fase, 'etiqueta' => 'Tu revisión', 'tipo' => 'revision', 'responsable' => 'cliente', 'desde' => $revision_desde, 'hasta' => $termina];
			}
			$fecha_hito[at_pt_clave_nombre((string) ($bloque['nombre'] ?? ''))] ??= $termina;
			if ($clave_fase === 'implementacion') {
				$fin_implementacion = $fin_bloque;
			}
			$fin = max($fin, $termina);
			$cursor = at_pt_siguiente_habil($termina, $feriados);
		}
	}
	$hitos_plan = [];
	$hitos = [];
	foreach (($plan['hitos'] ?? []) as $h) {
		$nombre = is_array($h) ? (string) ($h['nombre'] ?? '') : '';
		$clave = at_pt_clave_nombre(is_array($h) ? (string) ($h['despues_de'] ?? '') : '');
		if ($nombre === '' || !isset($fecha_hito[$clave]) || at_pt_clave_nombre($nombre) === 'entrega estimada') {
			continue; // la validación ya avisó; aquí se descarta sin ruido
		}
		$h['fecha'] = $fecha_hito[$clave];
		$hitos_plan[] = $h;
		$hitos[] = ['nombre' => $nombre, 'fecha' => $fecha_hito[$clave]];
	}
	$hitos[] = ['nombre' => 'Entrega estimada', 'fecha' => $fin_implementacion !== '' ? $fin_implementacion : $fin];
	usort($hitos, static fn(array $x, array $y): int => strcmp($x['fecha'], $y['fecha'])); // estable: a igual fecha, en su orden
	$plan['hitos'] = $hitos_plan;
	$plan['cronograma'] = [
		'inicio'  => $plan['fecha_inicio'],
		'fin'     => $fin,
		'semanas' => at_pt_semanas($plan['fecha_inicio'], $fin),
		'barras'  => $barras,
		'hitos'   => $hitos,
	];
	return $plan;
}

/* ---------- Estados del plan y costo de fotos (Task 4) ---------- */

/** Estados del plan y a cuáles puede pasar cada uno. «Destrabar» es generando, cambios o aprobando -> error; desde
 *  error se reintenta el borrador (-> generando) o se vuelve al borrador (-> borrador). 'enviado' es de la Etapa 2. */
function at_pt_transiciones(): array {
	return [
		'generando' => ['borrador', 'error'],
		'borrador'  => ['borrador', 'cambios', 'aprobando', 'error'],
		'cambios'   => ['borrador', 'error'],
		'aprobando' => ['listo', 'error'],
		'listo'     => ['borrador', 'cambios', 'aprobando', 'enviado'],
		'error'     => ['generando', 'borrador', 'cambios', 'aprobando'],
		'enviado'   => [],
	];
}

/** ¿Puede el plan pasar de $de a $a? Un estado desconocido no pasa a ninguno. */
function at_pt_transicion_valida(string $de, string $a): bool {
	return in_array($a, at_pt_transiciones()[$de] ?? [], true);
}

// Tarifa de lista por foto: la misma de at_propuesta_costo_fotos() (inc/proposals-flow.php:108 y :124, Soul 2,
// US$0,0032 c/u al 2026-09-20). Se copia aquí para que las pruebas puras no dependan de proposals-flow.php. Si cambia
// el modelo o la tarifa (spec §5: qwen-image o recraft se decide al implementar), cambiar los dos lados.
const AT_PT_USD_POR_FOTO = 0.0032;

// Revisión de texto de las fotos en «3 Render» (decisión D18, cambiada por Luis el 29-sep): una consulta a GPT-4o con
// las fotos nuevas. Es la misma cifra que suma at_propuesta_costo_fotos() para «3 Final» de las propuestas
// (inc/proposals-flow.php:121, 27-sep): ≈ US$0,026 por consulta con 9 fotos 16:9 en detail high, según la tarifa y
// el conteo de imágenes de la documentación de OpenAI, NO medido en una factura; con 8 o 10 fotos se usa la misma cifra
// (el mismo criterio que el panel de propuestas). Hasta dos consultas si hay que rehacer fotos. Si cambia el modelo o
// la tarifa, cambiar aquí, en inc/proposals-flow.php y en los docstrings de build_3_final.py y build_plan_3_render.py.
const AT_PT_USD_REVISION = 0.026;

/** Fotos nuevas del plan y su costo, para mostrar antes de «Aprobar» (decisión 7). Cuenta un brief válido por lámina
 *  (lámina de at_pt_slides_foto() y descripción no vacía). Si hay propuesta, 'cover' y 'cierre' no cuentan: se
 *  reutilizan sus fotos y no se vuelven a revisar (las propuestas generadas desde el 27-sep ya pasaron por la misma
 *  revisión en «3 Final»). Con al menos una foto nueva se suma la revisión de texto (D18): 'usd_lista' = fotos + una
 *  consulta; 'usd_max' = cada foto dos veces (rehecha por texto) y dos consultas, como at_propuesta_costo_fotos(). Sin
 *  fotos nuevas no hay revisión ni costo. */
function at_pt_costo_fotos(array $image_briefs, bool $hay_propuesta): array {
	$slides = at_pt_slides_foto();
	$reutilizadas = $hay_propuesta ? ['cover', 'cierre'] : [];
	$nuevas = [];
	foreach ($image_briefs as $b) {
		$slide = is_array($b) && is_string($b['slide'] ?? null) ? trim($b['slide']) : '';
		$prompt = is_array($b) && is_string($b['prompt'] ?? null) ? trim($b['prompt']) : '';
		if ($prompt !== '' && in_array($slide, $slides, true) && !in_array($slide, $reutilizadas, true)) {
			$nuevas[$slide] = true;
		}
	}
	$n = count($nuevas);
	$revision = $n > 0 ? AT_PT_USD_REVISION : 0.0;
	return [
		'fotos'        => $n,
		'usd_lista'    => round($n * AT_PT_USD_POR_FOTO + $revision, 4),
		'usd_revision' => $revision,
		'usd_max'      => round($n * AT_PT_USD_POR_FOTO * 2 + $revision * 2, 4),
	];
}

/* ---------- Cuerpo que WordPress manda al renderer (Task 4) ---------- */

/** Mensaje del enlace «Por WhatsApp con Tech» del cierre del documento (spec §7). */
function at_pt_texto_whatsapp_agenda(string $codigo): string {
	return 'Hola Tech, quiero agendar la llamada de seguimiento de mi plan de trabajo (código ' . $codigo . ')';
}

/** Cuerpo de POST /render del renderer con document_type 'plan' (forma en el esqueleto). $datos = ['codigo',
 *  'company_name', 'client_name', 'portal_url', 'whatsapp', 'fecha_firma', 'garantia_meses'] (at_pt_datos_render(),
 *  Task 5). company_name ya viene en el orden de la decisión D9 (company_name de la propuesta -> nombre_proyecto o
 *  razon_social_cliente del contrato -> nombre del cliente); si aun así llega vacío, se usa el nombre del cliente o
 *  el del proyecto, porque el renderer lo exige no vacío. garantia_meses es la del contrato firmado (decisión D8): si
 *  es un entero >= 0 (número o texto de dígitos) reemplaza soporte.garantia_meses del plan, incluido el 0 (el
 *  contrato no promete garantía, el documento tampoco); si falta o no es un entero >= 0, queda la del plan. El
 *  contrato manda. $final false = vista previa
 *  (draft true, sin fotos: image_briefs vacío); $final true = image_briefs del plan. 'images' sale como objeto JSON
 *  vacío ({}) para que n8n le agregue las fotos reutilizadas por lámina (un [] de PHP llegaría como arreglo y
 *  JSON.stringify perdería esas claves). portal_url solo si es http(s); sin correo no hay portal y la lámina «Sigue tu
 *  proyecto» va sin enlace (Review Focus 4). agenda.web_url apunta a ver-plan.php?id=…&agendar=1 cuando $datos['sitio'] viene (Etapa 2); sin sitio queda ''. */
function at_pt_armar_render(array $plan, array $datos, bool $final): array {
	$codigo = trim((string) ($datos['codigo'] ?? ''));
	$empresa = trim((string) ($datos['company_name'] ?? ''));
	$cliente = trim((string) ($datos['client_name'] ?? ''));
	$cronograma = is_array($plan['cronograma'] ?? null) ? $plan['cronograma'] : [];
	$soporte = is_array($plan['soporte'] ?? null) ? $plan['soporte'] : ['garantia_meses' => 3, 'mensuales' => []];
	$garantia = at_pt_entero($datos['garantia_meses'] ?? null);
	if ($garantia !== null && $garantia >= 0) {
		$soporte['garantia_meses'] = $garantia; // decisión D8: el contrato manda, también con 0 (sin garantía)
	}
	$proyecto = trim((string) ($plan['proyecto'] ?? ''));
	if ($proyecto === '') {
		$proyecto = $empresa !== '' ? $empresa : 'Tu proyecto';
	}
	if ($empresa === '') {
		// Persona natural sin empresa: el renderer exige company_name no vacío (validatePlanPayload).
		$empresa = $cliente !== '' ? $cliente : $proyecto;
	}
	$fecha_firma = at_pt_ymd((string) ($datos['fecha_firma'] ?? ''));
	if ($fecha_firma === '') {
		$fecha_firma = at_pt_ymd((string) ($plan['fecha_firma'] ?? ''));
	}
	$telefono = (string) preg_replace('/\D/', '', (string) ($datos['whatsapp'] ?? ''));
	if (strlen($telefono) === 9 && $telefono[0] === '9') {
		$telefono = '56' . $telefono; // mismo criterio que at_cc_telefono_normalizado()
	}
	$portal = trim((string) ($datos['portal_url'] ?? ''));
	if (!preg_match('#^https?://\S+$#i', $portal)) {
		$portal = '';
	}
	return [
		'document_type'     => 'plan',
		'unique_id'         => $codigo,
		'draft'             => !$final,
		'company_name'      => $empresa,
		'client_name'       => $cliente,
		'proyecto'          => $proyecto,
		'fecha_firma_larga' => at_pt_fecha_larga($fecha_firma),
		'fecha_inicio'      => (string) ($cronograma['inicio'] ?? ($plan['fecha_inicio'] ?? '')),
		'fecha_fin'         => (string) ($cronograma['fin'] ?? ''),
		'semanas'           => (int) ($cronograma['semanas'] ?? 0),
		'metodo'            => ['hechas' => ['diagnostico', 'priorizacion'], 'actual' => 'propuesta', 'proximas' => ['diseno_desarrollo', 'implementacion', 'soporte']],
		'fases'             => array_values($plan['fases'] ?? []),
		'cronograma'        => $cronograma,
		'necesitamos_de_ti' => array_values($plan['necesitamos_de_ti'] ?? []),
		'reuniones'         => array_values($plan['reuniones'] ?? []),
		'soporte'           => $soporte,
		'portal_url'        => $portal,
		'agenda'            => [
			'whatsapp_url' => $telefono === '' ? '' : 'https://wa.me/' . $telefono . '?text=' . rawurlencode(at_pt_texto_whatsapp_agenda($codigo)),
			'web_url'      => at_pt_url_ver_plan((string) ($datos['sitio'] ?? ''), $codigo, true),
		],
		'image_briefs'      => $final ? array_values($plan['image_briefs'] ?? []) : [],
		'images'            => new stdClass(),
	];
}

/* ---------- Etapa 2: envío al cliente, vista pública y agenda web ---------- */

/** Enlace público del plan en el sitio de AT; con $agendar abre el selector de horarios. '' sin sitio o con un código que
 *  no es alfanumérico de 6 a 32 caracteres. */
function at_pt_url_ver_plan(string $base, string $codigo, bool $agendar = false): string {
	$base = rtrim(trim($base), '/');
	if ($base === '' || !preg_match('/^[A-Za-z0-9]{6,32}$/', $codigo)) {
		return '';
	}
	return $base . '/ver-plan.php?id=' . $codigo . ($agendar ? '&agendar=1' : '');
}

/** wa.me de Tech con el mensaje para agendar y el código del plan; '' sin número de AT. */
function at_pt_url_whatsapp_agenda(string $telefono_at, string $codigo): string {
	$n = at_pt_telefono_wa($telefono_at);
	return $n === '' ? '' : 'https://wa.me/' . $n . '?text=' . rawurlencode(at_pt_texto_whatsapp_agenda($codigo));
}

/** Teléfono para wa.me: solo dígitos; un celular chileno de 9 dígitos que parte en 9 lleva 56 delante. '' con menos de
 *  9 dígitos o más de 15. */
function at_pt_telefono_wa(string $telefono): string {
	$n = (string) preg_replace('/\D/', '', $telefono);
	if (strlen($n) === 9 && $n[0] === '9') {
		$n = '56' . $n;
	}
	return (strlen($n) < 10 || strlen($n) > 15) ? '' : $n;
}

/** Mensaje que Luis manda desde su WhatsApp con el enlace del plan. */
function at_pt_texto_whatsapp_envio(string $nombre, string $proyecto, string $url_ver): string {
	$saludo = trim($nombre) !== '' ? 'Hola ' . trim($nombre) : 'Hola';
	$de = trim($proyecto) !== '' ? ' de ' . trim($proyecto) : '';
	return $saludo . ', te escribe Luis de AutomatizaTech. Te comparto el plan de trabajo' . $de
		. ', con las fases, las fechas estimadas y lo que necesitamos de ti: ' . $url_ver
		. "\nAhí mismo puedes agendar la llamada de seguimiento para revisarlo juntos.";
}

/** «Entrega estimada» del cronograma (D4); sin ese hito, el fin del cronograma; '' sin cronograma. */
function at_pt_entrega_estimada(array $plan): string {
	$crono = is_array($plan['cronograma'] ?? null) ? $plan['cronograma'] : [];
	foreach ((array) ($crono['hitos'] ?? []) as $h) {
		if (is_array($h) && ($h['nombre'] ?? '') === 'Entrega estimada') {
			return at_pt_ymd((string) ($h['fecha'] ?? ''));
		}
	}
	return at_pt_ymd((string) ($crono['fin'] ?? ''));
}

/**
 * Correo «Tu plan de trabajo». $v: nombre, proyecto, logo, url_ver, url_agendar, url_whatsapp, inicio y entrega (Y-m-d),
 * con_pdf (bool). Hostinger rechaza (554) los correos que enlazan a *.easypanel.host: un enlace del renderer no se dibuja.
 */
function at_pt_correo_plan_html(array $v): string {
	$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
	$url = function ($u): string {
		$u = trim((string) $u);
		return (preg_match('#^https?://\S+$#i', $u) && stripos($u, 'easypanel') === false) ? $u : '';
	};
	$nombre = trim((string) ($v['nombre'] ?? ''));
	$proyecto = trim((string) ($v['proyecto'] ?? ''));
	$logo = $url($v['logo'] ?? '');
	$ver = $url($v['url_ver'] ?? '');
	$agendar = $url($v['url_agendar'] ?? '');
	$wa = $url($v['url_whatsapp'] ?? '');
	$inicio = at_pt_fecha_larga((string) ($v['inicio'] ?? ''));
	$entrega = at_pt_fecha_larga((string) ($v['entrega'] ?? ''));
	$boton = function (string $href, string $texto, string $fondo) use ($h): string {
		return '<a href="' . $h($href) . '" style="display:inline-block;background:' . $fondo . ';color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 26px;border-radius:24px;margin:6px">' . $h($texto) . '</a>';
	};
	$fechas = ($inicio !== '' && $entrega !== '')
		? '<p>Partimos el <strong>' . $h($inicio) . '</strong> y la entrega estimada es el <strong>' . $h($entrega) . '</strong>. Son fechas estimadas: corren desde que recibimos el anticipo y tus insumos.</p>'
		: '';
	return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f4f6fb;margin:0;padding:0;color:#222">'
		. '<div style="max-width:600px;margin:24px auto;background:#ffffff;border-radius:10px;overflow:hidden">'
		. '<div style="background:#1e40af;color:#ffffff;text-align:center;padding:26px 20px">'
		. ($logo !== '' ? '<img src="' . $h($logo) . '" alt="AutomatizaTech" style="max-height:64px"><br>' : '')
		. '<h1 style="margin:12px 0 0;font-size:22px">Tu plan de trabajo</h1></div>'
		. '<div style="padding:26px;line-height:1.6">'
		. '<p>Hola <strong>' . $h($nombre !== '' ? $nombre : 'cliente') . '</strong>,</p>'
		. '<p>Te enviamos el plan de trabajo' . ($proyecto !== '' ? ' de <strong>' . $h($proyecto) . '</strong>' : '')
		. ': qué hacemos, en qué orden, cuánto demora cada parte, qué necesitamos de ti y cuándo revisas y apruebas.</p>'
		. $fechas
		. (!empty($v['con_pdf']) ? '<p>Va adjunto en PDF; también puedes verlo en línea.</p>' : '')
		. ($ver !== '' ? '<p style="text-align:center;margin:22px 0">' . $boton($ver, 'Ver mi plan de trabajo', '#1e40af') . '</p>' : '')
		. '<p>Queremos revisarlo contigo y aclarar tus dudas en una llamada de seguimiento. Agéndala cuando te acomode:</p>'
		. '<p style="text-align:center;margin:18px 0">'
		. ($agendar !== '' ? $boton($agendar, 'Agendar mi llamada de seguimiento', '#059669') : '')
		. ($wa !== '' ? $boton($wa, 'Agendar por WhatsApp con Tech', '#17b7b1') : '')
		. '</p>'
		. '<p>Cualquier duda, responde este correo.</p><p>Un abrazo,<br><strong>El equipo de AutomatizaTech</strong></p></div>'
		. '<div style="background:#f1f1f1;color:#777;text-align:center;font-size:12px;padding:14px">© ' . date('Y') . ' AutomatizaTech · automatizatech.cl</div>'
		. '</div></body></html>';
}

/**
 * '' si la hora sirve para la llamada; si no, la clave del motivo (at_pt_mensajes_agenda()). $fecha 'Y-m-d', $hora
 * 'HH:00' o 'HH:00:00', $ahora 'Y-m-d H:i' en hora de Chile (current_time). $disp es lo que devuelve
 * automatiza_tech_check_availability(): ['isFullDay' => bool, 'busySlots' => ['HH:MM', …], 'workingHours' =>
 * ['start' => 'HH:MM', 'end' => 'HH:MM']]; vacío o sin horario = día no disponible. Misma regla que la portada:
 * horas en punto desde el inicio hasta antes del fin, desde la hora siguiente a la actual y hasta 90 días.
 */
function at_pt_motivo_hora_agenda(string $fecha, string $hora, string $ahora, array $disp): string {
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || at_pt_ymd($fecha) === '') {
		return 'fecha_invalida';
	}
	if (!preg_match('/^([01]\d|2[0-3]):00(:00)?$/', $hora, $m)) {
		return 'hora_invalida';
	}
	$h = (int) $m[1];
	$hoy = substr($ahora, 0, 10);
	if ($fecha < $hoy || ($fecha === $hoy && $h <= (int) substr($ahora, 11, 2))) {
		return 'pasada';
	}
	$tope = (new DateTimeImmutable($hoy . ' 00:00:00', new DateTimeZone('UTC')))->modify('+90 days')->format('Y-m-d');
	if ($fecha > $tope) {
		return 'muy_lejos';
	}
	if (!empty($disp['isFullDay']) || !is_array($disp['workingHours'] ?? null)) {
		return 'dia_no_disponible';
	}
	$inicio = (int) explode(':', (string) ($disp['workingHours']['start'] ?? '00:00'))[0];
	$fin = (int) explode(':', (string) ($disp['workingHours']['end'] ?? '00:00'))[0];
	if ($h < $inicio || $h >= $fin) {
		return 'fuera_de_horario';
	}
	$texto = sprintf('%02d:00', $h);
	foreach ((array) ($disp['busySlots'] ?? []) as $ocupada) {
		if (substr((string) $ocupada, 0, 5) === $texto) {
			return 'hora_ocupada';
		}
	}
	return '';
}

/** Lo que ve el cliente en la agenda web por cada motivo. */
function at_pt_mensajes_agenda(): array {
	return [
		'fecha_invalida'     => 'Elige una fecha válida.',
		'hora_invalida'      => 'Elige una de las horas disponibles.',
		'pasada'             => 'Esa hora ya pasó. Elige otra.',
		'muy_lejos'          => 'Solo agendamos hasta 90 días hacia adelante.',
		'dia_no_disponible'  => 'Ese día no atendemos. Elige otra fecha.',
		'fuera_de_horario'   => 'Esa hora está fuera de nuestro horario. Elige otra.',
		'hora_ocupada'       => 'Esa hora se acaba de ocupar. Elige otra.',
		'plan_no_disponible' => 'Este plan de trabajo no está disponible.',
		'sesion_vencida'     => 'La página quedó abierta mucho rato. Recárgala e inténtalo de nuevo.',
		'muchos_intentos'    => 'Hiciste muchos intentos seguidos. Espera un rato o escríbenos por WhatsApp.',
		'sin_correo'         => 'No tenemos tu correo para enviarte la invitación. Escríbenos por WhatsApp y la agendamos.',
		'ya_agendada'        => 'Ya tienes una llamada de seguimiento agendada.',
		'limite_reuniones'   => 'Ya tienes 2 reuniones activas. Cancela o espera a que pase alguna, o escríbenos por WhatsApp.',
		'no_guardo'        => 'No pudimos agendar la llamada. Inténtalo de nuevo o escríbenos por WhatsApp.',
	];
}

/** Token de la agenda web de un plan para el día $dia (días desde 1970, intdiv(time(), 86400)): reemplaza al nonce de
 *  WordPress, que depende de la sesión (el cliente no tiene; Luis sí, y su nonce no valdría en la ruta pública). */
function at_pt_token_agenda(string $codigo, int $dia, string $sal): string {
	return substr(hash_hmac('sha256', 'at-pt-agenda|' . $codigo . '|' . $dia, $sal), 0, 24);
}

/** El token vale el día en que se creó y el siguiente. */
function at_pt_token_agenda_valido(string $token, string $codigo, int $hoy, string $sal): bool {
	if (!preg_match('/^[a-f0-9]{24}$/', $token)) {
		return false;
	}
	return hash_equals(at_pt_token_agenda($codigo, $hoy, $sal), $token) || hash_equals(at_pt_token_agenda($codigo, $hoy - 1, $sal), $token);
}
