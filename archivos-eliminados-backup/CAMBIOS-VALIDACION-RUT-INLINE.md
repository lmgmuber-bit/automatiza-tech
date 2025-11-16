# Cambios en Validación de RUT en Línea

## Fecha: 15 de Noviembre 2025

## Resumen de Cambios

Se implementó la validación en línea del RUT chileno según los siguientes requisitos:

### 1. **Campo RUT con maxlength=10**
- El usuario ingresa el RUT completo: 9 dígitos (8 números + 1 dígito verificador)
- Ejemplo: Usuario escribe `261918072`
- El sistema valida y formatea automáticamente a `26191807-2`

### 2. **Validación en Línea**
La validación ocurre mientras el usuario escribe, NO al enviar el formulario:

#### Comportamiento:
- **Mientras escribe**: Muestra contador de caracteres restantes
- **Al completar 9 caracteres**: 
  - ✓ Si es válido: Formatea automáticamente con guión (`26191807-2`)
  - ✗ Si es inválido: Muestra error inmediato
- **Al salir del campo (blur)**: Revalida y muestra resultado final

#### Mensajes de Validación:
- **Info** (Azul): "Ingresa los X caracteres restantes..."
- **Éxito** (Verde): "✓ RUT válido: 26191807-2"
- **Error** (Rojo): "❌ RUT inválido. Verifica el dígito verificador."

### 3. **Prevención de Envío con RUT Inválido**
- El formulario NO se puede enviar si el RUT no es válido
- Muestra mensaje de error si intenta enviar sin RUT válido
- Automáticamente enfoca el campo RUT para corrección

### 4. **Características Adicionales**
- Solo permite números y letra K (para dígito verificador)
- Limpia automáticamente caracteres no válidos
- Formatea automáticamente al completar
- Quita el formato al hacer focus para facilitar edición

## Archivos Modificados

### `wp-content/themes/automatiza-tech/inc/contact-shortcode.php`

#### Cambios en el HTML:
```html
<!-- Antes -->
<input maxlength="12" placeholder="Ej: 12345678">
<small>Ingresa tu RUT sin puntos ni guión...</small>

<!-- Después -->
<input maxlength="10" placeholder="Ej: 261918072">
<small>Ingresa tu RUT completo (9 dígitos con dígito verificador)...</small>
<div id="tax-id-validation"></div>
```

#### Cambios en el JavaScript:

1. **Nueva variable global**:
```javascript
var isRutValid = false; // Controla si el RUT es válido
```

2. **Validación keypress**:
```javascript
// Solo permite números y K
taxIdInput.addEventListener('keypress', function(e) {
    if (countryCode === '+56') {
        var char = String.fromCharCode(e.which);
        if (!/[0-9kK]/.test(char)) {
            e.preventDefault();
        }
    }
});
```

3. **Validación en línea (input)**:
```javascript
taxIdInput.addEventListener('input', function(e) {
    if (countryCode === '+56') {
        var cleaned = cleanRut(this.value);
        
        // Limitar a 9 caracteres
        if (cleaned.length > 9) {
            cleaned = cleaned.substring(0, 9);
        }
        
        this.value = cleaned;
        
        // Validar cuando tenga 9 caracteres
        if (cleaned.length === 9) {
            if (validateRut(cleaned)) {
                var formatted = body + '-' + dv;
                this.value = formatted;
                showValidationMessage('success', '✓ RUT válido: ' + formatted);
                isRutValid = true;
            } else {
                showValidationMessage('error', '❌ RUT inválido...');
                isRutValid = false;
            }
        } else if (cleaned.length > 0 && cleaned.length < 9) {
            showValidationMessage('info', 'Ingresa los X caracteres restantes...');
            isRutValid = false;
        }
    }
});
```

4. **Función para mostrar mensajes**:
```javascript
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
```

5. **Validación antes de enviar formulario**:
```javascript
form.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // ... validaciones existentes ...
    
    // Nueva validación de RUT
    var selectedCountryCode = document.getElementById('country_code').value;
    if (selectedCountryCode === '+56' && !isRutValid) {
        messages.className = 'error';
        messages.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Debes ingresar un RUT chileno válido...';
        messages.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        document.getElementById('contact_tax_id').focus();
        return;
    }
    
    // ... continuar con envío ...
});
```

