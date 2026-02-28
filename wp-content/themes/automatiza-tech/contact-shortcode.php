<?php
/**
 * Shortcode para el formulario de contacto
 */
function automatiza_tech_contact_form_shortcode($atts) {
    // Log para debug
    error_log('=== SHORTCODE EJECUTADO ===');
    
    $atts = shortcode_atts(array(
        'title' => '¿Listo para automatizar tu negocio?',
        'subtitle' => 'Completa el formulario y uno de nuestros expertos te contactará en menos de 24 horas'
    ), $atts);
    
    ob_start();
    ?>
    <div class="contact-form-container">
        <form id="automatiza-contact-form" class="contact-form">
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="contact_name">Nombre *</label>
                    <input type="text" 
                           id="contact_name" 
                           name="name" 
                           class="form-control" 
                           required 
                           minlength="2" 
                           maxlength="100"
                           pattern="[a-zA-ZáéíóúñüÁÉÍÓÚÑÜ\s\-\.]+"
                           title="Solo letras, espacios, guiones y puntos. Entre 2 y 100 caracteres.">
                </div>
                <div class="form-group col-md-6">
                    <label for="contact_email">Email *</label>
                    <input type="email" 
                           id="contact_email" 
                           name="email" 
                           class="form-control" 
                           required 
                           maxlength="100"
                           title="Ingresa un email válido (máximo 100 caracteres).">
                </div>
            </div>
            
            <!-- Empresa en fila completa -->
            <div class="form-group">
                <label for="contact_company">Empresa</label>
                <input type="text" 
                       id="contact_company" 
                       name="company" 
                       class="form-control"
                       maxlength="100"
                       title="Nombre de la empresa (máximo 100 caracteres).">
            </div>
            
            <!-- Teléfono en nueva fila -->
            <div class="form-group">
                <label for="contact_phone">Teléfono</label>
                <div class="phone-input-container">
                    <select id="country_code" name="country_code" class="form-control country-selector">
                        <option value="+56" data-country="CL" selected>🇨🇱 Chile (+56)</option>
                        <option value="+54" data-country="AR">🇦🇷 Argentina (+54)</option>
                        <option value="+57" data-country="CO">🇨🇴 Colombia (+57)</option>
                        <option value="+51" data-country="PE">🇵🇪 Perú (+51)</option>
                        <option value="+52" data-country="MX">🇲🇽 México (+52)</option>
                        <option value="+34" data-country="ES">🇪🇸 España (+34)</option>
                        <option value="+1" data-country="US">🇺🇸 USA (+1)</option>
                    </select>
                    <input type="tel" 
                           id="contact_phone" 
                           name="phone" 
                           class="form-control phone-number"
                           placeholder="Ej: 964324169"
                           maxlength="15"
                           pattern="[0-9]{8,15}"
                           title="Ingresa solo el número sin el código de país (8-15 dígitos).">
                </div>
                <small class="form-text text-muted">
                    <span id="phone-preview">Formato: +56 964324169</span>
                </small>
            </div>
            
            <div class="form-row">
                <div class="form-group col-12">
                    <label for="contact_message">Mensaje *</label>
                    <textarea id="contact_message" 
                              name="message" 
                              class="form-control" 
                              rows="5" 
                              placeholder="Cuéntanos sobre tu negocio y cómo podemos ayudarte" 
                              required
                              minlength="10"
                              maxlength="2000"
                              title="Describe tu consulta (entre 10 y 2000 caracteres)."></textarea>
                </div>
            </div>
            
            <div class="form-submit text-center">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane"></i> Enviar Mensaje
                </button>
            </div>
            
            <div id="form-messages"></div>
        </form>
    </div>
    
    <style>
    .contact-form-container {
        background: rgba(255, 255, 255, 0.1);
        padding: 2rem;
        border-radius: 15px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    }
    
    .contact-form .form-row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -0.5rem;
    }
    
    .contact-form .form-group {
        padding: 0 0.5rem;
        margin-bottom: 1.5rem;
    }
    
    .contact-form .col-md-6 {
        flex: 0 0 50%;
        max-width: 50%;
    }
    
    .contact-form .col-12 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .contact-form label {
        display: block !important;
        margin-bottom: 0.5rem !important;
        color: #1e3a8a !important;
        font-weight: 600 !important;
        font-size: 1rem !important;
        text-shadow: none !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    
    .phone-input-container {
        display: flex;
        gap: 0.5rem;
    }
    
    .country-selector {
        flex: 0 0 180px;
        font-size: 0.9rem;
    }
    
    .phone-number {
        flex: 1;
    }
    
    #phone-preview {
        color: #1e3a8a;
        font-weight: 500;
        font-size: 0.85rem;
    }
    
    .contact-form .form-control {
        width: 100% !important;
        padding: 0.75rem 1rem !important;
        border: 2px solid #1e3a8a !important;
        border-radius: 8px !important;
        background: #ffffff !important;
        color: #1e3a8a !important;
        font-size: 1rem !important;
        transition: all 0.3s ease !important;
        margin-top: 0.25rem !important;
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        box-shadow: 0 2px 4px rgba(30, 58, 138, 0.1) !important;
    }
    
    /* Asegurar visibilidad de labels */
    .contact-form-container label,
    .contact-section label,
    #contact label {
        display: block !important;
        color: #1e3a8a !important;
        font-weight: 600 !important;
        font-size: 1rem !important;
        text-shadow: none !important;
        visibility: visible !important;
        opacity: 1 !important;
        margin-bottom: 0.5rem !important;
        z-index: 10 !important;
        position: relative !important;
    }
    
    .contact-form .form-control:focus {
        border-color: #1e3a8a !important;
        box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.2) !important;
        outline: none !important;
        background: #ffffff !important;
    }
    
    /* Asegurar visibilidad de todos los inputs */
    .contact-form input,
    .contact-form textarea,
    .contact-section input,
    .contact-section textarea,
    #contact input,
    #contact textarea {
        width: 100% !important;
        padding: 0.75rem 1rem !important;
        border: 2px solid #1e3a8a !important;
        border-radius: 8px !important;
        background: #ffffff !important;
        color: #1e3a8a !important;
        font-size: 1rem !important;
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        box-shadow: 0 2px 4px rgba(30, 58, 138, 0.1) !important;
        margin-top: 0.25rem !important;
    }
    
    .contact-form input:focus,
    .contact-form textarea:focus,
    .contact-section input:focus,
    .contact-section textarea:focus,
    #contact input:focus,
    #contact textarea:focus {
        border-color: #1e3a8a !important;
        box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.2) !important;
        outline: none !important;
    }
    
    .contact-form textarea.form-control {
        resize: vertical;
        min-height: 120px;
    }
    
    .contact-form .btn-primary {
        background-color: #06d6a0;
        border-color: #06d6a0;
        color: #ffffff;
        padding: 1rem 2.5rem;
        font-size: 1.1rem;
        font-weight: 600;
        border-radius: 50px;
        border: none;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(6, 214, 160, 0.3);
    }
    
    .contact-form .btn-primary:hover {
        background-color: #05b08a;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(6, 214, 160, 0.4);
    }
    
    .contact-form .btn-primary:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }
    
    #form-messages {
        margin-top: 1rem;
        padding: 1rem;
        border-radius: 8px;
        display: none;
    }
    
    #form-messages.success {
        background-color: rgba(40, 167, 69, 0.9);
        color: white;
        border: 1px solid #28a745;
        display: block;
    }
    
    #form-messages.error {
        background-color: rgba(220, 53, 69, 0.9);
        color: white;
        border: 1px solid #dc3545;
        display: block;
    }
    
    .form-loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: #ffffff;
        animation: spin 1s ease-in-out infinite;
        margin-right: 10px;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    @media (max-width: 767.98px) {
        .contact-form .col-md-6 {
            flex: 0 0 100%;
            max-width: 100%;
        }
        
        .contact-form-container {
            padding: 1.5rem;
        }
        
        .contact-form .btn-primary {
            padding: 0.875rem 2rem;
            font-size: 1rem;
        }
        
        /* Teléfono en filas separadas para móvil */
        .phone-input-container {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .country-selector {
            flex: none;
            width: 100%;
            max-width: none;
        }
        
        .phone-number {
            flex: none;
            width: 100%;
        }
        
        /* Ajustar el texto de preview */
        #phone-preview {
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }
    }
    
    /* Media query para tablets */
    @media (min-width: 768px) and (max-width: 991.98px) {
        .phone-input-container {
            display: flex;
            flex-direction: row;
            gap: 0.5rem;
        }
        
        .country-selector {
            flex: 0 0 160px;
            font-size: 0.85rem;
        }
        
        .phone-number {
            flex: 1;
        }
    }
    </style>
    
    <script>
    // Usar JavaScript vanilla para mayor compatibilidad
    document.addEventListener('DOMContentLoaded', function() {
        // Definir configuración AJAX
        window.automatiza_ajax = {
            ajaxurl: '<?php echo admin_url("admin-ajax.php"); ?>',
            nonce: '<?php echo wp_create_nonce("automatiza_ajax_nonce"); ?>'
        };
        
        console.log('Form script loaded with config:', window.automatiza_ajax);
        
        // Funciones de validación del lado cliente
        function validateFormData(name, email, company, phone, message) {
            // Validar nombre
            if (!name || name.length < 2 || name.length > 100) {
                return 'El nombre debe tener entre 2 y 100 caracteres.';
            }
            if (!/^[a-zA-ZáéíóúñüÁÉÍÓÚÑÜ\s\-\.]+$/.test(name)) {
                return 'El nombre solo puede contener letras, espacios, guiones y puntos.';
            }
            
            // Validar email
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                return 'Por favor ingresa un email válido.';
            }
            if (email.length > 100) {
                return 'El email no puede tener más de 100 caracteres.';
            }
            
            // Validar empresa (opcional)
            if (company && company.length > 100) {
                return 'El nombre de la empresa no puede tener más de 100 caracteres.';
            }
            
            // Validar teléfono (opcional pero si se ingresa debe ser válido)
            if (phone) {
                if (!/^[0-9]+$/.test(phone)) {
                    return 'El número de teléfono solo debe contener dígitos (sin espacios ni guiones).';
                }
                
                // Obtener el código de país seleccionado
                var selectedCountryCode = document.getElementById('country_code').value;
                
                if (selectedCountryCode === '+56') {
                    // Chile: exactamente 9 dígitos
                    if (phone.length !== 9) {
                        return 'Los números chilenos deben tener exactamente 9 dígitos.';
                    }
                } else {
                    // Otros países: 8-15 dígitos
                    if (phone.length < 8 || phone.length > 15) {
                        return 'El número de teléfono debe contener entre 8 y 15 dígitos.';
                    }
                }
            }
            
            // Validar mensaje
            if (!message || message.length < 10 || message.length > 2000) {
                return 'El mensaje debe tener entre 10 y 2000 caracteres.';
            }
            
            // Detectar posibles intentos de inyección
            var dangerousPatterns = [
                /<script/i, /javascript:/i, /onload=/i, /onerror=/i,
                /SELECT.*FROM/i, /INSERT.*INTO/i, /UPDATE.*SET/i, /DELETE.*FROM/i,
                /UNION.*SELECT/i, /DROP.*TABLE/i
            ];
            
            var allContent = name + ' ' + email + ' ' + company + ' ' + phone + ' ' + message;
            for (var i = 0; i < dangerousPatterns.length; i++) {
                if (dangerousPatterns[i].test(allContent)) {
                    return 'Se detectó contenido no permitido en el formulario.';
                }
            }
            
            return null; // Sin errores
        }
        
        // Funciones de sanitización
        function sanitizeInput(input) {
            return input.replace(/[<>"\\']/g, '').trim();
        }
        
        function sanitizeEmail(email) {
            return email.toLowerCase().trim();
        }
        
        function sanitizePhone(phone) {
            return phone.replace(/[^0-9\+\-\s\(\)]/g, '').trim();
        }
        
        function sanitizeMessage(message) {
            return message.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '').trim();
        }
        
        // Manejo del selector de país y teléfono
        var countrySelect = document.getElementById('country_code');
        var phoneInput = document.getElementById('contact_phone');
        var phonePreview = document.getElementById('phone-preview');
        
        function updatePhonePreview() {
            var countryCode = countrySelect.value;
            var phoneNumber = phoneInput.value;
            var preview = 'Formato: ' + countryCode + ' ' + (phoneNumber || 'XXXXXXXXX');
            phonePreview.textContent = preview;
            
            // Validación específica para Chile (+56) - máximo 9 dígitos
            updatePhoneValidation(countryCode);
        }
        
        function updatePhoneValidation(countryCode) {
            if (countryCode === '+56') {
                // Chile: exactamente 9 dígitos
                phoneInput.setAttribute('maxlength', '9');
                phoneInput.setAttribute('pattern', '[0-9]{9}');
                phoneInput.setAttribute('title', 'Ingresa exactamente 9 dígitos para números chilenos (ej: 964324169)');
                phoneInput.setAttribute('placeholder', 'Ej: 964324169');
                
                // Si ya tiene más de 9 dígitos, recortar
                if (phoneInput.value.length > 9) {
                    phoneInput.value = phoneInput.value.substring(0, 9);
                }
            } else {
                // Otros países: 8-15 dígitos
                phoneInput.setAttribute('maxlength', '15');
                phoneInput.setAttribute('pattern', '[0-9]{8,15}');
                phoneInput.setAttribute('title', 'Ingresa el número sin el código de país (8-15 dígitos)');
                phoneInput.setAttribute('placeholder', 'Ej: 1234567890');
            }
        }
        
        // Actualizar preview cuando cambie el país o el número
        if (countrySelect && phoneInput && phonePreview) {
            countrySelect.addEventListener('change', function() {
                updatePhonePreview();
                // Limpiar el campo cuando se cambie de país
                phoneInput.value = '';
                updatePhonePreview();
            });
            phoneInput.addEventListener('input', updatePhonePreview);
            updatePhonePreview(); // Inicializar
        }
        
        // Función para verificar si el teléfono ya existe
        function checkPhoneExists(fullPhone, callback) {
            var checkData = new FormData();
            checkData.append('action', 'check_phone_exists');
            checkData.append('phone', fullPhone);
            checkData.append('nonce', window.automatiza_ajax.nonce);
            
            fetch(window.automatiza_ajax.ajaxurl, {
                method: 'POST',
                body: checkData
            })
            .then(response => response.json())
            .then(data => {
                callback(data.exists);
            })
            .catch(error => {
                console.error('Error verificando teléfono:', error);
                callback(false);
            });
        }

        var form = document.getElementById('automatiza-contact-form');
        if (!form) {
            console.error('Formulario no encontrado');
            return;
        }
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Form submitted');
            
            var submitBtn = form.querySelector('button[type="submit"]');
            var messages = document.getElementById('form-messages');
            var originalBtnText = submitBtn.innerHTML;
            
            // Mostrar loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="form-loading"></span>Enviando...';
            messages.className = '';
            messages.style.display = 'none';
            
            // Validar datos del formulario antes de enviar
            var name = document.getElementById('contact_name').value.trim();
            var email = document.getElementById('contact_email').value.trim();
            var company = document.getElementById('contact_company').value.trim();
            var phone = document.getElementById('contact_phone').value.trim();
            var message = document.getElementById('contact_message').value.trim();
            
            // Validaciones del lado cliente
            var validationError = validateFormData(name, email, company, phone, message);
            if (validationError) {
                messages.className = 'error';
                messages.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + validationError;
                messages.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                return;
            }
            
            // Si hay teléfono, verificar si ya existe
            if (phone) {
                var countryCode = document.getElementById('country_code').value;
                var fullPhone = countryCode + phone;
                
                checkPhoneExists(fullPhone, function(exists) {
                    if (exists) {
                        messages.className = 'error';
                        messages.innerHTML = '<i class="fas fa-exclamation-triangle"></i> El número de teléfono ' + fullPhone + ' ya se encuentra registrado en nuestro sistema. Si eres el propietario de este número y necesitas actualizar tu información, contáctanos por WhatsApp.';
                        messages.style.display = 'block';
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                        return;
                    } else {
                        // El teléfono no existe, proceder con el envío
                        proceedWithSubmission();
                    }
                });
            } else {
                // No hay teléfono, proceder con el envío
                proceedWithSubmission();
            }
            
            function proceedWithSubmission() {
            
            // Sanitizar datos antes de enviar
            var formData = new FormData();
            formData.append('action', 'submit_contact_form');
            formData.append('name', sanitizeInput(name));
            formData.append('email', sanitizeEmail(email));
            formData.append('company', sanitizeInput(company));
            formData.append('phone', sanitizePhone(fullPhone)); // Enviar teléfono completo con código de país
            formData.append('message', sanitizeMessage(message));
            formData.append('nonce', window.automatiza_ajax.nonce);
            
            console.log('Sending data to:', window.automatiza_ajax.ajaxurl);
            
            // Enviar via fetch
            fetch(window.automatiza_ajax.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Raw response:', response);
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                // Verificar si la respuesta es exitosa
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                
                // Intentar parsear como texto primero
                return response.text().then(text => {
                    console.log('Response text:', text);
                    
                    try {
                        // Intentar parsear como JSON
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        console.error('Response was not valid JSON:', text);
                        throw new Error('Respuesta del servidor no válida');
                    }
                });
            })
            .then(data => {
                console.log('Parsed JSON data:', data);
                if (data && data.success) {
                    messages.className = 'success';
                    messages.innerHTML = '<i class="fas fa-check-circle"></i> ' + (data.data || '¡Mensaje enviado correctamente!');
                    messages.style.display = 'block';
                    form.reset();
                    
                    // Redirigir a WhatsApp después de 2 segundos
                    setTimeout(function() {
                        var whatsappMsg = encodeURIComponent('Hola! Acabo de enviar el formulario de contacto desde su sitio web. Me gustaría conocer más sobre Automatiza Tech.');
                        var whatsappUrl = 'https://wa.me/56940331127?text=' + whatsappMsg;
                        window.open(whatsappUrl, '_blank');
                    }, 2000);
                } else {
                    messages.className = 'error';
                    messages.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + (data.data || 'Error desconocido');
                    messages.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                messages.className = 'error';
                messages.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + error.message;
                messages.style.display = 'block';
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
            } // Cerrar proceedWithSubmission
        });
        
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('contact_form', 'automatiza_tech_contact_form_shortcode');
?>