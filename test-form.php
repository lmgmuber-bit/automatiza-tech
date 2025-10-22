<!DOCTYPE html>
<html>
<head>
    <title>Prueba de Formulario de Contacto</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        textarea { height: 100px; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .message { margin-top: 15px; padding: 10px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <h1>Prueba de Formulario de Contacto - Automatiza Tech</h1>
    
    <form id="test-contact-form">
        <div class="form-group">
            <label for="name">Nombre *</label>
            <input type="text" id="name" name="name" required value="Juan Pérez">
        </div>
        
        <div class="form-group">
            <label for="email">Email *</label>
            <input type="email" id="email" name="email" required value="juan@example.com">
        </div>
        
        <div class="form-group">
            <label for="company">Empresa</label>
            <input type="text" id="company" name="company" value="Mi Empresa S.A.">
        </div>
        
        <div class="form-group">
            <label for="phone">Teléfono</label>
            <input type="tel" id="phone" name="phone" value="+57 300 123 4567">
        </div>
        
        <div class="form-group">
            <label for="message">Mensaje *</label>
            <textarea id="message" name="message" required>Hola, me interesa conocer más sobre sus servicios de automatización.</textarea>
        </div>
        
        <button type="submit">Enviar Mensaje</button>
    </form>
    
    <div id="form-messages"></div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#test-contact-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var $messages = $('#form-messages');
            var originalBtnText = $submitBtn.html();
            
            // Mostrar loading
            $submitBtn.prop('disabled', true).html('Enviando...');
            $messages.removeClass('success error').hide();
            
            // Obtener datos del formulario
            var formData = {
                action: 'submit_contact_form',
                name: $('#name').val(),
                email: $('#email').val(),
                company: $('#company').val(),
                phone: $('#phone').val(),
                message: $('#message').val(),
                nonce: $('#test-contact-form').data('nonce')
            };
            
            console.log('Enviando datos:', formData);
            
            // Enviar via AJAX
            $.post('/automatiza-tech/wp-admin/admin-ajax.php', formData)
                .done(function(response) {
                    console.log('Respuesta recibida:', response);
                    if (response.success) {
                        $messages.addClass('success').html('✅ ' + response.data).show();
                        $form[0].reset();
                    } else {
                        $messages.addClass('error').html('❌ ' + response.data).show();
                    }
                })
                .fail(function(jqXHR, textStatus, errorThrown) {
                    console.log('Error AJAX:', textStatus, errorThrown);
                    console.log('Respuesta completa:', jqXHR.responseText);
                    $messages.addClass('error').html('❌ Error de conexión: ' + textStatus).show();
                })
                .always(function() {
                    $submitBtn.prop('disabled', false).html(originalBtnText);
                });
        });
    });
    </script>
    
</body>
</html>

<?php
// Incluir WordPress para el nonce
require_once(dirname(__FILE__) . '/wp-load.php');
$nonce = wp_create_nonce('automatiza_ajax_nonce');
?>

<script>
// Actualizar el nonce en el JavaScript
jQuery(document).ready(function($) {
    $('#test-contact-form').find('input[name="nonce"]').remove();
    $('#test-contact-form').data('nonce', '<?php echo $nonce; ?>');
});
</script>