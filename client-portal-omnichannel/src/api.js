export const API_BASE = import.meta.env.VITE_API_URL || (import.meta.env.DEV ? '/api-omnichannel.php' : '/automatiza-tech/api-omnichannel.php');

let apiKey = localStorage.getItem('omni_api_key') || '';
let isAdmin = localStorage.getItem('omni_is_admin') === 'true';
let isAgent = localStorage.getItem('omni_is_agent') === 'true';

export function setApiKey(key) {
  if (key === '__wp_admin_session__') {
    isAdmin = true;
    isAgent = false;
    apiKey = '';
    localStorage.setItem('omni_is_admin', 'true');
    localStorage.removeItem('omni_api_key');
    localStorage.removeItem('omni_is_agent');
  } else if (key === '__agent_session__') {
    isAgent = true;
    isAdmin = false;
    apiKey = '';
    localStorage.setItem('omni_is_agent', 'true');
    localStorage.removeItem('omni_api_key');
    localStorage.removeItem('omni_is_admin');
  } else {
    isAdmin = false;
    isAgent = false;
    apiKey = key;
    localStorage.setItem('omni_api_key', key);
    localStorage.removeItem('omni_is_admin');
    localStorage.removeItem('omni_is_agent');
  }
}

export function getApiKey() {
  return apiKey;
}

export function getIsAdmin() {
  return isAdmin;
}

export function getIsAgent() {
  return isAgent;
}

export function getAgentData() {
  try {
    return JSON.parse(localStorage.getItem('omni_agent_data') || 'null');
  } catch { return null; }
}

export function getAgentRole() {
  const data = getAgentData();
  return data?.role || 'agent';
}

export function isSupervisorOrAdmin() {
  const role = getAgentRole();
  return role === 'supervisor' || role === 'admin';
}

export function clearAuth() {
  apiKey = '';
  isAdmin = false;
  isAgent = false;
  localStorage.removeItem('omni_api_key');
  localStorage.removeItem('omni_is_admin');
  localStorage.removeItem('omni_is_agent');
  localStorage.removeItem('omni_admin_token');
  localStorage.removeItem('omni_admin_user');
  localStorage.removeItem('omni_agent_token');
  localStorage.removeItem('omni_agent_data');
}

export function isAuthenticated() {
  return !!apiKey || isAdmin || isAgent;
}

async function request(route, method = 'GET', body = null) {
  // Separate route path from query params so PHP sees them in $_GET
  const [routePath, routeQs] = route.split('?');
  // Agent uses agent/ prefix routes
  // Admin uses admin/ prefix routes
  let effectiveRoute = routePath;
  if (isAgent && !routePath.startsWith('agent')) {
    effectiveRoute = `agent/${routePath}`;
  } else if (isAdmin && !routePath.startsWith('admin')) {
    effectiveRoute = `admin/${routePath}`;
  }
  const url = `${API_BASE}?route=${encodeURIComponent(effectiveRoute)}${routeQs ? '&' + routeQs : ''}`;
  const options = {
    method,
    headers: {
      'Content-Type': 'application/json',
    },
    credentials: isAdmin ? 'include' : 'same-origin',
  };
  if (isAgent) {
    const agentToken = localStorage.getItem('omni_agent_token');
    if (agentToken) {
      options.headers['X-Agent-Token'] = agentToken;
    }
  } else if (isAdmin) {
    const adminToken = localStorage.getItem('omni_admin_token');
    if (adminToken) {
      options.headers['X-Admin-Token'] = adminToken;
    }
  } else if (apiKey) {
    options.headers['X-API-Key'] = apiKey;
  }
  if (body && method !== 'GET') {
    options.body = JSON.stringify(body);
  }
  const res = await fetch(url, options);
  const data = await res.json();
  if (!res.ok) {
    throw new Error(data.error || `HTTP ${res.status}`);
  }
  return data;
}

// Conversaciones
export const getConversations = (params = {}) => {
  const qs = new URLSearchParams(params).toString();
  return request(`conversations${qs ? '?' + qs : ''}`);
};

export const getMessages = (convId, page = 1) =>
  request(`conversations/${convId}/messages?page=${page}`);

export const sendMessage = (convId, data) =>
  request(`conversations/${convId}/messages`, 'POST', data);

export const takeoverConversation = (convId, agentId, reason = '') =>
  request(`conversations/${convId}/takeover`, 'POST', { agent_id: agentId, reason });

