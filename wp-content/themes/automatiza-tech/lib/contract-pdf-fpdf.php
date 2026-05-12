<?php
/**
 * Generador de CONTRATOS en PDF (FPDF) — soporta doble firma.
 * Patrón consistente con quotation-pdf-fpdf.php / invoice-pdf-fpdf.php.
 *
 * $signatures = array(
 *     'at'     => array('image_path','signer_name','signer_rut','signed_at','ip','method','user_agent') | null,
 *     'client' => array('image_path','signer_name','signer_rut','signer_email','signed_at','ip','method','user_agent','token','hash') | null,
 * )
 */

if (!function_exists('get_template_directory')) die('WordPress required');

require_once get_template_directory() . '/lib/fpdf.php';

if (!function_exists('utf8_to_latin1')) {
    function utf8_to_latin1($t) {
        if (empty($t)) return $t;
        // Reemplazar caracteres comunes fuera de ISO-8859-1 antes de convertir
        $search  = array("\xe2\x80\x94", "\xe2\x80\x93", "\xe2\x80\xa6", "\xc2\xad",
                         "\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x98", "\xe2\x80\x99",
                         "\xe2\x80\xa2", "\xe2\x86\x92", "\xe2\x86\x90", "\xe2\x89\xa4",
                         "\xe2\x89\xa5", "\xe2\x89\xa0", "\xc3\x97",     "\xc3\xb7");
        $replace = array('--',          '-',             '...',          '-',
                         '"',           '"',             "'",            "'",
                         '-',           '>',             '<',            '<=',
                         '>=',          '!=',            'x',            '/');
        $t = str_replace($search, $replace, $t);
        // Strip 4-byte UTF-8 sequences (emojis, supplementary chars) that can't be in ISO-8859-1
        $t = preg_replace('/[\xF0-\xF7][\x80-\xBF]{3}/', '', $t);
        // Strip remaining 3-byte sequences outside ISO-8859-1 (e.g. ⚠ U+26A0 = \xE2\x9A\xA0)
        $t = preg_replace('/[\xE2-\xEF][\x80-\xBF]{2}/', '', $t);
        if (function_exists('mb_convert_encoding')) return mb_convert_encoding($t, 'ISO-8859-1', 'UTF-8');
        if (function_exists('utf8_decode')) return @utf8_decode($t);
        return $t;
    }
}

class ContractPDFFPDF extends FPDF {

    private $ph;
    private $body;
    private $signatures;

    private $primary   = array(0, 74, 173);
    private $secondary = array(0, 150, 199);
    private $text_col  = array(33, 33, 33);
    private $gray      = array(117, 117, 117);
    private $light_bg  = array(245, 247, 250);

    public function __construct($placeholders, $body_md, $signatures = array()) {
        parent::__construct('P', 'mm', 'A4');
        date_default_timezone_set('America/Santiago');
        $this->ph         = (array) $placeholders;
        $this->body       = (string) $body_md;
        $this->signatures = is_array($signatures) ? $signatures : array();
        $this->SetMargins(20, 33, 20);
        $this->SetAutoPageBreak(true, 25);
        $this->AliasNbPages();
        $this->SetTitle(utf8_to_latin1('Contrato ' . ($this->ph['contract_number'] ?? '')));
        $this->SetAuthor('AutomatizaTech SpA');
        $this->SetCreator('AutomatizaTech - Contracts Module');
    }

