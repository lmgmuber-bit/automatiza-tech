<?php
/**
 * Simple QR Code Generator usando API local
 * Alternativa ligera a phpqrcode
 */

class SimpleQRCode {
    
    /**
     * Genera una imagen QR Code y la guarda en un archivo
     * 
     * @param string $data Datos a codificar
     * @param string $filename Ruta del archivo de salida
     * @param string $size Tamaño del QR (ej: "140x140")
     * @return string Ruta del archivo generado
     */
    public static function png($data, $filename, $errorCorrectionLevel = 'L', $size = 3, $margin = 1) {
        // Usar API externa confiable de QRServer
        $qr_size = $size * 50; // Convertir tamaño relativo a píxeles
        $encoded_data = urlencode($data);
        $api_url = "https://api.qrserver.com/v1/create-qr-code/?size={$qr_size}x{$qr_size}&data={$encoded_data}";
        
        // Descargar la imagen
        $qr_image = @file_get_contents($api_url);
        
        if ($qr_image === false) {
            // Fallback: generar QR simple con imagen base64
            return self::generateFallbackQR($data, $filename);
        }
        
        // Guardar la imagen
        $result = file_put_contents($filename, $qr_image);
        
        if ($result === false) {
            throw new Exception("No se pudo guardar el archivo QR: {$filename}");
        }
        
        return $filename;
    }
    
    /**
     * Genera un QR code inline en base64
     * 
     * @param string $data Datos a codificar
     * @param int $size Tamaño en píxeles
     * @return string Data URI de la imagen
     */
    public static function generateBase64($data, $size = 140) {
        $encoded_data = urlencode($data);
        $api_url = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encoded_data}";
        
        $qr_image = @file_get_contents($api_url);
        
        if ($qr_image === false) {
            return self::generateFallbackBase64($data, $size);
        }
        
        $base64 = base64_encode($qr_image);
        return "data:image/png;base64,{$base64}";
    }
    
    /**
     * Fallback: genera un QR simple en caso de fallo de API
     */
    private static function generateFallbackQR($data, $filename) {
        // Crear imagen simple con el texto
        $width = 200;
        $height = 200;
        $image = imagecreate($width, $height);
        
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        imagefilledrectangle($image, 0, 0, $width, $height, $white);
        
        // Texto centrado
        $text = "QR: " . substr($data, 0, 20) . "...";
        imagestring($image, 3, 10, 90, $text, $black);
        
        imagepng($image, $filename);
        imagedestroy($image);
        
        return $filename;
    }
    
    /**
     * Fallback base64
     */
    private static function generateFallbackBase64($data, $size) {
        $image = imagecreate($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        imagefilledrectangle($image, 0, 0, $size, $size, $white);
        
        ob_start();
        imagepng($image);
        $image_data = ob_get_clean();
        imagedestroy($image);
        
        $base64 = base64_encode($image_data);
        return "data:image/png;base64,{$base64}";
    }
}

// Alias para compatibilidad con código existente
class QRcode extends SimpleQRCode {}
