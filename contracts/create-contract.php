<?php
/**
 * Endpoint para CREAR un contrato desde admin / N8N / frontend.
 * Requiere usuario WP autenticado con capability 'edit_posts'.
 *
 * POST /contracts/create-contract.php
 * Body JSON o form:
 *   client_id, project_id, proposal_id, monthly_amount, currency, starts_at, ends_at,
 *   placeholders: { razon_social_cliente, rut_cliente, email_cliente,
 *                   representante_cliente_nombre, representante_cliente_rut,
 *                   nombre_proyecto, ... }
 */

require_once dirname(__DIR__) . '/wp-load.php';
require_once __DIR__ . '/contract-service.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_user_logged_in() || !current_user_can('edit_posts')) {
    http_response_code(403); echo json_encode(array('error'=>'forbidden')); exit;
}

$raw = file_get_contents('php://input');
$body = $raw && $raw[0] === '{' ? json_decode($raw, true) : $_POST;

$args = array(
    'client_id'      => isset($body['client_id'])      ? intval($body['client_id'])      : null,
    'project_id'     => isset($body['project_id'])     ? intval($body['project_id'])     : null,
    'proposal_id'    => isset($body['proposal_id'])    ? intval($body['proposal_id'])    : null,
    'template_id'    => $body['template_id']    ?? 'soporte_v2',
    'type'           => $body['type']           ?? 'soporte',
    'monthly_amount' => isset($body['monthly_amount']) ? floatval($body['monthly_amount']) : null,
    'currency'       => $body['currency']       ?? 'CLP',
    'starts_at'      => $body['starts_at']      ?? date('Y-m-d'),
    'ends_at'        => $body['ends_at']        ?? null,
    'expires_in_days'=> intval($body['expires_in_days'] ?? 14),
    'placeholders'   => is_array($body['placeholders'] ?? null) ? $body['placeholders'] : array(),
    'created_by'     => get_current_user_id(),
);

$c = ContractService::create_contract($args);
if (is_wp_error($c)) {
    http_response_code(400);
    echo json_encode(array('error'=>$c->get_error_message()));
    exit;
}
echo json_encode(array(
    'ok'             => true,
    'id'             => $c->id,
    'contract_number'=> $c->contract_number,
    'status'         => $c->status,
    'pdf_url'        => $c->pdf_url,
    'at_review_url'  => home_url('/contracts/at-sign-contract.php?token=' . $c->at_review_token),
));
