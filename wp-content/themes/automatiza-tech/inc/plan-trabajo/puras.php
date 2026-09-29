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
