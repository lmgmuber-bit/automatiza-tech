<?php
/**
 * AutomatizaTech — Generador PDF de Informes QA usando FPDF
 *
 * Mismo patrón que contract-pdf-fpdf.php / quotation-pdf-fpdf.php.
 * Replica visualmente el informe HTML: header turquesa, info-boxes,
 * verdict box, stat boxes, módulos con barra de progreso, tabla de casos.
 *
 * Uso:
 *   require_once get_template_directory() . '/lib/qa-report-pdf-fpdf.php';
 *   $pdf = new QAReportPDF($project, $client, $modules_data, $global_stats, $verdict, $pass_rate);
 *   $pdf->build();
 *   $pdf->Output('F', $filepath);
 */

if (!defined('ABSPATH')) exit;

require_once get_template_directory() . '/lib/fpdf.php';

if (!function_exists('qa_utf8_to_latin1')) {
    function qa_utf8_to_latin1($t) {
        if ($t === null || $t === '') return '';
        $t = (string) $t;
        if (function_exists('mb_convert_encoding')) return mb_convert_encoding($t, 'ISO-8859-1', 'UTF-8');
        if (function_exists('utf8_decode')) return @utf8_decode($t);
        return $t;
    }
}

class QAReportPDF extends FPDF {

    private $project;
    private $client;
    private $modules;       // array of ['module'=>..., 'cases'=>..., 'stats'=>..., 'tester'=>...]
    private $stats;         // global stats
    private $verdict;       // 'APROBADO' | 'APROBADO CON OBSERVACIONES' | 'RECHAZADO'
    private $pass_rate;
    private $report_date;

    // Colors — paleta del HTML
    private $primary    = [13, 148, 136];   // teal-600
    private $primary_lt = [240, 253, 250];  // teal-50
    private $primary_bd = [153, 246, 228];  // teal-200
    private $text_col   = [55, 65, 81];
    private $gray       = [107, 114, 128];
    private $green_bg   = [236, 253, 245];
    private $green_tx   = [6, 95, 70];
    private $red_bg     = [254, 242, 242];
    private $red_tx     = [153, 27, 27];
    private $yellow_bg  = [254, 252, 232];
    private $yellow_tx  = [113, 63, 18];
    private $purple_bg  = [245, 243, 255];
    private $purple_tx  = [91, 33, 182];
    private $gray_bg    = [243, 244, 246];

    public function __construct($project, $client, $modules_data, $global_stats, $verdict, $pass_rate, $date_str = null) {
        parent::__construct('P', 'mm', 'A4');
        $this->project   = $project;
        $this->client    = $client;
        $this->modules   = $modules_data;
        $this->stats     = $global_stats;
        $this->verdict   = $verdict;
        $this->pass_rate = $pass_rate;
        $this->report_date = $date_str ?: date('d-m-Y');

        $this->SetMargins(15, 35, 15);
        $this->SetAutoPageBreak(true, 22);
        $this->AliasNbPages();
        $this->SetTitle(qa_utf8_to_latin1('Informe QA - ' . $project->name));
        $this->SetAuthor('AutomatizaTech SpA');
        $this->SetCreator('AutomatizaTech - QA Module');
    }

    /* =====================================================
     *  HEADER & FOOTER
     * ===================================================== */
    public function Header() {
        // Banda turquesa
        $this->SetFillColor(...$this->primary);
        $this->Rect(0, 0, 210, 28, 'F');

        $logo = get_template_directory() . '/assets/images/logo-automatiza-tech.png';
        if (file_exists($logo)) {
            $this->Image($logo, 15, 6, 15, 0, 'PNG');
        }

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 14);
        $this->SetXY(35, 8);
        $this->Cell(120, 6, qa_utf8_to_latin1('INFORME DE PRUEBAS QA'), 0, 2, 'L');
        $this->SetFont('Arial', '', 9);
        $this->SetX(35);
        $this->Cell(120, 5, qa_utf8_to_latin1($this->project->name . ' - ' . $this->report_date), 0, 0, 'L');

        // Pass rate badge derecha
        $this->SetXY(155, 9);
        $this->SetFillColor(255, 255, 255);
        $this->SetTextColor(...$this->primary);
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(40, 11, qa_utf8_to_latin1($this->pass_rate . '% Pass'), 0, 0, 'C', true);

