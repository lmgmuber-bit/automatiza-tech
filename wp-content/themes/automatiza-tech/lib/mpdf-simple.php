<?php
/**
 * Simple PDF Generator usando DomPDF como alternativa
 * Si no tienes mPDF o TCPDF instalado
 */

namespace Mpdf;

class Mpdf {
    private $html = '';
    private $title = '';
    private $author = '';
    private $creator = '';
    
    public function __construct($config = []) {
        // Configuración básica
    }
    
    public function SetTitle($title) {
        $this->title = $title;
    }
    
    public function SetAuthor($author) {
        $this->author = $author;
    }
    
    public function SetCreator($creator) {
        $this->creator = $creator;
    }
    
    public function SetSubject($subject) {
        // No usado en esta implementación simple
    }
    
    public function WriteHTML($html) {
        $this->html = $html;
    }
    
    public function Output($filename = '', $mode = 'I') {
        // D = Download, I = Inline, S = String, F = File
        
        // Crear PDF usando wkhtmltopdf si está disponible
        // O retornar HTML como fallback
        
        $temp_html = sys_get_temp_dir() . '/invoice_' . uniqid() . '.html';
        $temp_pdf = sys_get_temp_dir() . '/invoice_' . uniqid() . '.pdf';
        
        file_put_contents($temp_html, $this->html);
        
        // Intentar usar wkhtmltopdf
        $wkhtmltopdf_paths = [
            'C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe',
            '/usr/local/bin/wkhtmltopdf',
            '/usr/bin/wkhtmltopdf',
            'wkhtmltopdf'
        ];
        
        $wkhtmltopdf = null;
        foreach ($wkhtmltopdf_paths as $path) {
            if (file_exists($path) || shell_exec("which $path 2>/dev/null")) {
                $wkhtmltopdf = $path;
                break;
            }
        }
        
        if ($wkhtmltopdf) {
            // Generar PDF con wkhtmltopdf
            $cmd = escapeshellarg($wkhtmltopdf) . ' -q -s A4 -T 10 -B 10 -L 10 -R 10 ' . 
                   escapeshellarg($temp_html) . ' ' . escapeshellarg($temp_pdf);
            exec($cmd);
            
            if (file_exists($temp_pdf)) {
                $pdf_content = file_get_contents($temp_pdf);
                @unlink($temp_html);
                @unlink($temp_pdf);
                
                if ($mode === 'D') {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    echo $pdf_content;
                    exit;
                } elseif ($mode === 'I') {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; filename="' . $filename . '"');
                    echo $pdf_content;
                    exit;
                } elseif ($mode === 'S') {
                    return $pdf_content;
                } elseif ($mode === 'F') {
                    file_put_contents($filename, $pdf_content);
                    return true;
                }
            }
        }
        
        // Fallback: usar HTML directo con headers para descargar
        @unlink($temp_html);
        
        if ($mode === 'D' || $mode === 'I') {
            header('Content-Type: text/html; charset=utf-8');
            header('Content-Disposition: ' . ($mode === 'D' ? 'attachment' : 'inline') . '; filename="' . str_replace('.pdf', '.html', $filename) . '"');
            echo $this->html;
            exit;
        } elseif ($mode === 'S') {
            return $this->html;
        } elseif ($mode === 'F') {
            file_put_contents(str_replace('.pdf', '.html', $filename), $this->html);
            return true;
        }
    }
}
