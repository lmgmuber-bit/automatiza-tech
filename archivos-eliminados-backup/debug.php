<?php 
error_reporting(E_ALL); 
ini_set('display_errors', 1); 
echo "^<h2^>Debug de Automatiza Tech^</h2^>"; 
echo "^<p^>PHP Version: " . phpversion() . "^</p^>"; 
echo "^<p^>WordPress ABSPATH: "; 
if (file_exists('wp-config.php')) { 
    echo "wp-config.php existe"; 
    require_once('wp-config.php'); 
    if (defined('ABSPATH')) echo "ABSPATH definido"; 
} else { 
    echo "wp-config.php NO existe"; 
} 
echo "^</p^>"; 
?> 
