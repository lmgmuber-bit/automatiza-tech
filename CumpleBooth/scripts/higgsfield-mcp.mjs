/**
 * higgsfield-mcp.mjs — cliente mínimo del MCP de Higgsfield.
 *
 * Cómo se autentica (verificado 2026-08-16):
 *  - El endpoint https://mcp.higgsfield.ai/mcp acepta la API key de
 *    Higgsfield como `Authorization: Bearer <Api Key Secret>`.
 *  - La lee en runtime del archivo de claves que vive FUERA del repo
 *    (OneDrive, ver AT-ORCHESTRATION en AGENTS.md). Jamás la imprime,
 *    persiste ni copia a ningún archivo.
 *  - El token OAuth del conector de Claude Code está expirado hoy (401) —
 *    por eso se usa la API key; si algún día se renueva, se puede volver a él.
 *
 *  - Uso:
 *      node scripts/higgsfield-mcp.mjs tools/list
 *      node scripts/higgsfield-mcp.mjs call <herramienta> '<json-args>'
 *      node scripts/higgsfield-mcp.mjs balance        (atajo de crédito)
 */
import { readFileSync } from 'node:fs'

const ENDPOINT = 'https://mcp.higgsfield.ai/mcp'
const ARCHIVO_CLAVES = 'C:\\Users\\luis_\\OneDrive\\Documentos\\APIS KEy\\APIS KEY.txt'

function cargarToken() {
  const lineas = readFileSync(ARCHIVO_CLAVES, 'utf8').split('\n')
  // El patrón (Secret o ID) se elige por variable de entorno; por defecto Secret.
  const patron = process.env.HF_KEY_FIELD === 'id' ? /Api\s*KEY\s*ID\s*=\s*(\S+)/i : /Api\s*KEy\s*Secret\s*=\s*(\S+)/i
  for (const l of lineas) {
    const m = l.match(patron)
    if (m) return m[1].trim()
  }
  throw new Error('No se encontró la credencial en el archivo de claves externo')
}

const TOKEN = cargarToken()
let sessionId = null

/** Parsea respuestas JSON directas o SSE (`data: {...}` por línea). */
async function leerRespuesta(res) {
  const tipo = res.headers.get('content-type') || ''
  const texto = await res.text()
  if (tipo.includes('text/event-stream')) {
    const mensajes = []
    for (const linea of texto.split('\n')) {
      if (linea.startsWith('data:')) {
        try { mensajes.push(JSON.parse(linea.slice(5).trim())) } catch { /* keep-alive */ }
      }
    }
    return mensajes
  }
  try { return [JSON.parse(texto)] } catch { return [{ raw: texto }] }
}

async function rpc(method, params = {}, id = 1, notificacion = false) {
  const headers = {
    'content-type': 'application/json',
    'accept': 'application/json, text/event-stream',
    'authorization': `Bearer ${TOKEN}`,
  }
  if (sessionId) headers['mcp-session-id'] = sessionId
  const body = { jsonrpc: '2.0', method, params }
  if (!notificacion) body.id = id
  const res = await fetch(ENDPOINT, { method: 'POST', headers, body: JSON.stringify(body) })
  if (!sessionId) {
    const sid = res.headers.get('mcp-session-id')
    if (sid) sessionId = sid
  }
  if (res.status === 401 || res.status === 403) {
    throw new Error(`MCP respondió ${res.status}: el token OAuth cacheado expiró o fue revocado. Hay que reautorizar el conector Higgsfield en Claude Code (o pedirle a Luis que lo haga) y reintentar.`)
  }
  if (!res.ok) {
    const detalle = await res.text().catch(() => '')
    throw new Error(`MCP HTTP ${res.status}: ${detalle.slice(0, 300)}`)
  }
  return leerRespuesta(res)
}

async function main() {
  const [, , comando, herramienta, argsJson] = process.argv

  const init = await rpc('initialize', {
    protocolVersion: '2025-03-26',
    capabilities: {},
    clientInfo: { name: 'cumpleclick-opencode-reuse', version: '1.0.0' },
  })
  const serverInfo = init[0]?.result?.serverInfo
  console.log(`# conectado: ${serverInfo?.name || 'mcp.higgsfield.ai'} ${serverInfo?.version || ''}`.trim())
  await rpc('notifications/initialized', {}, 0, true)

  if (comando === 'tools/list') {
    const r = await rpc('tools/list', {})
    const tools = r[0]?.result?.tools || []
    console.log(`# ${tools.length} herramientas:`)
    for (const t of tools) console.log(`- ${t.name}: ${(t.description || '').split('\n')[0].slice(0, 110)}`)
    return
  }

  if (comando === 'call' && herramienta) {
    const args = argsJson ? JSON.parse(argsJson) : {}
    const r = await rpc('tools/call', { name: herramienta, arguments: args })
    const resultado = r[0]?.result
    if (resultado?.isError) {
      console.log('# isError:')
    }
    for (const parte of resultado?.content || []) {
      if (parte.type === 'text') console.log(parte.text)
      else console.log(`[${parte.type}]`)
    }
    if (!resultado) console.log(JSON.stringify(r[0], null, 2).slice(0, 3000))
    return
  }

  if (comando === 'balance') {
    const lista = await rpc('tools/list', {})
    const tools = (lista[0]?.result?.tools || []).map((t) => t.name)
    const candidata = ['get_credit_balance', 'show_plans_and_credits', 'credit_balance', 'get_balance']
      .find((n) => tools.includes(n))
    if (!candidata) {
      console.log('# no hay herramienta de balance; disponibles:', tools.join(', '))
      return
    }
    const r = await rpc('tools/call', { name: candidata, arguments: {} })
    for (const parte of r[0]?.result?.content || []) {
      if (parte.type === 'text') console.log(parte.text)
    }
    return
  }

  console.log('Uso: tools/list | call <herramienta> <json> | balance')
}

main().catch((e) => { console.error('Fatal:', e.message); process.exit(1) })
