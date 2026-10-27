<?php
/**
 * Colaborador AT por cliente. Spec: Docs/superpowers/specs/2026-10-27-crm-colaborador-por-cliente-design.md
 * Lo carga wp-content/mu-plugins/at-colaboradores.php. Las funciones de entregables y de la línea de tiempo se usan
 * dentro de los ganchos, cuando el tema y los demás mu-plugins ya cargaron.
 */
if (!defined('ABSPATH')) {
	exit;
}
require_once __DIR__ . '/puras.php';
require_once __DIR__ . '/permisos.php';
require_once __DIR__ . '/perfil.php';
require_once __DIR__ . '/datos.php';
require_once __DIR__ . '/pagina.php';