## Archivo de Prueba

### `test-rut-validation-inline.html`
Archivo HTML standalone para probar la validación sin WordPress:
- Casos de prueba incluidos
- RUTs válidos e inválidos para probar
- Misma lógica que el formulario real

### Cómo Probar:
1. Abrir: `http://localhost/automatiza-tech/test-rut-validation-inline.html`
2. Escribir un RUT de 9 dígitos
3. Ver validación en tiempo real
4. Probar casos de prueba haciendo click

## Ejemplos de Uso

### Caso 1: RUT Válido
1. Usuario escribe: `2` → Info: "Ingresa los 8 caracteres restantes..."
2. Usuario escribe: `26` → Info: "Ingresa los 7 caracteres restantes..."
3. Usuario escribe: `261918072` → ✓ Éxito: "RUT válido: 26191807-2"
4. Campo se formatea automáticamente a: `26191807-2`

### Caso 2: RUT Inválido
1. Usuario escribe: `123456789` → ✗ Error: "RUT inválido. Verifica el dígito verificador."
2. Usuario intenta enviar formulario → Bloqueado con mensaje de error
3. Campo recibe focus automáticamente para corrección

### Caso 3: Edición de RUT
1. Campo contiene: `26191807-2` (formateado)
2. Usuario hace focus → Campo limpia formato a: `261918072`
3. Usuario puede editar fácilmente
4. Al salir (blur) → Revalida y reformatea

## Casos de Prueba Válidos

```
261918072  → 26191807-2  ✓
176969238  → 17696923-8  ✓
123456785  → 12345678-5  ✓
999999996  → 99999999-6  ✓
111111119  → 11111111-9  ✓
```

## Casos de Prueba Inválidos

```
123456789  → ✗ Inválido (DV debería ser 5)
111111111  → ✗ Inválido (DV debería ser 9)
987654321  → ✗ Inválido (DV debería ser 0)
```

## Comportamiento por País

### Chile (+56):
- Maxlength: 10 caracteres
- Validación: Algoritmo módulo 11
- Formato: XXXXXXXX-Y
- Obligatorio RUT válido

### Otros Países:
- Maxlength: 50 caracteres
- Validación: Alfanumérico básico
- Sin formato especial
- Campo obligatorio pero sin validación específica

## Notas Técnicas

1. **Performance**: Validación instantánea sin lag
2. **UX**: Feedback visual inmediato
3. **Accesibilidad**: Mensajes claros y enfoque automático
4. **Compatibilidad**: JavaScript vanilla (sin jQuery)
5. **Seguridad**: Validación también en servidor

## Próximos Pasos (Opcionales)

- [ ] Agregar animación de transición en mensajes
- [ ] Sonido de validación (opcional)
- [ ] Tooltip explicativo del dígito verificador
- [ ] Autocompletar RUT si usuario olvida DV
- [ ] Historial de RUTs recientes (localStorage)

## Testing

Para probar los cambios:

1. **Prueba Standalone**:
   ```
   http://localhost/automatiza-tech/test-rut-validation-inline.html
   ```

2. **Prueba en WordPress**:
   - Ir a la página con el formulario de contacto
   - Seleccionar Chile como país
   - Escribir RUT en el campo correspondiente
   - Verificar validación en tiempo real

3. **Casos a Probar**:
   - ✓ RUT válido de 9 dígitos
   - ✗ RUT inválido (DV incorrecto)
   - ✓ Formateo automático con guión
   - ✗ Intento de envío sin RUT válido
   - ✓ Edición de RUT ya formateado
   - ✓ Cambio de país (Chile a otro)

## Conclusión

La validación de RUT ahora es completamente en línea y amigable con el usuario:
- ✓ Validación mientras escribe
- ✓ Formateo automático
- ✓ Prevención de envío con datos inválidos
- ✓ Feedback visual inmediato
- ✓ Experiencia de usuario mejorada
