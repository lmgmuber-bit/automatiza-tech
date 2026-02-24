<?php
/**
 * Generador de boletas en PDF usando FPDF
 * Solución 100% PHP, sin dependencias externas
 */

// Asegurarse de que estamos en contexto WordPress
if (!function_exists('get_template_directory')) {
    die('Este archivo debe ser cargado desde WordPress');
}

require_once(get_template_directory() . '/lib/fpdf.php');
require_once(get_template_directory() . '/lib/qrcode.php');

/**
 * Función helper para reemplazar utf8_decode() deprecado en PHP 8.2+
 * Convierte UTF-8 a ISO-8859-1 (Latin1) que es lo que usa FPDF
 */
if (!function_exists('utf8_to_latin1')) {
    function utf8_to_latin1($text) {
        if (empty($text)) {
            return $text;
        }
        
        // Para PHP 7.2+, usar mb_convert_encoding
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        }
        
        // Fallback: usar utf8_decode si está disponible (PHP < 8.2)
        if (function_exists('utf8_decode')) {
            return @utf8_decode($text);
        }
        
        // Último fallback: devolver texto original
        return $text;
    }
}

class ReceiptPDFFPDF extends FPDF {
    
    private $client_data;
    private $items_data;
    private $receipt_number;
    private $client_country;
    private $currency;
    private $currency_symbol;
    private $apply_iva;
    
    // Colores corporativos (basados en el logo)
    private $primary_color = array(0, 150, 199); // #0096C7 - Azul cyan del logo
    private $secondary_color = array(0, 191, 179); // #00BFB3 - Verde turquesa del logo
    private $text_color = array(33, 33, 33);
    private $gray_color = array(117, 117, 117);
    
    public function __construct($client_data, $items_data, $receipt_number = '') {
        parent::__construct('P', 'mm', 'A4');
        $this->client_data = $client_data;
        
        // Configurar zona horaria de Chile al inicio
        date_default_timezone_set('America/Santiago');
        
        // items_data debe ser un array de objetos/arrays con {name, quantity, price}
        $this->items_data = $items_data;
        
        $this->receipt_number = $receipt_number;
        
        // Detectar país del cliente y configurar moneda
        $this->client_country = $this->detect_client_country($client_data);
        $this->configure_currency($this->client_country);
        
        $this->SetAutoPageBreak(true, 15);
        $this->AddPage();
    }
    
    private function detect_client_country($client_data) {
        if (isset($client_data->country) && !empty($client_data->country)) {
            return strtoupper($client_data->country);
        }
        // Por defecto: Chile
        return 'CL';
    }
    
    private function configure_currency($country) {
        if ($country === 'CL') {
            $this->currency = 'CLP';
            $this->currency_symbol = '$';
            $this->apply_iva = true;
        } else {
            $this->currency = 'USD';
            $this->currency_symbol = 'USD $';
            $this->apply_iva = false;
        }
    }
    
