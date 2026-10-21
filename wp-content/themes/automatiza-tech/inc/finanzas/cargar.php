<?php
/** Finanzas AT: carga del módulo. Cada tarea posterior agrega aquí su propia línea. */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/puras.php';
require_once __DIR__ . '/datos.php';
require_once __DIR__ . '/cambio.php';
require_once __DIR__ . '/api.php';

// Barato: compara la opción y no hace nada si el esquema ya está al día.
add_action('admin_init', 'at_fin_migrar');
add_action('rest_api_init', 'at_fin_migrar');
