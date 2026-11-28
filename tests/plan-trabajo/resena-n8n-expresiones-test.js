'use strict';
// Recibe el JSON de aplicar.py --dry-run; compila y ejecuta expresiones con datos ficticios, sin red.
let input = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', s => { input += s; });
process.stdin.on('end', () => {
  const { workflows } = JSON.parse(input);
  let expressions = 0;
  function compile(value) {
    if (typeof value === 'string' && value.startsWith('={{') && value.endsWith('}}')) {
      // n8n corta la expresión en el primer «}}» interno (PROD 22-nov-2026: «invalid syntax», Meta nunca recibió nada).
      if (value.slice(3, -2).includes('}}')) throw Error('Expresión con «}}» interno: ' + value.slice(0, 80));
      if (value.includes('$env')) throw Error('n8n de AT no expone $env a los nodos: ' + value.slice(0, 80));
      new Function('$json', '$env', '$', 'return (' + value.slice(3, -2).trim() + ');');
      expressions++;
    } else if (value && typeof value === 'object') {
      Object.values(value).forEach(compile);
    }
  }
  workflows.forEach(w => w.nodes.forEach(n => {
    compile(n.parameters);
    if (n.type === 'n8n-nodes-base.code') new Function('$json', '$input', '$', n.parameters.jsCode);
  }));
  // Comprobaciones propias del flujo de WhatsApp: solo si viene en la entrada (resumen.py --dry-run no lo trae).
  // Se reconoce por un nodo de nombre ASCII: la tubería de PowerShell 5.1 estropea la «ñ» del nombre del flujo.
  const wa = workflows.find(w => w.nodes.some(n => n.name.startsWith('Enviar plantilla')));
  if (wa) {
    const send = wa.nodes.find(n => n.name.startsWith('Enviar plantilla'));
    const body = send.parameters.jsonBody.slice(3, -2).trim();
    const crudo = new Function('$json', 'return (' + body + ');')({body:{telefono:'+56900000000',nombre:'Persona',proyecto:'Proyecto ficticio'}});
    const data = typeof crudo === 'string' ? JSON.parse(crudo) : crudo;
    if (data.to !== '56900000000' || data.template.language.code !== 'es') throw Error('Destino o idioma inválido');
    const code = wa.nodes.find(n => n.name === 'Clasificar resultado').parameters.jsCode;
    const run = new Function('$json', '$', code);
    const lookup = () => ({item:{json:{body:{proyecto_id:1,intento_id:'ficticio',modo:'prueba'}}}});
    for (const [response, expected] of [
      [{statusCode:200,body:{messages:[{id:'wamid.ficticio01'}]}},'aceptado'],
      [{statusCode:400,body:{error:{code:131049}}},'fallido'],
      [{statusCode:503,body:{}},'desconocido'],
      [{error:'timeout sintético'},'desconocido']
    ]) {
      const result = run(response, lookup)[0].json;
      if (result.estado !== expected || result.modo !== 'prueba' || result.intento_id !== 'ficticio') throw Error('Contrato de resultado incorrecto');
    }
  }
  console.log('PASS: ' + expressions + ' expresiones compiladas' + (wa ? '; destinatario, idioma y 4 resultados verificados offline.' : ' (sin flujo de WhatsApp en la entrada).'));
});