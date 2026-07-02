import { useState, useEffect, useRef } from 'react';
import { Loader2, Save, Plus, Trash2, FileText, ChevronDown, ChevronUp, ChevronLeft, Eye, Pencil, Power, AlertTriangle, Upload, X, Copy, FileUp, ToggleLeft, ToggleRight, UserPlus, Phone, Mail, Building2 } from 'lucide-react';
import { getPromptConfigs, getPromptConfig, createPromptConfig, updatePromptConfig, deletePromptConfig, getChannels, getIsAdmin, getIsAgent, isSupervisorOrAdmin, getAgentsByChannel, getAgents } from '../api';
import ResultModal from './ResultModal';
import ConfirmDeleteModal from './ConfirmDeleteModal';

// Secciones que siempre están habilitadas (no se pueden desactivar)
const ALWAYS_ON_SECTIONS = ['negocio', 'asistente', 'reglas'];

// Estructura de campos del prompt, agrupados por sección
const PROMPT_SECTIONS = [
  {
    id: 'negocio',
    label: '🏢 Información del Negocio',
    description: 'Datos básicos del negocio: nombre, contacto, redes sociales y horario.',
    fields: [
      { key: 'nombre_negocio', label: 'Nombre del Negocio', type: 'text', placeholder: 'Ej: Kellscapilar' },
      { key: 'negocio_telefono', label: 'Teléfono', type: 'text', placeholder: '+56 9 12345678' },
      { key: 'negocio_direccion', label: 'Dirección', type: 'text', placeholder: 'Calle 123, Ciudad' },
      { key: 'negocio_instagram', label: 'Instagram', type: 'text', placeholder: '@negocio' },
      { key: 'negocio_facebook', label: 'Facebook', type: 'text', placeholder: 'facebook.com/negocio' },
      { key: 'negocio_tiktok', label: 'TikTok', type: 'text', placeholder: '@negocio' },
      { key: 'negocio_enlace_maps', label: 'Enlace Google Maps', type: 'text', placeholder: 'https://maps.app.goo.gl/...' },
      { key: 'negocio_sitio_web', label: 'Sitio Web', type: 'text', placeholder: 'https://www.negocio.cl' },
      { key: 'negocio_email', label: 'Email de Contacto', type: 'text', placeholder: 'contacto@negocio.cl' },
      { key: 'horario', label: 'Horario de Atención', type: 'text', placeholder: 'Lunes a Viernes 10:00 - 18:00' },
    ],
  },
  {
    id: 'asistente',
    label: '🤖 Configuración del Asistente',
    description: 'Personalidad, tono y comportamiento del bot.',
    fields: [
      { key: 'nombre_asistente', label: 'Nombre del Asistente', type: 'text', placeholder: 'Ej: Kells' },
      { key: 'emoji_principal', label: 'Emoji Principal', type: 'text', placeholder: '💇' },
      { key: 'emojis', label: 'Emojis Permitidos', type: 'text', placeholder: '💇✨💆🏻‍♀️💈🪮✅📅' },
      { key: 'tono', label: 'Tono de la Conversación', type: 'text', placeholder: 'cálido y profesional' },
      { key: 'max_parrafos', label: 'Máx. Párrafos por Respuesta', type: 'text', placeholder: '2-3' },
      { key: 'funcion_asistente', label: 'Función del Asistente', type: 'textarea', placeholder: 'Dar información sobre servicios, coordinar citas...' },
      { key: 'idioma', label: 'Idioma Principal', type: 'text', placeholder: 'Español' },
    ],
  },
  {
    id: 'mensajes',
    label: '💬 Mensajes Predefinidos',
    description: 'Mensajes automáticos: saludo, respuestas de agendamiento, cancelación y derivación a humano.',
    fields: [
      { key: 'saludo', label: 'Saludo Inicial', type: 'textarea', placeholder: 'Hola, soy... asistente virtual de...' },
      { key: 'respuesta_agendar', label: 'Respuesta al Agendar', type: 'textarea', placeholder: '¡Perfecto! Te muestro los servicios...' },
      { key: 'respuesta_cancelar', label: 'Respuesta al Cancelar', type: 'textarea', placeholder: 'Entiendo. Buscaré tu cita...' },
      { key: 'respuesta_escalacion', label: 'Respuesta de Escalación (derivar a humano)', type: 'textarea', placeholder: 'En los próximos minutos una persona te contactará...' },
      { key: 'mensaje_fuera_horario', label: 'Mensaje Fuera de Horario', type: 'textarea', placeholder: 'Gracias por escribirnos. Nuestro horario es...' },
      { key: 'mensaje_despedida', label: 'Mensaje de Despedida', type: 'textarea', placeholder: '¡Gracias por contactarnos! Que tengas un excelente día.' },
    ],
  },
  {
    id: 'servicios',
    label: '📋 Servicios e Información',
    description: 'Catálogo de servicios o productos con precios, duraciones y categorías. Actívalo si el negocio ofrece servicios diferenciados.',
    fields: [
      { key: 'categorias_servicios', label: 'Categorías de Servicios', type: 'textarea', placeholder: '💇 Alisados\n💆 Botox Capilar\n✂️ Cortes...' },
      { key: 'info_servicios', label: 'Información General de Servicios', type: 'textarea', placeholder: 'Tenemos X servicios. Precios desde...' },
      { key: 'catalogo_servicios_detallado', label: 'Catálogo de Servicios (JSON array)', type: 'textarea', placeholder: '[{"id":1,"nombre":"Alisado Corto","duracion_min":120,"precio":45000,"descripcion":"..."}]' },
      { key: 'info_tecnica', label: 'Información Técnica', type: 'textarea', placeholder: 'Descripción técnica de los servicios...' },
      { key: 'duracion_servicios', label: 'Duración de Servicios', type: 'text', placeholder: 'Entre 30 min a 3 horas' },
      { key: 'requerimientos', label: 'Requerimientos para el Cliente', type: 'textarea', placeholder: '- El cliente debe llegar con cabello limpio...' },
    ],
  },
  {
    id: 'productos',
    label: '🛒 Productos y Catálogo',
    description: 'Para negocios que venden productos físicos o digitales. Incluye catálogo, stock y despacho.',
    fields: [
      { key: 'catalogo_productos', label: 'Catálogo de Productos (JSON o texto)', type: 'textarea', placeholder: '[{"nombre":"Shampoo Reparador","precio":12000,"stock":true}]' },
      { key: 'categorias_productos', label: 'Categorías de Productos', type: 'textarea', placeholder: '🧴 Cuidado capilar\n💄 Maquillaje\n🧖 Skincare...' },
      { key: 'info_despacho', label: 'Información de Despacho/Envío', type: 'textarea', placeholder: 'Envío a todo Chile. Despacho en 24-48h hábiles...' },
      { key: 'politica_devolucion', label: 'Política de Devoluciones', type: 'textarea', placeholder: 'Aceptamos devoluciones dentro de 7 días...' },
    ],
  },
  {
    id: 'agenda',
    label: '📅 Agenda y Reservas',
    description: 'Configuración de citas: horarios, slots, bloqueos. Actívalo solo si el negocio trabaja con agendamiento.',
    fields: [
      { key: 'horario_inicio', label: 'Hora de Apertura', type: 'text', placeholder: '9:00' },
      { key: 'horario_fin', label: 'Hora de Cierre', type: 'text', placeholder: '18:00' },
      { key: 'dias_habiles', label: 'Días Hábiles (0=Dom,1=Lun,...,6=Sáb)', type: 'text', placeholder: '1,2,3,4,5' },
      { key: 'intervalo_slots', label: 'Intervalo entre Slots (minutos)', type: 'text', placeholder: '60' },
      { key: 'buffer_entre_citas', label: 'Buffer entre Citas (minutos)', type: 'text', placeholder: '10' },
      { key: 'moneda_codigo', label: 'Código Moneda (ISO)', type: 'text', placeholder: 'CLP' },
      { key: 'moneda_simbolo', label: 'Símbolo Moneda', type: 'text', placeholder: '$' },
      { key: 'moneda_nombre', label: 'Nombre Moneda', type: 'text', placeholder: 'Pesos Chilenos' },
      { key: 'bloqueos_horario', label: 'Bloqueos de Horario (JSON array)', type: 'textarea', placeholder: '[{"fecha":"*","hora_inicio":"13:00","hora_fin":"14:00","motivo":"Almuerzo","recurrente":"diario"}]' },
    ],
  },
  {
    id: 'pagos',
    label: '💰 Pagos y Condiciones',
    description: 'Métodos de pago, abonos y condiciones de reserva. Desactívalo si el negocio no cobra anticipos o no tiene condiciones especiales.',
    fields: [
      { key: 'condiciones_agendamiento', label: 'Condiciones de Agendamiento', type: 'textarea', placeholder: 'Para hacer efectiva la reserva se requiere...' },
      { key: 'condiciones_reembolso', label: 'Condiciones de Reembolso', type: 'textarea', placeholder: 'El monto no es reembolsable si...' },
      { key: 'pago_abono', label: 'Monto de Abono', type: 'text', placeholder: '20000' },
      { key: 'metodos_pago', label: 'Métodos de Pago Aceptados', type: 'textarea', placeholder: 'Transferencia bancaria, Efectivo, Tarjeta en local...' },
      { key: 'negocio_cuenta_bancaria', label: 'Cuenta Bancaria Principal', type: 'textarea', placeholder: 'Nombre\nRUT\nBanco\nTipo cuenta\nN° cuenta\nEmail' },
      { key: 'negocio_cuenta_bancaria2', label: 'Cuenta Bancaria Alternativa', type: 'textarea', placeholder: 'Otra cuenta (0 si no aplica)' },
    ],
  },
  {
    id: 'faq',
    label: '❓ Preguntas Frecuentes',
    description: 'Respuestas predefinidas a preguntas comunes. Ideal para negocios de atención al público, soporte o consultas.',
    fields: [
      { key: 'faq_contenido', label: 'Preguntas y Respuestas', type: 'textarea', placeholder: 'P: ¿Cuál es el horario?\nR: Atendemos de lunes a viernes 9-18h.\n\nP: ¿Tienen estacionamiento?\nR: Sí, contamos con estacionamiento gratuito.' },
      { key: 'info_adicional', label: 'Información Adicional del Negocio', type: 'textarea', placeholder: 'Información relevante que el bot debe conocer...' },
      { key: 'politicas_generales', label: 'Políticas Generales', type: 'textarea', placeholder: 'Política de privacidad, términos de servicio, etc.' },
    ],
  },
  {
    id: 'escalacion',
    label: '🔀 Escalación / Derivación',
    description: 'Configura a qué agente humano derivar cuando el bot no puede resolver. Define áreas y el agente responsable de cada una.',
    hasCustomUI: true,
    fields: [
      { key: 'escalacion_mensaje', label: 'Mensaje al Derivar', type: 'textarea', placeholder: 'Te voy a derivar con {agente} del área de {area} para que te ayude mejor...' },
      { key: 'escalacion_instrucciones', label: 'Instrucciones para el Bot', type: 'textarea', placeholder: 'Cuando no puedas resolver la consulta, identifica el área y deriva al agente correspondiente. Si no hay agente para esa área, usa el agente por defecto.' },
    ],
  },
  {
    id: 'reglas',
    label: '⚠️ Reglas y Capacidades',
    description: 'Límites, restricciones y capacidades del bot. Define qué puede y qué no puede hacer.',
    fields: [
      { key: 'restricciones', label: 'Restricciones (lo que NO debe hacer)', type: 'textarea', placeholder: '- No dar paso a paso técnico\n- No prometer resultados...' },
      { key: 'capacidades', label: 'Capacidades (lo que SÍ puede hacer)', type: 'textarea', placeholder: '- Explicar tratamientos\n- Informar precios...' },
      { key: 'ejemplo_conversacion', label: 'Ejemplo de Conversación', type: 'textarea', placeholder: 'Cliente: "Necesito alisar mi cabello"\nBot: "¡Hola!..."' },
      { key: 'temas_prohibidos', label: 'Temas Prohibidos', type: 'textarea', placeholder: '- Política\n- Religión\n- Competencia directa...' },
    ],
  },
];

