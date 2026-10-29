<?php
/**
 * Citas del número para cancelar o reprogramar con botones (diseño 2026-10-29-whatsapp-citas-botones).
 * Funciones puras, sin base de datos: reciben las filas ya leídas y deciden cuáles se ofrecen y cómo se nombran.
 */
if (!defined('ABSPATH') && !defined('AT_WA_PURAS_TEST')) {
	exit;
}

const AT_WA_CITA_INACTIVA = ['cancelled', 'completed', 'no_show'];
const AT_WA_CITAS_TOPE = 10;

/** «Diagnóstico gratis · mar 03-nov · 17:00» o «Seguimiento: <vínculo> · …»; $cuando es 'Y-m-d H:i'. */
function at_wa_cita_etiqueta(string $tipo, string $cuando, string $vinculo): string {
	$dias = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
	$meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
	$t = strtotime($cuando);
	$fecha = $t === false ? $cuando : $dias[(int) date('w', $t)] . ' ' . date('d', $t) . '-' . $meses[(int) date('n', $t) - 1] . ' · ' . date('H:i', $t);
	if ($tipo === 'diagnostico') {
		return 'Diagnóstico gratis · ' . $fecha;
	}
	$vinculo = trim((string) preg_replace('/\s+/u', ' ', $vinculo));
	return ($vinculo !== '' ? 'Seguimiento: ' . $vinculo : 'Seguimiento') . ' · ' . $fecha;
}

/** 'Y-m-d H:i' de una fecha y una hora de la base; '' si falta alguna. */
function at_wa_cita_cuando(string $fecha, string $hora): string {
	$fecha = trim($fecha);
	$hora = substr(trim($hora), 0, 5);
	return preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) && preg_match('/^\d{2}:\d{2}$/', $hora) ? $fecha . ' ' . $hora : '';
}

/**
 * Las citas activas y futuras (después de $ahora, 'Y-m-d H:i'), de la más próxima a la más lejana, con un tope de 10.
 * Diagnósticos: filas de automatiza_leads con empresa; seguimientos: filas de automatiza_followup_meetings con vinculo.
 */
function at_wa_citas_armar(array $diagnosticos, array $seguimientos, string $ahora): array {
	$citas = [];
	$sumar = function (string $tipo, $f, string $cuando, string $estado, array $datos) use (&$citas, $ahora) {
		if ($cuando === '' || $cuando <= $ahora || in_array($estado, AT_WA_CITA_INACTIVA, true)) {
			return;
		}
		$citas[] = array_merge(['tipo' => $tipo, 'id' => (int) $f['id'], 'cuando' => $cuando,
			'etiqueta' => at_wa_cita_etiqueta($tipo, $cuando, $tipo === 'seguimiento' ? (string) ($f['vinculo'] ?? '') : '')], $datos);
	};
	foreach ($diagnosticos as $f) {
		$f = (array) $f;
		$sumar('diagnostico', $f, at_wa_cita_cuando((string) ($f['scheduled_date'] ?? ''), (string) ($f['scheduled_time'] ?? '')),
			(string) ($f['status'] ?? ''), ['nombre' => (string) ($f['name'] ?? ''), 'correo' => (string) ($f['email'] ?? ''),
				'empresa' => (string) ($f['empresa'] ?? ''), 'contrato_id' => 0, 'propuesta_id' => 0]);
	}
	foreach ($seguimientos as $f) {
		$f = (array) $f;
		$sumar('seguimiento', $f, at_wa_cita_cuando((string) ($f['meeting_date'] ?? ''), (string) ($f['meeting_time'] ?? '')),
			(string) ($f['status'] ?? ''), ['nombre' => (string) ($f['client_name'] ?? ''), 'correo' => (string) ($f['client_email'] ?? ''),
				'empresa' => (string) ($f['company'] ?? ''), 'contrato_id' => (int) ($f['contrato_id'] ?? 0), 'propuesta_id' => (int) ($f['propuesta_id'] ?? 0)]);
	}
	usort($citas, function ($a, $b) {
		return [$a['cuando'], $a['tipo'], $a['id']] <=> [$b['cuando'], $b['tipo'], $b['id']];
	});
	return array_slice($citas, 0, AT_WA_CITAS_TOPE);
}
