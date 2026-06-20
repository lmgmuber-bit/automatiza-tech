<?php
/**
 * Email corporativo branded para flujo de firma de contratos.
 * Wraps wp_mail() con SMTP corporativo del tema.
 */

if (!defined('ABSPATH')) require_once dirname(__DIR__) . '/wp-load.php';

class ContractMailer {

    public static function from_headers($extra = array()) {
        $h = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: AutomatizaTech <contacto@automatizatech.cl>',
            'Reply-To: AutomatizaTech <contacto@automatizatech.cl>',
        );
        if (defined('OMNICHANNEL_ADMIN_BCC') && OMNICHANNEL_ADMIN_BCC) {
            foreach (explode(',', OMNICHANNEL_ADMIN_BCC) as $b) {
                $b = trim($b); if ($b) $h[] = 'Bcc: ' . $b;
            }
        }
        return array_merge($h, $extra);
    }

    /* 1) Borrador listo: notifica al rep AT para revisar/firmar internamente */
    public static function send_internal_review($to_email, $to_name, $contract, $review_url, $pdf_path = null) {
        $ph = json_decode($contract->placeholders, true) ?: array();
        $subject = '🔍 Revisar y firmar contrato — ' . $contract->contract_number;
        $body = self::render(array(
            'preheader' => 'Contrato listo para tu revisión y firma interna.',
            'title'     => 'Contrato listo para revisión',
            'lead'      => 'Hola ' . esc_html($to_name) . ', se generó el contrato <strong>' . esc_html($contract->contract_number) . '</strong> para el cliente <strong>' . esc_html($ph['razon_social_cliente'] ?? '') . '</strong>. Revísalo, y si todo está correcto, firmá como representante de AutomatizaTech para que el sistema lo envíe al cliente.',
            'sections' => array(
                array('label'=>'Cliente',     'value'=> esc_html($ph['razon_social_cliente'] ?? '')),
                array('label'=>'Proyecto',    'value'=> esc_html($ph['nombre_proyecto'] ?? '')),
                array('label'=>'Tipo',        'value'=> esc_html(strtoupper($contract->type))),
                array('label'=>'Valor mensual','value'=> $contract->monthly_amount ? '$ ' . number_format($contract->monthly_amount, 0, ',', '.') . ' ' . esc_html($contract->currency) . ' + IVA' : '—'),
                array('label'=>'Vigencia',    'value'=> esc_html($contract->starts_at) . ($contract->ends_at ? ' → ' . esc_html($contract->ends_at) : '')),
            ),
            'cta_label' => '🖋️ Revisar y firmar como AT',
            'cta_url'   => $review_url,
            'footnote'  => 'El cliente NO recibe el contrato hasta que tu firma como representante AT esté registrada. Tu firma quedará registrada con tu nombre, RUT, IP, fecha/hora y un hash SHA-256.',
        ));
        $att = ($pdf_path && file_exists($pdf_path)) ? array($pdf_path) : array();
        return wp_mail($to_email, $subject, $body, self::from_headers(), $att);
    }

    /* 2) AT firmó → enviar al cliente para que firme */
    public static function send_signature_request($to_email, $to_name, $contract, $sign_url, $pdf_path = null) {
        $ph = json_decode($contract->placeholders, true) ?: array();
        $subject = '📝 Contrato pendiente de tu firma — ' . ($ph['nombre_proyecto'] ?? 'AutomatizaTech');
        $body = self::render(array(
            'preheader' => 'Tu contrato ya fue firmado por AutomatizaTech. Solo falta tu firma.',
            'title'     => 'Contrato listo para tu firma',
            'lead'      => 'Hola ' . esc_html($to_name) . ', AutomatizaTech ya firmó el contrato de servicios y soporte para tu proyecto <strong>' . esc_html($ph['nombre_proyecto'] ?? '') . '</strong>. Solo falta tu firma para formalizarlo.',
            'sections' => array(
                array('label'=>'N° Contrato',  'value'=> esc_html($contract->contract_number)),
                array('label'=>'Tipo',         'value'=> esc_html(strtoupper($contract->type))),
                array('label'=>'Vigencia',     'value'=> esc_html($contract->starts_at) . ($contract->ends_at ? ' → ' . esc_html($contract->ends_at) : '')),
                array('label'=>'Valor mensual','value'=> $contract->monthly_amount ? '$ ' . number_format($contract->monthly_amount, 0, ',', '.') . ' ' . esc_html($contract->currency) . ' + IVA' : '—'),
                array('label'=>'Firmado por AT','value'=> esc_html($contract->at_signer_name) . ' el ' . esc_html($contract->at_signed_at)),
            ),
            'cta_label' => '🖋️ Revisar y firmar contrato',
            'cta_url'   => $sign_url,
            'footnote'  => 'Tu enlace personal expira el ' . esc_html($contract->expires_at) . '. Tu firma quedará registrada con tu nombre, RUT, IP, fecha/hora y un hash SHA-256, conforme a la Ley 19.799 de Firma Electrónica.',
        ));
        $att = ($pdf_path && file_exists($pdf_path)) ? array($pdf_path) : array();
        return wp_mail($to_email, $subject, $body, self::from_headers(), $att);
    }

    /* 3) Cliente firmó → copia FINAL al cliente con PDF firmado por ambos */
    public static function send_signed_copy($contract, $pdf_path) {
        $subject = '✅ Contrato firmado — ' . $contract->contract_number;
        $body = self::render(array(
            'preheader' => 'Adjuntamos tu contrato firmado por ambas partes.',
            'title'     => '¡Gracias! Contrato firmado por ambas partes',
            'lead'      => 'Hola ' . esc_html($contract->signer_name) . ', tu contrato <strong>' . esc_html($contract->contract_number) . '</strong> fue firmado electrónicamente por ambas partes y queda formalizado.',
            'sections' => array(
                array('label'=>'Firmado por AT',     'value'=> esc_html($contract->at_signer_name) . ' — ' . esc_html($contract->at_signed_at)),
                array('label'=>'Firmado por cliente','value'=> esc_html($contract->signer_name) . ' — ' . esc_html($contract->signed_at)),
                array('label'=>'Hash documento',     'value'=> '<code>' . esc_html(substr($contract->signed_document_hash, 0, 32)) . '...</code>'),
            ),
            'cta_label' => '📄 Acceder al portal y ver el contrato',
            'cta_url'   => home_url('/portal-omnichannel/?contract=' . $contract->id),
            'footnote'  => 'El PDF firmado va adjunto a este correo y queda disponible permanentemente en la ficha de tu cuenta dentro del Portal AutomatizaTech.',
        ));
        $att = file_exists($pdf_path) ? array($pdf_path) : array();
        return wp_mail($contract->signer_email, $subject, $body, self::from_headers(), $att);
    }

    /* 4) Cliente firmó → copia FINAL interna */
    public static function send_signed_copy_internal($contract, $pdf_path) {
        $to = defined('OMNI_MASTER_EMAIL') ? OMNI_MASTER_EMAIL : 'contacto@automatizatech.cl';
        $subject = '🆕 Contrato firmado por cliente — ' . $contract->contract_number;
        $body = self::render(array(
            'preheader' => 'Notificación interna',
            'title'     => 'Contrato firmado por el cliente',
            'lead'      => 'El cliente <strong>' . esc_html($contract->signer_name) . '</strong> (' . esc_html($contract->signer_email) . ') firmó el contrato.',
            'sections' => array(
                array('label'=>'Contrato',   'value'=> esc_html($contract->contract_number)),
                array('label'=>'Firmado AT', 'value'=> esc_html($contract->at_signer_name) . ' — ' . esc_html($contract->at_signed_at)),
                array('label'=>'Firmado CL', 'value'=> esc_html($contract->signer_name) . ' — ' . esc_html($contract->signed_at)),
                array('label'=>'IP cliente', 'value'=> esc_html($contract->signer_ip)),
                array('label'=>'Hash final', 'value'=> '<code>' . esc_html($contract->signed_document_hash) . '</code>'),
            ),
            'cta_label' => 'Ver en backoffice',
            'cta_url'   => admin_url('admin.php?page=at-contracts&id=' . $contract->id),
            'footnote'  => 'Notificación automática del módulo de contratos.',
        ));
        $att = file_exists($pdf_path) ? array($pdf_path) : array();
        return wp_mail($to, $subject, $body, self::from_headers(), $att);
    }

    /* Template HTML branded */
    public static function render($d) {
        $logo  = home_url('/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png');
        $brand = '#004AAD'; $accent = '#0096C7'; $year = date('Y');

        $rows = '';
        if (!empty($d['sections'])) {
            $rows .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0;border-collapse:collapse;">';
            foreach ($d['sections'] as $s) {
                $rows .= '<tr>'
                       . '<td style="padding:8px 12px;background:#F5F7FA;border:1px solid #E5E9F0;font:600 13px Arial,sans-serif;color:#21303D;width:35%;">' . $s['label'] . '</td>'
                       . '<td style="padding:8px 12px;background:#FFFFFF;border:1px solid #E5E9F0;font:400 13px Arial,sans-serif;color:#21303D;">' . $s['value'] . '</td>'
                       . '</tr>';
            }
            $rows .= '</table>';
        }

        $cta = '';
        if (!empty($d['cta_url'])) {
            $cta = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px auto;"><tr><td style="border-radius:8px;background:' . $brand . ';">'
                 . '<a href="' . esc_url($d['cta_url']) . '" style="display:inline-block;padding:14px 28px;font:600 15px Arial,sans-serif;color:#fff;text-decoration:none;border-radius:8px;">'
                 . esc_html($d['cta_label']) . '</a></td></tr></table>';
        }

        $pre = !empty($d['preheader']) ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . esc_html($d['preheader']) . '</div>' : '';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#EEF2F7;">'
. $pre .
'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEF2F7;padding:30px 10px;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border-radius:12px;overflow:hidden;box-shadow:0 4px 18px rgba(0,0,0,.06);">
  <tr><td style="background:linear-gradient(135deg,' . $brand . ' 0%,' . $accent . ' 100%);padding:24px;text-align:center;">
    <img src="' . esc_url($logo) . '" alt="AutomatizaTech" height="40" style="display:inline-block;border:0;outline:none;">
  </td></tr>
  <tr><td style="padding:32px 36px 12px 36px;">
    <h1 style="margin:0 0 8px 0;font:700 22px Arial,sans-serif;color:' . $brand . ';">' . esc_html($d['title']) . '</h1>
    <p style="margin:0;font:400 14px/1.55 Arial,sans-serif;color:#3B4A5A;">' . $d['lead'] . '</p>
    ' . $rows . '
    ' . $cta . '
    <p style="margin:18px 0 0 0;font:400 12px/1.5 Arial,sans-serif;color:#7A8694;">' . $d['footnote'] . '</p>
  </td></tr>
  <tr><td style="background:#F5F7FA;padding:18px 36px;border-top:1px solid #E5E9F0;text-align:center;">
    <p style="margin:0;font:400 11px Arial,sans-serif;color:#7A8694;">© ' . $year . ' AutomatizaTech SpA · contacto@automatizatech.cl · www.automatizatech.cl</p>
    <p style="margin:6px 0 0 0;font:400 10px Arial,sans-serif;color:#9AA5B1;">Mensaje generado automáticamente.</p>
  </td></tr>
</table>
</td></tr></table></body></html>';
    }
}