function getAllKeys() {
  const keys = [];
  PROMPT_SECTIONS.forEach(s => s.fields.forEach(f => keys.push(f.key)));
  return keys;
}

function defaultSeccionesHabilitadas() {
  const sh = {};
  PROMPT_SECTIONS.forEach(s => { sh[s.id] = ALWAYS_ON_SECTIONS.includes(s.id) ? true : true; });
  return sh;
}

function emptyPromptData() {
  const data = {};
  getAllKeys().forEach(k => { data[k] = ''; });
  data.secciones_habilitadas = defaultSeccionesHabilitadas();
  data.agentes_escalacion = [];
  return data;
}

const EMPTY_AGENT = { agent_id: '', area: '', es_defecto: false };

// Import de demos: workflow n8n que lee el Google Sheet de una demo registrada
// (valida contra el registro "Demos"; solo sheets de demos activas son legibles)
const DEMO_IMPORT_URL = 'https://n8n-n8n.kchiba.easypanel.host/webhook/import-demo-config';
const DEMO_IMPORT_TOKEN = 'AT2026import';

/** Parse CSV with parametro;valor format (semicolon separator, quoted multiline values) */
function parseCsvToPromptData(csvText) {
  const validKeys = new Set(getAllKeys());
  const result = {};
  // Normalize line endings
  const text = csvText.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
  const lines = text.split('\n');
  let currentKey = null;
  let currentValue = '';
  let inQuotes = false;

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];

    if (inQuotes) {
      // Check if this line closes the quote
      if (line.includes('"')) {
        const idx = line.indexOf('"');
        currentValue += '\n' + line.substring(0, idx);
        inQuotes = false;
        const trimmedKey = currentKey?.trim();
        if (trimmedKey && validKeys.has(trimmedKey)) {
          result[trimmedKey] = currentValue.trim();
        }
        currentKey = null;
        currentValue = '';
      } else {
        currentValue += '\n' + line;
      }
      continue;
    }

    // Skip empty lines or header
    if (!line.trim() || line.toLowerCase().startsWith('parametro;')) continue;

    const sepIdx = line.indexOf(';');
    if (sepIdx === -1) continue;

    const key = line.substring(0, sepIdx).trim();
    let val = line.substring(sepIdx + 1);

    if (!key) continue;

    // Check if value starts with a quote (multiline)
    if (val.trimStart().startsWith('"')) {
      val = val.trimStart().substring(1); // remove opening quote
      if (val.includes('"')) {
        // Closing quote on same line
        val = val.substring(0, val.lastIndexOf('"'));
        if (validKeys.has(key)) result[key] = val.trim();
      } else {
        // Multiline — keep reading
        currentKey = key;
        currentValue = val;
        inQuotes = true;
      }
    } else {
      if (validKeys.has(key)) result[key] = val.trim();
    }
  }

  return result;
}