    // Header
    function Header() {
        // Fondo del header con gradiente visual
        $this->SetFillColor(245, 248, 252);
        $this->Rect(0, 0, 210, 45, 'F');
        
        // Buscar logo
        $logo_paths = array(
            get_template_directory() . '/assets/images/logo-automatiza-tech.png',
            get_template_directory() . '/assets/images/solo-logo.svg',
            get_template_directory() . '/lib/tutorial/logo.png'
        );
        
        $logo_found = false;
        foreach ($logo_paths as $logo_path) {
            if (file_exists($logo_path)) {
                $ext = strtolower(pathinfo($logo_path, PATHINFO_EXTENSION));
                if ($ext === 'png') {
                    $this->Image($logo_path, 15, 8, 35); 
                    $logo_found = true;
                    break;
                }
            }
        }
        
        if (!$logo_found) {
            $this->SetFont('Arial', 'B', 24);
            $this->SetTextColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
            $this->SetXY(18, 14);
            $this->Cell(50, 10, 'AutomatizaTech', 0, 0, 'L');
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
            $this->SetXY(18, 24);
            $this->Cell(50, 4, utf8_to_latin1('Transformación Digital'), 0, 0, 'L');
        }
        
        // Info empresa
        $company_name = get_option('company_name', 'AutomatizaTech SpA');
        $company_rut = get_option('company_rut', '77.123.456-7');
        $company_email = get_option('company_email', 'contacto@automatizatech.cl');
        $company_phone = get_option('company_phone', '+56 9 2700 2984');
        $company_website = get_option('company_website', 'www.automatizatech.cl');
        
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
        $this->SetXY(110, 12);
        $this->Cell(85, 6, utf8_to_latin1($company_name), 0, 1, 'R');
        
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
        $this->SetX(110);
        $this->Cell(85, 4, 'RUT: ' . utf8_to_latin1($company_rut), 0, 1, 'R');
        $this->SetX(110);
        $this->Cell(85, 4, utf8_to_latin1($company_email), 0, 1, 'R');
        $this->SetX(110);
        $this->Cell(85, 4, utf8_to_latin1($company_phone), 0, 1, 'R');
        $this->SetX(110);
        $this->Cell(85, 4, utf8_to_latin1($company_website), 0, 1, 'R');
        
        $this->SetDrawColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->SetLineWidth(1);
        $this->Line(15, 45, 195, 45);
        $this->Ln(18);
    }
    
    // Footer
    function Footer() {
        $company_name = get_option('company_name', 'AutomatizaTech SpA');
        $company_rut = get_option('company_rut', '77.123.456-7');
        
        $this->SetY(-18);
        $this->SetDrawColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->SetLineWidth(0.5);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        
        $this->SetY(-12);
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
        $this->Cell(0, 3, utf8_to_latin1($company_name . ' - RUT: ' . $company_rut . ' - Boleta válida para efectos tributarios'), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 6);
        $this->Cell(0, 3, utf8_to_latin1('© ' . current_time('Y') . ' ' . $company_name . '. Todos los derechos reservados. Documento generado electrónicamente.'), 0, 0, 'C');
    }
    
    private function generate_qr_code() {
        // Apunta a validar-boleta.php
        $validation_url = 'https://automatizatech.cl/validar-boleta.php?id=' . urlencode($this->receipt_number);
        
        $upload_dir = wp_upload_dir();
        $qr_dir = $upload_dir['basedir'] . '/qr-codes/';
        
        if (!file_exists($qr_dir)) {
            wp_mkdir_p($qr_dir);
        }
        
        $qr_file = $qr_dir . 'qr-boleta-' . sanitize_file_name($this->receipt_number) . '.png';
        
        try {
            if (class_exists('QRcode')) {
                QRcode::png($validation_url, $qr_file, 'L', 4, 2);
            } else {
                 error_log("CRITICAL: Class QRcode not found in ReceiptPDFFPDF");
                 return false;
            }
            return $qr_file;
        } catch (Exception $e) {
            error_log("Error generando QR: " . $e->getMessage());
            return false;
        } catch (Throwable $t) {
             error_log("Fatal Error generando QR: " . $t->getMessage());
             return false;
        }
    }
    
    public function build() {
        $this->SetX(15);
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
        
        // BOLETA ELECTRONICA
        $this->Cell(110, 8, utf8_to_latin1('BOLETA N° ') . $this->receipt_number, 0, 0, 'L');
        
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(70, 8, 'Fecha: ' . current_time('d/m/Y H:i'), 0, 1, 'R');
        $this->Ln(8);
        
        $this->SetDrawColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->SetLineWidth(0.6);
        $this->Rect(15, $this->GetY(), 180, 33, 'D');
        
        $box_y = $this->GetY();
        $this->SetFillColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->Rect(15, $box_y, 180, 6, 'F');
        
        $this->SetXY(20, $box_y + 1);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 4, 'DATOS DEL CLIENTE', 0, 1, 'L');
        
        $this->SetXY(20, $box_y + 8);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
        
        $tax_id_label = 'RUT/DNI:';
        if (!empty($this->client_data->country) && ($this->client_data->country === 'Chile' || $this->client_data->country === 'CL')) {
            $tax_id_label = 'RUT:';
        }
        
