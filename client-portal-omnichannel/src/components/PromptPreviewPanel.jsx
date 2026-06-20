import { useState, useEffect, useMemo } from 'react';
import { Loader2, Eye, Copy, Check, FileText, ChevronDown, ChevronUp } from 'lucide-react';
import { getPromptConfigs, getPromptConfig, getChannels, getIsAdmin, isSupervisorOrAdmin, getAgentsByChannel } from '../api';

const DAY_NAMES = { '1': 'Lunes', '2': 'Martes', '3': 'Miércoles', '4': 'Jueves', '5': 'Viernes', '6': 'Sábado', '7': 'Domingo' };

function formatDays(daysStr) {
  if (!daysStr) return '';
  return daysStr.split(',').map(d => DAY_NAMES[d.trim()] || d).join(', ');
}

function buildFullPrompt(pd, agents = []) {
  if (!pd) return '(Sin datos de prompt configurados)';

  const enabled = pd.secciones_habilitadas || {};
  const lines = [];

  // Header
  lines.push(`Eres ${pd.nombre_asistente || 'un asistente virtual'}, asistente virtual de ${pd.nombre_negocio || '[Negocio]'}.`);
  if (pd.emoji_principal) lines.push(`Tu emoji principal es ${pd.emoji_principal}`);
  if (pd.funcion_asistente) lines.push(`\nTu función: ${pd.funcion_asistente}`);
  if (pd.tono) lines.push(`Tono: ${pd.tono}`);
  if (pd.idioma) lines.push(`Idioma: ${pd.idioma}`);
  if (pd.max_parrafos) lines.push(`Máximo ${pd.max_parrafos} párrafos por respuesta.`);
  if (pd.emojis) lines.push(`Emojis permitidos: ${pd.emojis}`);

  // Business info
  const bizFields = [
    ['Teléfono', pd.negocio_telefono],
    ['Dirección', pd.negocio_direccion],
    ['Horario', pd.horario],
    ['Email', pd.negocio_email],
    ['Web', pd.negocio_sitio_web],
    ['Instagram', pd.negocio_instagram],
    ['Facebook', pd.negocio_facebook],
    ['TikTok', pd.negocio_tiktok],
    ['Google Maps', pd.negocio_enlace_maps],
  ].filter(([, v]) => v);
  if (bizFields.length) {
    lines.push('\n--- INFORMACIÓN DEL NEGOCIO ---');
    bizFields.forEach(([label, val]) => lines.push(`${label}: ${val}`));
  }

  // Messages
  if (enabled.mensajes !== false) {
    const msgFields = [
      ['Saludo', pd.saludo],
      ['Respuesta al agendar', pd.respuesta_agendar],
      ['Respuesta al cancelar', pd.respuesta_cancelar],
      ['Respuesta escalación', pd.respuesta_escalacion],
      ['Mensaje fuera de horario', pd.mensaje_fuera_horario],
      ['Mensaje despedida', pd.mensaje_despedida],
    ].filter(([, v]) => v);
    if (msgFields.length) {
      lines.push('\n--- MENSAJES PREDEFINIDOS ---');
      msgFields.forEach(([label, val]) => lines.push(`${label}: ${val}`));
    }
  }

  // Services
  if (enabled.servicios !== false) {
    const svcParts = [];
    if (pd.categorias_servicios) svcParts.push(`Categorías:\n${pd.categorias_servicios}`);
    if (pd.info_servicios) svcParts.push(`Info: ${pd.info_servicios}`);
    if (pd.catalogo_servicios_detallado) svcParts.push(`Catálogo detallado: ${pd.catalogo_servicios_detallado}`);
    if (pd.info_tecnica) svcParts.push(`Info técnica: ${pd.info_tecnica}`);
    if (pd.duracion_servicios) svcParts.push(`Duración: ${pd.duracion_servicios}`);
    if (pd.requerimientos) svcParts.push(`Requerimientos: ${pd.requerimientos}`);
    if (svcParts.length) {
      lines.push('\n--- SERVICIOS ---');
      lines.push(svcParts.join('\n'));
    }
  }

  // Products
  if (enabled.productos !== false) {
    const prodParts = [];
    if (pd.catalogo_productos) prodParts.push(`Catálogo: ${pd.catalogo_productos}`);
    if (pd.categorias_productos) prodParts.push(`Categorías: ${pd.categorias_productos}`);
    if (pd.info_despacho) prodParts.push(`Despacho: ${pd.info_despacho}`);
    if (pd.politica_devolucion) prodParts.push(`Devoluciones: ${pd.politica_devolucion}`);
    if (prodParts.length) {
      lines.push('\n--- PRODUCTOS ---');
      lines.push(prodParts.join('\n'));
    }
  }

  // Schedule
  if (enabled.agenda !== false) {
    const agParts = [];
    if (pd.horario_inicio && pd.horario_fin) agParts.push(`Horario: ${pd.horario_inicio} a ${pd.horario_fin}`);
    if (pd.dias_habiles) agParts.push(`Días hábiles: ${pd.dias_habiles}`);
    if (pd.intervalo_slots) agParts.push(`Intervalo slots: ${pd.intervalo_slots} min`);
    if (pd.buffer_entre_citas) agParts.push(`Buffer entre citas: ${pd.buffer_entre_citas} min`);
    if (pd.moneda_codigo) agParts.push(`Moneda: ${pd.moneda_simbolo || '$'} ${pd.moneda_nombre || pd.moneda_codigo}`);
    if (pd.bloqueos_horario) agParts.push(`Bloqueos: ${pd.bloqueos_horario}`);
    if (agParts.length) {
      lines.push('\n--- AGENDA Y RESERVAS ---');
      lines.push(agParts.join('\n'));
    }
  }

  // Payments
  if (enabled.pagos !== false) {
    const payParts = [];
    if (pd.condiciones_agendamiento) payParts.push(`Condiciones: ${pd.condiciones_agendamiento}`);
    if (pd.pago_abono) payParts.push(`Abono: ${pd.pago_abono}`);
    if (pd.metodos_pago) payParts.push(`Métodos: ${pd.metodos_pago}`);
    if (pd.negocio_cuenta_bancaria) payParts.push(`Cuenta bancaria: ${pd.negocio_cuenta_bancaria}`);
    if (pd.condiciones_reembolso) payParts.push(`Reembolso: ${pd.condiciones_reembolso}`);
    if (payParts.length) {
      lines.push('\n--- PAGOS ---');
      lines.push(payParts.join('\n'));
    }
  }

  // FAQ
  if (enabled.faq !== false) {
    const faqParts = [];
    if (pd.faq_contenido) faqParts.push(pd.faq_contenido);
    if (pd.info_adicional) faqParts.push(`Info adicional: ${pd.info_adicional}`);
    if (pd.politicas_generales) faqParts.push(`Políticas: ${pd.politicas_generales}`);
    if (faqParts.length) {
      lines.push('\n--- PREGUNTAS FRECUENTES ---');
      lines.push(faqParts.join('\n'));
    }
  }

  // Escalation
  if (enabled.escalacion !== false) {
    const escParts = [];
    if (pd.escalacion_mensaje) escParts.push(`Mensaje: ${pd.escalacion_mensaje}`);
    if (pd.escalacion_instrucciones) escParts.push(`Instrucciones: ${pd.escalacion_instrucciones}`);
    if (agents.length > 0) {
      escParts.push('\nAgentes disponibles para derivación:');
      agents.forEach(a => {
        let agLine = `- ${a.name || `Agente #${a.agent_id}`}`;
        if (a.area) agLine += ` | Área: ${a.area}`;
        if (a.es_defecto) agLine += ' | (DEFECTO)';
        if (a.schedule_start && a.schedule_end) {
          agLine += ` | Horario: ${a.schedule_start}-${a.schedule_end}`;
        }
        if (a.available_days) {
          agLine += ` | Días: ${formatDays(a.available_days)}`;
        }
        agLine += ` | Estado: ${a.status === 'active' ? 'Activo' : a.status === 'away' ? 'Ausente' : 'Inactivo'}`;
        escParts.push(agLine);
      });
    }
    if (escParts.length) {
      lines.push('\n--- ESCALACIÓN / DERIVACIÓN ---');
      lines.push(escParts.join('\n'));
    }
  }

  // Rules
  const rulesParts = [];
  if (pd.restricciones) rulesParts.push(`RESTRICCIONES (NO hacer):\n${pd.restricciones}`);
  if (pd.capacidades) rulesParts.push(`CAPACIDADES (SÍ puede):\n${pd.capacidades}`);
  if (pd.temas_prohibidos) rulesParts.push(`TEMAS PROHIBIDOS:\n${pd.temas_prohibidos}`);
  if (pd.ejemplo_conversacion) rulesParts.push(`EJEMPLO:\n${pd.ejemplo_conversacion}`);
  if (rulesParts.length) {
    lines.push('\n--- REGLAS Y CAPACIDADES ---');
    lines.push(rulesParts.join('\n\n'));
  }

  return lines.join('\n');
}

