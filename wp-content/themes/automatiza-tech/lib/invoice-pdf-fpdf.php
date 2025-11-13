<?php
/**
 * Generador de facturas en PDF usando FPDF
 * SoluciÃ³n 100% PHP, sin dependencias externas
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

class InvoicePDFFPDF extends FPDF {
    
    private $client_data;
    private $plan_data;
    private $invoice_number;
    private $client_country;
    private $currency;
    private $currency_symbol;
    private $apply_iva;
    
    // Colores corporativos (basados en el logo)
    private $primary_color = array(0, 150, 199); // #0096C7 - Azul cyan del logo
    private $secondary_color = array(0, 191, 179); // #00BFB3 - Verde turquesa del logo
    private $text_color = array(33, 33, 33);
    private $gray_color = array(117, 117, 117);
    
    public function __construct($client_data, $plan_data, $invoice_number = '') {
        parent::__construct('P', 'mm', 'A4');
        $this->client_data = $client_data;
        
        // DEBUG: Log de lo que recibe el constructor
        error_log("DEBUG InvoicePDFFPDF Constructor: tipo de plan_data = " . gettype($plan_data));
        if (is_array($plan_data)) {
            error_log("DEBUG InvoicePDFFPDF: Recibiendo array con " . count($plan_data) . " planes");
        } else {
            error_log("DEBUG InvoicePDFFPDF: Recibiendo objeto único, convirtiéndolo a array");
        }
        
        // Soportar tanto un solo plan como múltiples planes
        // Si recibe un array de planes, lo guarda directamente
        // Si recibe un objeto, lo convierte en array de un elemento
        if (is_array($plan_data)) {
            $this->plan_data = $plan_data;
        } else {
            $this->plan_data = array($plan_data);
        }
        
        error_log("DEBUG InvoicePDFFPDF: plan_data final contiene " . count($this->plan_data) . " planes");
        
        $this->invoice_number = $invoice_number;
        
        // Detectar paÃ­s del cliente y configurar moneda
        $this->client_country = $this->detect_client_country($client_data);
        $this->configure_currency($this->client_country);
        
        $this->SetAutoPageBreak(true, 15);
        $this->AddPage();
    }
    
    /**
     * Detectar paÃ­s del cliente basado en campo country o cÃ³digo telefÃ³nico
     */
    private function detect_client_country($client_data) {
        // Prioridad 1: Campo country en la base de datos
        if (isset($client_data->country) && !empty($client_data->country)) {
            return strtoupper($client_data->country);
        }
        
        // Prioridad 2: Detectar por cÃ³digo telefÃ³nico de WhatsApp
        if (isset($client_data->phone) && !empty($client_data->phone)) {
            $phone = $client_data->phone;
            
            // CÃ³digos telefÃ³nicos internacionales
            $country_codes = [
                '+56' => 'CL',  // Chile
                '+1'  => 'US',  // USA/CanadÃ¡
                '+54' => 'AR',  // Argentina
                '+57' => 'CO',  // Colombia
                '+52' => 'MX',  // MÃ©xico
                '+51' => 'PE',  // PerÃº
                '+34' => 'ES',  // EspaÃ±a
                '+55' => 'BR',  // Brasil
            ];
            
            foreach ($country_codes as $code => $country) {
                if (strpos($phone, $code) === 0) {
                    return $country;
                }
            }
        }
        
        // Por defecto: Chile
        return 'CL';
    }
    
    /**
     * Configurar moneda segÃºn el paÃ­s
     */
    private function configure_currency($country) {
        if ($country === 'CL') {
            // Chile: Pesos chilenos con IVA
            $this->currency = 'CLP';
            $this->currency_symbol = '$';
            $this->apply_iva = true;
        } else {
            // Otros paÃ­ses: DÃ³lares americanos sin IVA
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
        
        // Buscar logo en las rutas disponibles
        $logo_paths = array(
            get_template_directory() . '/assets/images/logo-automatiza-tech.png',
            get_template_directory() . '/assets/images/solo-logo.svg',
            get_template_directory() . '/lib/tutorial/logo.png'
        );
        
        $logo_found = false;
        foreach ($logo_paths as $logo_path) {
            if (file_exists($logo_path)) {
                // Logo encontrado - colocarlo en el header (tamaÃ±o ajustado)
                $ext = strtolower(pathinfo($logo_path, PATHINFO_EXTENSION));
                if ($ext === 'png') {
                    $this->Image($logo_path, 15, 8, 35); // Logo 35mm
                    $logo_found = true;
                    break;
                }
            }
        }
        
        if (!$logo_found) {
            // Texto de empresa elegante si no hay logo
            $this->SetFont('Arial', 'B', 24);
            $this->SetTextColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
            $this->SetXY(18, 14);
            $this->Cell(50, 10, 'AutomatizaTech', 0, 0, 'L');
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
            $this->SetXY(18, 24);
            $this->Cell(50, 4, utf8_to_latin1('Transformación Digital'), 0, 0, 'L');
        }
        
        // Info empresa (derecha) - diseÃ±o mejorado (configurables desde panel admin)
        $company_name = get_option('company_name', 'AutomatizaTech SpA');
        $company_rut = get_option('company_rut', '77.123.456-7');
        $company_email = get_option('company_email', 'info@automatizatech.shop');
        $company_phone = get_option('company_phone', '+56 9 1234 5678');
        $company_website = get_option('company_website', 'www.automatizatech.shop');
        
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
        
        // LÃ­nea separadora elegante
        $this->SetDrawColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->SetLineWidth(1);
        $this->Line(15, 45, 195, 45);
        
        $this->Ln(18);
    }
    
    // Footer - Solo texto legal (configurables desde panel admin)
    function Footer() {
        // Obtener datos configurables
        $company_name = get_option('company_name', 'AutomatizaTech SpA');
        $company_rut = get_option('company_rut', '77.123.456-7');
        
        // LÃ­nea separadora en la parte inferior
        $this->SetY(-18);
        $this->SetDrawColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->SetLineWidth(0.5);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        
        // Texto legal profesional
        $this->SetY(-12);
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
        $this->Cell(0, 3, utf8_to_latin1($company_name . ' - RUT: ' . $company_rut . ' - Factura válida para efectos tributarios'), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 6);
        $this->Cell(0, 3, utf8_to_latin1('© ' . date('Y') . ' ' . $company_name . '. Todos los derechos reservados. Documento generado electrónicamente.'), 0, 0, 'C');
    }
    
    private function generate_qr_code() {
        $validation_url = home_url('/validar-factura/?numero=' . urlencode($this->invoice_number));
        
        $upload_dir = wp_upload_dir();
        $qr_dir = $upload_dir['basedir'] . '/qr-codes/';
        
        if (!file_exists($qr_dir)) {
            wp_mkdir_p($qr_dir);
        }
        
        $qr_file = $qr_dir . 'qr-' . sanitize_file_name($this->invoice_number) . '.png';
        
        try {
            QRcode::png($validation_url, $qr_file, 'L', 4, 2);
            return $qr_file;
        } catch (Exception $e) {
            error_log("Error generando QR: " . $e->getMessage());
            return false;
        }
    }
    
    public function build() {
        // Datos de la factura en negrita sin cuadro (margen 15mm)
        $this->SetX(15);
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
        
        // FACTURA + Número en la misma línea (sin fondo)
        $this->Cell(110, 8, utf8_to_latin1('FACTURA N° ') . $this->invoice_number, 0, 0, 'L');
        
        // Fecha al lado derecho (en negrita) - ancho 70mm para llegar al margen derecho
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(70, 8, 'Fecha: ' . date('d/m/Y H:i'), 0, 1, 'R');
        
        $this->Ln(8);
        
        // Datos del cliente (cuadro compacto)
        $this->SetDrawColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->SetLineWidth(0.6);
        
        // RectÃ¡ngulo compacto
        $this->Rect(15, $this->GetY(), 180, 28, 'D');
        
        // Fondo para el tÃ­tulo
        $box_y = $this->GetY();
        $this->SetFillColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->Rect(15, $box_y, 180, 6, 'F');
        
        // TÃ­tulo de la secciÃ³n
        $this->SetXY(20, $box_y + 1);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 4, 'DATOS DEL CLIENTE', 0, 1, 'L');
        
        // Datos del cliente compactos
        $this->SetXY(20, $box_y + 8);
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
        $this->Cell(0, 5, $this->client_data->phone, 0, 1, 'L');
        
        $this->SetX(20);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
        $this->Cell(25, 5, 'Email:', 0, 0, 'L');
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
        $this->Cell(0, 5, $this->client_data->email, 0, 1, 'L');
        
        $this->SetLineWidth(0.2); // Restaurar grosor de lÃ­nea
        $this->Ln(10);
        
        // Detalles del servicio (tabla mejorada) - margen 15mm
        $this->SetX(15);
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->Cell(180, 6, 'DETALLE DEL SERVICIO', 0, 1, 'L');
        $this->Ln(3);
        
        // Cabecera de tabla (mÃ¡s alta y con mejor contraste) - empieza en 15mm
        $this->SetX(15);
        $this->SetFillColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 11);
        $this->SetDrawColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->Cell(100, 10, utf8_to_latin1('Descripción'), 1, 0, 'C', true);
        $this->Cell(40, 10, 'Cantidad', 1, 0, 'C', true);
        $this->Cell(40, 10, 'Monto', 1, 1, 'C', true);
        
        // Filas de datos (soporta múltiples items)
        $items = is_array($this->plan_data) ? $this->plan_data : array($this->plan_data);
        $total_items = 0;
        
        // DEBUG: Log de cuántos items se van a renderizar
        error_log("DEBUG PDF Render: Renderizando " . count($items) . " items en la tabla");
        
        foreach ($items as $index => $item) {
            // Obtener precio segÃºn moneda del cliente
            $item_price = $this->get_item_price($item);
            
            error_log("DEBUG PDF Render: Item " . ($index + 1) . " - Nombre: {$item->name}, Precio: {$item_price}");
            
            $this->SetX(15);
            $this->SetFillColor($index % 2 == 0 ? 250 : 255, $index % 2 == 0 ? 250 : 255, $index % 2 == 0 ? 250 : 255);
            $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
            $this->SetFont('Arial', '', 10);
            $this->SetDrawColor(200, 200, 200);
            $this->Cell(100, 12, utf8_to_latin1($item->name), 1, 0, 'L', true);
            $this->Cell(40, 12, '1', 1, 0, 'C', true);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(40, 12, $this->format_currency($item_price), 1, 1, 'R', true);
            $total_items += $item_price;
        }
        
        $this->Ln(8);
        
        // CÃ¡lculos segÃºn paÃ­s y moneda
        if ($this->apply_iva) {
            // Chile: Precio incluye IVA del 19%
            $total_con_iva = $total_items;
            $neto = round($total_con_iva / 1.19); // Neto sin IVA
            $iva = $total_con_iva - $neto; // IVA = Total - Neto
            
            // Resumen financiero (neto, IVA, total) - alineado desde margen 15mm
            $this->SetDrawColor(200, 200, 200);
            $this->SetLineWidth(0.2);
            
            // Neto
            $this->SetX(15);
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
            $this->Cell(100, 8, '', 0, 0);
            $this->Cell(40, 8, 'Neto:', 0, 0, 'R');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(40, 8, $this->format_currency($neto), 0, 1, 'R');
            
            // IVA (19%)
            $this->SetX(15);
            $this->SetFont('Arial', '', 10);
            $this->Cell(100, 8, '', 0, 0);
            $this->Cell(40, 8, 'IVA (19%):', 0, 0, 'R');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(40, 8, $this->format_currency($iva), 0, 1, 'R');
            
            $this->Ln(2);
            
            // Total con IVA
            $total_final = $total_con_iva;
        } else {
            // Otros paÃ­ses: Sin IVA, precio tal cual de la base de datos
            $this->Ln(2);
            $total_final = $total_items;
            
            // Nota: Factura internacional sin IVA
            $this->SetX(15);
            $this->SetFont('Arial', 'I', 9);
            $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
            $this->Cell(180, 6, utf8_to_latin1('* Factura internacional - No aplica IVA chileno'), 0, 1, 'R');
            $this->Ln(2);
        }
        
        // Total (destacado en verde)
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
        $this->Ln(20); // Mayor separaciÃ³n (de 8 a 20)
        
        // Mensaje de agradecimiento profesional (mÃ¡s compacto)
        $this->SetFillColor(232, 245, 233); // Verde muy claro
        $this->SetDrawColor($this->secondary_color[0], $this->secondary_color[1], $this->secondary_color[2]);
        $this->SetLineWidth(0.5);
        $this->Rect(15, $this->GetY(), 180, 16, 'DF');
        
        $current_y = $this->GetY();
        
        // Mensaje principal (sin icono para evitar problemas de codificaciÃ³n)
        $this->SetXY(20, $current_y + 3);
        // Mensaje de agradecimiento con nombre de empresa configurable
        $company_name = get_option('company_name', 'AutomatizaTech SpA');
        
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
        $this->Cell(0, 5, utf8_to_latin1('¡Gracias por confiar en ' . $company_name . '!'), 0, 1, 'L');
        
        // Mensaje secundario
        $this->SetX(20);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
        $this->MultiCell(160, 3, utf8_to_latin1('Este documento es válido como factura para efectos tributarios.'), 0, 'L');
        
        $this->SetLineWidth(0.2);
        $this->Ln(15); // Mayor separación (de 5 a 15)
        
        // Información de la empresa en 3 columnas compactas
        $col_width = 60;
        $x_start = 15;
        $y_start = $this->GetY();
        
        // Columna 1: Contacto (configurables desde panel admin)
        $company_email = get_option('company_email', 'info@automatizatech.shop');
        $company_phone = get_option('company_phone', '+56 9 1234 5678');
        $company_website = get_option('company_website', 'www.automatizatech.shop');
        
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
        
        // Columna 2: Información Tributaria (configurables desde panel admin)
        $company_rut = get_option('company_rut', '77.123.456-7');
        $company_giro = get_option('company_giro', 'Servicios tecnológicos');
        $company_website = get_option('company_website', 'www.automatizatech.shop');
        
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
        // Quitar el www. para la URL de validación
        $validation_domain = str_replace('www.', '', $company_website);
        $this->Cell($col_width, 3, utf8_to_latin1($validation_domain . '/validar'), 0, 0, 'L');
        
        // Columna 3: QR Code más pequeño
        $qr_path = $this->generate_qr_code();
        if ($qr_path && file_exists($qr_path)) {
            // QR más pequeño
            $qr_x = $x_start + ($col_width * 2) + 18;
            $qr_y = $y_start;
            
            // Marco del QR
            $this->SetDrawColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
            $this->SetLineWidth(0.4);
            $this->Rect($qr_x - 1, $qr_y - 1, 26, 26);
            
            // Fondo blanco
            $this->SetFillColor(255, 255, 255);
            $this->Rect($qr_x, $qr_y, 24, 24, 'F');
            
            $this->Image($qr_path, $qr_x, $qr_y, 24);
            
            // Texto debajo
            $this->SetXY($qr_x - 1, $qr_y + 25);
            $this->SetFont('Arial', '', 6);
            $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
            $this->Cell(26, 2, 'Validar', 0, 0, 'C');
        }
    }
    
    /**
     * Obtener precio del item segÃºn moneda del cliente
     */
    private function get_item_price($item) {
        if ($this->currency === 'CLP') {
            // Chile: usar price_clp
            return isset($item->price_clp) ? floatval($item->price_clp) : 0;
        } else {
            // Otros paÃ­ses: usar price_usd
            return isset($item->price_usd) ? floatval($item->price_usd) : 0;
        }
    }
    
    /**
     * Formatear moneda segÃºn paÃ­s
     */
    private function format_currency($amount) {
        if ($this->currency === 'CLP') {
            // Chile: $ 350.000 (sin decimales)
            return '$' . number_format($amount, 0, ',', '.');
        } else {
            // USD: USD $ 350.00 (con decimales)
            return 'USD $' . number_format($amount, 2, '.', ',');
        }
    }
    
    public function generate() {
        $this->build();
        return $this->Output('S'); // Retorna el PDF como string
    }
    
    public function save($filepath) {
        $this->build();
        $this->Output('F', $filepath);
        return file_exists($filepath);
    }
}