    public function Header() {
        $logo = get_template_directory() . '/assets/images/logo-automatiza-tech.png';
        if (file_exists($logo)) {
            // 22mm wide ≈ 22mm tall for square logo → fits cleanly above separator line at y=28
            $this->Image($logo, 20, 6, 22);
        } else {
            $this->SetFont('Arial', 'B', 13);
            $this->SetTextColor(...$this->primary);
            $this->SetXY(20, 10);
            $this->Cell(50, 6, utf8_to_latin1('AutomatizaTech'), 0, 0, 'L');
        }
        // Right-side info — \xc2\xba is UTF-8 for º so mb_convert_encoding handles it correctly
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(...$this->gray);
        $this->SetXY(110, 8);
        $this->Cell(80, 4.5, utf8_to_latin1("Contrato N\xc2\xba " . ($this->ph['contract_number'] ?? '')), 0, 2, 'R');
        $this->Cell(80, 4.5, utf8_to_latin1('Emitido: ' . date('d-m-Y')), 0, 2, 'R');
        $this->Cell(80, 4.5, utf8_to_latin1('AutomatizaTech SpA'), 0, 2, 'R');
        $this->SetDrawColor(...$this->primary);
        $this->SetLineWidth(0.5);
        $this->Line(20, 29, 190, 29);
        $this->SetY(33);
    }

    public function Footer() {
        $this->SetY(-18);
        $this->SetDrawColor(220, 220, 220);
        $this->Line(20, $this->GetY(), 190, $this->GetY());
        $this->Ln(2);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(...$this->gray);
        $hash = $this->ph['document_hash'] ?? '';
        $h = $hash ? substr($hash, 0, 16) . '...' : '---';
        $this->Cell(0, 4, utf8_to_latin1('Hash SHA-256: ' . $h . '  ·  Ley 19.799 de Firma Electrónica'), 0, 1, 'L');
        $this->Cell(0, 4, utf8_to_latin1('Pág. ' . $this->PageNo() . ' de {nb}  ·  contacto@automatizatech.cl  ·  www.automatizatech.cl'), 0, 0, 'L');
    }

    public function build() {
        $this->AddPage();
        $this->renderTitleBlock();
        $this->renderStatusBlock();
        $this->renderBody();
        $this->renderSignatureBlock();
        $this->renderAuditBlock();
    }

    private function renderTitleBlock() {
        $this->SetFont('Arial', 'B', 13);
        $this->SetTextColor(...$this->primary);
        $title = $this->ph['contract_title'] ?? 'CONTRATO DE PRESTACIÓN DE SERVICIOS Y SOPORTE TÉCNICO';
        $this->MultiCell(0, 7, utf8_to_latin1($title), 0, 'C');
        $this->Ln(2);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(...$this->gray);
        $sub = ($this->ph['nombre_proyecto'] ?? '') . '  ·  ' . ($this->ph['razon_social_cliente'] ?? '');
        $this->Cell(0, 5, utf8_to_latin1($sub), 0, 1, 'C');
        $this->Ln(4);
    }