export default function PromptPreviewPanel() {
  const [configs, setConfigs] = useState([]);
  const [channels, setChannels] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedId, setSelectedId] = useState('');
  const [promptData, setPromptData] = useState(null);
  const [agents, setAgents] = useState([]);
  const [copied, setCopied] = useState(false);
  const [showRaw, setShowRaw] = useState(false);

  useEffect(() => {
    async function load() {
      setLoading(true);
      try {
        const [cfgs, chs] = await Promise.all([
          getPromptConfigs().catch(() => []),
          getChannels().catch(() => []),
        ]);
        setConfigs(Array.isArray(cfgs) ? cfgs : []);
        setChannels(Array.isArray(chs) ? chs : []);
        // Auto-select first
        if (Array.isArray(cfgs) && cfgs.length > 0) {
          handleSelect(cfgs[0].id, cfgs);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    }
    load();
  }, []);

  async function handleSelect(id, cfgList) {
    setSelectedId(id);
    const list = cfgList || configs;
    const cfg = list.find(c => String(c.id) === String(id));
    if (!cfg) { setPromptData(null); setAgents([]); return; }

    try {
      const full = await getPromptConfig(id);
      const pd = typeof full.prompt_data === 'string' ? JSON.parse(full.prompt_data) : (full.prompt_data || {});
      setPromptData(pd);

      // Load agents for escalation preview
      if (pd.agentes_escalacion?.length > 0 && cfg.channel_id) {
        const allAgents = await getAgentsByChannel(cfg.channel_id).catch(() => []);
        const agentsList = Array.isArray(allAgents) ? allAgents : [];
        const enriched = pd.agentes_escalacion.map(ea => {
          const info = agentsList.find(a => String(a.id) === String(ea.agent_id));
          return {
            ...ea,
            name: info?.name || '',
            department: info?.department || '',
            status: info?.status || 'inactive',
            schedule_start: info?.schedule_start || '',
            schedule_end: info?.schedule_end || '',
            available_days: info?.available_days || '',
          };
        });
        setAgents(enriched);
      } else {
        setAgents([]);
      }
    } catch {
      setPromptData(null);
      setAgents([]);
    }
  }

  const fullPrompt = useMemo(() => buildFullPrompt(promptData, agents), [promptData, agents]);

  function handleCopy() {
    navigator.clipboard.writeText(fullPrompt).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    });
  }

  const wordCount = fullPrompt.split(/\s+/).filter(Boolean).length;
  const charCount = fullPrompt.length;

  if (loading) {
    return (
      <div className="h-full flex items-center justify-center">
        <Loader2 className="animate-spin text-blue-500" size={32} />
      </div>
    );
  }

  return (
    <div className="h-full flex flex-col overflow-hidden">
      {/* Selector bar */}
      <div className="shrink-0 px-4 py-3 bg-gray-50 dark:bg-slate-800/50 border-b border-gray-100 dark:border-slate-700/60 flex flex-wrap items-center gap-3">
        <div className="flex items-center gap-2 flex-1 min-w-[200px]">
          <span className="w-9 h-9 rounded-2xl flex items-center justify-center bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-md shrink-0"><Eye size={16} /></span>
          <select aria-label="Seleccionar configuración"
            value={selectedId}
            onChange={e => handleSelect(e.target.value)}
            className="flex-1 px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 border-0 bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
          >
            <option value="">— Seleccionar configuración —</option>
            {configs.map(cfg => (
              <option key={cfg.id} value={cfg.id}>
                {cfg.config_name} ({cfg.channel_name || `Canal #${cfg.channel_id}`})
              </option>
            ))}
          </select>
        </div>
        <div className="flex items-center gap-2">
          <span className="text-[11px] text-gray-400 dark:text-slate-500 px-2 py-1 rounded-md bg-white dark:bg-slate-700/60 ring-1 ring-gray-100 dark:ring-slate-700">{wordCount} palabras · {charCount} caracteres</span>
          <button
            onClick={handleCopy}
            disabled={!promptData}
            className="flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 ring-1 ring-gray-200 dark:ring-slate-600 hover:bg-gray-50 dark:hover:bg-slate-600 disabled:opacity-40 transition-colors"
          >
            {copied ? <Check size={13} className="text-emerald-500" /> : <Copy size={13} />}
            {copied ? 'Copiado' : 'Copiar'}
          </button>
          <button
            onClick={() => setShowRaw(!showRaw)}
            disabled={!promptData}
            className={`flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg disabled:opacity-40 transition-all duration-300 ${
              showRaw
                ? 'bg-gradient-to-br from-blue-400 to-blue-600 text-white shadow-md'
                : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 ring-1 ring-gray-200 dark:ring-slate-600 hover:bg-gray-50 dark:hover:bg-slate-600'
            }`}
          >
            <FileText size={13} />
            {showRaw ? 'Formateado' : 'JSON Raw'}
          </button>
        </div>
      </div>

      {/* Preview content */}
      <div className="flex-1 overflow-y-auto custom-scrollbar p-4">
        {!promptData ? (
          <div className="flex items-center justify-center h-full">
            <div className="text-center">
              <div className="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center bg-sky-50 text-sky-500 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/20">
                <Eye size={30} />
              </div>
              <p className="text-sm font-medium text-gray-600 dark:text-slate-300" style={{ fontFamily: 'Poppins, sans-serif' }}>Selecciona una configuración para ver la vista previa del prompt</p>
              <p className="text-xs mt-1 text-gray-400 dark:text-slate-500">El prompt final es la concatenación de todas las secciones activas</p>
            </div>
          </div>
        ) : showRaw ? (
          <pre className="text-xs font-mono bg-slate-50 dark:bg-slate-900 ring-1 ring-gray-100 dark:ring-slate-700/60 rounded-2xl p-4 whitespace-pre-wrap break-words text-slate-700 dark:text-slate-300 leading-relaxed shadow-sm">
            {JSON.stringify(promptData, null, 2)}
          </pre>
        ) : (
          <div className="max-w-3xl mx-auto">
            <div className="bg-white dark:bg-slate-800 ring-1 ring-gray-100 dark:ring-slate-700/60 rounded-2xl overflow-hidden shadow-md hover:shadow-xl transition-all duration-300">
              <div className="px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white border-b border-blue-400/20 flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <div className="w-7 h-7 rounded-xl bg-white/15 flex items-center justify-center text-white text-[10px] font-bold shadow-sm ring-1 ring-white/25">AI</div>
                  <span className="text-sm font-bold" style={{ fontFamily: 'Poppins, sans-serif' }}>System Prompt Final</span>
                </div>
                <span className="text-[10px] font-semibold text-white px-2.5 py-0.5 rounded-full bg-white/15 ring-1 ring-white/25">
                  v{configs.find(c => String(c.id) === String(selectedId))?.version || '?'}
                </span>
              </div>
              <pre className="p-4 sm:p-5 text-sm font-mono whitespace-pre-wrap break-words text-slate-700 dark:text-slate-300 leading-relaxed">
                {fullPrompt}
              </pre>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
