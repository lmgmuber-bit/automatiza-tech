/**
 * Admin JavaScript - Actualización de Precios CLP
 * 
 * @package AutomatizaTech
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        /**
         * Botón de actualización manual
         */
        $('#update-now-btn').on('click', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var $result = $('#update-result');
            
            // Deshabilitar botón
            $btn.prop('disabled', true);
            $btn.html('<span class="spinner-border"></span> Actualizando precios...');
            
            // Mostrar mensaje de proceso
            $result.html('<div class="update-info"><strong>⏳ Procesando...</strong> Obteniendo tipo de cambio y actualizando servicios...</div>').slideDown();
            
            // Ejecutar AJAX
            $.ajax({
                url: automatizaCurrency.ajax_url,
                type: 'POST',
                data: {
                    action: 'update_clp_prices_manually',
                    nonce: automatizaCurrency.nonce
                },
                timeout: 30000, // 30 segundos
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        var html = '<div class="update-success">';
                        html += '<h3 style="margin-top: 0;">✓ Actualización Exitosa</h3>';
                        html += '<p><strong>Servicios actualizados:</strong> ' + data.updated + '</p>';
                        html += '<p><strong>Tipo de cambio usado:</strong> $' + parseFloat(data.exchange_rate).toFixed(2) + ' CLP por USD</p>';
                        
                        if (data.details && data.details.length > 0) {
                            html += '<div style="margin-top: 15px;"><strong>Detalles de cambios:</strong></div>';
                            html += '<ul style="margin-top: 10px;">';
                            
                            data.details.forEach(function(item) {
                                var changeColor = item.change_percent >= 0 ? '#28a745' : '#dc3545';
                                html += '<li style="margin: 8px 0;">';
                                html += '<strong>' + item.name + ':</strong> ';
                                html += '$' + parseInt(item.old_clp).toLocaleString() + ' → ';
                                html += '<span style="color: ' + changeColor + '; font-weight: bold;">$' + parseInt(item.new_clp).toLocaleString() + '</span> ';
                                html += '<em style="color: #666;">(' + (item.change_percent >= 0 ? '+' : '') + item.change_percent.toFixed(1) + '%)</em>';
                                html += '</li>';
                            });
                            
                            html += '</ul>';
                        }
                        
                        html += '<p style="margin-top: 15px; font-size: 0.95em; color: #666;"><em>La página se recargará en 3 segundos para mostrar los nuevos precios...</em></p>';
                        html += '</div>';
                        
                        $result.html(html).slideDown();
                        
                        // Recargar página después de 3 segundos
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                        
                    } else {
                        var html = '<div class="update-error">';
                        html += '<h3 style="margin-top: 0;">✗ Error en la Actualización</h3>';
                        html += '<p>' + (response.data && response.data.message ? response.data.message : 'Error desconocido') + '</p>';
                        html += '</div>';
                        
                        $result.html(html).slideDown();
                        
                        // Rehabilitar botón
                        $btn.prop('disabled', false);
                        $btn.html('🔄 Actualizar Ahora');
                    }
                },
                error: function(xhr, status, error) {
                    var html = '<div class="update-error">';
                    html += '<h3 style="margin-top: 0;">✗ Error de Conexión</h3>';
                    html += '<p><strong>Error:</strong> ' + error + '</p>';
                    html += '<p><strong>Estado:</strong> ' + status + '</p>';
                    
                    if (status === 'timeout') {
                        html += '<p>La operación tardó demasiado. Verifica tu conexión a internet.</p>';
                    }
                    
                    html += '</div>';
                    
                    $result.html(html).slideDown();
                    
                    // Rehabilitar botón
                    $btn.prop('disabled', false);
                    $btn.html('🔄 Actualizar Ahora');
                }
            });
        });
        
        /**
         * Resaltar servicios que necesitan actualización
         */
        $('.wp-list-table tbody tr').each(function() {
            var $row = $(this);
            var bgColor = $row.css('background-color');
            
            if (bgColor === 'rgb(255, 243, 205)' || bgColor === '#fff3cd') {
                $row.hover(
                    function() {
                        $(this).css('background-color', '#ffe69c');
                    },
                    function() {
                        $(this).css('background-color', '#fff3cd');
                    }
                );
            }
        });
        
        /**
         * Tooltip informativo en hover sobre los porcentajes
         */
        $('[data-tooltip]').hover(
            function() {
                var tooltip = $(this).data('tooltip');
                var $tooltip = $('<div class="custom-tooltip">' + tooltip + '</div>');
                $('body').append($tooltip);
                
                var offset = $(this).offset();
                $tooltip.css({
                    top: offset.top - $tooltip.outerHeight() - 10,
                    left: offset.left + ($(this).outerWidth() / 2) - ($tooltip.outerWidth() / 2)
                }).fadeIn(200);
            },
            function() {
                $('.custom-tooltip').fadeOut(200, function() {
                    $(this).remove();
                });
            }
        );
        
    });
    
})(jQuery);
