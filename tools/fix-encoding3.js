const fs = require('fs');
const path = process.argv[2] || 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

console.log('Leyendo:', path);

// Leer como buffer
const buffer = fs.readFileSync(path);
let content = buffer.toString('utf8');

// Buscar secuencias corruptas específicas y mostrarlas
const samples = [
  content.substring(content.indexOf('SUSCRIPCI') || 0, (content.indexOf('SUSCRIPCI') || 0) + 20),
  content.substring(content.indexOf('COMUNICACI') || 0, (content.indexOf('COMUNICACI') || 0) + 20),
];
console.log('Muestras encontradas:', samples);

// Reemplazos usando Buffer para evitar problemas de encoding
// Ã" (C3 93 en UTF8 mal interpretado) -> Ó (C3 93 correcto)
// El problema es que está como: C3 83 C2 93

// Detectar el patrón exacto
const idx = content.indexOf('SUSCRIPCI');
if (idx > 0) {
  const slice = buffer.slice(idx, idx + 15);
  console.log('Bytes en SUSCRIPCI...:', Array.from(slice).map(b => b.toString(16)).join(' '));
}

// Método directo: buscar y reemplazar strings específicos
const fixes = [
  ['SUSCRIPCI\u00c3\u201dN', 'SUSCRIPCIÓN'],
  ['COMUNICACI\u00c3\u201dN', 'COMUNICACIÓN'],
  ['ACCI\u00c3\u201dN', 'ACCIÓN'],
];

let count = 0;
for (const [bad, good] of fixes) {
  while (content.includes(bad)) {
    content = content.replace(bad, good);
    count++;
  }
}

// Guardar
fs.writeFileSync(path, content, 'utf8');
console.log('Reemplazos:', count);

// Verificar resultado
const check = fs.readFileSync(path, 'utf8');
console.log('Contiene SUSCRIPCIÓN:', check.includes('SUSCRIPCIÓN'));
