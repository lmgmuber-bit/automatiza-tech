<?php
// php tests/seguridad-errores/sin-detalle-en-respuesta-test.php
// Revision estatica de los archivos arreglados: ninguna sentencia que arme una respuesta puede
// llevar el detalle tecnico (last_error, getMessage, curl_error, get_error_message, texto de errores
// de capas inferiores). Se revisa por SENTENCIA (separada por ;), asi cubre construcciones en varias
// lineas. Limite: es una heuristica sobre texto, no un analisis de flujo; no ve datos que pasan por
// variables intermedias con otro nombre.
require __DIR__ . '/harness.php';
$archivos = ['api-chat-proxy.php', 'openai-controller.php', 'api-save-presentation.php', 'api-save-proposal.php',
    'contracts/contract-service.php', 'contracts/client-contracts-widget.php', 'omnichannel-controller.php',
    'omnichannel-atfinance-controller.php', 'wp-content/themes/automatiza-tech/inc/rest-proposals.php',
    'wp-content/themes/automatiza-tech/inc/receipts-module.php', 'wp-content/themes/automatiza-tech/inc/admin-qa-module.php',
    'wp-content/themes/automatiza-tech/inc/client-operations-module.php', 'wp-content/themes/automatiza-tech/inc/credentials-vault-module.php',
    'wp-content/mu-plugins/crm-ai-completo.php'];
$detalle = '/last_error|getMessage\(\)|curl_error\(|\$curl_error|get_error_message\(|\$detail\b|\$(ycloud|ig)\[\s*\'error\'\s*\]/';
$respuesta = '/json_encode|wp_send_json|WP_Error\(|return\s*\[\s*\'error\'|\'(error|message|reply|respuesta|delivery_error)\'\s*=>|\[\s*\'(delivery_error|error|message)\'\s*\]\s*=(?!=)/';
$errorLog = '/error_log\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/';
foreach ($archivos as $a) {
    $malas = [];
    $codigo = preg_replace($errorLog, 'error_log()', (string) file_get_contents(raiz() . '/' . $a));
    // last_error solo como condicion: se descartan las cabeceras de if (...) y los ternarios
    $codigo = preg_replace('/if\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/', 'if()', $codigo);
    $codigo = preg_replace('/last_error\s*\?/', '', $codigo);
    foreach (explode(';', $codigo) as $sentencia) {
        if (preg_match($detalle, $sentencia) && preg_match($respuesta, $sentencia)) {
            $malas[] = preg_replace('/\s+/', ' ', trim(substr($sentencia, -160)));
        }
    }
    comprobar(!$malas, "$a sin detalle tecnico en respuestas", implode(' | ', array_slice($malas, 0, 3)));
}
cierre();
