import { useState, useEffect, useRef } from 'react';
import { Loader2, Shield, Key, Headphones, ArrowLeft, Mail, CheckCircle, LifeBuoy, X, Paperclip, Image as ImageIcon, Moon, Sun } from 'lucide-react';
import { API_BASE, uploadPublicImages, submitPublicSupportTicket } from '../api';

export default function LoginScreen({ onLogin }) {
  const [loginMode, setLoginMode] = useState('agent'); // 'agent' | 'admin' | 'client'
  const [apiKey, setApiKey] = useState('');
  const [adminUser, setAdminUser] = useState('');
  const [adminPass, setAdminPass] = useState('');
  const [agentEmail, setAgentEmail] = useState('');
  const [agentPass, setAgentPass] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [robotState, setRobotState] = useState('waving');
  const [exiting, setExiting] = useState(false);
  const [speechText, setSpeechText] = useState('¡Hola! 👋 Ingresa tus credenciales');

  // Prohibited characters to prevent injection
  const PROHIBITED_CHARS = /[<>"';\\`{}|]/;
  const PROHIBITED_LIST = '< > " \' ; \\ ` { } |';

  function hasProhibitedChars(value) {
    return PROHIBITED_CHARS.test(value);
  }
  const formRef = useRef(null);

  // Forgot / Reset password state
  const [forgotMode, setForgotMode] = useState(false);       // show forgot form
  const [forgotEmail, setForgotEmail] = useState('');
  const [forgotSent, setForgotSent] = useState(false);       // email sent confirmation
  const [resetMode, setResetMode] = useState(false);          // show reset form (from URL token)
  const [resetToken, setResetToken] = useState('');
  const [resetEmail, setResetEmail] = useState('');
  const [resetNewPass, setResetNewPass] = useState('');
  const [resetConfirmPass, setResetConfirmPass] = useState('');
  const [resetSuccess, setResetSuccess] = useState(false);
  const [resetAgentName, setResetAgentName] = useState('');

  // Support form state (login screen)
  const [showSupport, setShowSupport] = useState(false);
  const [supportForm, setSupportForm] = useState({ name: '', email: '', user_type: 'agent', description: '' });
  const [supportImages, setSupportImages] = useState([]);
  const [supportSending, setSupportSending] = useState(false);
  const [supportError, setSupportError] = useState('');
  const [supportSent, setSupportSent] = useState(false);
  const [supportTicketNum, setSupportTicketNum] = useState('');
  const supportFileRef = useRef(null);

  // Dark mode toggle
  const [darkMode, setDarkMode] = useState(() => localStorage.getItem('omni_theme') === 'dark');
  function toggleDark() {
    const next = !darkMode;
    setDarkMode(next);
    const root = document.documentElement;
    root.setAttribute('data-theme', next ? 'dark' : 'light');
    root.classList.toggle('dark', next);
    localStorage.setItem('omni_theme', next ? 'dark' : 'light');
  }

  // Check for existing admin or agent token
  useEffect(() => {
    const adminToken = localStorage.getItem('omni_admin_token');
    if (adminToken) {
      fetch(`${API_BASE}?route=admin/session-check`, {
        headers: { 'X-Admin-Token': adminToken }
      })
        .then(r => r.json())
        .then(data => {
          if (data.authenticated && data.role === 'admin') {
            setSpeechText(`¡Hola ${data.user}! 👋 Sesión activa`);
            setExiting(true);
            setTimeout(() => onLogin('__wp_admin_session__'), 800);
          }
        })
        .catch(() => {});
      return;
    }
    const agentToken = localStorage.getItem('omni_agent_token');
    if (agentToken) {
      fetch(`${API_BASE}?route=agent/session-check`, {
        headers: { 'X-Agent-Token': agentToken }
      })
        .then(r => r.json())
        .then(data => {
          if (data.expired) {
            localStorage.removeItem('omni_agent_token');
            localStorage.removeItem('omni_agent_data');
            setError('⛔ El período de servicio ha expirado. Contacta al administrador.');
            setSpeechText('Período expirado 😔');
            return;
          }
          if (data.authenticated && data.role === 'agent') {
            localStorage.setItem('omni_agent_data', JSON.stringify(data.agent));
            if (data.period_warning) {
              localStorage.setItem('omni_period_warning', JSON.stringify(data.period_warning));
            }
            setSpeechText(`¡Hola ${data.agent.name}! 👋 Sesión activa`);
            setExiting(true);
            setTimeout(() => onLogin('__agent_session__'), 800);
          }
        })
        .catch(() => {});
    }
  }, []);

  // Check URL for reset_token (from password reset email)
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const token = params.get('reset_token');
    const email = params.get('email');
    if (token && email) {
      setResetToken(token);
      setResetEmail(email);
      setLoading(true);
      setSpeechText('Verificando enlace de recuperación... 🔍');
      fetch(`${API_BASE}?route=agent/validate-reset-token`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, token }),
      })
        .then(r => r.json())
        .then(data => {
          if (data.valid) {
            setResetMode(true);
            setResetAgentName(data.agent_name);
            setSpeechText(`Hola ${data.agent_name}, ingresa tu nueva contraseña 🔐`);
          } else {
            setError(data.error || 'Token inválido');
            setSpeechText('El enlace no es válido o ha expirado 😕');
          }
          setLoading(false);
        })
        .catch(() => {
          setError('Error de conexión');
          setLoading(false);
        });
      // Clean URL to remove token
      window.history.replaceState({}, '', window.location.pathname);
    }
  }, []);

  function switchMode(mode) {
    setLoginMode(mode);
    setError('');
    setForgotMode(false);
    setForgotSent(false);
    setSpeechText(
      mode === 'agent'
        ? '🎧 Ingresa tu email y contraseña de agente'
        : mode === 'admin'
        ? '🔐 Ingresa tu usuario y contraseña de WordPress'
        : '🔑 Ingresa la API Key de tu empresa'
    );
  }
  async function handleSupportSubmit(e) {
    e.preventDefault();
    setSupportError('');
    if (!supportForm.name.trim() || !supportForm.email.trim() || !supportForm.description.trim()) {
      setSupportError('Nombre, email y descripción son requeridos');
      return;
    }
    setSupportSending(true);
    try {
      let attachments = null;
      if (supportImages.length > 0) {
        const uploaded = await uploadPublicImages(supportImages);
        if (uploaded.urls?.length) attachments = uploaded.urls;
      }
      const result = await submitPublicSupportTicket({ ...supportForm, ...(attachments ? { attachments } : {}) });
      setSupportSent(true);
      setSupportTicketNum(result.ticket_number || '');
    } catch (err) {
      setSupportError(err.message);
    } finally {
      setSupportSending(false);
    }
  }
  async function handleAdminSubmit(e) {
    e.preventDefault();
    if (!adminUser.trim() || !adminPass) return;
    if (hasProhibitedChars(adminUser) || hasProhibitedChars(adminPass)) {
      setError(`Caracteres no permitidos: ${PROHIBITED_LIST}`);
      return;
    }

    setLoading(true);
    setError('');
    setRobotState('thinking');
    setSpeechText('Verificando credenciales admin... 🔍');

    try {
      const res = await fetch(`${API_BASE}?route=admin/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: adminUser.trim(), password: adminPass }),
      });
      const data = await res.json();

      if (!res.ok) {
        throw new Error(data.error || 'Error de autenticación');
      }

      // Store token
      localStorage.setItem('omni_admin_token', data.token);
      localStorage.setItem('omni_admin_user', data.user);

      setRobotState('celebrating');
      setSpeechText(`¡Bienvenido ${data.user}! 🎉`);
      setExiting(true);
      setTimeout(() => onLogin('__wp_admin_session__'), 1200);
    } catch (err) {
      setError(err.message || 'Error de conexión');
      setRobotState('waving');
      setSpeechText('Credenciales incorrectas 🤔');
      setLoading(false);
    }
  }

  async function handleClientSubmit(e) {
    e.preventDefault();
    if (!apiKey.trim()) return;
    if (hasProhibitedChars(apiKey)) {
      setError(`Caracteres no permitidos: ${PROHIBITED_LIST}`);
      return;
    }

    setLoading(true);
    setError('');
    setRobotState('thinking');
    setSpeechText('Verificando API Key... 🔍');

    try {
      const res = await fetch(`${API_BASE}?route=channels`, {
        headers: { 'X-API-Key': apiKey.trim(), 'Content-Type': 'application/json' }
      });

      if (!res.ok) {
        const data = await res.json();
        if (data.expired) {
          setError('⛔ Tu período de servicio ha expirado. Contacta al administrador para renovar tu acceso.');
          setRobotState('waving');
          setSpeechText('Período expirado 😔 Contacta al admin');
          setLoading(false);
          return;
        }
        throw new Error(data.error || 'API key inválida');
      }

      // Fetch period status to check for warnings
      try {
        const pwRes = await fetch(`${API_BASE}?route=period-status`, {
          headers: { 'X-API-Key': apiKey.trim() }
        });
        if (pwRes.ok) {
          const pw = await pwRes.json();
          if (pw && pw.warning) {
            localStorage.setItem('omni_period_warning', JSON.stringify(pw));
          }
        }
      } catch {}

      setRobotState('celebrating');
      setSpeechText('¡Bienvenido! 🎉 Acceso concedido');
      setExiting(true);
      setTimeout(() => onLogin(apiKey.trim()), 1200);
    } catch (err) {
      setError(err.message || 'Error de conexión');
      setRobotState('waving');
      setSpeechText('Hmm, no pude verificar. Intenta de nuevo 🤔');
      setLoading(false);
    }
  }

  async function handleAgentSubmit(e) {
    e.preventDefault();
    if (!agentEmail.trim() || !agentPass) return;
    if (hasProhibitedChars(agentEmail) || hasProhibitedChars(agentPass)) {
      setError(`Caracteres no permitidos: ${PROHIBITED_LIST}`);
      return;
    }

    setLoading(true);
    setError('');
    setRobotState('thinking');
    setSpeechText('Verificando credenciales de agente... 🔍');

    try {
      const res = await fetch(`${API_BASE}?route=agent/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: agentEmail.trim(), password: agentPass }),
      });
      const data = await res.json();

      if (!res.ok) {
        throw new Error(data.error || 'Error de autenticación');
      }

      // Check for expired period
      if (data.expired) {
        setError(`⛔ ${data.error || 'El período de servicio del cliente ha expirado. Contacta al administrador.'}`);
        setRobotState('waving');
        setSpeechText('Período expirado 😔 Contacta al admin');
        setLoading(false);
        return;
      }

      // Store token and agent data
      localStorage.setItem('omni_agent_token', data.token);
      localStorage.setItem('omni_agent_data', JSON.stringify(data.agent));
      if (data.period_warning) {
        localStorage.setItem('omni_period_warning', JSON.stringify(data.period_warning));
      }

      setRobotState('celebrating');
      setSpeechText(`¡Bienvenido ${data.agent.name}! 🎉`);
      setExiting(true);
      setTimeout(() => onLogin('__agent_session__'), 1200);
    } catch (err) {
      setError(err.message || 'Error de conexión');
      setRobotState('waving');
      setSpeechText('Credenciales incorrectas 🤔');
      setLoading(false);
    }
  }

  async function handleForgotSubmit(e) {
    e.preventDefault();
    if (!forgotEmail.trim()) return;
    if (hasProhibitedChars(forgotEmail)) {
      setError(`Caracteres no permitidos: ${PROHIBITED_LIST}`);
      return;
    }

    setLoading(true);
    setError('');
    setRobotState('thinking');
    setSpeechText('Enviando enlace de recuperación... 📧');

    try {
      const res = await fetch(`${API_BASE}?route=agent/forgot-password`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: forgotEmail.trim() }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Error');

      setForgotSent(true);
      setRobotState('celebrating');
      setSpeechText('¡Correo enviado! Revisa tu bandeja 📬');
    } catch (err) {
      setError(err.message);
      setRobotState('waving');
      setSpeechText('Algo falló, intenta de nuevo 🤔');
    } finally {
      setLoading(false);
    }
  }

  async function handleResetSubmit(e) {
    e.preventDefault();
    if (hasProhibitedChars(resetNewPass) || hasProhibitedChars(resetConfirmPass)) {
      setError(`Caracteres no permitidos en contraseña: ${PROHIBITED_LIST}`);
      return;
    }
    if (resetNewPass.length < 8) {
      setError('La contraseña debe tener al menos 8 caracteres');
      return;
    }
    if (!/[A-Z]/.test(resetNewPass)) {
      setError('Debe incluir al menos una letra mayúscula');
      return;
    }
    if (!/[0-9]/.test(resetNewPass)) {
      setError('Debe incluir al menos un número');
      return;
    }
    if (!/[!@#$%^&*()_+=\-~,.?]/.test(resetNewPass)) {
      setError('Debe incluir al menos un carácter especial (!@#$%^&*_+-~,.?)');
      return;
    }
    if (resetNewPass !== resetConfirmPass) {
      setError('Las contraseñas no coinciden');
      return;
    }

    setLoading(true);
    setError('');
    setRobotState('thinking');
    setSpeechText('Guardando nueva contraseña... 🔐');

    try {
      const res = await fetch(`${API_BASE}?route=agent/reset-password`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: resetEmail, token: resetToken, new_password: resetNewPass }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Error');

      setResetSuccess(true);
      setRobotState('celebrating');
      setSpeechText('¡Contraseña actualizada! 🎉');
    } catch (err) {
      setError(err.message);
      setRobotState('waving');
      setSpeechText('Algo falló 🤔');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className={`login-page ${exiting ? 'login-exit' : 'login-enter'}`}>
      {/* Particles */}
      <div className="login-particles">
        <div className="p1" />
        <div className="p2" />
        <div className="p3" />
        <div className="p4" />
        <div className="p5" />
        <div className="p6" />
      </div>

      {/* Theme toggle */}
      <button
        onClick={toggleDark}
        className="fixed top-4 right-4 z-50 p-2.5 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 text-white hover:bg-white/20 transition-all shadow-lg"
        title={darkMode ? 'Modo Claro' : 'Modo Oscuro'}
      >
        {darkMode ? <Sun size={18} /> : <Moon size={18} />}
      </button>

      {/* Content */}
      <div className="flex flex-col items-center relative z-10 w-full max-w-md">
        {/* Robot */}
        <div className={`robot-container ${robotState === 'waving' ? 'robot-waving' : robotState === 'thinking' ? 'robot-thinking' : 'robot-celebrating'}`}>
          <div className="robot-speech-bubble">{speechText}</div>
          <div className="w-[160px] h-[160px] sm:w-[140px] sm:h-[140px] flex items-center justify-center text-6xl select-none" style={{ filter: 'drop-shadow(0 0 20px rgba(6,214,160,0.4))' }}>
            🤖
          </div>
          <div className="robot-glow" />
        </div>

        {/* Login Card */}
        <div className="login-card">
          <div className="login-card-header">
            <img src={`${import.meta.env.BASE_URL}logo-automatiza-tech.png`} alt="AutomatizaTech" className="login-logo" />
            <h1>Autom<span>atiza</span>Tech</h1>
            <p>Portal Omnicanal de Clientes</p>
          </div>

          {/* Mode Tabs — hide during password reset */}
          {!resetMode && (
          <div className="login-tabs flex rounded-xl bg-gray-100 p-1 mb-5">
            <button
              type="button"
              onClick={() => switchMode('agent')}
              className={`flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-medium transition-all ${
                loginMode === 'agent'
                  ? 'login-tab-active bg-white text-indigo-700 shadow-sm'
                  : 'text-gray-500 hover:text-gray-700'
              }`}
            >
              <Headphones size={13} /> Agente
            </button>
            <button
              type="button"
              onClick={() => switchMode('admin')}
              className={`flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-medium transition-all ${
                loginMode === 'admin'
                  ? 'login-tab-active bg-white text-green-700 shadow-sm'
                  : 'text-gray-500 hover:text-gray-700'
              }`}
            >
              <Shield size={13} /> Admin
            </button>
            <button
              type="button"
              onClick={() => switchMode('client')}
              className={`flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-medium transition-all ${
                loginMode === 'client'
                  ? 'login-tab-active bg-white text-blue-700 shadow-sm'
                  : 'text-gray-500 hover:text-gray-700'
              }`}
            >
              <Key size={13} /> Cliente
            </button>
          </div>
          )}

          {error && <div className="login-error">{error}</div>}

          {/* Client Form */}
          {loginMode === 'client' && !resetMode && (
            <form onSubmit={handleClientSubmit} ref={formRef}>
              <div className="mb-5">
                <label className="block text-sm font-medium text-gray-600 mb-1.5">API Key de tu empresa</label>
                <div className="relative">
                  <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">🔑</span>
                  <input
                    type="password"
                    value={apiKey}
                    onChange={e => setApiKey(e.target.value)}
                    placeholder="Ingresa tu API Key..."
                    className="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-[14px] text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-4 focus:ring-blue-800/10 focus:border-blue-600 transition-all"
                    autoFocus
                    autoComplete="off"
                  />
                </div>
              </div>

              <button
                type="submit"
                disabled={loading || !apiKey.trim()}
                className="w-full py-3 rounded-[14px] font-semibold text-white transition-all btn-hover disabled:opacity-50 disabled:transform-none"
                style={{ background: loading ? '#94a3b8' : 'linear-gradient(135deg, #1e40af, #1e3a8a)' }}
              >
                {loading ? (
                  <span className="flex items-center justify-center gap-2">
                    <Loader2 size={18} className="animate-spin" />
                    Verificando...
                  </span>
                ) : (
                  'Acceder al Portal'
                )}
              </button>

              <p className="text-center text-xs text-gray-400 mt-4">
                Obtén tu API Key contactando al administrador de{' '}
                <span className="text-[var(--color-secondary)] font-medium">AutomatizaTech</span>
              </p>
              <button
                type="button"
                onClick={() => { setShowSupport(true); setSupportSent(false); setSupportError(''); setSupportForm({ name: '', email: '', user_type: 'client', description: '' }); setSupportImages([]); }}
                className="w-full text-center text-xs text-gray-400 hover:text-indigo-500 mt-2 transition-colors flex items-center justify-center gap-1"
              >
                <LifeBuoy size={12} /> ¿Problemas para iniciar sesión?
              </button>
            </form>
          )}

          {/* Admin Form */}
          {loginMode === 'admin' && !resetMode && (
            <form onSubmit={handleAdminSubmit}>
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-600 mb-1.5">Usuario WordPress</label>
                <div className="relative">
                  <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">👤</span>
                  <input
                    type="text"
                    value={adminUser}
                    onChange={e => setAdminUser(e.target.value)}
                    placeholder="Tu usuario de WordPress..."
                    className="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-[14px] text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-4 focus:ring-green-800/10 focus:border-green-600 transition-all"
                    autoFocus
                    autoComplete="username"
                  />
                </div>
              </div>

              <div className="mb-5">
                <label className="block text-sm font-medium text-gray-600 mb-1.5">Contraseña</label>
                <div className="relative">
                  <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">🔒</span>
                  <input
                    type="password"
                    value={adminPass}
                    onChange={e => setAdminPass(e.target.value)}
                    placeholder="Tu contraseña..."
                    className="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-[14px] text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-4 focus:ring-green-800/10 focus:border-green-600 transition-all"
                    autoComplete="current-password"
                  />
                </div>
              </div>

              <button
                type="submit"
                disabled={loading || !adminUser.trim() || !adminPass}
                className="w-full py-3 rounded-[14px] font-semibold text-white transition-all btn-hover disabled:opacity-50 disabled:transform-none"
                style={{ background: loading ? '#94a3b8' : 'linear-gradient(135deg, #06d6a0, #059669)' }}
              >
                {loading ? (
                  <span className="flex items-center justify-center gap-2">
                    <Loader2 size={18} className="animate-spin" />
                    Verificando...
                  </span>
                ) : (
                  <span className="flex items-center justify-center gap-2">
                    <Shield size={18} />
                    Entrar como Admin
                  </span>
                )}
              </button>

              <p className="text-center text-xs text-gray-400 mt-4">
                Usa las credenciales de tu cuenta de WordPress admin
              </p>
              <button
                type="button"
                onClick={() => { setShowSupport(true); setSupportSent(false); setSupportError(''); setSupportForm({ name: '', email: '', user_type: 'admin', description: '' }); setSupportImages([]); }}
                className="w-full text-center text-xs text-gray-400 hover:text-indigo-500 mt-2 transition-colors flex items-center justify-center gap-1"
              >
                <LifeBuoy size={12} /> ¿Problemas para iniciar sesión?
              </button>
            </form>
          )}

          {/* Agent Form */}
          {loginMode === 'agent' && !forgotMode && !resetMode && (
            <form onSubmit={handleAgentSubmit}>
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-600 mb-1.5">Email del agente</label>
                <div className="relative">
                  <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">📧</span>
                  <input
                    type="email"
                    value={agentEmail}
                    onChange={e => setAgentEmail(e.target.value)}
                    placeholder="tu@email.com"
                    className="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-[14px] text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-4 focus:ring-indigo-800/10 focus:border-indigo-600 transition-all"
                    autoFocus
                    autoComplete="email"
                  />
                </div>
              </div>

              <div className="mb-5">
                <label className="block text-sm font-medium text-gray-600 mb-1.5">Contraseña</label>
                <div className="relative">
                  <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">🔒</span>
                  <input
                    type="password"
                    value={agentPass}
                    onChange={e => setAgentPass(e.target.value)}
                    placeholder="Tu contraseña..."
                    className="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-[14px] text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-4 focus:ring-indigo-800/10 focus:border-indigo-600 transition-all"
                    autoComplete="current-password"
                  />
                </div>
              </div>

              <button
                type="submit"
                disabled={loading || !agentEmail.trim() || !agentPass}
                className="w-full py-3 rounded-[14px] font-semibold text-white transition-all btn-hover disabled:opacity-50 disabled:transform-none"
                style={{ background: loading ? '#94a3b8' : 'linear-gradient(135deg, #6366f1, #4f46e5)' }}
              >
                {loading ? (
                  <span className="flex items-center justify-center gap-2">
                    <Loader2 size={18} className="animate-spin" />
                    Verificando...
                  </span>
                ) : (
                  <span className="flex items-center justify-center gap-2">
                    <Headphones size={18} />
                    Entrar como Agente
                  </span>
                )}
              </button>

              <p className="text-center text-xs text-gray-400 mt-4">
                Tu administrador debe haberte creado una cuenta con contraseña
              </p>

              <button
                type="button"
                onClick={() => { setForgotMode(true); setForgotEmail(agentEmail); setError(''); setSpeechText('📧 Ingresa tu email para recuperar tu contraseña'); }}
                className="w-full text-center text-xs text-indigo-500 hover:text-indigo-700 mt-3 font-medium transition-colors"
              >
                ¿Olvidaste tu contraseña?
              </button>
              <button
                type="button"
                onClick={() => { setShowSupport(true); setSupportSent(false); setSupportError(''); setSupportForm({ name: '', email: agentEmail || '', user_type: 'agent', description: '' }); setSupportImages([]); }}
                className="w-full text-center text-xs text-gray-400 hover:text-indigo-500 mt-2 transition-colors flex items-center justify-center gap-1"
              >
                <LifeBuoy size={12} /> ¿Problemas para iniciar sesión?
              </button>
            </form>
          )}

          {/* Forgot Password Form (Agent) */}
          {loginMode === 'agent' && forgotMode && !forgotSent && !resetMode && (
            <div>
              <button
                type="button"
                onClick={() => { setForgotMode(false); setError(''); setSpeechText('🎧 Ingresa tu email y contraseña de agente'); }}
                className="flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700 mb-4 transition-colors"
              >
                <ArrowLeft size={14} /> Volver al login
              </button>
              <form onSubmit={handleForgotSubmit}>
                <div className="mb-5">
                  <label className="block text-sm font-medium text-gray-600 mb-1.5">Email del agente</label>
                  <div className="relative">
                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">📧</span>
                    <input
                      type="email"
                      value={forgotEmail}
                      onChange={e => setForgotEmail(e.target.value)}
                      placeholder="tu@email.com"
                      className="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-[14px] text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-4 focus:ring-indigo-800/10 focus:border-indigo-600 transition-all"
                      autoFocus
                      autoComplete="email"
                    />
                  </div>
                </div>
                <button
                  type="submit"
                  disabled={loading || !forgotEmail.trim()}
                  className="w-full py-3 rounded-[14px] font-semibold text-white transition-all btn-hover disabled:opacity-50"
                  style={{ background: loading ? '#94a3b8' : 'linear-gradient(135deg, #6366f1, #4f46e5)' }}
                >
                  {loading ? (
                    <span className="flex items-center justify-center gap-2">
                      <Loader2 size={18} className="animate-spin" /> Enviando...
                    </span>
                  ) : (
                    <span className="flex items-center justify-center gap-2">
                      <Mail size={18} /> Enviar enlace de recuperación
                    </span>
                  )}
                </button>
              </form>
            </div>
          )}

          {/* Forgot Password — Email Sent Confirmation */}
          {loginMode === 'agent' && forgotMode && forgotSent && !resetMode && (
            <div className="text-center py-4">
              <div className="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
                <CheckCircle size={32} className="text-green-600" />
              </div>
              <h3 className="text-lg font-semibold text-gray-800 mb-2">¡Correo enviado!</h3>
              <p className="text-sm text-gray-500 mb-4">
                Si tu email está registrado, recibirás un enlace para restablecer tu contraseña.
                Revisa tu bandeja de entrada y spam.
              </p>
              <button
                type="button"
                onClick={() => { setForgotMode(false); setForgotSent(false); setSpeechText('🎧 Ingresa tu email y contraseña de agente'); }}
                className="text-sm text-indigo-600 hover:text-indigo-800 font-medium transition-colors"
              >
                ← Volver al login
              </button>
            </div>
          )}

          {/* Reset Password Form (from email link) */}
          {resetMode && !resetSuccess && (
            <div>
              <div className="text-center mb-5">
                <div className="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center mx-auto mb-3">
                  <Key size={24} className="text-indigo-600" />
                </div>
                <h3 className="text-lg font-semibold text-gray-800">Nueva contraseña</h3>
                <p className="text-xs text-gray-500 mt-1">Hola {resetAgentName}, ingresa tu nueva contraseña</p>
              </div>
              <form onSubmit={handleResetSubmit}>
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-600 mb-1.5">Nueva contraseña</label>
                  <div className="relative">
                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">🔒</span>
                    <input
                      type="password"
                      value={resetNewPass}
                      onChange={e => setResetNewPass(e.target.value)}
                      placeholder="Mín. 8 chars, mayúscula, número, especial"
                      className="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-[14px] text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-4 focus:ring-indigo-800/10 focus:border-indigo-600 transition-all"
                      autoFocus
                      autoComplete="new-password"
                      minLength={8}
                    />
                  </div>
                  <p className="mt-1.5 text-[10px] text-gray-400">
                    Mín. 8 caracteres · 1 mayúscula · 1 número · 1 especial (!@#$%^&*_+-~,.?) · Prohibidos: {PROHIBITED_LIST}
                  </p>
                </div>
                <div className="mb-5">
                  <label className="block text-sm font-medium text-gray-600 mb-1.5">Confirmar contraseña</label>
                  <div className="relative">
                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">🔒</span>
                    <input
                      type="password"
                      value={resetConfirmPass}
                      onChange={e => setResetConfirmPass(e.target.value)}
                      placeholder="Repite la contraseña"
                      className="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-[14px] text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-4 focus:ring-indigo-800/10 focus:border-indigo-600 transition-all"
                      autoComplete="new-password"
                      minLength={8}
                    />
                  </div>
                </div>
                <button
                  type="submit"
                  disabled={loading || resetNewPass.length < 8 || resetNewPass !== resetConfirmPass}
                  className="w-full py-3 rounded-[14px] font-semibold text-white transition-all btn-hover disabled:opacity-50"
                  style={{ background: loading ? '#94a3b8' : 'linear-gradient(135deg, #6366f1, #4f46e5)' }}
                >
                  {loading ? (
                    <span className="flex items-center justify-center gap-2">
                      <Loader2 size={18} className="animate-spin" /> Guardando...
                    </span>
                  ) : (
                    'Guardar nueva contraseña'
                  )}
                </button>
              </form>
            </div>
          )}

          {/* Reset Password Success */}
          {resetMode && resetSuccess && (
            <div className="text-center py-4">
              <div className="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
                <CheckCircle size={32} className="text-green-600" />
              </div>
              <h3 className="text-lg font-semibold text-gray-800 mb-2">¡Contraseña actualizada!</h3>
              <p className="text-sm text-gray-500 mb-4">
                Tu contraseña ha sido cambiada exitosamente. Ya puedes iniciar sesión con tus nuevas credenciales.
              </p>
              <button
                type="button"
                onClick={() => { setResetMode(false); setResetSuccess(false); setLoginMode('agent'); setSpeechText('🎧 Ingresa tu email y nueva contraseña'); }}
                className="inline-flex items-center gap-2 px-5 py-2.5 rounded-[12px] font-semibold text-white text-sm transition-all"
                style={{ background: 'linear-gradient(135deg, #6366f1, #4f46e5)' }}
              >
                <Headphones size={16} /> Iniciar sesión
              </button>
            </div>
          )}
        </div>

        {/* Channels Preview */}
        <div className="flex justify-center gap-5 mt-6">
          {[
            { name: 'WhatsApp', color: '#25D366', icon: '💬' },
            { name: 'Instagram', color: '#E4405F', icon: '📸' },
            { name: 'Telegram', color: '#229ED9', icon: '✈️' },
            { name: 'Messenger', color: '#1877F2', icon: '💬' },
          ].map(ch => (
            <div key={ch.name} className="flex flex-col items-center gap-1">
              <div
                className="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm shadow-lg"
                style={{ background: ch.color }}
              >
                {ch.icon}
              </div>
              <span className="text-[10px] text-white/50">{ch.name}</span>
            </div>
          ))}
        </div>

        {/* Branding */}
        <div className="login-branding">
          Powered by <span>AutomatizaTech</span>
        </div>
      </div>

      {/* Support form modal */}
      {showSupport && (
        <div className="fixed inset-0 bg-black/60 z-[100] flex items-center justify-center p-4" onClick={() => setShowSupport(false)}>
          <div className="support-modal-card bg-white rounded-2xl shadow-2xl max-w-md w-full max-h-[90vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
            <div className="p-5 border-b border-gray-100 flex items-center justify-between">
              <h3 className="font-semibold text-gray-900 flex items-center gap-2"><LifeBuoy size={18} className="text-indigo-600" /> Solicitud de Soporte</h3>
              <button onClick={() => setShowSupport(false)} className="p-1 hover:bg-gray-100 rounded-lg text-gray-500"><X size={18} /></button>
            </div>

            {supportSent ? (
              <div className="p-6 text-center">
                <div className="w-14 h-14 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-3">
                  <CheckCircle size={28} className="text-green-600" />
                </div>
                <h4 className="text-lg font-semibold text-gray-900 mb-1">¡Solicitud enviada!</h4>
                {supportTicketNum && <p className="text-sm text-gray-500 mb-1">Número de ticket: <strong className="text-indigo-600">{supportTicketNum}</strong></p>}
                <p className="text-sm text-gray-500 mb-4">Nuestro equipo revisará tu caso y te contactará por email.</p>
                <button onClick={() => setShowSupport(false)} className="px-5 py-2 text-sm font-semibold text-white rounded-xl" style={{ background: 'linear-gradient(135deg, #6366f1, #4f46e5)' }}>Cerrar</button>
              </div>
            ) : (
              <form onSubmit={handleSupportSubmit} className="p-5 space-y-3.5">
                {supportError && (
                  <div className="flex items-center gap-2 p-2.5 rounded-lg text-sm bg-red-50 text-red-700 border border-red-200">
                    <span>⚠️</span> {supportError}
                  </div>
                )}
                <div>
                  <label className="block text-xs font-medium text-gray-600 mb-1">Nombre completo *</label>
                  <input
                    type="text"
                    value={supportForm.name}
                    onChange={e => setSupportForm(f => ({ ...f, name: e.target.value }))}
                    className="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="Tu nombre"
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-600 mb-1">Email *</label>
                  <input
                    type="email"
                    value={supportForm.email}
                    onChange={e => setSupportForm(f => ({ ...f, email: e.target.value }))}
                    className="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="tu@email.com"
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-600 mb-1">Tipo de usuario</label>
                  <select
                    value={supportForm.user_type}
                    onChange={e => setSupportForm(f => ({ ...f, user_type: e.target.value }))}
                    className="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 text-gray-900"
                  >
                    <option value="agent">Agente</option>
                    <option value="admin">Administrador</option>
                    <option value="client">Cliente</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-600 mb-1">Describe el problema *</label>
                  <textarea
                    value={supportForm.description}
                    onChange={e => setSupportForm(f => ({ ...f, description: e.target.value }))}
                    rows={3}
                    className="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none"
                    placeholder="¿Qué problema tienes al iniciar sesión?"
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-600 mb-1">Capturas de error (máx. 5)</label>
                  <input ref={supportFileRef} type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple className="hidden" onChange={e => {
                    const files = Array.from(e.target.files || []);
                    setSupportImages(prev => [...prev, ...files].slice(0, 5));
                    e.target.value = '';
                  }} />
                  <button type="button" onClick={() => supportFileRef.current?.click()} disabled={supportImages.length >= 5}
                    className="w-full flex items-center justify-center gap-2 px-3 py-2.5 text-sm border-2 border-dashed border-gray-200 rounded-xl text-gray-500 hover:border-indigo-400 hover:text-indigo-600 transition-colors disabled:opacity-30">
                    <ImageIcon size={16} /> {supportImages.length === 0 ? 'Agregar capturas' : `${supportImages.length}/5 imágenes`}
                  </button>
                  {supportImages.length > 0 && (
                    <div className="flex flex-wrap gap-2 mt-2">
                      {supportImages.map((f, i) => (
                        <div key={i} className="relative group">
                          <img src={URL.createObjectURL(f)} alt="" className="w-14 h-14 object-cover rounded-lg border border-gray-200" />
                          <button type="button" onClick={() => setSupportImages(prev => prev.filter((_, idx) => idx !== i))} className="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">×</button>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
                <div className="flex justify-end gap-2 pt-1">
                  <button type="button" onClick={() => setShowSupport(false)} className="px-4 py-2 text-sm bg-gray-100 text-gray-600 rounded-xl hover:bg-gray-200">Cancelar</button>
                  <button type="submit" disabled={supportSending} className="px-4 py-2 text-sm text-white rounded-xl font-semibold disabled:opacity-50 flex items-center gap-1.5" style={{ background: 'linear-gradient(135deg, #6366f1, #4f46e5)' }}>
                    {supportSending ? <Loader2 size={14} className="animate-spin" /> : <LifeBuoy size={14} />} Enviar Solicitud
                  </button>
                </div>
              </form>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
