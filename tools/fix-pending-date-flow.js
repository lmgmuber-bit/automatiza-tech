const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Quitar la conexión directa Process Text -> Check Pending Date
// porque ya va Process Text -> Check Pending Booking -> Check Pending Date
const ptConns = j.connections['Process Text'];
if (ptConns?.main?.[0]) {
    const idx = ptConns.main[0].findIndex(c => c.node === 'Check Pending Date');
    if (idx >= 0) {
        ptConns.main[0].splice(idx, 1);
        console.log('✅ Removida conexión directa Process Text -> Check Pending Date');
    }
}

// Verificar el flujo actual
console.log('\n=== FLUJO ACTUAL ===');
console.log('Process Text conecta a:', ptConns?.main?.[0]?.map(c => c.node));

const cpbConns = j.connections['Check Pending Booking'];
console.log('Check Pending Booking conecta a:', cpbConns?.main?.[0]?.map(c => c.node));

const cpdConns = j.connections['Check Pending Date'];
console.log('Check Pending Date conecta a:', cpdConns?.main?.[0]?.map(c => c.node));

const mbdConns = j.connections['Merge Booking Data'];
console.log('Merge Booking Data conecta a:', mbdConns?.main?.[0]?.map(c => c.node));

const cebConns = j.connections['Check Email Booking'];
console.log('Check Email Booking conecta a:', cebConns?.main?.[0]?.map(c => c.node));

// Ahora verificar Check Email Booking código completo
const checkEmail = j.nodes.find(n => n.name === 'Check Email Booking');
console.log('\n=== Check Email Booking - Código completo ===');
console.log(checkEmail?.parameters?.jsCode);

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');
