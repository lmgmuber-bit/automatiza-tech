jQuery(document).ready(function($) {
    // Usar delegación de eventos en un contenedor estático que exista al cargar la página.
    // '#wpbody' es un buen candidato porque envuelve todo el contenido del admin.
    $('#wpbody').on('click', '.regenerate-invoice-op-btn', function(e) {
        e.preventDefault();

        if (!confirm('¿Estás seguro de que deseas regenerar y reenviar la factura a este cliente?')) {
            return;
        }

        const button = $(this);
        const clientId = button.data('id');
        const nonce = button.data('nonce');
        
        button.text('Enviando...');
        button.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'regenerate_and_resend_invoice_op',
            id: clientId,
            nonce: nonce
        })
        .done(function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                // Asegurarse de que response.data sea un string para evitar [Object object]
                const errorMessage = typeof response.data === 'string' ? response.data : 'Ocurrió un error desconocido.';
                alert('Error: ' + errorMessage);
                button.text('♻️ Regenerar');
                button.prop('disabled', false);
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            alert('Error de conexión con el servidor: ' + textStatus);
            button.text('♻️ Regenerar');
            button.prop('disabled', false);
        });
    });
});
