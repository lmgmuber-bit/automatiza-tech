<?php
/**
 * Quién escribe por WhatsApp: nuevo, prospecto o cliente (diseño 2026-10-28-whatsapp-reconoce-cliente).
 * Funciones puras, sin base de datos: reciben las fichas ya armadas y deciden la situación y las opciones.
 */
if (!defined('ABSPATH') && !defined('AT_WA_PURAS_TEST')) {
	exit;
}

const AT_WA_TIPOS_TRABAJO = ['servicios', 'soporte', 'sla'];
const AT_WA_PROPUESTA_ABIERTA = ['sent', 'evaluando'];
const AT_WA_PROPUESTA_NO_CUENTA = ['borrador', 'error'];
const AT_WA_DIAGNOSTICO_NO_VALE = ['cancelled', 'no_show'];

/** Los últimos 9 dígitos de un teléfono, sin signos; '' si tiene menos de 9. */
function at_wa_ultimos9(string $tel): string {
	$d = (string) preg_replace('/\D/', '', $tel);
	return strlen($d) >= 9 ? substr($d, -9) : '';
}

/** Primera palabra del nombre con mayúscula inicial ('julio pérez' => 'Julio'); '' si no hay. */
function at_wa_primer_nombre(string $nombre): string {
	$partes = preg_split('/\s+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
	return $partes ? mb_convert_case($partes[0], MB_CASE_TITLE, 'UTF-8') : '';
}

/** El código de 12 caracteres de «(código XXXXXXXXXXXX)» que traen los botones de WhatsApp del plan; '' si no viene. */
function at_wa_codigo_de_texto(string $texto): string {
	return preg_match('/\(\s*c[oó]digo\s+([A-Za-z0-9]{12})\s*\)/iu', $texto, $m) ? $m[1] : '';
}

/** Nombre corto de un contrato para la persona: el proyecto del plan, el primer servicio, «Soporte técnico» o su número. */
function at_wa_nombre_contrato(string $tipo, array $placeholders, string $proyecto_plan, string $numero): string {
	if (trim($proyecto_plan) !== '') {
		return trim($proyecto_plan);
	}
	if ($tipo === 'servicios') {
		foreach (preg_split('/\R/u', (string) ($placeholders['servicios_contratados'] ?? '')) ?: [] as $linea) {
			$linea = trim((string) preg_replace('/^\s*[-•*]\s*/u', '', $linea));
			if ($linea !== '') {
				return $linea;
			}
		}
	}
	if (in_array($tipo, ['soporte', 'sla'], true)) {
		return 'Soporte técnico';
	}
	return 'Contrato ' . trim($numero);
}

/** Nombre corto de una propuesta: su servicio, la empresa o «Propuesta del DD-MM». */
function at_wa_nombre_propuesta(string $servicio, string $empresa, string $creada): string {
	if (trim($servicio) !== '') {
		return trim($servicio);
	}
	if (trim($empresa) !== '') {
		return trim($empresa);
	}
	return preg_match('/^\d{4}-(\d{2})-(\d{2})/', $creada, $m) ? 'Propuesta del ' . $m[2] . '-' . $m[1] : 'Propuesta';
}

/**
 * Situación de un número a partir de sus fichas (una por cliente del CRM o, sin CRM, por correo).
 * Cliente: un contrato de trabajo firmado y no cancelado. Prospecto: una propuesta que cuenta o un
 * diagnóstico ya realizado. Nuevo: lo demás. Con varias fichas manda la situación más alta y no hay nombre.
 */
function at_wa_clasificar(array $fichas, string $ahora): array {
	$rango = ['nuevo' => 0, 'prospecto' => 1, 'cliente' => 2];
	$situacion = 'nuevo';
	$proyectos = [];
	$abiertas = [];
	$proximo = '';
	foreach ($fichas as $f) {
		$agenda = (array) ($f['agenda'] ?? []);
		$es_cliente = false;
		foreach ((array) ($f['contratos'] ?? []) as $c) {
			if (!in_array((string) $c['tipo'], AT_WA_TIPOS_TRABAJO, true) || empty($c['firmado']) || (string) $c['estado'] === 'cancelled') {
				continue;
			}
			$es_cliente = true;
			if ((string) $c['estado'] === 'signed') {
				$proyectos[] = ['tipo' => 'proyecto', 'id' => (int) $c['id'], 'nombre' => (string) $c['nombre'], 'plan_codigo' => (string) ($c['plan_codigo'] ?? ''), 'agenda' => $agenda];
			}
		}
		$es_prospecto = false;
		foreach ((array) ($f['propuestas'] ?? []) as $p) {
			if (!in_array((string) $p['estado'], AT_WA_PROPUESTA_NO_CUENTA, true)) {
				$es_prospecto = true;
			}
			if (in_array((string) $p['estado'], AT_WA_PROPUESTA_ABIERTA, true)) {
				$abiertas[] = ['tipo' => 'propuesta', 'id' => (int) $p['id'], 'nombre' => (string) $p['nombre'], 'plan_codigo' => '', 'agenda' => $agenda];
			}
		}
		foreach ((array) ($f['diagnosticos'] ?? []) as $d) {
			$cuando = (string) $d['cuando'];
			if (in_array((string) $d['estado'], AT_WA_DIAGNOSTICO_NO_VALE, true)) {
				continue;
			}
			if ($cuando < $ahora) {
				if ((string) ($d['asistencia'] ?? '') !== 'no_show') {
					$es_prospecto = true;
				}
			} elseif ($proximo === '' || $cuando < $proximo) {
				$proximo = $cuando;
			}
		}
		$propia = $es_cliente ? 'cliente' : ($es_prospecto ? 'prospecto' : 'nuevo');
		if ($rango[$propia] > $rango[$situacion]) {
			$situacion = $propia;
		}
	}
	$por_id = function ($a, $b) { return $b['id'] <=> $a['id']; };
	usort($proyectos, $por_id);
	usort($abiertas, $por_id);
	$opciones = $situacion === 'cliente' ? array_merge($proyectos, $abiertas) : ($situacion === 'prospecto' ? $abiertas : []);
	return [
		'situacion'           => $situacion,
		'nombre'              => count($fichas) === 1 ? at_wa_primer_nombre((string) ($fichas[0]['nombre'] ?? '')) : '',
		'opciones'            => $opciones,
		'diagnostico_proximo' => $proximo,
	];
}

/** El proyecto cuyo plan tiene exactamente ese código (el del botón de WhatsApp del plan); null si no hay. */
function at_wa_elegida(array $opciones, string $codigo): ?array {
	if ($codigo === '') {
		return null;
	}
	foreach ($opciones as $o) {
		if (($o['tipo'] ?? '') === 'proyecto' && (string) ($o['plan_codigo'] ?? '') !== '' && hash_equals((string) $o['plan_codigo'], $codigo)) {
			return $o;
		}
	}
	return null;
}
