const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// 1. Modificar Delete For Reschedule para hacer hard delete
const deleteReschedule = j.nodes.find(n => n.name === 'Delete For Reschedule');
if (deleteReschedule) {
    // Agregar ?hard_delete=true para eliminar completamente
    deleteReschedule.parameters.url = "=https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments/{{ $('Parse Reschedule Event').first().json.appointmentId }}?hard_delete=true";
    console.log('✅ Delete For Reschedule: Ahora hace hard delete');
}

// 2. Verificar Delete Cancelled Appointment (cancelación normal) - debe ser soft delete
const deleteCancelled = j.nodes.find(n => n.name === 'Delete Cancelled Appointment');
if (deleteCancelled) {
    console.log('Delete Cancelled Appointment URL:', deleteCancelled.parameters.url);
    // Asegurar que NO tenga hard_delete (soft delete por defecto)
    if (!deleteCancelled.parameters.url.includes('hard_delete')) {
        console.log('✅ Delete Cancelled Appointment: Ya usa soft delete (correcto)');
    }
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}

console.log('\n=== RESUMEN ===');
console.log('Reprogramación: hard_delete=true (elimina completamente)');
console.log('Cancelación: soft delete (status=cancelled, aparece en "todas las citas")');
