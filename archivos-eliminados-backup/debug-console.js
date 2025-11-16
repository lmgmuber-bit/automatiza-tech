// SCRIPT DE DEBUG PARA CONSOLA DEL NAVEGADOR
// Copia y pega este código en la consola del navegador cuando estés en el admin de servicios

console.log('=== DEBUG DE SERVICIOS AUTOMATIZA TECH ===');

// Verificar si están las funciones JavaScript
console.log('Funciones disponibles:');
console.log('- editService:', typeof editService !== 'undefined' ? '✅ Disponible' : '❌ No disponible');
console.log('- deleteService:', typeof deleteService !== 'undefined' ? '✅ Disponible' : '❌ No disponible');
console.log('- duplicateService:', typeof duplicateService !== 'undefined' ? '✅ Disponible' : '❌ No disponible');

// Verificar variables AJAX
console.log('\nVariables AJAX:');
if (typeof automatiza_ajax !== 'undefined') {
    console.log('- automatiza_ajax.ajax_url:', automatiza_ajax.ajax_url);
    console.log('- automatiza_ajax.nonce:', automatiza_ajax.nonce);
} else {
    console.log('❌ Variable automatiza_ajax no está definida');
}

// Verificar jQuery
console.log('\njQuery:');
console.log('- jQuery disponible:', typeof jQuery !== 'undefined' ? '✅ SÍ' : '❌ NO');
console.log('- $ disponible:', typeof $ !== 'undefined' ? '✅ SÍ' : '❌ NO');

// Buscar todos los botones de editar
console.log('\nBotones de editar encontrados:');
const editButtons = document.querySelectorAll('button[onclick*="editService"]');
editButtons.forEach((button, index) => {
    const onclick = button.getAttribute('onclick');
    const serviceId = onclick.match(/editService\((\d+)\)/)?.[1];
    const isDisabled = button.disabled;
    const isVisible = button.offsetParent !== null;
    
    console.log(`- Botón ${index + 1}: Servicio ID ${serviceId}`);
    console.log(`  - Deshabilitado: ${isDisabled ? '❌ SÍ' : '✅ NO'}`);
    console.log(`  - Visible: ${isVisible ? '✅ SÍ' : '❌ NO'}`);
    console.log(`  - Elemento:`, button);
});

// Probar función editService con el ID 4 (Atención 24/7)
console.log('\n=== PRUEBA DE FUNCIÓN editService CON ID 4 ===');
if (typeof editService !== 'undefined') {
    console.log('Ejecutando editService(4)...');
    try {
        editService(4);
        console.log('✅ Función ejecutada sin errores');
    } catch (error) {
        console.error('❌ Error al ejecutar función:', error);
    }
} else {
    console.log('❌ Función editService no disponible');
}

// Información adicional
console.log('\n=== INFORMACIÓN ADICIONAL ===');
console.log('URL actual:', window.location.href);
console.log('User agent:', navigator.userAgent);
console.log('Errores en consola:', console.error);