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
