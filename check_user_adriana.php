<?php
require_once('wp-load.php');
$user = get_user_by('email', 'Adriana.perez@automatizatech.cl');
if ($user) {
    echo "Usuario encontrado: " . $user->user_login . " (ID: " . $user->ID . ")\n";
    echo "Roles: " . implode(', ', $user->roles) . "\n";
} else {
    echo "Usuario NO encontrado.\n";
}
