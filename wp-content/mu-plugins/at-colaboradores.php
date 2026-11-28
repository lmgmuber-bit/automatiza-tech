<?php
/**
 * Colaborador AT: acceso a «Mis clientes» con el historial y los entregables de los clientes asignados.
 * Spec: Docs/superpowers/specs/2026-10-27-crm-colaborador-por-cliente-design.md
 * Solo carga el módulo del tema; functions.php no se toca.
 */
if (!defined('ABSPATH')) {
	exit;
}
$at_colab_cargar = WP_CONTENT_DIR . '/themes/automatiza-tech/inc/colaboradores/cargar.php';
if (is_readable($at_colab_cargar)) {
	require_once $at_colab_cargar;
}
