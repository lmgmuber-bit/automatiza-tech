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
