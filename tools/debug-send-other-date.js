const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Ver Send Other Date Options
const sendOther = j.nodes.find(n => n.name === 'Send Other Date Options');
console.log('=== Send Other Date Options ===');
console.log('Type:', sendOther?.type);
console.log('Params keys:', Object.keys(sendOther?.parameters || {}));
console.log('textBody:', sendOther?.parameters?.textBody);
console.log('to:', sendOther?.parameters?.to);
console.log('phoneNumberId:', sendOther?.parameters?.additionalFields?.phoneNumberId);

// Ver conexiones hacia Send Other Date Options
console.log('\n=== Qué conecta a Send Other Date Options ===');
for (let [name, conns] of Object.entries(j.connections)) {
    if (conns.main) {
        conns.main.forEach((arr, i) => {
            arr.forEach(c => {
                if (c.node === 'Send Other Date Options') {
                    console.log(`${name} (output ${i})`);
                }
            });
        });
    }
}

// Ver conexiones de Send Other Date Options
console.log('\n=== Send Other Date Options conecta a ===');
const sodConns = j.connections['Send Other Date Options'];
console.log(sodConns?.main?.[0]?.map(c => c.node));

// El problema: Send Other Date Options usa $json.phoneNumber
// pero ¿de dónde viene ese $json?
// Viene del nodo anterior: Button Action

// Ver Button Action - output que va a Send Other Date Options
console.log('\n=== Button Action ===');
const buttonAction = j.nodes.find(n => n.name === 'Button Action');
console.log('Type:', buttonAction?.type);
// Es un Switch, ver sus outputs

console.log('\n=== Button Action conexiones ===');
const baConns = j.connections['Button Action'];
baConns?.main?.forEach((arr, i) => {
    console.log(`Output ${i}:`, arr.map(c => c.node));
});

// Verificar si "Otra Fecha" está en el switch
const baRules = buttonAction?.parameters?.rules?.values;
baRules?.forEach((rule, i) => {
    if (rule.outputKey?.toLowerCase().includes('fecha') || rule.conditions?.conditions?.[0]?.rightValue?.includes('date')) {
        console.log(`\nOutput ${i}: ${rule.outputKey}`);
        console.log('Condition:', rule.conditions?.conditions?.[0]?.rightValue);
    }
});
