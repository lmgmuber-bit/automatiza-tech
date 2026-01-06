const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Seguir el flujo desde Get Config For Parsing
const traceFlow = (startNode, maxDepth = 8) => {
    let current = startNode;
    let depth = 0;
    const visited = new Set();
    
    while (current && depth < maxDepth) {
        if (visited.has(current)) {
            console.log(`  ${depth}. ${current} (loop detectado)`);
            break;
        }
        visited.add(current);
        
        const node = j.nodes.find(n => n.name === current);
        console.log(`${depth}. ${current} [${node?.type?.split('.').pop() || 'unknown'}]`);
        
        const conns = j.connections[current];
        if (!conns?.main?.[0]?.[0]) break;
        
        // Si hay múltiples conexiones
        if (conns.main[0].length > 1) {
            console.log(`   (${conns.main[0].length} salidas: ${conns.main[0].map(c => c.node).join(', ')})`);
        }
        
        current = conns.main[0][0].node;
        depth++;
    }
};

console.log('=== FLUJO PARSE_CUSTOM_DATE ===\n');
traceFlow('Get Config For Parsing');

// Ver el código de Parse Custom Date
const parseCustom = j.nodes.find(n => n.name === 'Parse Custom Date');
console.log('\n=== Parse Custom Date (primeras 500 chars) ===');
console.log((parseCustom?.parameters?.jsCode || '').substring(0, 500));

// Ver Is Custom Date Valid?
const isValid = j.nodes.find(n => n.name === 'Is Custom Date Valid?');
console.log('\n=== Is Custom Date Valid? ===');
console.log(JSON.stringify(isValid?.parameters?.rules?.values?.map(v => ({
    output: v.outputKey,
    condition: v.conditions?.conditions?.[0]?.rightValue
})), null, 2));

// Ver conexiones de Is Custom Date Valid?
console.log('\n=== Conexiones de Is Custom Date Valid? ===');
const icdvConns = j.connections['Is Custom Date Valid?'];
if (icdvConns?.main) {
    icdvConns.main.forEach((arr, i) => {
        console.log(`Output ${i}:`, arr.map(c => c.node));
    });
}
