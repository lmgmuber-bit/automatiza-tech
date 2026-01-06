const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Cambiar Delete Cancelled Appointment para que NO haga hard delete (soft delete)
const deleteCancelled = j.nodes.find(n => n.name === 'Delete Cancelled Appointment');
if (deleteCancelled) {
    // Quitar hard_delete para que haga soft delete (marca como cancelled)
    deleteCancelled.parameters.url = "=https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments/{{ $json.appointmentId }}";
    console.log('✅ Delete Cancelled Appointment: Ahora hace SOFT delete (status=cancelled)');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Archivo guardado');

// Verificar ambos
const dr = j.nodes.find(n => n.name === 'Delete For Reschedule');
const dc = j.nodes.find(n => n.name === 'Delete Cancelled Appointment');

console.log('\n=== CONFIGURACIÓN FINAL ===');
console.log('Delete For Reschedule:', dr.parameters.url.includes('hard_delete=true') ? 'HARD DELETE ✅' : 'SOFT DELETE');
console.log('Delete Cancelled Appointment:', dc.parameters.url.includes('hard_delete') ? 'HARD DELETE' : 'SOFT DELETE ✅');