export default function PromptsView() {
  const isAdmin = getIsAdmin();
  const isAgent = getIsAgent();
  const canEdit = isAdmin || !isAgent; // Admin AT o cliente (API key) pueden editar
  const canView = isAdmin || isSupervisorOrAdmin(); // Supervisor puede ver

  const [configs, setConfigs] = useState([]);
  const [channels, setChannels] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedId, setSelectedId] = useState(null);
  const [editMode, setEditMode] = useState(false);
  const [creating, setCreating] = useState(false);
  const [form, setForm] = useState({ config_name: '', channel_id: '', is_active: 1, prompt_data: emptyPromptData() });
  const [saving, setSaving] = useState(false);
  const [expandedSections, setExpandedSections] = useState({ negocio: true, asistente: true });
  const [resultModal, setResultModal] = useState(null);
  const [deleteModal, setDeleteModal] = useState(null);
  const [mobilePanel, setMobilePanel] = useState('list'); // 'list' | 'detail'
  const [importModal, setImportModal] = useState(false);
  const [importTab, setImportTab] = useState('config'); // 'config' | 'csv'
  const [importSourceId, setImportSourceId] = useState('');
  const [importDestChannelId, setImportDestChannelId] = useState('');
  const [importConfigName, setImportConfigName] = useState('');
  const [importLoading, setImportLoading] = useState(false);
  const [csvFileName, setCsvFileName] = useState('');
  const [csvParsedData, setCsvParsedData] = useState(null);
  const csvInputRef = useRef(null);
  const [demoList, setDemoList] = useState(null); // null = aún no cargada
  const [demoSheetId, setDemoSheetId] = useState('');
  const [availableAgents, setAvailableAgents] = useState([]);
  const [loadingAgents, setLoadingAgents] = useState(false);

  useEffect(() => { load(); }, []);

  async function load() {
    setLoading(true);
    try {
      // Load channels independently so a prompts error doesn't block them
      const chsPromise = getChannels().catch(() => []);
      const cfgsPromise = getPromptConfigs().catch(() => []);
      const [cfgs, chs] = await Promise.all([cfgsPromise, chsPromise]);
      setConfigs(Array.isArray(cfgs) ? cfgs : []);
      setChannels(Array.isArray(chs) ? chs : []);
    } catch (err) {
      console.error('Error loading prompt configs:', err);
    } finally {
      setLoading(false);
    }
  }

  async function loadAvailableAgents(channelId) {
    setLoadingAgents(true);
    try {
      const agents = channelId
        ? await getAgentsByChannel(channelId)
        : await getAgents();
      setAvailableAgents(Array.isArray(agents) ? agents.filter(a => a.status === 'active') : []);
    } catch {
      setAvailableAgents([]);
    } finally {
      setLoadingAgents(false);
    }
  }

  function selectConfig(cfg) {
    setSelectedId(cfg.id);
    setEditMode(false);
    setCreating(false);
    const pd = typeof cfg.prompt_data === 'string' ? JSON.parse(cfg.prompt_data) : (cfg.prompt_data || {});
    // Ensure secciones_habilitadas has defaults for all sections
    if (!pd.secciones_habilitadas) pd.secciones_habilitadas = defaultSeccionesHabilitadas();
    else pd.secciones_habilitadas = { ...defaultSeccionesHabilitadas(), ...pd.secciones_habilitadas };
    if (!Array.isArray(pd.agentes_escalacion)) pd.agentes_escalacion = [];
    setForm({
      config_name: cfg.config_name || '',
      channel_id: cfg.channel_id || '',
      is_active: cfg.is_active ?? 1,
      prompt_data: { ...emptyPromptData(), ...pd },
    });
    setExpandedSections({ negocio: true, asistente: true });
    setMobilePanel('detail');
    if (cfg.channel_id) loadAvailableAgents(cfg.channel_id);
  }

  function startCreate() {
    setSelectedId(null);
    setCreating(true);
    setEditMode(true);
    setForm({ config_name: '', channel_id: '', is_active: 1, prompt_data: emptyPromptData() });
    setExpandedSections({ negocio: true, asistente: true, mensajes: true });
    setMobilePanel('detail');
  }

  function startEdit() {
    setEditMode(true);
    setExpandedSections(prev => ({ ...prev, negocio: true, asistente: true }));
  }

  function updateField(key, value) {
    setForm(f => ({ ...f, prompt_data: { ...f.prompt_data, [key]: value } }));
  }

  async function handleSave() {
    if (!form.config_name.trim()) {
      setResultModal({ type: 'error', title: 'Error', message: 'El nombre de configuración es requerido.' });
      return;
    }
    if (!form.channel_id) {
      setResultModal({ type: 'error', title: 'Error', message: 'Selecciona un canal.' });
      return;
    }

    setSaving(true);
    try {
      const payload = {
        config_name: form.config_name,
        channel_id: parseInt(form.channel_id),
        is_active: form.is_active,
        prompt_data: form.prompt_data,
      };

      if (creating) {
        const res = await createPromptConfig(payload);
        if (res.error) throw new Error(res.error);
        setResultModal({ type: 'success', title: 'Creado', message: 'Configuración de prompt creada exitosamente.' });
        setCreating(false);
      } else {
        const res = await updatePromptConfig(selectedId, payload);
        if (res.error) throw new Error(res.error);
        setResultModal({ type: 'success', title: 'Guardado', message: `Configuración actualizada (v${res.version || '?'}).` });
      }
      setEditMode(false);
      await load();
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(id) {
    try {
      const res = await deletePromptConfig(id);
      if (res.error) throw new Error(res.error);
      setResultModal({ type: 'success', title: 'Eliminado', message: 'Configuración eliminada.' });
      setSelectedId(null);
      setEditMode(false);
      setCreating(false);
      await load();
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    }
  }

  function toggleSection(sectionId) {
    setExpandedSections(prev => ({ ...prev, [sectionId]: !prev[sectionId] }));
  }

  function isSectionEnabled(sectionId) {
    const sh = form.prompt_data?.secciones_habilitadas;
    if (!sh || sh[sectionId] === undefined) return true; // default enabled
    return !!sh[sectionId];
  }

  function toggleSectionEnabled(sectionId) {
    if (ALWAYS_ON_SECTIONS.includes(sectionId)) return; // core sections can't be disabled
    setForm(f => {
      const sh = { ...defaultSeccionesHabilitadas(), ...(f.prompt_data?.secciones_habilitadas || {}) };
      sh[sectionId] = !sh[sectionId];
      // If disabling, collapse the section
      if (!sh[sectionId]) {
        setExpandedSections(prev => ({ ...prev, [sectionId]: false }));
      }
      return { ...f, prompt_data: { ...f.prompt_data, secciones_habilitadas: sh } };
    });
  }

  // --- Escalation agents CRUD ---
  function getAgentes() {
    const ag = form.prompt_data?.agentes_escalacion;
    return Array.isArray(ag) ? ag : [];
  }

  function addAgente(agentId) {
    if (!agentId) return;
    setForm(f => {
      const list = Array.isArray(f.prompt_data.agentes_escalacion) ? f.prompt_data.agentes_escalacion : [];
      if (list.some(a => String(a.agent_id) === String(agentId))) return f;
      return {
        ...f,
        prompt_data: {
          ...f.prompt_data,
          agentes_escalacion: [...list, { ...EMPTY_AGENT, agent_id: agentId }],
        },
      };
    });
  }

  function updateAgente(index, field, value) {
    setForm(f => {
      const list = [...(Array.isArray(f.prompt_data.agentes_escalacion) ? f.prompt_data.agentes_escalacion : [])];
      list[index] = { ...list[index], [field]: value };
      // If setting es_defecto, unset others
      if (field === 'es_defecto' && value) {
        list.forEach((a, i) => { if (i !== index) a.es_defecto = false; });
      }
      return { ...f, prompt_data: { ...f.prompt_data, agentes_escalacion: list } };
    });
  }

  function removeAgente(index) {
    setForm(f => {
      const list = [...(Array.isArray(f.prompt_data.agentes_escalacion) ? f.prompt_data.agentes_escalacion : [])];
      list.splice(index, 1);
      return { ...f, prompt_data: { ...f.prompt_data, agentes_escalacion: list } };
    });
  }

  function openImportModal() {
    setImportModal(true);
    setImportTab('config');
    setImportSourceId('');
    setImportDestChannelId('');
    setImportConfigName('');
    setCsvFileName('');
    setCsvParsedData(null);
  }

  async function handleImportFromConfig() {
    if (!importSourceId || !importDestChannelId) return;
    setImportLoading(true);
    try {
      const source = await getPromptConfig(importSourceId);
      if (!source || source.error) throw new Error(source?.error || 'Config no encontrada');
      const pd = typeof source.prompt_data === 'string' ? JSON.parse(source.prompt_data) : (source.prompt_data || {});
      const destChannel = channels.find(ch => String(ch.id) === String(importDestChannelId));
      const configName = importConfigName.trim() || `${source.config_name} → ${destChannel?.channel_name || 'Canal #' + importDestChannelId}`;
      // Create directly with destination channel
      const payload = {
        config_name: configName,
        channel_id: parseInt(importDestChannelId),
        is_active: 1,
        prompt_data: { ...emptyPromptData(), ...pd },
      };
      const res = await createPromptConfig(payload);
      if (res.error) throw new Error(res.error);
      setImportModal(false);
      setResultModal({ type: 'success', title: 'Importado y Creado', message: `Configuración "${configName}" creada para ${destChannel?.channel_name || 'el canal destino'}. Puedes editarla cuando quieras.` });
      await load();
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    } finally {
      setImportLoading(false);
    }
  }

  async function loadDemoList() {
    try {
      const r = await fetch(`${DEMO_IMPORT_URL}?t=${DEMO_IMPORT_TOKEN}&action=list`);
      const data = await r.json();
      setDemoList(Array.isArray(data.demos) ? data.demos : []);
    } catch {
      setDemoList([]);
    }
  }

  async function handleImportFromDemo() {
    if (!demoSheetId || !importDestChannelId) return;
    setImportLoading(true);
    try {
      const r = await fetch(`${DEMO_IMPORT_URL}?t=${DEMO_IMPORT_TOKEN}&action=import&sheet_id=${encodeURIComponent(demoSheetId)}`);
      if (!r.ok) throw new Error('No se pudo leer el Sheet de la demo');
      const data = await r.json();
      if (!data.ok || !data.prompt_data) throw new Error(data.error || 'Respuesta inválida del importador');
      const destChannel = channels.find(ch => String(ch.id) === String(importDestChannelId));
      const configName = importConfigName.trim() || `${data.nombre} → ${destChannel?.channel_name || 'Canal #' + importDestChannelId}`;
      const payload = {
        config_name: configName,
        channel_id: parseInt(importDestChannelId),
        is_active: 1,
        prompt_data: { ...emptyPromptData(), ...data.prompt_data },
      };
      const res = await createPromptConfig(payload);
      if (res.error) throw new Error(res.error);
      setImportModal(false);
      setResultModal({ type: 'success', title: 'Demo Importada', message: `Configuración "${configName}" creada desde el Sheet de la demo ${data.nombre}. Revisa los campos y ajusta lo que necesites.` });
      await load();
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    } finally {
      setImportLoading(false);
    }
  }

  function handleCsvFile(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    setCsvFileName(file.name);
    const reader = new FileReader();
    reader.onload = (evt) => {
      try {
        const parsed = parseCsvToPromptData(evt.target.result);
        const filledKeys = Object.keys(parsed).filter(k => parsed[k]);
        if (filledKeys.length === 0) throw new Error('No se encontraron campos válidos en el CSV');
        setCsvParsedData(parsed);
      } catch (err) {
        setCsvParsedData(null);
        setResultModal({ type: 'error', title: 'Error CSV', message: err.message });
      }
    };
    reader.readAsText(file, 'UTF-8');
  }

  async function handleImportFromCsv() {
    if (!csvParsedData || !importDestChannelId) return;
    setImportLoading(true);
    try {
      const destChannel = channels.find(ch => String(ch.id) === String(importDestChannelId));
      const configName = importConfigName.trim() || `CSV Import → ${destChannel?.channel_name || 'Canal #' + importDestChannelId}`;
      const count = Object.keys(csvParsedData).filter(k => csvParsedData[k]).length;
      const payload = {
        config_name: configName,
        channel_id: parseInt(importDestChannelId),
        is_active: 1,
        prompt_data: { ...emptyPromptData(), ...csvParsedData },
      };
      const res = await createPromptConfig(payload);
      if (res.error) throw new Error(res.error);
      setImportModal(false);
      setResultModal({ type: 'success', title: 'CSV Importado', message: `${count} campos importados desde "${csvFileName}" y configuración "${configName}" creada.` });
      await load();
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    } finally {
      setImportLoading(false);
    }
  }

  if (loading) {
    return (
      <div className="h-full flex items-center justify-center">
        <Loader2 className="animate-spin text-blue-500" size={32} />
      </div>
    );
  }

  // Detail/Edit Panel
  const renderDetail = () => {
    if (!selectedId && !creating) {
      return (
        <div className="flex-1 flex items-center justify-center">
          <div className="text-center">
            <div className="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center bg-blue-50 text-blue-500 ring-1 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20">
              <FileText size={30} />
            </div>
            <p className="text-gray-600 dark:text-slate-300 font-medium" style={{ fontFamily: 'Poppins, sans-serif' }}>Selecciona una configuración o crea una nueva</p>
          </div>
        </div>
      );
    }

    const selectedCfg = configs.find(c => String(c.id) === String(selectedId));

    return (
      <div className="flex-1 overflow-y-auto custom-scrollbar">
        {/* Header */}
        <div className="sticky top-0 z-10 bg-white/95 dark:bg-slate-800/95 backdrop-blur border-b border-gray-100 dark:border-slate-700/60 px-3 sm:px-4 py-3 flex items-center justify-between gap-2">
          <div className="flex items-center gap-2 flex-1 min-w-0">
            {!editMode && (
              <button
                onClick={() => setMobilePanel('list')}
                className="flex md:hidden p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 shrink-0"
                title="Volver a la lista"
              >
                <ChevronLeft size={18} />
              </button>
            )}
          <div className="flex-1 min-w-0">
            {editMode ? (
              <input aria-label="Nombre de la configuración"
                type="text"
                value={form.config_name}
                onChange={e => setForm(f => ({ ...f, config_name: e.target.value }))}
                className="w-full text-lg font-semibold bg-transparent border-b-2 border-blue-400 focus:border-blue-600 outline-none px-1 py-0.5 text-slate-800 dark:text-white"
                placeholder="Nombre de la configuración"
                style={{ fontFamily: 'Poppins, sans-serif' }}
              />
            ) : (
              <h2 className="text-lg font-semibold text-slate-800 dark:text-white truncate" style={{ fontFamily: 'Poppins, sans-serif' }}>{form.config_name}</h2>
            )}
            {selectedCfg && (
              <div className="flex items-center flex-wrap gap-1.5 mt-1">
                <span className="text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-blue-50 text-blue-600 ring-1 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20">v{selectedCfg.version}</span>
                <span className="text-[11px] text-gray-400 dark:text-slate-500">
                  Canal: {selectedCfg.channel_name || `#${selectedCfg.channel_id}`}
                  {selectedCfg.updated_at && ` · ${new Date(selectedCfg.updated_at).toLocaleDateString('es-CL')}`}
                </span>
              </div>
            )}
          </div>
          </div>
          <div className="flex items-center gap-1.5 sm:gap-2 shrink-0">
            {canEdit && !editMode && (
              <>
                <button onClick={startEdit} className="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-md hover:shadow-lg hover:-translate-y-0.5 flex items-center gap-1 transition-all duration-300">
                  <Pencil size={13} /> Editar
                </button>
                <button
                  onClick={() => setDeleteModal({ id: selectedId, name: form.config_name })}
                  className="px-3 py-1.5 text-xs font-medium rounded-lg bg-rose-50 text-rose-600 ring-1 ring-rose-200 hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/20 dark:hover:bg-rose-500/20 flex items-center gap-1 transition-colors"
                >
                  <Trash2 size={13} /> Eliminar
                </button>
              </>
            )}
            {editMode && (
              <>
                <button
                  onClick={() => { setEditMode(false); if (creating) { setCreating(false); setMobilePanel('list'); } }}
                  className="px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 transition-colors"
                >
                  Cancelar
                </button>
                <button
                  onClick={handleSave}
                  disabled={saving}
                  className="px-4 py-1.5 text-xs font-semibold rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-md hover:shadow-lg hover:-translate-y-0.5 disabled:opacity-50 disabled:hover:translate-y-0 flex items-center gap-1 transition-all duration-300"
                >
                  {saving ? <Loader2 size={13} className="animate-spin" /> : <Save size={13} />}
                  {creating ? 'Crear' : 'Guardar'}
                </button>
              </>
            )}
          </div>
        </div>

        {/* Meta fields */}
        {editMode && (
          <div className="px-4 py-3 bg-gray-50 dark:bg-slate-800/50 border-b border-gray-100 dark:border-slate-700/60 flex flex-wrap gap-4">
            <div className="flex-1 min-w-[200px]">
              <label htmlFor="promptsview-fld1" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1" style={{ fontFamily: 'Poppins, sans-serif' }}>Canal</label>
              <select id="promptsview-fld1"
                value={form.channel_id}
                onChange={e => { const v = e.target.value; setForm(f => ({ ...f, channel_id: v })); if (v) loadAvailableAgents(v); else setAvailableAgents([]); }}
                className="w-full px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 border-0 bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
              >
                <option value="">— Seleccionar canal —</option>
                {channels.map(ch => (
                  <option key={ch.id} value={ch.id}>{ch.channel_name} ({ch.channel_type})</option>
                ))}
              </select>
            </div>
            <div className="flex items-end gap-2">
              <label htmlFor="promptsview-fld2" className="flex items-center gap-2 text-sm cursor-pointer px-3 py-2 rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 bg-white dark:bg-slate-700">
                <input id="promptsview-fld2"
                  type="checkbox"
                  checked={!!form.is_active}
                  onChange={e => setForm(f => ({ ...f, is_active: e.target.checked ? 1 : 0 }))}
                  className="w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                />
                <span className="text-slate-600 dark:text-slate-300 font-medium">Activo</span>
              </label>
            </div>
          </div>
        )}

        {/* Sections */}
        <div className="p-4 space-y-3">
          {PROMPT_SECTIONS.map(section => {
            const isExpanded = expandedSections[section.id];
            const filledCount = section.fields.filter(f => form.prompt_data[f.key]?.trim()).length;
            const agentCount = section.id === 'escalacion' ? getAgentes().length : 0;
            const enabled = isSectionEnabled(section.id);
            const isAlwaysOn = ALWAYS_ON_SECTIONS.includes(section.id);

            return (
              <div key={section.id} className={`rounded-2xl ring-1 overflow-hidden transition-all duration-300 ${enabled ? 'bg-white dark:bg-slate-800 ring-gray-100 dark:ring-slate-700/60 shadow-md hover:shadow-xl hover:ring-gray-200 dark:hover:ring-slate-600' : 'bg-gray-50/60 dark:bg-slate-800/40 ring-gray-100/70 dark:ring-slate-700/40 opacity-60'}`}>
                <div className="flex items-center justify-between px-4 py-3 bg-gray-50/70 dark:bg-slate-800/60">
                  <button
                    onClick={() => enabled && toggleSection(section.id)}
                    className={`flex-1 flex items-center gap-2 text-left ${enabled ? 'hover:opacity-80' : 'cursor-default'}`}
                    disabled={!enabled}
                  >
                    <span className={`font-semibold text-sm ${enabled ? 'text-slate-700 dark:text-slate-200' : 'text-slate-400 dark:text-slate-500'}`} style={{ fontFamily: 'Poppins, sans-serif' }}>{section.label}</span>
                  </button>
                  <div className="flex items-center gap-2 shrink-0">
                    {!enabled && (
                      <span className="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 ring-1 ring-slate-200 dark:ring-slate-600 font-medium">
                        Deshabilitada
                      </span>
                    )}
                    {enabled && (
                      <span className="text-[10px] px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-300 ring-1 ring-blue-200 dark:ring-blue-500/20 font-medium">
                        {section.id === 'escalacion' ? `${filledCount}/${section.fields.length} · ${agentCount} agente${agentCount !== 1 ? 's' : ''}` : `${filledCount}/${section.fields.length}`}
                      </span>
                    )}
                    {/* Toggle switch - only in edit mode for non-core sections */}
                    {editMode && !isAlwaysOn && (
                      <button
                        onClick={(e) => { e.stopPropagation(); toggleSectionEnabled(section.id); }}
                        className={`relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none ${enabled ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'}`}
                        title={enabled ? 'Desactivar sección' : 'Activar sección'}
                      >
                        <span className={`inline-block h-3.5 w-3.5 rounded-full bg-white shadow-sm transform transition-transform ${enabled ? 'translate-x-[18px]' : 'translate-x-[3px]'}`} />
                      </button>
                    )}
                    {enabled && (
                      <button onClick={() => toggleSection(section.id)} className="p-0.5">
                        {isExpanded ? <ChevronUp size={16} className="text-slate-400" /> : <ChevronDown size={16} className="text-slate-400" />}
                      </button>
                    )}
                  </div>
                </div>
                {/* Description shown in edit mode when section is expanded or just enabled */}
                {editMode && section.description && enabled && !isExpanded && (
                  <div className="px-4 py-1.5 text-[11px] text-slate-400 dark:text-slate-500 bg-slate-50/50 dark:bg-slate-800/30 border-t border-slate-100 dark:border-slate-700/50">
                    {section.description}
                  </div>
                )}
                {enabled && isExpanded && (
                  <div className="p-4 space-y-3 bg-white dark:bg-slate-800">
                    {editMode && section.description && (
                      <p className="text-xs text-slate-400 dark:text-slate-500 -mt-1 mb-2">{section.description}</p>
                    )}
                    {/* Regular fields */}
                    {section.fields.map(field => (
                      <div key={field.key}>
                        <label htmlFor="promptsview-fld3" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1" style={{ fontFamily: 'Poppins, sans-serif' }}>{field.label}</label>
                        {editMode ? (
                          field.type === 'textarea' ? (
                            <textarea id="promptsview-fld3"
                              value={form.prompt_data[field.key] || ''}
                              onChange={e => updateField(field.key, e.target.value)}
                              placeholder={field.placeholder}
                              rows={4}
                              className="w-full px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 border-0 bg-white dark:bg-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 outline-none resize-y"
                            />
                          ) : (
                            <input aria-label="Valor del campo"
                              type="text"
                              value={form.prompt_data[field.key] || ''}
                              onChange={e => updateField(field.key, e.target.value)}
                              placeholder={field.placeholder}
                              className="w-full px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 border-0 bg-white dark:bg-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 outline-none"
                            />
                          )
                        ) : (
                          <div className="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap min-h-[1.5rem] px-1">
                            {form.prompt_data[field.key] || <span className="text-slate-300 dark:text-slate-600 italic">Sin configurar</span>}
                          </div>
                        )}
                      </div>
                    ))}

                    {/* Custom UI: Escalation agents list */}
                    {section.id === 'escalacion' && (
                      <div className="mt-4 pt-3 border-t border-slate-200 dark:border-slate-700">
                        <div className="flex items-center justify-between mb-3">
                          <label htmlFor="promptsview-fld4" className="text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wide flex items-center gap-1.5">
                            <UserPlus size={14} /> Agentes para Escalación
                          </label>
                        </div>

                        {/* Agent selector dropdown (edit mode) */}
                        {editMode && (
                          <div className="mb-3">
                            {loadingAgents ? (
                              <div className="flex items-center gap-2 text-xs text-slate-400 py-2">
                                <Loader2 size={14} className="animate-spin" /> Cargando agentes...
                              </div>
                            ) : availableAgents.length === 0 ? (
                              <div className="text-xs text-slate-400 dark:text-slate-500 bg-slate-50 dark:bg-slate-800/50 rounded-lg p-3 text-center">
                                {form.channel_id
                                  ? 'No hay agentes activos para este canal. Asígnalos desde el módulo de Agentes.'
                                  : 'Selecciona un canal primero para ver los agentes disponibles.'}
                              </div>
                            ) : (
                              <div className="flex items-center gap-2">
                                <select id="promptsview-fld4"
                                  className="flex-1 px-2.5 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 border-0 bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                                  value=""
                                  onChange={e => { if (e.target.value) addAgente(e.target.value); }}
                                >
                                  <option value="">— Seleccionar agente para agregar —</option>
                                  {availableAgents
                                    .filter(a => !getAgentes().some(ea => String(ea.agent_id) === String(a.id)))
                                    .map(a => (
                                      <option key={a.id} value={a.id}>
                                        {a.name}{a.department ? ` · ${a.department}` : ''}{a.email ? ` · ${a.email}` : ''}
                                      </option>
                                    ))}
                                </select>
                              </div>
                            )}
                          </div>
                        )}

                        {getAgentes().length === 0 ? (
                          <div className="text-center py-6 text-slate-400 dark:text-slate-500">
                            <UserPlus size={28} className="mx-auto mb-2 opacity-40" />
                            <p className="text-xs">No hay agentes asignados para escalación</p>
                            {editMode && <p className="text-[11px] mt-1">Selecciona agentes del listado para definir a quién derivar</p>}
                          </div>
                        ) : (
                          <div className="space-y-3">
                            {getAgentes().map((agente, idx) => {
                              const agentInfo = availableAgents.find(a => String(a.id) === String(agente.agent_id));
                              return (
                                <div key={agente.agent_id || idx} className={`rounded-lg border p-3 ${agente.es_defecto ? 'border-amber-300 dark:border-amber-500/40 bg-amber-50/50 dark:bg-amber-500/5' : 'border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30'}`}>
                                  {editMode ? (
                                    <div className="space-y-2">
                                      <div className="flex items-center justify-between gap-2">
                                        <div className="flex items-center gap-2 flex-1 min-w-0">
                                          <div className="w-7 h-7 rounded-full bg-blue-100 dark:bg-blue-500/20 flex items-center justify-center shrink-0">
                                            <UserPlus size={12} className="text-blue-600 dark:text-blue-400" />
                                          </div>
                                          <div className="min-w-0">
                                            <span className="text-sm font-medium text-slate-700 dark:text-slate-200 truncate block">
                                              {agentInfo?.name || `Agente #${agente.agent_id}`}
                                            </span>
                                            {agentInfo && (
                                              <span className="text-[11px] text-slate-400 truncate block">
                                                {agentInfo.email}{agentInfo.department ? ` · ${agentInfo.department}` : ''}
                                                {agentInfo.schedule_start && agentInfo.schedule_end ? ` · 🕐 ${agentInfo.schedule_start.slice(0,5)}-${agentInfo.schedule_end.slice(0,5)}` : ''}
                                              </span>
                                            )}
                                          </div>
                                        </div>
                                        <button
                                          onClick={() => removeAgente(idx)}
                                          className="p-1.5 rounded-lg text-red-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 shrink-0"
                                          title="Quitar agente"
                                        >
                                          <Trash2 size={14} />
                                        </button>
                                      </div>
                                      <div className="flex items-center gap-3">
                                        <div className="flex items-center gap-2 flex-1">
                                          <Building2 size={14} className="text-slate-400 shrink-0" />
                                          <input aria-label="Empresa"
                                            type="text"
                                            value={agente.area || ''}
                                            onChange={e => updateAgente(idx, 'area', e.target.value)}
                                            placeholder="Área de escalación (ej: Ventas, Soporte...)"
                                            className="flex-1 px-2.5 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 border-0 bg-white dark:bg-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 outline-none"
                                          />
                                        </div>
                                        <label htmlFor="promptsview-fld5" className="flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400 cursor-pointer whitespace-nowrap shrink-0">
                                          <input id="promptsview-fld5"
                                            type="checkbox"
                                            checked={!!agente.es_defecto}
                                            onChange={e => updateAgente(idx, 'es_defecto', e.target.checked)}
                                            className="w-3.5 h-3.5 rounded border-slate-300 text-amber-500 focus:ring-amber-400"
                                          />
                                          Por defecto
                                        </label>
                                      </div>
                                    </div>
                                  ) : (
                                    <div className="flex items-start gap-3">
                                      <div className="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-500/20 flex items-center justify-center shrink-0 mt-0.5">
                                        <UserPlus size={14} className="text-blue-600 dark:text-blue-400" />
                                      </div>
                                      <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                          <span className="text-sm font-medium text-slate-700 dark:text-slate-200">
                                            {agentInfo?.name || `Agente #${agente.agent_id}`}
                                          </span>
                                          {agente.es_defecto && (
                                            <span className="text-[9px] px-1.5 py-0.5 rounded-full bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400 font-medium">
                                              DEFECTO
                                            </span>
                                          )}
                                        </div>
                                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                          {agente.area || 'Sin área asignada'}
                                          {agentInfo?.email ? ` · ${agentInfo.email}` : ''}
                                          {agentInfo?.department ? ` · ${agentInfo.department}` : ''}
                                        </p>
                                        {(agentInfo?.schedule_start && agentInfo?.schedule_end) && (
                                          <p className="text-[11px] text-sky-600 dark:text-sky-400 mt-0.5 flex items-center gap-1">
                                            🕐 {agentInfo.schedule_start.slice(0,5)} - {agentInfo.schedule_end.slice(0,5)}
                                            {agentInfo.available_days && (
                                              <span className="text-slate-400 dark:text-slate-500">
                                                · {agentInfo.available_days.split(',').map(d => ({1:'Lu',2:'Ma',3:'Mi',4:'Ju',5:'Vi',6:'Sá',7:'Do'})[d] || d).join(', ')}
                                              </span>
                                            )}
                                          </p>
                                        )}
                                      </div>
                                    </div>
                                  )}
                                </div>
                              );
                            })}
                          </div>
                        )}
                      </div>
                    )}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      </div>
    );
  };

  return (
    <div className="h-full flex flex-col">
      {/* Top bar */}
      <div className="shrink-0 border-b border-gray-100 dark:border-slate-700/60 px-4 py-3 flex items-center justify-between gap-3 bg-white dark:bg-slate-800">
        <div className="flex items-center gap-3 min-w-0">
          <span className="w-11 h-11 rounded-2xl flex items-center justify-center bg-gradient-to-br from-blue-400 to-blue-600 text-white shadow-md shrink-0">
            <FileText size={22} />
          </span>
          <div className="min-w-0">
            <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-gray-900 dark:text-white truncate" style={{ fontFamily: 'Poppins, sans-serif' }}>Configuración de Prompts</h1>
            <p className="text-sm text-gray-500 dark:text-slate-400 hidden sm:block">
              Parametriza el comportamiento del bot para cada canal
            </p>
          </div>
        </div>
        {canEdit && (
          <div className="flex items-center gap-2 shrink-0">
            <button
              onClick={openImportModal}
              className="px-3 sm:px-4 py-2 text-xs sm:text-sm font-semibold rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-white flex items-center gap-1.5 shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300"
            >
              <Upload size={16} /> <span className="hidden sm:inline">Importar</span>
            </button>
            <button
              onClick={startCreate}
              className="px-3 sm:px-4 py-2 text-xs sm:text-sm font-semibold rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 text-white flex items-center gap-1.5 shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300"
            >
              <Plus size={16} /> <span className="hidden sm:inline">Nueva Config</span><span className="sm:hidden">Nueva</span>
            </button>
          </div>
        )}
      </div>

      <div className="flex-1 flex min-h-0">
        {/* Sidebar list - hidden on mobile when detail panel is active */}
        <div className={`${mobilePanel === 'detail' ? 'hidden md:flex md:flex-col' : 'flex flex-col'} w-full md:w-72 md:shrink-0 border-r border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 overflow-y-auto custom-scrollbar`}>
          {configs.length === 0 ? (
            <div className="p-6 text-center">
              <div className="w-14 h-14 mx-auto mb-3 rounded-2xl flex items-center justify-center bg-blue-50 text-blue-500 ring-1 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20">
                <FileText size={26} />
              </div>
              <p className="text-sm font-medium text-gray-600 dark:text-slate-300" style={{ fontFamily: 'Poppins, sans-serif' }}>No hay configuraciones</p>
              {canEdit && <p className="text-xs mt-1 text-gray-400 dark:text-slate-500">Crea una nueva con el botón +</p>}
            </div>
          ) : (
            <div className="p-2 space-y-1.5">
              {configs.map(cfg => {
                const isSelected = String(cfg.id) === String(selectedId);
                return (
                  <button
                    key={cfg.id}
                    onClick={() => selectConfig(cfg)}
                    className={`relative w-full text-left pl-4 pr-3 py-2.5 rounded-xl ring-1 transition-all duration-300 overflow-hidden ${
                      isSelected
                        ? 'bg-white dark:bg-slate-800 ring-blue-300 dark:ring-blue-500/40 shadow-md hover:shadow-lg before:absolute before:inset-y-0 before:left-0 before:w-1.5 before:bg-gradient-to-b before:from-blue-500 before:to-blue-600'
                        : 'bg-transparent ring-transparent hover:bg-white dark:hover:bg-slate-700/40 hover:ring-gray-100 dark:hover:ring-slate-700'
                    }`}
                  >
                    <div className="flex items-center justify-between gap-2">
                      <span className={`text-sm font-medium truncate ${isSelected ? 'text-blue-700 dark:text-blue-300' : 'text-slate-700 dark:text-slate-200'}`}>
                        {cfg.config_name}
                      </span>
                      {cfg.is_active ? (
                        <span className="shrink-0 inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20" title="Activo">
                          <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" /> Activo
                        </span>
                      ) : (
                        <span className="shrink-0 inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-400 ring-1 ring-slate-200 dark:bg-slate-700 dark:text-slate-400 dark:ring-slate-600" title="Inactivo">
                          <span className="w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-slate-500" /> Inactivo
                        </span>
                      )}
                    </div>
                    <p className="text-[11px] text-gray-400 dark:text-slate-500 mt-1 truncate">
                      {cfg.channel_name || `Canal #${cfg.channel_id}`} · v{cfg.version}
                    </p>
                  </button>
                );
              })}
            </div>
          )}
        </div>

        {/* Detail panel - hidden on mobile when list panel is active */}
        <div className={`${mobilePanel === 'list' ? 'hidden md:flex' : 'flex'} flex-1 flex-col min-h-0 overflow-hidden`}>
          {renderDetail()}
        </div>
      </div>

      {/* Modals */}
      {resultModal && (
        <ResultModal
          type={resultModal.type}
          title={resultModal.title}
          message={resultModal.message}
          onClose={() => setResultModal(null)}
        />
      )}
      {deleteModal && (
        <ConfirmDeleteModal
          isOpen={true}
          title="Eliminar Configuración"
          description={`¿Estás seguro de eliminar "${deleteModal.name}"? Esta acción no se puede deshacer.`}
          skipApiKey={true}
          onConfirm={() => { handleDelete(deleteModal.id); setDeleteModal(null); }}
          onClose={() => setDeleteModal(null)}
        />
      )}

      {/* Import modal */}
      {importModal && (
        <div role="dialog" aria-modal="true" aria-label="Editor de prompt" className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
            {/* Header */}
            <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700/60">
              <h3 className="text-lg font-semibold text-slate-800 dark:text-white flex items-center gap-2" style={{ fontFamily: 'Poppins, sans-serif' }}>
                <span className="w-9 h-9 rounded-2xl flex items-center justify-center bg-gradient-to-br from-amber-400 to-orange-500 text-white shadow-md"><Upload size={16} /></span> Importar Prompts
              </h3>
              <button onClick={() => setImportModal(false)} className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400">
                <X size={18} />
              </button>
            </div>

            {/* Tabs */}
            <div className="flex border-b border-slate-200 dark:border-slate-700">
              <button
                onClick={() => setImportTab('config')}
                className={`flex-1 px-4 py-2.5 text-sm font-semibold flex items-center justify-center gap-1.5 transition-all duration-300 ${
                  importTab === 'config'
                    ? 'text-white bg-gradient-to-br from-blue-500 to-blue-600 shadow-md'
                    : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'
                }`}
              >
                <Copy size={15} /> Desde Canal Existente
              </button>
              <button
                onClick={() => setImportTab('csv')}
                className={`flex-1 px-4 py-2.5 text-sm font-semibold flex items-center justify-center gap-1.5 transition-all duration-300 ${
                  importTab === 'csv'
                    ? 'text-white bg-gradient-to-br from-blue-500 to-blue-600 shadow-md'
                    : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'
                }`}
              >
                <FileUp size={15} /> Desde CSV
              </button>
              <button
                onClick={() => { setImportTab('demo'); if (demoList === null) loadDemoList(); }}
                className={`flex-1 px-4 py-2.5 text-sm font-semibold flex items-center justify-center gap-1.5 transition-all duration-300 ${
                  importTab === 'demo'
                    ? 'text-white bg-gradient-to-br from-blue-500 to-blue-600 shadow-md'
                    : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'
                }`}
              >
                <FileText size={15} /> Desde Demo
              </button>
            </div>

            {/* Tab content */}
            <div className="p-5">
              {importTab === 'config' ? (
                <div className="space-y-4">
                  <p className="text-sm text-slate-500 dark:text-slate-400">
                    Copia los prompts de una configuración existente a un canal diferente.
                  </p>
                  <div>
                    <label htmlFor="promptsview-fld6" className="block text-xs font-medium text-slate-500 mb-1.5">Configuración origen</label>
                    <select id="promptsview-fld6"
                      value={importSourceId}
                      onChange={e => setImportSourceId(e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 border-0 bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                    >
                      <option value="">— Seleccionar config origen —</option>
                      {configs.map(cfg => (
                        <option key={cfg.id} value={cfg.id}>
                          {cfg.config_name} ({cfg.channel_name || `Canal #${cfg.channel_id}`})
                        </option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label htmlFor="promptsview-fld7" className="block text-xs font-medium text-slate-500 mb-1.5">Canal destino</label>
                    <select id="promptsview-fld7"
                      value={importDestChannelId}
                      onChange={e => setImportDestChannelId(e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 border-0 bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                    >
                      <option value="">— Seleccionar canal destino —</option>
                      {channels.map(ch => (
                        <option key={ch.id} value={ch.id}>
                          {ch.channel_name} ({ch.channel_type})
                        </option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label htmlFor="promptsview-fld8" className="block text-xs font-medium text-slate-500 mb-1.5">Nombre de la nueva configuración <span className="text-slate-400 font-normal">(opcional)</span></label>
                    <input id="promptsview-fld8"
                      type="text"
                      value={importConfigName}
                      onChange={e => setImportConfigName(e.target.value)}
                      placeholder="Se generará automáticamente si se deja vacío"
                      className="w-full px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 border-0 bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                    />
                  </div>
                  {importSourceId && importDestChannelId && (
                    <div className="text-xs text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 rounded-lg p-3 flex items-start gap-2">
                      <span className="mt-0.5">✅</span>
                      <span>Se copiarán todos los campos del prompt (negocio, asistente, servicios, agenda, pagos, reglas) al canal seleccionado. Podrás editarlos después.</span>
                    </div>
                  )}
                  <button
                    onClick={handleImportFromConfig}
                    disabled={!importSourceId || !importDestChannelId || importLoading}
                    className="w-full px-4 py-2.5 text-sm font-semibold rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 transition-all duration-300"
                  >
                    {importLoading ? <Loader2 size={16} className="animate-spin" /> : <Copy size={16} />}
                    Importar Configuración
                  </button>
                </div>
              ) : importTab === 'demo' ? (
                <div className="space-y-4">
                  <p className="text-sm text-slate-500 dark:text-slate-400">
                    Crea la configuración de un cliente real desde el Google Sheet de una demo: prompts, catálogo de servicios, agenda, bloqueos y pagos.
                  </p>
                  <div>
                    <label htmlFor="promptsview-demo-src" className="block text-xs font-medium text-slate-500 mb-1.5">Demo origen</label>
                    <select id="promptsview-demo-src"
                      value={demoSheetId}
                      onChange={e => setDemoSheetId(e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 border-0 bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                    >
                      <option value="">{demoList === null ? 'Cargando demos…' : demoList.length === 0 ? 'No hay demos disponibles' : '— Seleccionar demo —'}</option>
                      {(demoList || []).map(d => (
                        <option key={d.sheet_id} value={d.sheet_id}>
                          {d.emoji} {d.nombre}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label htmlFor="promptsview-demo-dest" className="block text-xs font-medium text-slate-500 mb-1.5">Canal destino (cliente real)</label>
                    <select id="promptsview-demo-dest"
                      value={importDestChannelId}
                      onChange={e => setImportDestChannelId(e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 border-0 bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                    >
                      <option value="">— Seleccionar canal destino —</option>
                      {channels.map(ch => (
                        <option key={ch.id} value={ch.id}>
                          {ch.channel_name} ({ch.channel_type})
                        </option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label htmlFor="promptsview-demo-name" className="block text-xs font-medium text-slate-500 mb-1.5">Nombre de la nueva configuración <span className="text-slate-400 font-normal">(opcional)</span></label>
                    <input id="promptsview-demo-name"
                      type="text"
                      value={importConfigName}
                      onChange={e => setImportConfigName(e.target.value)}
                      placeholder="Se generará automáticamente si se deja vacío"
                      className="w-full px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 border-0 bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                    />
                  </div>
                  {demoSheetId && importDestChannelId && (
                    <div className="text-xs text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 rounded-lg p-3 flex items-start gap-2">
                      <span className="mt-0.5">✅</span>
                      <span>Se leerán del Sheet: prompts del asistente, servicios (convertidos a JSON), horarios, bloqueos y condiciones de pago. El número de WhatsApp lo define el canal destino. Podrás editar todo antes de que el bot lo use.</span>
                    </div>
                  )}
                  <button
                    onClick={handleImportFromDemo}
                    disabled={!demoSheetId || !importDestChannelId || importLoading}
                    className="w-full px-4 py-2.5 text-sm font-semibold rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 transition-all duration-300"
                  >
                    {importLoading ? <Loader2 size={16} className="animate-spin" /> : <FileText size={16} />}
                    Importar desde Demo
                  </button>
                </div>
              ) : (
                <div className="space-y-4">
                  <p className="text-sm text-slate-500 dark:text-slate-400">
                    Sube un archivo CSV con formato <code className="text-xs bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded">parametro;valor</code> (separado por punto y coma).
                  </p>
                  <div
                    onClick={() => csvInputRef.current?.click()}
                    className="border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl p-6 text-center cursor-pointer hover:border-blue-400 hover:bg-blue-50/30 dark:hover:bg-blue-500/5 transition-colors"
                  >
                    <input aria-label="Subir archivo"
                      ref={csvInputRef}
                      type="file"
                      accept=".csv,.txt"
                      onChange={handleCsvFile}
                      className="hidden"
                    />
                    <FileUp size={32} className="mx-auto mb-2 text-slate-400" />
                    {csvFileName ? (
                      <div>
                        <p className="text-sm font-medium text-slate-700 dark:text-slate-200">{csvFileName}</p>
                        {csvParsedData && (
                          <p className="text-xs text-emerald-600 dark:text-emerald-400 mt-1">
                            ✓ {Object.keys(csvParsedData).filter(k => csvParsedData[k]).length} campos detectados
                          </p>
                        )}
                      </div>
                    ) : (
                      <p className="text-sm text-slate-400">Click para seleccionar archivo CSV</p>
                    )}
                  </div>
                  <div>
                    <label htmlFor="promptsview-fld9" className="block text-xs font-medium text-slate-500 mb-1.5">Canal destino</label>
                    <select id="promptsview-fld9"
                      value={importDestChannelId}
                      onChange={e => setImportDestChannelId(e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 border-0 bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                    >
                      <option value="">— Seleccionar canal destino —</option>
                      {channels.map(ch => (
                        <option key={ch.id} value={ch.id}>
                          {ch.channel_name} ({ch.channel_type})
                        </option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label htmlFor="promptsview-fld10" className="block text-xs font-medium text-slate-500 mb-1.5">Nombre de la configuración <span className="text-slate-400 font-normal">(opcional)</span></label>
                    <input id="promptsview-fld10"
                      type="text"
                      value={importConfigName}
                      onChange={e => setImportConfigName(e.target.value)}
                      placeholder="Se generará automáticamente si se deja vacío"
                      className="w-full px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 border-0 bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                    />
                  </div>
                  {csvParsedData && (
                    <div className="text-xs text-slate-400 bg-slate-50 dark:bg-slate-700/50 rounded-lg p-3 max-h-32 overflow-y-auto">
                      <p className="font-medium text-slate-500 dark:text-slate-300 mb-1">Campos detectados:</p>
                      {Object.entries(csvParsedData).filter(([,v]) => v).map(([k, v]) => (
                        <span key={k} className="inline-block mr-2 mb-1 px-2 py-0.5 bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300 rounded text-[10px]">
                          {k}
                        </span>
                      ))}
                    </div>
                  )}
                  <button
                    onClick={handleImportFromCsv}
                    disabled={!csvParsedData || !importDestChannelId || importLoading}
                    className="w-full px-4 py-2.5 text-sm font-semibold rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 transition-all duration-300"
                  >
                    {importLoading ? <Loader2 size={16} className="animate-spin" /> : <FileUp size={16} />}
                    Importar desde CSV
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