        $this->SetTextColor(...$this->text_col);
        $this->SetY(34);
    }

    public function Footer() {
        $this->SetY(-18);
        $this->SetDrawColor(...$this->primary_bd);
        $this->SetLineWidth(0.3);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(2);
        $this->SetTextColor(...$this->gray);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 4, qa_utf8_to_latin1('Generado por AutomatizaTech SpA  -  contacto@automatizatech.cl  -  www.automatizatech.cl'), 0, 1, 'C');
        $this->Cell(0, 4, qa_utf8_to_latin1('Pag. ' . $this->PageNo() . ' de {nb}  -  (c) ' . date('Y') . ' AutomatizaTech. Todos los derechos reservados.'), 0, 0, 'C');
    }

    /* =====================================================
     *  BUILD
     * ===================================================== */
    public function build() {
        $this->AddPage();
        $this->renderInfoBoxes();
        $this->renderVerdict();
        $this->renderStatRow();
        $this->renderModuleDetails();
    }

    /* ---- Info boxes (proyecto / cliente) ---- */
    private function renderInfoBoxes() {
        $col_w = 87;
        $gap   = 6;
        $box_h = 32;

        $y = $this->GetY();
        // Caja Proyecto
        $this->SetFillColor(...$this->primary_lt);
        $this->SetDrawColor(...$this->primary_bd);
        $this->Rect(15, $y, $col_w, $box_h, 'DF');

        $this->SetXY(18, $y + 2);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(...$this->primary);
        $this->Cell(0, 4, qa_utf8_to_latin1('PROYECTO'), 0, 2, 'L');

        $this->SetTextColor(...$this->text_col);
        $this->SetFont('Arial', 'B', 10);
        $this->SetX(18);
        $this->Cell(0, 5, qa_utf8_to_latin1($this->project->name), 0, 2, 'L');
        $this->SetFont('Arial', '', 8.5);
        $this->SetX(18);
        $this->Cell(0, 4, qa_utf8_to_latin1('Version: ' . ($this->project->version ?: '1.0')), 0, 2, 'L');
        $this->SetX(18);
        $this->Cell(0, 4, qa_utf8_to_latin1('Fecha inicio: ' . ($this->project->started_at ? date('d-m-Y', strtotime($this->project->started_at)) : 'N/A')), 0, 2, 'L');
        $this->SetX(18);
        $this->Cell(0, 4, qa_utf8_to_latin1('Fecha cierre: ' . $this->report_date), 0, 0, 'L');

        // Caja Cliente
        $this->Rect(15 + $col_w + $gap, $y, $col_w, $box_h, 'DF');
        $this->SetXY(18 + $col_w + $gap, $y + 2);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(...$this->primary);
        $this->Cell(0, 4, qa_utf8_to_latin1('CLIENTE'), 0, 2, 'L');

        $this->SetTextColor(...$this->text_col);
        if ($this->client) {
            $this->SetFont('Arial', 'B', 10);
            $this->SetX(18 + $col_w + $gap);
            $this->Cell(0, 5, qa_utf8_to_latin1($this->client->nombre ?? '—'), 0, 2, 'L');
            $this->SetFont('Arial', '', 8.5);
            $this->SetX(18 + $col_w + $gap);
            $this->Cell(0, 4, qa_utf8_to_latin1($this->client->empresa ?? ''), 0, 2, 'L');
            $this->SetX(18 + $col_w + $gap);
            $this->Cell(0, 4, qa_utf8_to_latin1('Email: ' . ($this->client->email ?? '')), 0, 2, 'L');
            $this->SetX(18 + $col_w + $gap);
            $this->Cell(0, 4, qa_utf8_to_latin1('Tel: ' . ($this->client->telefono ?? '')), 0, 0, 'L');
        } else {
            $this->SetFont('Arial', 'I', 9);
            $this->SetX(18 + $col_w + $gap);
            $this->Cell(0, 5, qa_utf8_to_latin1('Sin cliente vinculado'), 0, 0, 'L');
        }

        $this->SetY($y + $box_h + 6);
    }

    /* ---- Verdict box ---- */
    private function renderVerdict() {
        if ($this->pass_rate >= 95)      { $bg = $this->green_bg;  $tx = $this->green_tx;  $icon = '[APROBADO]'; }
        elseif ($this->pass_rate >= 70)  { $bg = $this->yellow_bg; $tx = $this->yellow_tx; $icon = '[OBSERV.]'; }
        else                              { $bg = $this->red_bg;    $tx = $this->red_tx;    $icon = '[RECHAZADO]'; }

        $this->SetFillColor(...$bg);
        $this->SetDrawColor(...$tx);
        $this->SetLineWidth(0.4);
        $this->Rect(15, $this->GetY(), 180, 14, 'DF');

        $this->SetTextColor(...$tx);
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(180, 14, qa_utf8_to_latin1($icon . '  ' . $this->verdict . '  -  ' . $this->pass_rate . '% Pass Rate'), 0, 1, 'C');
        $this->SetTextColor(...$this->text_col);
        $this->SetLineWidth(0.2);
        $this->Ln(4);
    }

    /* ---- Stats cards ---- */
    private function renderStatRow() {
        $boxes = [
            ['Total',      $this->stats['total'],      $this->primary_lt, $this->primary],
            ['Pass',       $this->stats['pass'],       $this->green_bg,   $this->green_tx],
            ['Fail',       $this->stats['fail'],       $this->red_bg,     $this->red_tx],
            ['Bloqueados', $this->stats['blocked'],    $this->yellow_bg,  $this->yellow_tx],
            ['Omitidos',   $this->stats['skipped'],    $this->purple_bg,  $this->purple_tx],
            ['Sin probar', $this->stats['not_tested'], $this->gray_bg,    $this->gray],
        ];
        $w = 28;
        $gap = 2;
        $start_x = (210 - (count($boxes) * $w + (count($boxes)-1) * $gap)) / 2;
        $y = $this->GetY();
        foreach ($boxes as $i => $b) {
            $x = $start_x + $i * ($w + $gap);
            $this->SetFillColor(...$b[2]);
            $this->Rect($x, $y, $w, 16, 'F');

            $this->SetTextColor(...$b[3]);
            $this->SetFont('Arial', 'B', 14);
            $this->SetXY($x, $y + 2);
            $this->Cell($w, 6, (string) $b[1], 0, 2, 'C');
            $this->SetFont('Arial', '', 7);
            $this->SetX($x);
            $this->Cell($w, 4, qa_utf8_to_latin1(strtoupper($b[0])), 0, 0, 'C');
        }
        $this->SetTextColor(...$this->text_col);
        $this->SetY($y + 16 + 6);
    }

    /* ---- Modules + tablas ---- */
    private function renderModuleDetails() {
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(...$this->primary);
        $this->Cell(0, 7, qa_utf8_to_latin1('Detalle por Modulo'), 0, 1, 'L');
        $this->SetDrawColor(...$this->primary_bd);
        $this->SetLineWidth(0.4);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->SetLineWidth(0.2);
        $this->Ln(3);
        $this->SetTextColor(...$this->text_col);

        foreach ($this->modules as $mc) {
            $this->renderModuleSection($mc);
        }
    }

    private function renderModuleSection($mc) {
        $m = $mc['module'];
        $st = $mc['stats'];
        $tester = $mc['tester'];
        $rate = $st['total'] > 0 ? round(($st['pass'] / $st['total']) * 100, 1) : 0;

        // Espacio mínimo para no cortar el header del módulo
        if ($this->GetY() > 250) $this->AddPage();

        // Título módulo
        $y = $this->GetY();
        $this->SetFillColor(...$this->primary_lt);
        $this->Rect(15, $y, 180, 12, 'F');
        // Borde izquierdo
        $this->SetFillColor(...$this->primary);
        $this->Rect(15, $y, 1.2, 12, 'F');

        $this->SetXY(18, $y + 1.5);
        $this->SetTextColor(...$this->primary);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(160, 5, qa_utf8_to_latin1($m->title), 0, 2, 'L');

        $this->SetX(18);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(...$this->gray);
        $meta = sprintf('Tester: %s  -  %d casos  -  Pass:%d  Fail:%d  Bloq:%d  -  %s%%',
            $tester, $st['total'], $st['pass'], $st['fail'], $st['blocked'], $rate);
        $this->Cell(160, 4, qa_utf8_to_latin1($meta), 0, 0, 'L');

        $this->SetY($y + 12);
        $this->renderProgressBar($st);
        $this->Ln(3);

        // Tabla de casos
        if (!empty($mc['cases'])) {
            $this->renderCasesTable($mc['cases']);
        } else {
            $this->SetFont('Arial', 'I', 8.5);
            $this->SetTextColor(...$this->gray);
            $this->Cell(0, 5, qa_utf8_to_latin1('Sin casos en este modulo.'), 0, 1, 'L');
        }
        $this->SetTextColor(...$this->text_col);
        $this->Ln(4);
    }

    private function renderProgressBar($st) {
        $y = $this->GetY();
        $x = 15;
        $w_total = 180;
        $h = 2;
        if ($st['total'] === 0) {
            $this->SetFillColor(229, 231, 235);
            $this->Rect($x, $y, $w_total, $h, 'F');
            return;
        }
        $w_pass = $w_total * ($st['pass'] / $st['total']);
        $w_fail = $w_total * ($st['fail'] / $st['total']);
        $w_block = $w_total * ($st['blocked'] / $st['total']);
        $w_rest = $w_total - $w_pass - $w_fail - $w_block;
        // base
        $this->SetFillColor(229, 231, 235);
        $this->Rect($x, $y, $w_total, $h, 'F');
        if ($w_pass > 0)  { $this->SetFillColor(16, 185, 129);  $this->Rect($x, $y, $w_pass, $h, 'F'); }
        if ($w_fail > 0)  { $this->SetFillColor(239, 68, 68);   $this->Rect($x + $w_pass, $y, $w_fail, $h, 'F'); }
        if ($w_block > 0) { $this->SetFillColor(245, 158, 11);  $this->Rect($x + $w_pass + $w_fail, $y, $w_block, $h, 'F'); }
        $this->SetY($y + $h);
    }

    private function renderCasesTable($cases) {
        // Headers
        $cols = [
            ['ID',         18],
            ['Caso',       82],
            ['Prioridad',  20],
            ['Estado',     22],
            ['Bug ID',     18],
            ['Tester',     20],
        ];

        $this->SetFillColor(248, 250, 252);
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetTextColor(...$this->gray);
        $this->SetDrawColor(229, 231, 235);
        foreach ($cols as $c) {
            $this->Cell($c[1], 6, qa_utf8_to_latin1(strtoupper($c[0])), 'B', 0, 'L', true);
        }
        $this->Ln();

        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(...$this->text_col);

        $st_map = [
            'pass'       => ['PASS',     [6, 95, 70]],
            'fail'       => ['FAIL',     [153, 27, 27]],
            'blocked'    => ['BLOQ.',    [146, 64, 14]],
            'skipped'    => ['OMIT.',    [91, 33, 182]],
            'not_tested' => ['Sin probar', [156, 163, 175]],
        ];

        foreach ($cases as $c) {
            // Salto de página
            if ($this->GetY() > 268) {
                $this->AddPage();
                // Repetir cabecera
                $this->SetFillColor(248, 250, 252);
                $this->SetFont('Arial', 'B', 7.5);
                $this->SetTextColor(...$this->gray);
                foreach ($cols as $cc) $this->Cell($cc[1], 6, qa_utf8_to_latin1(strtoupper($cc[0])), 'B', 0, 'L', true);
                $this->Ln();
                $this->SetFont('Arial', '', 8);
                $this->SetTextColor(...$this->text_col);
            }

            // Truncar título largo
            $title = $c->title;
            if (mb_strlen($title) > 78) $title = mb_substr($title, 0, 75) . '...';

            $this->Cell(18, 5, qa_utf8_to_latin1($c->case_id), 'B', 0, 'L');
            $this->SetFont('Arial', '', 8);
            $this->Cell(82, 5, qa_utf8_to_latin1($title), 'B', 0, 'L');
            $this->Cell(20, 5, qa_utf8_to_latin1(ucfirst($c->priority)), 'B', 0, 'L');

            $st_info = $st_map[$c->status] ?? [$c->status, $this->text_col];
            $this->SetTextColor(...$st_info[1]);
            $this->SetFont('Arial', 'B', 8);
            $this->Cell(22, 5, qa_utf8_to_latin1($st_info[0]), 'B', 0, 'L');

            $this->SetTextColor(...$this->text_col);
            $this->SetFont('Arial', '', 8);
            $this->Cell(18, 5, qa_utf8_to_latin1($c->bug_id ?: '—'), 'B', 0, 'L');
            $this->Cell(20, 5, qa_utf8_to_latin1($c->tester ?: '—'), 'B', 0, 'L');
            $this->Ln();
        }
    }
}