export const releaseConversation = (convId, agentId) =>
  request(`conversations/${convId}/release`, 'POST', { agent_id: agentId });

export const transferConversation = (convId, fromAgentId, toAgentId, notes = '') =>
  request(`conversations/${convId}/transfer`, 'POST', { from_agent_id: fromAgentId, to_agent_id: toAgentId, notes });

// Canales
export const getChannels = () => request('channels');
export const createChannel = (data) => request('channels', 'POST', data);
export const updateChannel = (id, data) => request(`channels/${id}`, 'PUT', data);
export const deleteChannel = (id, confirmApiKey) => request(`channels/${id}`, 'DELETE', { confirm_api_key: confirmApiKey });

// Tipos de Canal
export const getChannelTypes = () => request('channel-types');
export const createChannelType = (data) => request('channel-types', 'POST', data);
export const updateChannelType = (id, data) => request(`channel-types/${id}`, 'PUT', data);
export const deleteChannelType = (id) => request(`channel-types/${id}`, 'DELETE');

// Bots
export const getBotConfigs = () => request('bots');
export const getBotConfig = (channelId) => request(`bots/${channelId}`);
export const updateBotConfig = (configId, data) => request(`bots/${configId}`, 'PUT', data);

// Agentes
export const getAgents = () => request('agents');
export const getAgentsPaginated = ({ page = 1, per_page = 10, orderby = 'created_at', order = 'DESC', search = '' } = {}) =>
  request(`agents?page=${page}&per_page=${per_page}&orderby=${orderby}&order=${order}&search=${encodeURIComponent(search)}`);
export const createAgent = (data) => request('agents', 'POST', data);
export const updateAgent = (id, data) => request(`agents/${id}`, 'PUT', data);
export const deleteAgent = (id, confirmApiKey) => request(`agents/${id}`, 'DELETE', { confirm_api_key: confirmApiKey });

// Auditoría
export const getAuditLogs = ({ page = 1, per_page = 15, orderby = 'created_at', order = 'DESC', search = '' } = {}) =>
  request(`audit?page=${page}&per_page=${per_page}&orderby=${orderby}&order=${order}&search=${encodeURIComponent(search)}`);
export const getAdminAuditLogs = ({ page = 1, per_page = 15, orderby = 'created_at', order = 'DESC', search = '' } = {}) =>
  request(`admin/audit?page=${page}&per_page=${per_page}&orderby=${orderby}&order=${order}&search=${encodeURIComponent(search)}`);

// ============================================================
// ADMIN-ONLY ENDPOINTS
// ============================================================

// Clients management (admin)
export const getClients = (params = {}) => {
  const qs = new URLSearchParams(params).toString();
  return request(`clients${qs ? '?' + qs : ''}`);
};
export const getClient = (id) => request(`clients/${id}`);
export const createClient = (data) => request('clients', 'POST', data);
export const updateClient = (id, data) => request(`clients/${id}`, 'PUT', data);
export const deleteClient = (id) => request(`clients/${id}`, 'DELETE');

// Period status (client/agent)
export const getPeriodStatus = () => request('period-status');

// Import WP users (admin)
export const getImportableWpUsers = (search = '') =>
  request(`wp-users${search ? '?search=' + encodeURIComponent(search) : ''}`);
export const importWpUser = (data) => request('wp-users', 'POST', data);

// Import CRM prospects (admin)
export const getCrmProspects = (search = '') =>
  request(`crm-prospects${search ? '?search=' + encodeURIComponent(search) : ''}`);
export const importCrmProspect = (data) => request('crm-prospects', 'POST', data);

// Bot Templates (admin)
export const getBotTemplates = (clientId) =>
  request(`bot-templates${clientId ? '?client_id=' + clientId : ''}`);
export const getBotTemplate = (id) => request(`bot-templates/${id}`);
export const createBotTemplate = (data) => request('bot-templates', 'POST', data);
export const updateBotTemplate = (id, data) => request(`bot-templates/${id}`, 'PUT', data);
export const deleteBotTemplate = (id) => request(`bot-templates/${id}`, 'DELETE');

// N8N Workflows (admin)
export const getWorkflows = (clientId) =>
  request(`workflows${clientId ? '?client_id=' + clientId : ''}`);
export const createWorkflow = (data) => request('workflows', 'POST', data);
export const updateWorkflow = (id, data) => request(`workflows/${id}`, 'PUT', data);
export const deleteWorkflow = (id) => request(`workflows/${id}`, 'DELETE');