        $this->Cell(25, 5, $tax_id_label, 0, 0, 'L');
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
        $this->Cell(0, 5, utf8_to_latin1(!empty($this->client_data->tax_id) ? $this->client_data->tax_id : 'N/A'), 0, 1, 'L');
        
        $this->SetX(20);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
        $this->Cell(25, 5, 'Nombre:', 0, 0, 'L');
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
        $this->Cell(0, 5, utf8_to_latin1($this->client_data->name), 0, 1, 'L');
        
        $this->SetX(20);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
        $this->Cell(25, 5, utf8_to_latin1('Teléfono:'), 0, 0, 'L');
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
        $this->Cell(0, 5, isset($this->client_data->phone) ? $this->client_data->phone : '', 0, 1, 'L');
        
        $this->SetX(20);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
        $this->Cell(25, 5, 'Email:', 0, 0, 'L');
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
        $this->Cell(0, 5, isset($this->client_data->email) ? $this->client_data->email : '', 0, 1, 'L');
        
        $this->SetLineWidth(0.2); 
        $this->Ln(10);
        
        $this->SetX(15);
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->Cell(180, 6, 'DETALLE', 0, 1, 'L');
        $this->Ln(3);
        
        $this->SetX(15);
        $this->SetFillColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 11);
        $this->SetDrawColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->Cell(100, 10, utf8_to_latin1('Descripción'), 1, 0, 'C', true);
        $this->Cell(40, 10, 'Cantidad', 1, 0, 'C', true);
        $this->Cell(40, 10, 'Monto', 1, 1, 'C', true);
        
        $items = $this->items_data;
        $total_items = 0;
        
        foreach ($items as $index => $item) {
            // Asumimos item es objeto {name, quantity, price}
            // O array
            $name = is_object($item) ? $item->name : $item['name'];
            $qty = is_object($item) ? $item->quantity : $item['quantity'];
            $price = is_object($item) ? $item->price : $item['price'];
            
            $this->SetX(15);
            $this->SetFillColor($index % 2 == 0 ? 250 : 255, $index % 2 == 0 ? 250 : 255, $index % 2 == 0 ? 250 : 255);
            $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
            $this->SetFont('Arial', '', 10);
            $this->SetDrawColor(200, 200, 200);
            $this->Cell(100, 12, utf8_to_latin1($name), 1, 0, 'L', true);
            $this->Cell(40, 12, $qty, 1, 0, 'C', true);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(40, 12, $this->format_currency($price * $qty), 1, 1, 'R', true);
            $total_items += ($price * $qty);
        }
        
        $this->Ln(8);
        
        if ($this->apply_iva) {
            $total_con_iva = $total_items;
            $neto = round($total_con_iva / 1.19);
            $iva = $total_con_iva - $neto;
            
            $this->SetDrawColor(200, 200, 200);
            $this->SetLineWidth(0.2);
            
            $this->SetX(15);
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
            $this->Cell(100, 8, '', 0, 0);
            $this->Cell(40, 8, 'Neto:', 0, 0, 'R');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(40, 8, $this->format_currency($neto), 0, 1, 'R');
            
            $this->SetX(15);
            $this->SetFont('Arial', '', 10);
            $this->Cell(100, 8, '', 0, 0);
            $this->Cell(40, 8, 'IVA (19%):', 0, 0, 'R');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(40, 8, $this->format_currency($iva), 0, 1, 'R');
            
            $this->Ln(2);
            $total_final = $total_con_iva;
        } else {
            $this->Ln(2);
            $total_final = $total_items;
        }
        
        $this->SetX(15);
        $this->SetFillColor($this->secondary_color[0], $this->secondary_color[1], $this->secondary_color[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 14);
        $this->SetDrawColor($this->secondary_color[0], $this->secondary_color[1], $this->secondary_color[2]);
        $this->SetLineWidth(0.5);
        $this->Cell(100, 12, '', 0, 0);
        $this->Cell(40, 12, 'TOTAL:', 1, 0, 'R', true);
        $this->Cell(40, 12, $this->format_currency($total_final), 1, 1, 'R', true);
        
        $this->SetLineWidth(0.2);
        $this->Ln(20);
        
        $this->SetFillColor(232, 245, 233);
        $this->SetDrawColor($this->secondary_color[0], $this->secondary_color[1], $this->secondary_color[2]);
        $this->SetLineWidth(0.5);
        $this->Rect(15, $this->GetY(), 180, 16, 'DF');
        
        $current_y = $this->GetY();
        $company_name = get_option('company_name', 'AutomatizaTech SpA');
        
        $this->SetXY(20, $current_y + 3);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
        $this->Cell(0, 5, utf8_to_latin1('¡Gracias por confiar en ' . $company_name . '!'), 0, 1, 'L');
        
        $this->SetX(20);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
        $this->MultiCell(160, 3, utf8_to_latin1('Este documento es válido como boleta para efectos tributarios.'), 0, 'L');
        
        $this->SetLineWidth(0.2);
        $this->Ln(15);
        
        $col_width = 60;
        $x_start = 15;
        $y_start = $this->GetY();
        
        $company_email = get_option('company_email', 'contacto@automatizatech.cl');
        $company_phone = get_option('company_phone', '+56 9 2700 2984');
        $company_website = get_option('company_website', 'www.automatizatech.cl');
        $company_giro = get_option('company_giro', 'Servicios tecnolÃ³gicos');
        
        $this->SetXY($x_start, $y_start);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->Cell($col_width, 4, 'CONTACTO', 0, 1, 'L');
        
        $this->SetXY($x_start, $this->GetY());
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
        $this->Cell($col_width, 3, utf8_to_latin1($company_email), 0, 0, 'L');
        
        $this->SetXY($x_start, $this->GetY() + 3);
        $this->Cell($col_width, 3, utf8_to_latin1($company_phone), 0, 0, 'L');
        $this->SetXY($x_start, $this->GetY() + 3);
        $this->Cell($col_width, 3, utf8_to_latin1($company_website), 0, 0, 'L');
        
        $this->SetXY($x_start + $col_width, $y_start);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->Cell($col_width, 4, utf8_to_latin1('INFORMACIÓN'), 0, 1, 'L');
        
        $this->SetXY($x_start + $col_width, $y_start + 4);
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
        $this->Cell($col_width, 3, 'RUT: ' . utf8_to_latin1($company_rut), 0, 0, 'L');
        
        $this->SetXY($x_start + $col_width, $y_start + 7);
        $this->Cell($col_width, 3, utf8_to_latin1('Giro: ' . $company_giro), 0, 0, 'L');
        
        $this->SetXY($x_start + $col_width, $y_start + 10);
        $this->SetFont('Arial', 'B', 6);
        $this->SetTextColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $validation_domain = str_replace('www.', '', $company_website);
        $this->Cell($col_width, 3, utf8_to_latin1($validation_domain . '/validar-boleta'), 0, 0, 'L');
        
        $qr_path = $this->generate_qr_code();
        if ($qr_path && file_exists($qr_path)) {
            $qr_x = $x_start + ($col_width * 2) + 18;
            $qr_y = $y_start;
            $this->SetDrawColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
            $this->SetLineWidth(0.4);
            $this->Rect($qr_x - 1, $qr_y - 1, 26, 26);
            $this->SetFillColor(255, 255, 255);
            $this->Rect($qr_x, $qr_y, 24, 24, 'F');
            $this->Image($qr_path, $qr_x, $qr_y, 24);
            $this->SetXY($qr_x - 1, $qr_y + 25);
            $this->SetFont('Arial', '', 6);
            $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
            $this->Cell(26, 2, 'Validar', 0, 0, 'C');
        }
    }
    
    private function format_currency($amount) {
        if ($this->currency === 'CLP') {
            return '$' . number_format($amount, 0, ',', '.');
        } else {
            return 'USD $' . number_format($amount, 2, '.', ',');
        }
    }
}
