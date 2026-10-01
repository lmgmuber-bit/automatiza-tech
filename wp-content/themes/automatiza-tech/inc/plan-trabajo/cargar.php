<?php
/**
 * Plan de trabajo del proyecto (cronograma con carta Gantt). Etapas 1 y 2.
 * Spec: Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md
 * Módulo independiente de la propuesta. Se carga siempre (panel, admin-post, REST y la página de firma
 * de contratos, que hace wp-load) desde inc/admin-proposals.php; functions.php no se toca.
 */
if (!defined('ABSPATH')) {
	exit;
}
require_once __DIR__ . '/puras.php';
require_once __DIR__ . '/datos.php';
require_once __DIR__ . '/disparador.php';
require_once __DIR__ . '/rest.php';
require_once __DIR__ . '/envio.php';
require_once __DIR__ . '/agenda.php';
require_once __DIR__ . '/ajustes.php';
require_once __DIR__ . '/panel.php';
