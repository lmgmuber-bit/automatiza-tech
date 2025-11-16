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
                        <!-- América del Sur -->
                        <optgroup label="--- America del Sur ---">
                            <option value="+54" data-country="AR">AR Argentina (+54)</option>
                            <option value="+591" data-country="BO">BO Bolivia (+591)</option>
                            <option value="+55" data-country="BR">BR Brasil (+55)</option>
                            <option value="+56" data-country="CL" selected>CL Chile (+56)</option>
                            <option value="+57" data-country="CO">CO Colombia (+57)</option>
                            <option value="+593" data-country="EC">EC Ecuador (+593)</option>
                            <option value="+594" data-country="GF">GF Guyana Francesa (+594)</option>
                            <option value="+592" data-country="GY">GY Guyana (+592)</option>
                            <option value="+595" data-country="PY">PY Paraguay (+595)</option>
                            <option value="+51" data-country="PE">PE Peru (+51)</option>
                            <option value="+597" data-country="SR">SR Surinam (+597)</option>
                            <option value="+598" data-country="UY">UY Uruguay (+598)</option>
                            <option value="+58" data-country="VE">VE Venezuela (+58)</option>
                        </optgroup>
                        
                        <!-- América Central -->
                        <optgroup label="--- America Central ---">
                            <option value="+501" data-country="BZ">BZ Belice (+501)</option>
                            <option value="+506" data-country="CR">CR Costa Rica (+506)</option>
                            <option value="+503" data-country="SV">SV El Salvador (+503)</option>
                            <option value="+502" data-country="GT">GT Guatemala (+502)</option>
                            <option value="+504" data-country="HN">HN Honduras (+504)</option>
                            <option value="+52" data-country="MX">MX Mexico (+52)</option>
                            <option value="+505" data-country="NI">NI Nicaragua (+505)</option>
                            <option value="+507" data-country="PA">PA Panama (+507)</option>
                        </optgroup>
                        
                        <!-- Caribe -->
                        <optgroup label="--- Caribe ---">
                            <option value="+53" data-country="CU">CU Cuba (+53)</option>
                            <option value="+509" data-country="HT">HT Haiti (+509)</option>
                            <option value="+1787" data-country="PR">PR Puerto Rico (+1787)</option>
                            <option value="+1809" data-country="DO">DO Rep. Dominicana (+1809)</option>
                        </optgroup>
                        
                        <!-- Otros Países -->
                        <optgroup label="--- Otros Paises ---">
                            <option value="+1" data-country="US">US USA/Canada (+1)</option>
                            <option value="+34" data-country="ES">ES Espana (+34)</option>
                            <option value="+351" data-country="PT">PT Portugal (+351)</option>
                            <option value="+44" data-country="GB">GB Reino Unido (+44)</option>
                            <option value="+33" data-country="FR">FR Francia (+33)</option>
                        </optgroup>
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
            
            <!-- RUT/DNI/Pasaporte en nueva fila -->
            <div class="form-group">
                <label for="contact_tax_id"><span id="tax-id-label">RUT</span> *</label>
                <input type="text" 
                       id="contact_tax_id" 
                       name="tax_id" 
                       class="form-control"
                       placeholder="Ej: 154972986"
                       required
                       minlength="5"
                       maxlength="10"
                       title="Ingresa tu RUT, DNI, Cédula o Pasaporte según tu país.">
                <small class="form-text text-muted">
                    <span id="tax-id-help">Ingresa tu RUT completo (9 dígitos con dígito verificador). Ejemplo: 261918072</span>
                </small>
                <div id="tax-id-validation" style="display: none; margin-top: 0.5rem; padding: 0.5rem; border-radius: 4px; font-size: 0.9rem;"></div>
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
        
        // ============================================
        // FUNCIONES DE VALIDACIÓN Y FORMATEO DE RUT CHILENO
        // ============================================
        
        /**
         * Limpia el RUT dejando solo números y K
         */
        function cleanRut(rut) {
            return rut.replace(/[^0-9kK]/g, '').toUpperCase();
        }
        
        /**
         * Calcula el dígito verificador de un RUT chileno
         */
        function calculateDV(rut) {
            var rutNumerico = parseInt(rut, 10);
            var m = 0, s = 1;
            
            while (rutNumerico > 0) {
                s = (s + rutNumerico % 10 * (9 - m++ % 6)) % 11;
                rutNumerico = Math.floor(rutNumerico / 10);
            }
            
            return s ? (s - 1).toString() : 'K';
        }
        
        /**
         * Valida un RUT completo
         */
        function validateRut(rut) {
            if (!rut || rut.length < 2) return false;
            
            var cleaned = cleanRut(rut);
            if (cleaned.length < 2) return false;
            
            var body = cleaned.slice(0, -1);
            var dv = cleaned.slice(-1);
            
            // Validar que el cuerpo sea numérico
            if (!/^[0-9]+$/.test(body)) return false;
            
            // Validar longitud (RUT chileno tiene entre 7 y 8 dígitos + DV)
            if (body.length < 7 || body.length > 8) return false;
            
            return calculateDV(body) === dv;
        }
        
        /**
         * Formatea un RUT con puntos y guión
         */
        function formatRut(rut) {
            var cleaned = cleanRut(rut);
            if (cleaned.length < 2) return cleaned;
            
            var body = cleaned.slice(0, -1);
            var dv = cleaned.slice(-1);
            
            // Agregar puntos cada 3 dígitos desde la derecha
            var formatted = body.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            
            return formatted + '-' + dv;
        }
        
        /**
         * Autocompleta el dígito verificador si falta
         */
        function autoCompleteRut(rut) {
            var cleaned = cleanRut(rut);
            
            // Si ya tiene DV (tiene guión o K al final), no hacer nada
            if (rut.includes('-') || /[kK]$/.test(rut)) {
                return rut;
            }
            
            // Si tiene entre 7 y 8 dígitos sin DV, calcularlo
            if (cleaned.length >= 7 && cleaned.length <= 8 && /^[0-9]+$/.test(cleaned)) {
                var dv = calculateDV(cleaned);
                return formatRut(cleaned + dv);
            }
            
            return rut;
        }
        
        // ============================================
        // FIN FUNCIONES RUT
        // ============================================
        
        // Funciones de validación del lado cliente
        function validateFormData(name, email, company, phone, tax_id, message) {
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
            
            // Validar RUT/DNI/Pasaporte (obligatorio)
            if (!tax_id || tax_id.length < 5 || tax_id.length > 50) {
                return 'El RUT/DNI/Pasaporte es obligatorio y debe tener entre 5 y 50 caracteres.';
            }
            
            // Validación especial para RUT chileno
            var selectedCountryCode = document.getElementById('country_code').value;
            if (selectedCountryCode === '+56') {
                if (!validateRut(tax_id)) {
                    return 'El RUT chileno ingresado no es válido. Verifica el número y el dígito verificador.';
                }
            } else {
                // Otros países: solo validar formato básico
                if (!/^[a-zA-Z0-9\.\-]+$/.test(tax_id)) {
                    return 'El RUT/DNI/Pasaporte solo puede contener letras, números, puntos y guiones.';
                }
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
            
            var allContent = name + ' ' + email + ' ' + company + ' ' + phone + ' ' + tax_id + ' ' + message;
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
        
        // Bloquear letras en el campo de teléfono (solo números)
        var phoneInput = document.getElementById('contact_phone');
        var countrySelect = document.getElementById('country_code');
        
        if (phoneInput) {
            phoneInput.addEventListener('keypress', function(e) {
                var char = String.fromCharCode(e.which);
                
                // Validación especial para Chile: el primer dígito debe ser 9
                if (countrySelect && countrySelect.value === '+56') {
                    if (phoneInput.value.length === 0 && char !== '9') {
                        e.preventDefault();
                        return;
                    }
                }
                
                // Permitir solo números (0-9)
                if (!/[0-9]/.test(char)) {
                    e.preventDefault();
                }
            });
            
            phoneInput.addEventListener('paste', function(e) {
                // Limpiar cualquier caracter que no sea número al pegar
                setTimeout(function() {
                    var cleanValue = phoneInput.value.replace(/[^0-9]/g, '');
                    
                    // Validación especial para Chile: debe empezar con 9
                    if (countrySelect && countrySelect.value === '+56') {
                        if (cleanValue.length > 0 && cleanValue[0] !== '9') {
                            // Si no empieza con 9, limpiar el campo
                            phoneInput.value = '';
                            alert('Los números chilenos deben comenzar con 9');
                            return;
                        }
                    }
                    
                    phoneInput.value = cleanValue;
                }, 0);
            });
            
            phoneInput.addEventListener('input', function(e) {
                // Remover cualquier caracter que no sea número en tiempo real
                var cleanValue = this.value.replace(/[^0-9]/g, '');
                
                // Validación especial para Chile: debe empezar con 9
                if (countrySelect && countrySelect.value === '+56') {
                    if (cleanValue.length > 0 && cleanValue[0] !== '9') {
                        // Si no empieza con 9, mantener vacío o solo el 9
                        this.value = '';
                        return;
                    }
                }
                
                this.value = cleanValue;
            });
        }
        
        // Bloquear números en el campo de nombre (solo letras)
        var nameInput = document.getElementById('contact_name');
        if (nameInput) {
            nameInput.addEventListener('keypress', function(e) {
                // Permitir letras, espacios, guiones y puntos
                var char = String.fromCharCode(e.which);
                if (!/[a-zA-ZáéíóúñüÁÉÍÓÚÑÜ\s\-\.]/.test(char)) {
                    e.preventDefault();
                }
            });
            
            nameInput.addEventListener('paste', function(e) {
                // Limpiar números y caracteres especiales al pegar
                setTimeout(function() {
                    nameInput.value = nameInput.value.replace(/[^a-zA-ZáéíóúñüÁÉÍÓÚÑÜ\s\-\.]/g, '');
                }, 0);
            });
            
            nameInput.addEventListener('input', function(e) {
                // Remover números y caracteres especiales en tiempo real
                this.value = this.value.replace(/[^a-zA-ZáéíóúñüÁÉÍÓÚÑÜ\s\-\.]/g, '');
            });
        }
        
        // Manejo del selector de país y teléfono
        var countrySelect = document.getElementById('country_code');
        var phonePreview = document.getElementById('phone-preview');
        var taxIdLabel = document.getElementById('tax-id-label');
        var taxIdHelp = document.getElementById('tax-id-help');
        var taxIdInput = document.getElementById('contact_tax_id');
        
        function updatePhonePreview() {
            var countryCode = countrySelect.value;
            var phoneNumber = phoneInput.value;
            var preview = 'Formato: ' + countryCode + ' ' + (phoneNumber || 'XXXXXXXXX');
            phonePreview.textContent = preview;
            
            // Validación específica según el país
            updatePhoneValidation(countryCode);
            
            // Actualizar label y ayuda de RUT/DNI según el país
            updateTaxIdLabel(countryCode);
        }
        
        function updateTaxIdLabel(countryCode) {
            if (countryCode === '+56') {
                // Chile
                taxIdLabel.textContent = 'RUT';
                taxIdHelp.textContent = 'Ingresa tu RUT completo (9 dígitos con dígito verificador). Ejemplo: 261918072';
                taxIdInput.placeholder = 'Ej: 261918072';
                taxIdInput.maxLength = 10; // 9 dígitos máximo
            } else {
                // Otros países
                taxIdLabel.textContent = 'DNI/Cédula/Pasaporte';
                taxIdHelp.textContent = 'Ingresa tu número de identificación (DNI, Cédula, CI o Pasaporte según tu país)';
                taxIdInput.placeholder = 'Ej: 12345678 o ABC123456';
                taxIdInput.maxLength = 50;
            }
        }
        
        // ============================================
        // MANEJO DEL CAMPO RUT/DNI
        // ============================================
        
        var taxIdValidationDiv = document.getElementById('tax-id-validation');
        var isRutValid = false; // Variable para controlar si el RUT es válido
        
        if (taxIdInput && countrySelect) {
            // Permitir solo números y K en tiempo real para Chile
            taxIdInput.addEventListener('keypress', function(e) {
                var countryCode = countrySelect.value;
                
                if (countryCode === '+56') {
                    var char = String.fromCharCode(e.which);
                    // Permitir solo números y K/k
                    if (!/[0-9kK]/.test(char)) {
                        e.preventDefault();
                    }
                }
            });
            
            // Formatear y validar automáticamente cuando el usuario escribe
            taxIdInput.addEventListener('input', function(e) {
                var countryCode = countrySelect.value;
                
                if (countryCode === '+56') {
                    // Solo para Chile: validar y formatear RUT
                    var cleaned = cleanRut(this.value);
                    
                    // Limitar a 9 caracteres (8 dígitos + 1 DV)
                    if (cleaned.length > 9) {
                        cleaned = cleaned.substring(0, 9);
                    }
                    
                    // Actualizar el valor sin formato mientras escribe
                    this.value = cleaned;
                    
                    // Validar en línea cuando tenga 9 caracteres
                    if (cleaned.length === 9) {
                        var body = cleaned.slice(0, -1);
                        var dv = cleaned.slice(-1);
                        
                        // Validar que el cuerpo sea numérico
                        if (!/^[0-9]+$/.test(body)) {
                            showValidationMessage('error', '❌ El RUT debe contener solo números y el dígito verificador (0-9 o K)');
                            isRutValid = false;
                            return;
                        }
                        
                        // Validar longitud del cuerpo (7-8 dígitos)
                        if (body.length < 7 || body.length > 8) {
                            showValidationMessage('error', '❌ El RUT debe tener entre 8 y 9 dígitos en total');
                            isRutValid = false;
                            return;
                        }
                        
                        // Validar el dígito verificador
                        if (validateRut(cleaned)) {
                            // RUT válido: formatear con guión
                            var formatted = body + '-' + dv;
                            this.value = formatted;
                            showValidationMessage('success', '✓ RUT válido: ' + formatted);
                            isRutValid = true;
                        } else {
                            showValidationMessage('error', '❌ RUT inválido. Verifica el dígito verificador.');
                            isRutValid = false;
                        }
                    } else if (cleaned.length > 0 && cleaned.length < 9) {
                        // Todavía está escribiendo
                        showValidationMessage('info', 'Ingresa los ' + (9 - cleaned.length) + ' caracteres restantes...');
                        isRutValid = false;
                    } else {
                        hideValidationMessage();
                        isRutValid = false;
                    }
                } else {
                    // Otros países: solo permitir alfanumérico, puntos y guiones
                    this.value = this.value.replace(/[^a-zA-Z0-9\.\-]/g, '');
                    isRutValid = true; // Para otros países, asumimos válido si tiene contenido
                }
            });
            
            // Limpiar formato al hacer focus para permitir edición
            taxIdInput.addEventListener('focus', function(e) {
                var countryCode = countrySelect.value;
                
                if (countryCode === '+56') {
                    // Remover el guión para permitir edición
                    var value = this.value.replace('-', '');
                    this.value = value;
                    hideValidationMessage();
                }
            });
            
            // Validar al salir del campo (blur)
            taxIdInput.addEventListener('blur', function(e) {
                var countryCode = countrySelect.value;
                
                if (countryCode === '+56') {
                    var cleaned = cleanRut(this.value);
                    
                    if (cleaned.length === 9 && validateRut(cleaned)) {
                        var body = cleaned.slice(0, -1);
                        var dv = cleaned.slice(-1);
                        var formatted = body + '-' + dv;
                        this.value = formatted;
                        showValidationMessage('success', '✓ RUT válido: ' + formatted);
                        isRutValid = true;
                    } else if (cleaned.length > 0) {
                        showValidationMessage('error', '❌ RUT inválido. Debe tener 9 dígitos con dígito verificador correcto.');
                        isRutValid = false;
                    } else {
                        hideValidationMessage();
                        isRutValid = false;
                    }
                }
            });
        }
        
        function showValidationMessage(type, message) {
            if (!taxIdValidationDiv) return;
            
            taxIdValidationDiv.style.display = 'block';
            taxIdValidationDiv.textContent = message;
            
            if (type === 'success') {
                taxIdValidationDiv.style.backgroundColor = '#d4edda';
                taxIdValidationDiv.style.color = '#155724';
                taxIdValidationDiv.style.borderLeft = '4px solid #28a745';
            } else if (type === 'error') {
                taxIdValidationDiv.style.backgroundColor = '#f8d7da';
                taxIdValidationDiv.style.color = '#721c24';
                taxIdValidationDiv.style.borderLeft = '4px solid #dc3545';
            } else if (type === 'info') {
                taxIdValidationDiv.style.backgroundColor = '#d1ecf1';
                taxIdValidationDiv.style.color = '#0c5460';
                taxIdValidationDiv.style.borderLeft = '4px solid #17a2b8';
            }
        }
        
        function hideValidationMessage() {
            if (taxIdValidationDiv) {
                taxIdValidationDiv.style.display = 'none';
            }
        }
        
        // ============================================
        // FIN MANEJO RUT/DNI
        // ============================================
        
        function updatePhoneValidation(countryCode) {
            // Configuración de longitud según el país
            var phoneConfig = {
                '+56': { min: 9, max: 9, digits: 9, example: '964324169', name: 'Chile' },
                '+54': { min: 9, max: 10, digits: '9-10', example: '91234567890', name: 'Argentina' },
                '+55': { min: 10, max: 11, digits: '10-11', example: '11987654321', name: 'Brasil' },
                '+57': { min: 10, max: 10, digits: 10, example: '3001234567', name: 'Colombia' },
                '+51': { min: 9, max: 9, digits: 9, example: '987654321', name: 'Perú' },
                '+52': { min: 10, max: 10, digits: 10, example: '5512345678', name: 'México' },
                '+591': { min: 8, max: 9, digits: '8-9', example: '71234567', name: 'Bolivia' },
                '+593': { min: 9, max: 9, digits: 9, example: '987654321', name: 'Ecuador' },
                '+595': { min: 9, max: 9, digits: 9, example: '981234567', name: 'Paraguay' },
                '+598': { min: 9, max: 9, digits: 9, example: '91234567', name: 'Uruguay' },
                '+58': { min: 10, max: 10, digits: 10, example: '4121234567', name: 'Venezuela' },
                '+501': { min: 7, max: 7, digits: 7, example: '6221234', name: 'Belice' },
                '+506': { min: 8, max: 8, digits: 8, example: '88881234', name: 'Costa Rica' },
                '+503': { min: 8, max: 8, digits: 8, example: '78901234', name: 'El Salvador' },
                '+502': { min: 8, max: 8, digits: 8, example: '51234567', name: 'Guatemala' },
                '+504': { min: 8, max: 8, digits: 8, example: '91234567', name: 'Honduras' },
                '+505': { min: 8, max: 8, digits: 8, example: '81234567', name: 'Nicaragua' },
                '+507': { min: 8, max: 8, digits: 8, example: '61234567', name: 'Panamá' },
                '+53': { min: 8, max: 8, digits: 8, example: '51234567', name: 'Cuba' },
                '+509': { min: 8, max: 8, digits: 8, example: '34123456', name: 'Haití' },
                '+1787': { min: 10, max: 10, digits: 10, example: '7871234567', name: 'Puerto Rico' },
                '+1809': { min: 10, max: 10, digits: 10, example: '8091234567', name: 'Rep. Dominicana' },
                '+34': { min: 9, max: 9, digits: 9, example: '612345678', name: 'España' },
                '+1': { min: 10, max: 10, digits: 10, example: '2025551234', name: 'USA/Canadá' },
                '+351': { min: 9, max: 9, digits: 9, example: '912345678', name: 'Portugal' },
                '+44': { min: 10, max: 10, digits: 10, example: '7912345678', name: 'Reino Unido' },
                '+33': { min: 9, max: 9, digits: 9, example: '612345678', name: 'Francia' }
            };
            
            var config = phoneConfig[countryCode] || { min: 8, max: 15, digits: '8-15', example: '123456789', name: 'Internacional' };
            
            phoneInput.setAttribute('minlength', config.min);
            phoneInput.setAttribute('maxlength', config.max);
            
            // Validación especial para Chile: debe empezar con 9
            if (countryCode === '+56') {
                phoneInput.setAttribute('pattern', '9[0-9]{8}');
                phoneInput.setAttribute('title', 'Ingresa exactamente 9 dígitos comenzando con 9 para Chile (ej: ' + config.example + ')');
                phoneInput.setAttribute('placeholder', 'Ej: ' + config.example);
                
                // Si ya tiene un número y no empieza con 9, limpiar
                if (phoneInput.value.length > 0 && phoneInput.value[0] !== '9') {
                    phoneInput.value = '';
                }
                
                // Si ya tiene más de 9 dígitos, recortar
                if (phoneInput.value.length > 9) {
                    phoneInput.value = phoneInput.value.substring(0, 9);
                }
            } else if (config.min === config.max) {
                phoneInput.setAttribute('pattern', '[0-9]{' + config.min + '}');
                phoneInput.setAttribute('title', 'Ingresa exactamente ' + config.digits + ' dígitos para ' + config.name + ' (ej: ' + config.example + ')');
                phoneInput.setAttribute('placeholder', 'Ej: ' + config.example);
                
                // Si ya tiene más dígitos de los permitidos, recortar
                if (phoneInput.value.length > config.max) {
                    phoneInput.value = phoneInput.value.substring(0, config.max);
                }
            } else {
                phoneInput.setAttribute('pattern', '[0-9]{' + config.min + ',' + config.max + '}');
                phoneInput.setAttribute('title', 'Ingresa entre ' + config.digits + ' dígitos para ' + config.name + ' (ej: ' + config.example + ')');
                phoneInput.setAttribute('placeholder', 'Ej: ' + config.example);
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
            var tax_id = document.getElementById('contact_tax_id').value.trim();
            var message = document.getElementById('contact_message').value.trim();
            
            // Validaciones del lado cliente
            var validationError = validateFormData(name, email, company, phone, tax_id, message);
            if (validationError) {
                messages.className = 'error';
                messages.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + validationError;
                messages.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                return;
            }
            
            // Validación adicional de RUT para Chile
            var selectedCountryCode = document.getElementById('country_code').value;
            if (selectedCountryCode === '+56' && !isRutValid) {
                messages.className = 'error';
                messages.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Debes ingresar un RUT chileno válido antes de enviar el formulario.';
                messages.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                // Hacer focus en el campo RUT
                document.getElementById('contact_tax_id').focus();
                return;
            }
            
            // Preparar teléfono completo con código de país
            var countryCode = document.getElementById('country_code').value;
            var fullPhone = phone ? countryCode + phone : '';
            
            // Si hay teléfono, verificar si ya existe
            if (phone) {
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
            formData.append('tax_id', sanitizeInput(tax_id)); // Agregar RUT/DNI con guión
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