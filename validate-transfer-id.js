/**
 * 🔍 VALIDACIÓN DE TRANSFER_ID
 * Ejemplos prácticos de normalización y validación
 */

// ============================================
// FUNCIÓN DE NORMALIZACIÓN (implementada en n8n)
// ============================================

function normalizeTransferId(rawId) {
  if (!rawId) return '';
  
  const text = String(rawId).trim();
  // Extraer solo números
  const numbersOnly = text.replace(/[^0-9]/g, '');
  // Remover ceros a la izquierda (parseInt + toString)
  const normalized = String(parseInt(numbersOnly, 10) || numbersOnly).trim();
  
  return normalized;
}

// ============================================
// EJEMPLOS DE VALIDACIÓN
// ============================================

console.log('=== CASOS DE NORMALIZACIÓN ===\n');

// CASO 1: Número con ceros a la izquierda
const case1 = normalizeTransferId('000224080048');
console.log(`Input:  '000224080048'`);
console.log(`Output: '${case1}'`);
console.log(`✅ Resultado: ${case1 === '224080048' ? 'CORRECTO' : 'ERROR'}\n`);

// CASO 2: Número sin ceros
const case2 = normalizeTransferId('224080048');
console.log(`Input:  '224080048'`);
console.log(`Output: '${case2}'`);
console.log(`✅ Resultado: ${case2 === '224080048' ? 'CORRECTO' : 'ERROR'}\n`);

// CASO 3: Número con múltiples ceros
const case3 = normalizeTransferId('00089765432');
console.log(`Input:  '00089765432'`);
console.log(`Output: '${case3}'`);
console.log(`✅ Resultado: ${case3 === '89765432' ? 'CORRECTO' : 'ERROR'}\n`);

// CASO 4: Con caracteres especiales
const case4 = normalizeTransferId('REF-000224080048');
console.log(`Input:  'REF-000224080048'`);
console.log(`Output: '${case4}'`);
console.log(`✅ Resultado: ${case4 === '224080048' ? 'CORRECTO' : 'ERROR'}\n`);

// CASO 5: Con guiones y puntos
const case5 = normalizeTransferId('00-0224-080048');
console.log(`Input:  '00-0224-080048'`);
console.log(`Output: '${case5}'`);
console.log(`✅ Resultado: ${case5 === '224080048' ? 'CORRECTO' : 'ERROR'}\n`);

// CASO 6: Solo ceros
const case6 = normalizeTransferId('000000000');
console.log(`Input:  '000000000'`);
console.log(`Output: '${case6}'`);
console.log(`✅ Resultado: ${case6 === '0' ? 'CORRECTO' : 'ERROR'}\n`);

// CASO 7: Vacío
const case7 = normalizeTransferId('');
console.log(`Input:  ''`);
console.log(`Output: '${case7}'`);
console.log(`✅ Resultado: ${case7 === '' ? 'CORRECTO' : 'ERROR'}\n`);

// CASO 8: null/undefined
const case8 = normalizeTransferId(null);
console.log(`Input:  null`);
console.log(`Output: '${case8}'`);
console.log(`✅ Resultado: ${case8 === '' ? 'CORRECTO' : 'ERROR'}\n`);

// ============================================
// VALIDACIÓN DE CUENTAS (sin ceros)
// ============================================

console.log('\n=== VALIDACIÓN DE CUENTAS BANCARIAS ===\n');

function validateBankAccount(accountFromProof, authorizedAccounts) {
  // Normalizar la cuenta del comprobante
  const proofAccount = normalizeTransferId(accountFromProof);
  
  // Normalizar todas las cuentas autorizadas
  const normalizedAuthorized = authorizedAccounts.map(acc => 
    normalizeTransferId(acc)
  );
  
  // Verificar si coincide
  const isValid = normalizedAuthorized.includes(proofAccount);
  
  return {
    proofAccount,
    normalizedAuthorized,
    isValid
  };
}

// Test 1: Cuenta exacta
const test1 = validateBankAccount('000224080048', ['0224080048', '0987654321']);
console.log('Test 1: Cuenta con múltiples ceros vs cuenta autorizada');
console.log(`Comprobante: ${test1.proofAccount}`);
console.log(`Autorizadas: ${test1.normalizedAuthorized.join(', ')}`);
console.log(`✅ ¿Válida? ${test1.isValid ? 'SÍ' : 'NO'}\n`);

// Test 2: Últimos 4 dígitos
const test2 = validateBankAccount('***80048', ['0224080048']);
console.log('Test 2: Últimos 4 dígitos del comprobante');
console.log(`Comprobante: ${test2.proofAccount}`);
console.log(`Autorizadas: ${test2.normalizedAuthorized.join(', ')}`);
console.log(`✅ Coincide últimos 4: ${test2.proofAccount === '80048' && '0224080048'.endsWith('80048') ? 'SÍ' : 'NO'}\n`);

// Test 3: Múltiples cuentas
const test3 = validateBankAccount('000087654321', ['0224080048', '000087654321', '0999888777']);
console.log('Test 3: Cuenta entre múltiples opciones');
console.log(`Comprobante: ${test3.proofAccount}`);
console.log(`Autorizadas: ${test3.normalizedAuthorized.join(', ')}`);
console.log(`✅ ¿Válida? ${test3.isValid ? 'SÍ' : 'NO'}\n`);

// ============================================
// RESUMEN DE CAMBIOS EN EL WORKFLOW
// ============================================

console.log('\n=== FLUJO EN N8N ===\n');
console.log('1️⃣  GPT4 Vision Validate:');
console.log('   - Extrae transfer_id del comprobante');
console.log('   - Lo incluye en la respuesta JSON');
console.log('   - Ejemplo: transfer_id: "000224080048"\n');

console.log('2️⃣  Process Validation Result:');
console.log('   - Lee transfer_id de GPT4');
console.log('   - Normaliza: 000224080048 → 224080048');
console.log('   - Guarda como: transfer_id_normalized\n');

console.log('3️⃣  Prepare Confirmed Data:');
console.log('   - Obtiene transfer_id normalizado');
console.log('   - Lo incluye en payload: transfer_id: "224080048"\n');

console.log('4️⃣  Save Confirmed Appointment:');
console.log('   - Envía a Google Sheets');
console.log('   - Se asigna a columna Y\n');

console.log('5️⃣  Google Sheets "Citas":');
console.log('   - Columna Y: transfer_id');
console.log('   - Valor: 224080048 (sin ceros)\n');
