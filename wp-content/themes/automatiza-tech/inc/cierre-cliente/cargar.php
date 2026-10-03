<?php
/**
 * Cierre de cliente: respuesta a la propuesta, ficha única, contrato de servicio y bienvenida.
 * Spec: Docs/superpowers/specs/2026-09-25-cierre-de-cliente-design.md
 * Se carga siempre (panel, página pública, admin-post y REST) desde inc/admin-proposals.php.
 */
if (!defined('ABSPATH')) {
	exit;
}
require_once dirname(__DIR__) . '/proposals-flow.php';
require_once __DIR__ . '/puras.php';
require_once __DIR__ . '/clientes.php';
require_once __DIR__ . '/contrato.php';
require_once __DIR__ . '/ajustes.php';
require_once __DIR__ . '/bienvenida.php';
require_once __DIR__ . '/respuesta.php';
require_once __DIR__ . '/pagina.php';
require_once __DIR__ . '/panel.php';
require_once __DIR__ . '/archivo.php';
require_once __DIR__ . '/whatsapp.php';
require_once __DIR__ . '/whatsapp-recordatorios.php';
