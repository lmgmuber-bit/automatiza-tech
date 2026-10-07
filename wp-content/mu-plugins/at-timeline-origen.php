<?php
/**
 * Línea de tiempo del CRM: nombre de cada tipo de evento y quién lo originó.
 *
 * Funciones puras (sin base de datos) que usa crm-ai-completo.php en la ficha, en el portal del cliente y en la
 * ficha del prospecto. Orígenes: 'cliente' (lo hizo el cliente), 'equipo' (lo hizo el equipo de AutomatizaTech en
 * el CRM) y 'automatico' (lo hizo el sistema solo). AT habla siempre como equipo: nunca «Luis» como actor.
 */

if (!defined('ABSPATH') && !defined('AT_TL_PRUEBAS')) {
	exit;
}

/** tipo_evento de crm_historial → detail_type de la línea de tiempo (gana sobre el mapa viejo de crm-ai-completo.php). */
function at_tl_mapa_historial(): array {
	return [
		'entregable_nota'      => 'entregable_nota',
		'entregable_version'   => 'entregable_version',
		'entregable_respuesta' => 'entregable_respuesta',
		'whatsapp'             => 'whatsapp',
		'email_bienvenida'     => 'email_bienvenida',
		'proyecto_creado'      => 'item_proyecto',
		'conversion'           => 'conversion',
	];
}

/** Etiquetas, íconos y colores de los tipos nuevos y de «legacy» (ganan sobre las de cada vista). */
function at_tl_tipos_extra(): array {
	return [
		'entregable_nota'      => ['label' => 'Comentario del cliente · entregable', 'icon' => '💬', 'color' => '#0ea5e9', 'bg' => '#f0f9ff'],
		'entregable_version'   => ['label' => 'Versión enviada · entregable', 'icon' => '📦', 'color' => '#84cc16', 'bg' => '#ecfccb'],
		'entregable_respuesta' => ['label' => 'Respuesta del equipo · entregable', 'icon' => '↩️', 'color' => '#6366f1', 'bg' => '#eef2ff'],
		'whatsapp'             => ['label' => 'WhatsApp', 'icon' => '💬', 'color' => '#16a34a', 'bg' => '#dcfce7'],
		'email_bienvenida'     => ['label' => 'Correo de bienvenida', 'icon' => '📧', 'color' => '#8b5cf6', 'bg' => '#f5f3ff'],
		'conversion'           => ['label' => 'Conversión', 'icon' => '🎯', 'color' => '#7c3aed', 'bg' => '#f5f3ff'],
		// Antes «🤖 Sistema» para todo evento sin tipo propio: ahora quién lo hizo lo dice la etiqueta de origen.
		'legacy'               => ['label' => 'Evento', 'icon' => '📌', 'color' => '#64748b', 'bg' => '#f8fafc'],
	];
}

/** Títulos de crm_historial que registran algo que hizo el cliente (los escriben cierre-cliente y plan-trabajo). */
function at_tl_titulos_cliente(): array {
	return ['Aceptó la propuesta', 'No aceptó la propuesta', 'La sigue evaluando', 'Llamada de seguimiento agendada'];
}

/**
 * Origen de un evento ya armado para la línea de tiempo: 'cliente', 'equipo', 'automatico' o '' (sin etiqueta).
 * Lee: source ('system' = crm_historial, 'client' / 'prospect' = detalles), detail_type, tipo_evento, title,
 * description, usuario_id, created_by y metadata (JSON; 'origen' explícito gana).
 */
function at_tl_origen(array $h): string {
	$validos = ['cliente', 'equipo', 'automatico'];
	$meta = is_string($h['metadata'] ?? null) ? json_decode((string) $h['metadata'], true) : ($h['metadata'] ?? null);
	if (is_array($meta) && in_array($meta['origen'] ?? '', $validos, true)) {
		return $meta['origen'];
	}
	$fuente = (string) ($h['source'] ?? '');
	$dtype = (string) ($h['detail_type'] ?? '');
	if ($dtype === 'separator') {
		return 'automatico';
	}
	if ($fuente === 'system') {
		$tipo = (string) ($h['tipo_evento'] ?? '');
		$titulo = trim((string) ($h['title'] ?? ''));
		$desc = ltrim((string) ($h['description'] ?? ''));
		if ($tipo === 'entregable_nota' || in_array($titulo, at_tl_titulos_cliente(), true) || strpos($desc, 'El cliente ') === 0) {
			return 'cliente';
		}
		if ((int) ($h['usuario_id'] ?? 0) > 0) {
			return 'equipo';
		}
		// Sin usuario con sesión: lo que solo hace una persona (reuniones, notas, envíos manuales) lo anotó el equipo por fuera
		// del panel; lo demás (bienvenida al firmar, conversión al aceptar) lo hizo el sistema solo.
		$humanos = ['reunion', 'llamada', 'nota', 'email', 'whatsapp', 'proyecto_update', 'proyecto_creado', 'update', 'entregable_version', 'entregable_respuesta'];
		return in_array($tipo, $humanos, true) ? 'equipo' : 'automatico';
	}
	if ($fuente === 'client' || $fuente === 'prospect') {
		if ($dtype === 'respuesta_cliente') {
			return 'cliente';
		}
		if (in_array($dtype, ['aviso_operativo', 'whatsapp_no_entregado', 'pedido_respuesta'], true)) {
			return 'automatico';
		}
		if ($dtype === 'contratacion') {
			return (int) ($h['created_by'] ?? 0) > 0 ? 'equipo' : 'automatico';
		}
		return 'equipo';
	}
	return '';
}

/** Etiqueta HTML del origen (texto fijo, sin datos del usuario). '' si no hay origen. */
function at_tl_origen_html(array $h): string {
	$etiquetas = [
		'cliente'    => ['👤 Cliente', '#0369a1', '#e0f2fe'],
		'equipo'     => ['👥 Equipo AutomatizaTech', '#3730a3', '#e0e7ff'],
		'automatico' => ['⚙️ Automático', '#475569', '#f1f5f9'],
	];
	$o = at_tl_origen($h);
	if (!isset($etiquetas[$o])) {
		return '';
	}
	[$texto, $color, $fondo] = $etiquetas[$o];
	return '<span class="tl-origen tl-origen-' . $o . '" style="font-size:11px;background:' . $fondo . ';color:' . $color . ';padding:2px 8px;border-radius:10px;white-space:nowrap;">' . $texto . '</span>';
}
