<?php
/**
 * AT Escape - wrappers thin sobre las funciones de escape de WordPress.
 *
 * Convenciones para evitar XSS en plantillas / templates:
 *   - Texto en HTML       -> at_e($v)         // esc_html
 *   - Atributo HTML       -> at_attr($v)      // esc_attr
 *   - URL                 -> at_url($v)       // esc_url
 *   - JSON dentro de JS   -> at_json($v)      // wp_json_encode
 *   - HTML rico permitido -> at_kses($v)      // wp_kses_post
 *
 * No reemplaza a las funciones nativas; sirve para uniformidad y para que
 * un futuro grep/regla detecte cualquier <?= sin uno de estos helpers.
 */

if (!defined('ABSPATH')) { exit; }

if (!function_exists('at_e')) {
    function at_e($v) { echo esc_html((string)$v); }
}
if (!function_exists('at_attr')) {
    function at_attr($v) { echo esc_attr((string)$v); }
}
if (!function_exists('at_url')) {
    function at_url($v) { echo esc_url((string)$v); }
}
if (!function_exists('at_json')) {
    function at_json($v) { echo wp_json_encode($v); }
}
if (!function_exists('at_kses')) {
    function at_kses($v) { echo wp_kses_post((string)$v); }
}