// Human Intervention (admin)
export const startIntervention = (conversationId, agentId) =>
  request('intervention/start', 'POST', { conversation_id: conversationId, agent_id: agentId });
export const endIntervention = (conversationId, agentId) =>
  request('intervention/end', 'POST', { conversation_id: conversationId, agent_id: agentId });
export const sendInterventionMessage = (conversationId, data) =>
  request('intervention/send', 'POST', { conversation_id: conversationId, ...data });

// Stats (admin)
export const getAdminStats = () => request('stats');

// ============================================================
// ADMIN: Agent management (password, skills)
// ============================================================
export const updateAgentAdmin = (id, data) => request(`agents/${id}`, 'PUT', data);
export const adminResetAgentPassword = (agentId, newPassword) => request(`agents/${agentId}/reset-password`, 'POST', { new_password: newPassword });
export const autoAssignConversation = (conversationId, skill = '') =>
  request('auto-assign', 'POST', { conversation_id: conversationId, skill });

// ============================================================
// AGENT: Profile
// ============================================================
export const getAgentProfile = () => request('profile');
export const updateAgentProfile = (data) => request('profile', 'PUT', data);
export const requestPasswordCode = (oldPassword) => request('request-password-code', 'POST', { old_password: oldPassword });
export const changePasswordWithCode = (code, newPassword) => request('change-password', 'POST', { code, new_password: newPassword });

export async function uploadAvatar(file) {
  const formData = new FormData();
  formData.append('avatar', file);
  const agentToken = localStorage.getItem('omni_agent_token');
  const effectiveRoute = 'agent/avatar';
  const url = `${API_BASE}?route=${encodeURIComponent(effectiveRoute)}`;
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'X-Agent-Token': agentToken },
    body: formData,
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
  return data;
}

// ============================================================
// SUPPORT TICKETS
// ============================================================
export const getTickets = (params = {}) => {
  const qs = new URLSearchParams(params).toString();
  return request(`tickets${qs ? '?' + qs : ''}`);
};
export const createTicket = (data) => request('tickets', 'POST', data);
export const getTicket = (id) => request(`tickets/${id}`);
export const getTicketMessages = (id) => request(`tickets/${id}`).then(t => t.messages || []);
export const addTicketMessage = (id, message, attachments = null) => request(`tickets/${id}/messages`, 'POST', { message, ...(attachments ? { attachments } : {}) });
export const updateTicketStatus = (id, data) => request(`tickets/${id}`, 'PUT', data);
export const getOpenTicketCount = () => request('admin/tickets/count');

/**
 * Upload ticket images (up to 5). Works for both agents and admins.
 */
export async function uploadTicketImages(files) {
  const formData = new FormData();
  const maxFiles = Math.min(files.length, 5);
  for (let i = 0; i < maxFiles; i++) {
    formData.append('images[]', files[i]);
  }

  let effectiveRoute = 'agent/ticket-images';
  const headers = {};
  if (isAgent) {
    const agentToken = localStorage.getItem('omni_agent_token');
    if (agentToken) headers['X-Agent-Token'] = agentToken;
  } else if (isAdmin) {
    effectiveRoute = 'admin/tickets/upload-images';
    const adminToken = localStorage.getItem('omni_admin_token');
    if (adminToken) headers['X-Admin-Token'] = adminToken;
  }

  const url = `${API_BASE}?route=${encodeURIComponent(effectiveRoute)}`;
  const res = await fetch(url, {
    method: 'POST',
    headers,
    credentials: isAdmin ? 'include' : 'same-origin',
    body: formData,
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
  return data;
}

/**
 * Upload images via public endpoint (no auth, for login screen support form)
 */
export async function uploadPublicImages(files) {
  const formData = new FormData();
  const maxFiles = Math.min(files.length, 5);
  for (let i = 0; i < maxFiles; i++) {
    formData.append('images[]', files[i]);
  }
  const url = `${API_BASE}?route=public/upload-images`;
  const res = await fetch(url, { method: 'POST', body: formData });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
  return data;
}

/**
 * Submit public support ticket from login screen (no auth)
 */
export async function submitPublicSupportTicket(data) {
  const url = `${API_BASE}?route=public/support-ticket`;
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  const result = await res.json();
  if (!res.ok) throw new Error(result.error || `HTTP ${res.status}`);
  return result;
}