    private function renderStatusBlock() {
        $at_signed = !empty($this->signatures['at']);
        $cl_signed = !empty($this->signatures['client']);

        if ($at_signed && $cl_signed) {
            $bg = array(220, 248, 230); $fg = array(28, 124, 64);
            $label = 'CONTRATO FIRMADO POR AMBAS PARTES';
        } elseif ($at_signed && !$cl_signed) {
            $bg = array(220, 235, 250); $fg = array(0, 74, 173);
            $label = 'FIRMADO POR AUTOMATIZATECH - PENDIENTE FIRMA DEL CLIENTE';
        } else {
            $bg = array(255, 244, 220); $fg = array(180, 100, 0);
            $label = 'BORRADOR - PENDIENTE DE REVISION Y FIRMA INTERNA';
        }
        $this->SetFillColor(...$bg);
        $this->SetTextColor(...$fg);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 7, utf8_to_latin1($label), 0, 1, 'C', true);
        $this->Ln(3);
        $this->SetTextColor(...$this->text_col);
    }

    private function renderBody() {
        $body = $this->replacePlaceholders($this->body);
        $body = preg_replace('#</?(?!br\b)[a-z][^>]*>#i', '', $body);
        $lines = preg_split("/\r\n|\n|\r/", $body);

        // skip until first H1 to avoid re-rendering doc title
        $started = false;
        foreach ($lines as $raw) {
            $line = rtrim($raw);
            if (!$started) {
                if (preg_match('/^##\s+/', $line)) $started = true;
                else continue;
            }
            // stop when reaching the ACEPTACION section (we render our own)
            if (preg_match('/^##\s+ACEPTACI/i', $line)) break;

            if (preg_match('/^```/', $line)) continue;
            if (preg_match('/^---+$/', $line)) {
                $this->Ln(2);
                $this->SetDrawColor(220, 220, 220);
                $this->Line($this->GetX(), $this->GetY(), $this->GetX() + 170, $this->GetY());
                $this->Ln(3);
                continue;
            }
            if (preg_match('/^>\s?(.*)$/', $line, $m)) {
                $this->SetFillColor(...$this->light_bg);
                $this->SetFont('Arial', 'I', 9);
                $this->SetTextColor(...$this->gray);
                $this->MultiCell(0, 5, utf8_to_latin1($this->stripInline($m[1])), 0, 'L', true);
                $this->Ln(1);
                $this->SetTextColor(...$this->text_col);
                continue;
            }
            if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
                $level = strlen($m[1]);
                $sizes = array(1=>13, 2=>12, 3=>11, 4=>10, 5=>10, 6=>10);
                $this->Ln(2);
                $this->SetFont('Arial', 'B', $sizes[$level] ?? 10);
                $this->SetTextColor(...($level <= 2 ? $this->primary : $this->text_col));
                $this->MultiCell(0, 6, utf8_to_latin1($this->stripInline($m[2])), 0, 'L');
                $this->SetTextColor(...$this->text_col);
                $this->Ln(1);
                continue;
            }
            if (preg_match('/^\|[-:\s|]+\|$/', $line)) {
                // Guardar si la siguiente fila es header (la fila anterior era encabezado)
                $table_is_header = true;
                continue;
            }
            if (preg_match('/^\|(.+)\|$/', $line, $m)) {
                $cells = array_map('trim', explode('|', $m[1]));
                $bold = !empty($table_is_header) ? false : false;
                // Detectar si esta fila es la primera (encabezado)
                if (!isset($table_header_done)) {
                    $bold = true;
                    $table_header_done = true;
                }
                $this->renderTableRow($cells, 170, $bold);
                $table_is_header = false;
                continue;
            }
            // Reset table state when leaving a table
            unset($table_header_done, $table_is_header);
            if (preg_match('/^\s*[-*]\s+(.*)$/', $line, $m)) {
                $this->SetFont('Arial', '', 9);
                $this->Cell(5, 5, utf8_to_latin1('-'), 0, 0);
                $this->MultiCell(0, 5, utf8_to_latin1($this->stripInline($m[1])), 0, 'L');
                continue;
            }
            if (trim($line) === '') { $this->Ln(2); continue; }
            $this->SetFont('Arial', '', 9);
            $this->MultiCell(0, 5, utf8_to_latin1($this->stripInline($line)), 0, 'J');
        }
    }

    /**
     * Renderiza una fila de tabla con celdas que soportan texto multilínea (word-wrap).
     * FPDF Cell() no hace wrap — este método calcula la altura máxima de fila y
     * dibuja cada celda manualmente con Rect + Cell por línea.
     */
    private function renderTableRow(array $cells, $totalW, $isHeader = false) {
        $n      = max(1, count($cells));
        $colW   = $totalW / $n;
        $padX   = 2;
        $padY   = 1.5;
        $lineH  = 4.5;
        $style  = $isHeader ? 'B' : '';
        $this->SetFont('Arial', $style, 8.5);

        // Calcular líneas de texto necesarias por celda
        $allLines = array();
        foreach ($cells as $idx => $text) {
            $text   = utf8_to_latin1($this->stripInline($text));
            $maxTxt = $colW - $padX * 2;
            $words  = preg_split('/\s+/', trim($text));
            $lines  = array();
            $curr   = '';
            foreach ($words as $word) {
                if ($word === '') continue;
                $test = $curr !== '' ? $curr . ' ' . $word : $word;
                if ($this->GetStringWidth($test) <= $maxTxt) {
                    $curr = $test;
                } else {
                    if ($curr !== '') $lines[] = $curr;
                    // Si la palabra sola es mayor que el ancho, truncar
                    while ($this->GetStringWidth($word) > $maxTxt && strlen($word) > 1) {
                        $word = substr($word, 0, -1);
                    }
                    $curr = $word;
                }
            }
            if ($curr !== '') $lines[] = $curr;
            if (empty($lines)) $lines[] = '';
            $allLines[$idx] = $lines;
        }

        $maxL   = max(array_map('count', $allLines));
        $rowH   = $maxL * $lineH + $padY * 2;

        // Salto de página preventivo
        if ($this->GetY() + $rowH > ($this->h - $this->bMargin - 2)) {
            $this->AddPage();
        }

        $startY = $this->GetY();
        $startX = $this->lMargin;
        $fillBg = $isHeader ? $this->primary : array(255, 255, 255);

        for ($i = 0; $i < $n; $i++) {
            $cx = $startX + $i * $colW;
            // Fondo
            $this->SetFillColor(...$fillBg);
            $this->Rect($cx, $startY, $colW, $rowH, $isHeader ? 'DF' : 'D');
            // Texto
            if ($isHeader) {
                $this->SetTextColor(255, 255, 255);
            } else {
                $this->SetTextColor(...$this->text_col);
            }
            foreach ($allLines[$i] as $li => $ln) {
                $this->SetXY($cx + $padX, $startY + $padY + $li * $lineH);
                $this->Cell($colW - $padX * 2, $lineH, $ln, 0, 0, 'L');
            }
        }
        $this->SetXY($startX, $startY + $rowH);
        $this->SetTextColor(...$this->text_col);
        $this->SetFillColor(...$this->light_bg);
    }

    private function stripInline($s) {
        $s = preg_replace('/\*\*(.+?)\*\*/s', '$1', $s);
        $s = preg_replace('/(?<!\*)\*(?!\*)([^*]+)\*(?!\*)/s', '$1', $s);
        $s = preg_replace('/`([^`]+)`/', '$1', $s);
        $s = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '$1 ($2)', $s);
        return $s;
    }

    private function replacePlaceholders($text) {
        foreach ($this->ph as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $text = str_replace('{{' . $k . '}}', (string) $v, $text);
            }
        }
        $text = preg_replace('/\{\{[a-zA-Z0-9_]+\}\}/', '_______', $text);
        return $text;
    }

    private function renderSignatureBlock() {
        $this->Ln(6);
        if ($this->GetY() > 200) $this->AddPage();

        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(...$this->primary);
        $this->Cell(0, 7, utf8_to_latin1('FIRMAS'), 0, 1, 'L');
        $this->SetTextColor(...$this->text_col);
        $this->Ln(2);

        $col_w = 80; $box_h = 40;
        $x0 = $this->GetX(); $y0 = $this->GetY();

        // PROVEEDOR
        $this->Rect($x0, $y0, $col_w, $box_h);
        $at_sig = $this->signatures['at'] ?? null;
        if ($at_sig && !empty($at_sig['image_path']) && file_exists($at_sig['image_path'])) {
            $this->Image($at_sig['image_path'], $x0 + 10, $y0 + 5, 60);
        } else {
            $sig_at_default = get_template_directory() . '/assets/images/firma-at.png';
            if (file_exists($sig_at_default)) $this->Image($sig_at_default, $x0 + 10, $y0 + 5, 60);
        }

        // CLIENTE
        $this->Rect($x0 + $col_w + 10, $y0, $col_w, $box_h);
        $cl_sig = $this->signatures['client'] ?? null;
        if ($cl_sig && !empty($cl_sig['image_path']) && file_exists($cl_sig['image_path'])) {
            $this->Image($cl_sig['image_path'], $x0 + $col_w + 20, $y0 + 5, 60);
        }

        $this->SetY($y0 + $box_h + 1);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(...$this->gray);
        $this->Cell($col_w, 4, utf8_to_latin1('POR EL PROVEEDOR'), 0, 0, 'C');
        $this->Cell(10, 4, '', 0, 0);
        $this->Cell($col_w, 4, utf8_to_latin1('POR EL CLIENTE'), 0, 1, 'C');

        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(...$this->text_col);
        $at_name = $at_sig['signer_name'] ?? ($this->ph['representante_at_nombre'] ?? '');
        $cl_name = $cl_sig['signer_name'] ?? ($this->ph['representante_cliente_nombre'] ?? '');
        $this->Cell($col_w, 5, utf8_to_latin1($at_name), 0, 0, 'C');
        $this->Cell(10, 5, '', 0, 0);
        $this->Cell($col_w, 5, utf8_to_latin1($cl_name), 0, 1, 'C');

        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(...$this->gray);
        $at_rut = $at_sig['signer_rut'] ?? ($this->ph['representante_at_rut'] ?? '');
        $cl_rut = $cl_sig['signer_rut'] ?? ($this->ph['representante_cliente_rut'] ?? '');
        $this->Cell($col_w, 4, utf8_to_latin1('RUT: ' . $at_rut), 0, 0, 'C');
        $this->Cell(10, 4, '', 0, 0);
        $this->Cell($col_w, 4, utf8_to_latin1('RUT: ' . $cl_rut), 0, 1, 'C');

        $this->Cell($col_w, 4, utf8_to_latin1('AutomatizaTech SpA'), 0, 0, 'C');
        $this->Cell(10, 4, '', 0, 0);
        $this->Cell($col_w, 4, utf8_to_latin1($this->ph['razon_social_cliente'] ?? ''), 0, 1, 'C');

        if ($at_sig && !empty($at_sig['signed_at'])) {
            $this->Cell($col_w, 4, utf8_to_latin1('Firmado: ' . $at_sig['signed_at']), 0, 0, 'C');
        } else {
            $this->Cell($col_w, 4, '', 0, 0);
        }
        $this->Cell(10, 4, '', 0, 0);
        if ($cl_sig && !empty($cl_sig['signed_at'])) {
            $this->Cell($col_w, 4, utf8_to_latin1('Firmado: ' . $cl_sig['signed_at']), 0, 1, 'C');
        } else {
            $this->Cell($col_w, 4, utf8_to_latin1('Pendiente de firma'), 0, 1, 'C');
        }

        $this->SetTextColor(...$this->text_col);
    }

    private function renderAuditBlock() {
        $at = $this->signatures['at'] ?? null;
        $cl = $this->signatures['client'] ?? null;
        if (!$at && !$cl) return;

        $this->Ln(6);
        if ($this->GetY() > 230) $this->AddPage();

        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(...$this->primary);
        $this->Cell(0, 6, utf8_to_latin1('REGISTRO DE FIRMA ELECTRÓNICA SIMPLE (Ley 19.799)'), 0, 1, 'L');
        $this->SetTextColor(...$this->text_col);

        if ($at) $this->renderAuditTable('PROVEEDOR (AutomatizaTech)', $at);
        if ($cl) $this->renderAuditTable('CLIENTE', $cl);
    }

    private function renderAuditTable($title, $sig) {
        $this->Ln(2);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(...$this->secondary);
        $this->Cell(0, 5, utf8_to_latin1($title), 0, 1, 'L');
        $this->SetTextColor(...$this->text_col);

        $rows = array(
            'Firmante'      => ($sig['signer_name'] ?? '') . '  ·  RUT ' . ($sig['signer_rut'] ?? ''),
            'Email'         => $sig['signer_email'] ?? '',
            'Fecha y hora'  => $sig['signed_at'] ?? '',
            'Dirección IP'  => $sig['ip'] ?? '',
            'Método'        => $sig['method'] ?? '',
            'User-Agent'    => substr($sig['user_agent'] ?? '', 0, 90),
            'Hash documento'=> $sig['hash'] ?? '',
        );
        $this->SetFont('Arial', '', 8);
        $this->SetFillColor(...$this->light_bg);
        foreach ($rows as $k => $v) {
            if ($v === '' || $v === null) continue;
            $this->Cell(40, 5, utf8_to_latin1($k), 1, 0, 'L', true);
            $this->Cell(0,  5, utf8_to_latin1((string) $v), 1, 1, 'L');
        }
    }
}
