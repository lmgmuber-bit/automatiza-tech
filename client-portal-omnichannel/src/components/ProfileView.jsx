import { useState, useEffect, useRef } from 'react';
import { User, Camera, Lock, Save, Loader2, CheckCircle, AlertCircle, Eye, EyeOff, Mail, X, ZoomIn, MessageSquare, Layers } from 'lucide-react';
import { getAgentProfile, updateAgentProfile, uploadAvatar, requestPasswordCode, changePasswordWithCode, getAgentData } from '../api';

const roleLabels = { admin: 'Administrador', supervisor: 'Supervisor', agent: 'Agente' };

export default function ProfileView() {
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [editForm, setEditForm] = useState({ name: '', department: '' });
  const [message, setMessage] = useState(null);

  // Avatar
  const [uploading, setUploading] = useState(false);
  const [showAvatarModal, setShowAvatarModal] = useState(false);
  const fileRef = useRef(null);

  // Password change flow
  const [pwStep, setPwStep] = useState(0); // 0=idle, 1=enter old, 2=enter new, 3=enter code
  const [oldPassword, setOldPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [verifyCode, setVerifyCode] = useState('');
  const [pwLoading, setPwLoading] = useState(false);
  const [pwError, setPwError] = useState('');
  const [pwSuccess, setPwSuccess] = useState('');
  const [showOld, setShowOld] = useState(false);
  const [showNew, setShowNew] = useState(false);

  useEffect(() => { loadProfile(); }, []);

  async function loadProfile() {
    setLoading(true);
    try {
      const data = await getAgentProfile();
      setProfile(data);
      setEditForm({ name: data.name || '', department: data.department || '' });
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  }

  async function handleSaveProfile(e) {
    e.preventDefault();
    if (!editForm.name.trim()) return;
    setSaving(true);
    setMessage(null);
    try {
      await updateAgentProfile(editForm);
      setMessage({ type: 'success', text: 'Perfil actualizado correctamente' });
      // Update localStorage agent data
      const agentData = getAgentData();
      if (agentData) {
        agentData.name = editForm.name;
        agentData.department = editForm.department;
        localStorage.setItem('omni_agent_data', JSON.stringify(agentData));
      }
      window.dispatchEvent(new Event('agentDataUpdated'));
      loadProfile();
    } catch (err) {
      setMessage({ type: 'error', text: err.message });
    } finally {
      setSaving(false);
    }
  }

  async function handleAvatarUpload(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    setUploading(true);
    setMessage(null);
    try {
      const result = await uploadAvatar(file);
      setProfile(prev => ({ ...prev, avatar_url: result.avatar_url }));
      // Update localStorage
      const agentData = getAgentData();
      if (agentData) {
        agentData.avatar_url = result.avatar_url;
        localStorage.setItem('omni_agent_data', JSON.stringify(agentData));
      }
      window.dispatchEvent(new Event('agentDataUpdated'));
      setMessage({ type: 'success', text: 'Foto actualizada' });
    } catch (err) {
      setMessage({ type: 'error', text: err.message });
    } finally {
      setUploading(false);
    }
  }

  // Password change step 1: verify old password
  async function handleRequestCode() {
    if (!oldPassword) { setPwError('Ingresa tu contraseña actual'); return; }
    setPwLoading(true);
    setPwError('');
    try {
      await requestPasswordCode(oldPassword);
      setPwStep(2);
    } catch (err) {
      setPwError(err.message);
    } finally {
      setPwLoading(false);
    }
  }

  // Prohibited characters to prevent injection
  const PROHIBITED_CHARS = /[<>"';\\`{}|]/;
  const PROHIBITED_LIST = '< > " \' ; \\ ` { } |';

  // Password change step 2: validate new password and send code
  function handleNewPasswordNext() {
    setPwError('');
    if (PROHIBITED_CHARS.test(newPassword)) { setPwError(`Caracteres no permitidos: ${PROHIBITED_LIST}`); return; }
    if (newPassword.length < 8) { setPwError('Mínimo 8 caracteres'); return; }
    if (!/[A-Z]/.test(newPassword)) { setPwError('Debe incluir al menos una mayúscula'); return; }
    if (!/[0-9]/.test(newPassword)) { setPwError('Debe incluir al menos un número'); return; }
    if (!/[!@#$%^&*()_+=\-~,.?]/.test(newPassword)) { setPwError('Debe incluir al menos un carácter especial (!@#$%^&*_+-~,.?)'); return; }
    if (newPassword !== confirmPassword) { setPwError('Las contraseñas no coinciden'); return; }
    if (newPassword === oldPassword) { setPwError('La nueva contraseña no puede ser igual a la actual'); return; }
    setPwStep(3);
  }

  // Password change step 3: verify code
  async function handleVerifyCode() {
    if (verifyCode.length !== 6) { setPwError('El código debe ser de 6 dígitos'); return; }
    setPwLoading(true);
    setPwError('');
    try {
      await changePasswordWithCode(verifyCode, newPassword);
      setPwSuccess('¡Contraseña actualizada exitosamente!');
      setPwStep(0);
      setOldPassword('');
      setNewPassword('');
      setConfirmPassword('');
      setVerifyCode('');
    } catch (err) {
      setPwError(err.message);
    } finally {
      setPwLoading(false);
    }
  }

  function resetPasswordFlow() {
    setPwStep(0);
    setOldPassword('');
    setNewPassword('');
    setConfirmPassword('');
    setVerifyCode('');
    setPwError('');
    setPwSuccess('');
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-full text-gray-400">
        <Loader2 className="animate-spin mr-2" size={20} /> Cargando perfil...
      </div>
    );
  }

  if (!profile) {
    return <div className="flex items-center justify-center h-full text-gray-400">No se pudo cargar el perfil</div>;
  }

  return (
    <div className="h-full overflow-y-auto bg-gray-50 dark:bg-slate-900">
      {/* HERO FULL-BLEED */}
      <div className="relative overflow-hidden px-6 py-6 text-white shadow-lg" style={{ backgroundImage: 'linear-gradient(120deg, #6366f1, #8b5cf6)' }}>
        <div className="absolute -top-12 -right-8 w-52 h-52 rounded-full blur-3xl bg-white/20" />
        <div className="absolute inset-0 opacity-[0.06]" style={{ backgroundImage: 'radial-gradient(white 1px, transparent 1px)', backgroundSize: '20px 20px' }} />
        <div className="relative flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-2xl bg-white/15 backdrop-blur-sm ring-1 ring-white/25 flex items-center justify-center">
              <User size={26} />
            </div>
            <div>
              <h1 className="text-2xl font-bold tracking-tight" style={{ fontFamily: 'Poppins, sans-serif' }}>Mi Perfil</h1>
              <p className="text-sm text-white/75">Gestiona tu información personal y contraseña</p>
            </div>
          </div>
        </div>
      </div>

      {/* CONTENIDO */}
      <div className="p-4 sm:p-6">
      <div className="max-w-2xl mx-auto space-y-6">
        {message && (
          <div className={`flex items-center gap-2 p-3 rounded-xl text-sm ring-1 ${message.type === 'success' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20' : 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/20'}`}>
            {message.type === 'success' ? <CheckCircle size={16} /> : <AlertCircle size={16} />}
            {message.text}
          </div>
        )}

        {/* Avatar + Info Card */}
        <div className="bg-white dark:bg-slate-800 rounded-2xl p-4 md:p-6 shadow-md ring-1 ring-gray-100 dark:ring-slate-700/60 hover:shadow-xl transition-all duration-300">
          <div className="flex flex-col items-center gap-4 sm:gap-5">
            {/* Avatar grande + cambiar foto */}
            <div className="relative group cursor-pointer" onClick={() => setShowAvatarModal(true)}>
              {profile.avatar_url ? (
                <img src={profile.avatar_url} alt={profile.name} className="w-36 h-36 sm:w-44 sm:h-44 rounded-full object-cover ring-4 ring-white dark:ring-slate-700 shadow-xl transition-transform group-hover:scale-105" />
              ) : (
                <div className="w-36 h-36 sm:w-44 sm:h-44 rounded-full bg-gradient-to-br from-sky-400 via-indigo-500 to-violet-600 flex items-center justify-center text-white text-5xl sm:text-6xl font-bold ring-4 ring-white dark:ring-slate-700 shadow-xl transition-transform group-hover:scale-105" style={{ fontFamily: 'Poppins, sans-serif' }}>
                  {profile.name?.charAt(0)?.toUpperCase() || '?'}
                </div>
              )}
              <div className="absolute inset-0 rounded-full bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center">
                <div className="opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center text-white">
                  <ZoomIn size={24} />
                  <span className="text-xs font-medium mt-1">Ver foto</span>
                </div>
              </div>
              <button
                onClick={(e) => { e.stopPropagation(); fileRef.current?.click(); }}
                disabled={uploading}
                className="absolute bottom-1 right-1 w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 text-white rounded-full flex items-center justify-center shadow-md hover:shadow-lg transition-all duration-300 ring-2 ring-white dark:ring-slate-800"
                title="Cambiar foto"
              >
                {uploading ? <Loader2 size={16} className="animate-spin" /> : <Camera size={16} />}
              </button>
              <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp,image/gif" onChange={handleAvatarUpload} className="hidden" />
            </div>

            {/* Info */}
            <div className="text-center flex-1">
              <h2 className="text-xl font-bold text-gray-900 dark:text-white" style={{ fontFamily: 'Poppins, sans-serif' }}>{profile.name}</h2>
              <p className="text-sm text-gray-500 dark:text-slate-400 flex items-center gap-1 justify-center mt-0.5">
                <Mail size={14} /> {profile.email}
              </p>
              <div className="flex items-center gap-2 mt-3 justify-center flex-wrap">
                <span className="text-xs font-medium px-2.5 py-1 rounded-full ring-1 bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/20">
                  {roleLabels[profile.role] || profile.role}
                </span>
                {profile.department && (
                  <span className="text-xs font-medium px-2.5 py-1 rounded-full ring-1 bg-gray-50 text-gray-600 ring-gray-200 dark:bg-slate-700/60 dark:text-slate-300 dark:ring-slate-600">{profile.department}</span>
                )}
                <span className="text-xs font-medium px-2.5 py-1 rounded-full ring-1 bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/20">{profile.client_name}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Avatar Modal */}
        {showAvatarModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4" onClick={() => setShowAvatarModal(false)}>
            <div className="relative max-w-lg w-full flex flex-col items-center" onClick={(e) => e.stopPropagation()}>
              {/* Close button */}
              <button
                onClick={() => setShowAvatarModal(false)}
                className="absolute -top-2 -right-2 sm:-top-3 sm:-right-3 z-10 w-9 h-9 sm:w-10 sm:h-10 bg-white rounded-full flex items-center justify-center shadow-lg hover:bg-gray-100 transition-colors text-gray-600"
              >
                <X size={18} />
              </button>

              {/* Full size photo */}
              {profile.avatar_url ? (
                <img
                  src={profile.avatar_url}
                  alt={profile.name}
                  className="w-56 h-56 sm:w-72 sm:h-72 md:w-80 md:h-80 rounded-2xl object-cover shadow-2xl border-4 border-white"
                />
              ) : (
                <div className="w-56 h-56 sm:w-72 sm:h-72 md:w-80 md:h-80 rounded-2xl bg-gradient-to-br from-sky-400 via-indigo-500 to-violet-600 flex items-center justify-center text-white text-6xl sm:text-8xl font-bold shadow-2xl border-4 border-white" style={{ fontFamily: 'Poppins, sans-serif' }}>
                  {profile.name?.charAt(0)?.toUpperCase() || '?'}
                </div>
              )}

              {/* Name */}
              <p className="mt-3 sm:mt-4 text-white text-base sm:text-lg font-semibold">{profile.name}</p>

              {/* Change photo button */}
              <button
                onClick={() => { fileRef.current?.click(); setShowAvatarModal(false); }}
                disabled={uploading}
                className="mt-3 sm:mt-4 flex items-center gap-2 px-5 py-2 sm:px-6 sm:py-2.5 bg-gradient-to-br from-blue-500 to-indigo-600 text-white rounded-full text-sm sm:text-base font-medium shadow-lg hover:shadow-xl transition-all duration-300"
              >
                {uploading ? <Loader2 size={18} className="animate-spin" /> : <Camera size={18} />}
                Cambiar foto de perfil
              </button>
            </div>
          </div>
        )}

        {/* Edit Profile Form */}
        <div className="bg-white dark:bg-slate-800 rounded-2xl p-4 md:p-6 shadow-md ring-1 ring-gray-100 dark:ring-slate-700/60 hover:shadow-xl transition-all duration-300">
          <h3 className="font-semibold text-gray-900 dark:text-white mb-3 sm:mb-4 flex items-center gap-2 text-sm sm:text-base" style={{ fontFamily: 'Poppins, sans-serif' }}>
            <span className="w-7 h-7 rounded-xl flex items-center justify-center bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-md"><User size={15} /></span>
            Editar Información
          </h3>
          <form onSubmit={handleSaveProfile}>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1" style={{ fontFamily: 'Poppins, sans-serif' }}>Nombre completo</label>
                <input
                  type="text"
                  value={editForm.name}
                  onChange={e => setEditForm({ ...editForm, name: e.target.value })}
                  className="w-full px-3 py-2 rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 bg-white dark:bg-slate-900 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  required
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1" style={{ fontFamily: 'Poppins, sans-serif' }}>Email <span className="text-gray-400 dark:text-slate-500 font-normal">(no editable)</span></label>
                <input
                  type="email"
                  value={profile.email}
                  readOnly
                  disabled
                  className="w-full px-3 py-2 rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 text-sm bg-gray-50 dark:bg-slate-900/60 text-gray-500 dark:text-slate-400 opacity-70 cursor-not-allowed"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1" style={{ fontFamily: 'Poppins, sans-serif' }}>Departamento</label>
                <input
                  type="text"
                  value={editForm.department}
                  onChange={e => setEditForm({ ...editForm, department: e.target.value })}
                  className="w-full px-3 py-2 rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 bg-white dark:bg-slate-900 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Ej: Ventas"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1" style={{ fontFamily: 'Poppins, sans-serif' }}>Rol</label>
                <input
                  type="text"
                  value={roleLabels[profile.role] || profile.role}
                  readOnly
                  disabled
                  className="w-full px-3 py-2 rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 text-sm bg-gray-50 dark:bg-slate-900/60 text-gray-500 dark:text-slate-400 opacity-70 cursor-not-allowed"
                />
              </div>
            </div>
            <button
              type="submit"
              disabled={saving}
              className="mt-4 flex items-center gap-2 px-4 py-2 bg-gradient-to-br from-blue-500 to-indigo-600 text-white rounded-lg text-sm font-medium hover:shadow-lg transition-all duration-300 shadow-md disabled:opacity-50"
              style={{ fontFamily: 'Poppins, sans-serif' }}
            >
              {saving ? <Loader2 size={14} className="animate-spin" /> : <Save size={14} />}
              Guardar Cambios
            </button>
          </form>
        </div>

        {/* Change Password */}
        <div className="bg-white dark:bg-slate-800 rounded-2xl p-4 md:p-6 shadow-md ring-1 ring-gray-100 dark:ring-slate-700/60 hover:shadow-xl transition-all duration-300">
          <h3 className="font-semibold text-gray-900 dark:text-white mb-3 sm:mb-4 flex items-center gap-2 text-sm sm:text-base" style={{ fontFamily: 'Poppins, sans-serif' }}>
            <span className="w-7 h-7 rounded-xl flex items-center justify-center bg-gradient-to-br from-amber-400 to-orange-500 text-white shadow-md"><Lock size={15} /></span>
            Cambiar Contraseña
          </h3>

          {pwSuccess && (
            <div className="flex items-center gap-2 p-3 rounded-xl text-sm bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20 mb-4">
              <CheckCircle size={16} /> {pwSuccess}
            </div>
          )}

          {pwStep === 0 && !pwSuccess && (
            <div>
              <p className="text-sm text-gray-500 dark:text-slate-400 mb-4">Para cambiar tu contraseña, primero verifica tu contraseña actual.</p>
              <button
                onClick={() => { setPwStep(1); setPwSuccess(''); }}
                className="flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-200 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-slate-600 ring-1 ring-gray-200 dark:ring-slate-600"
                style={{ fontFamily: 'Poppins, sans-serif' }}
              >
                <Lock size={14} /> Cambiar Contraseña
              </button>
            </div>
          )}

          {pwStep === 1 && (
            <div className="space-y-3">
              <p className="text-sm text-gray-600 dark:text-slate-300 font-medium" style={{ fontFamily: 'Poppins, sans-serif' }}>Paso 1 de 3 — Contraseña actual</p>
              <div className="relative">
                <input
                  type={showOld ? 'text' : 'password'}
                  value={oldPassword}
                  onChange={e => setOldPassword(e.target.value)}
                  placeholder="Tu contraseña actual"
                  className="w-full px-3 py-2 pr-10 rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 bg-white dark:bg-slate-900 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  autoFocus
                />
                <button type="button" onClick={() => setShowOld(!showOld)} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-slate-200">
                  {showOld ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
              {pwError && <p className="text-rose-500 text-xs">{pwError}</p>}
              <div className="flex gap-2">
                <button onClick={handleRequestCode} disabled={pwLoading} className="flex items-center gap-2 px-4 py-2 bg-gradient-to-br from-blue-500 to-indigo-600 text-white rounded-lg text-sm font-medium hover:shadow-lg transition-all duration-300 shadow-md disabled:opacity-50" style={{ fontFamily: 'Poppins, sans-serif' }}>
                  {pwLoading ? <Loader2 size={14} className="animate-spin" /> : 'Verificar'}
                </button>
                <button onClick={resetPasswordFlow} className="px-4 py-2 bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-200 rounded-lg text-sm hover:bg-gray-200 dark:hover:bg-slate-600">Cancelar</button>
              </div>
            </div>
          )}

          {pwStep === 2 && (
            <div className="space-y-3">
              <p className="text-sm text-gray-600 dark:text-slate-300 font-medium" style={{ fontFamily: 'Poppins, sans-serif' }}>Paso 2 de 3 — Nueva contraseña</p>
              <div className="relative">
                <input
                  type={showNew ? 'text' : 'password'}
                  value={newPassword}
                  onChange={e => setNewPassword(e.target.value)}
                  placeholder="Nueva contraseña"
                  className="w-full px-3 py-2 pr-10 rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 bg-white dark:bg-slate-900 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  autoFocus
                />
                <button type="button" onClick={() => setShowNew(!showNew)} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-slate-200">
                  {showNew ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
              <input
                type="password"
                value={confirmPassword}
                onChange={e => setConfirmPassword(e.target.value)}
                placeholder="Confirmar nueva contraseña"
                className="w-full px-3 py-2 rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 bg-white dark:bg-slate-900 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              <div className="text-xs text-gray-400 dark:text-slate-500 space-y-0.5">
                <p className={newPassword.length >= 8 ? 'text-emerald-600 dark:text-emerald-400' : ''}>• Mínimo 8 caracteres</p>
                <p className={/[A-Z]/.test(newPassword) ? 'text-emerald-600 dark:text-emerald-400' : ''}>• Al menos una mayúscula</p>
                <p className={/[0-9]/.test(newPassword) ? 'text-emerald-600 dark:text-emerald-400' : ''}>• Al menos un número</p>
                <p className={/[!@#$%^&*()_+=\-~,.?]/.test(newPassword) ? 'text-emerald-600 dark:text-emerald-400' : ''}>• Al menos un carácter especial (!@#$%^&*_+-~,.?)</p>
                <p className={!PROHIBITED_CHARS.test(newPassword) && newPassword.length > 0 ? 'text-emerald-600 dark:text-emerald-400' : ''}>• Sin caracteres prohibidos: {PROHIBITED_LIST}</p>
              </div>
              {pwError && <p className="text-rose-500 text-xs">{pwError}</p>}
              <div className="flex gap-2">
                <button onClick={handleNewPasswordNext} className="flex items-center gap-2 px-4 py-2 bg-gradient-to-br from-blue-500 to-indigo-600 text-white rounded-lg text-sm font-medium hover:shadow-lg transition-all duration-300 shadow-md" style={{ fontFamily: 'Poppins, sans-serif' }}>
                  Continuar
                </button>
                <button onClick={resetPasswordFlow} className="px-4 py-2 bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-200 rounded-lg text-sm hover:bg-gray-200 dark:hover:bg-slate-600">Cancelar</button>
              </div>
            </div>
          )}

          {pwStep === 3 && (
            <div className="space-y-3">
              <p className="text-sm text-gray-600 dark:text-slate-300 font-medium" style={{ fontFamily: 'Poppins, sans-serif' }}>Paso 3 de 3 — Código de verificación</p>
              <p className="text-xs text-gray-500 dark:text-slate-400">Hemos enviado un código de 6 dígitos a <strong>{profile.email}</strong>. Expira en 5 minutos.</p>
              <input
                type="text"
                value={verifyCode}
                onChange={e => setVerifyCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                placeholder="000000"
                maxLength={6}
                className="w-full px-3 py-3 rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 bg-white dark:bg-slate-900 text-center text-2xl tracking-[0.5em] font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                autoFocus
              />
              {pwError && <p className="text-rose-500 text-xs">{pwError}</p>}
              <div className="flex gap-2">
                <button onClick={handleVerifyCode} disabled={pwLoading || verifyCode.length !== 6} className="flex items-center gap-2 px-4 py-2 bg-gradient-to-br from-blue-500 to-indigo-600 text-white rounded-lg text-sm font-medium hover:shadow-lg transition-all duration-300 shadow-md disabled:opacity-50" style={{ fontFamily: 'Poppins, sans-serif' }}>
                  {pwLoading ? <Loader2 size={14} className="animate-spin" /> : 'Confirmar'}
                </button>
                <button onClick={resetPasswordFlow} className="px-4 py-2 bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-200 rounded-lg text-sm hover:bg-gray-200 dark:hover:bg-slate-600">Cancelar</button>
              </div>
            </div>
          )}
        </div>

        {/* Stats */}
        <div className="bg-white dark:bg-slate-800 rounded-2xl p-4 md:p-6 shadow-md ring-1 ring-gray-100 dark:ring-slate-700/60 hover:shadow-xl transition-all duration-300">
          <h3 className="font-semibold text-gray-900 dark:text-white mb-3 text-sm sm:text-base" style={{ fontFamily: 'Poppins, sans-serif' }}>Estadísticas</h3>
          <div className="grid grid-cols-2 gap-3 sm:gap-4">
            <div className="relative overflow-hidden text-center p-3 sm:p-4 rounded-2xl ring-1 bg-sky-50 ring-sky-200 dark:bg-sky-500/10 dark:ring-sky-500/20 shadow-md">
              <div className="w-10 h-10 mx-auto mb-2 rounded-2xl flex items-center justify-center bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-md"><MessageSquare size={18} /></div>
              <div className="text-xl sm:text-2xl font-bold text-sky-700 dark:text-sky-300" style={{ fontFamily: 'Poppins, sans-serif' }}>{profile.active_chats || 0}</div>
              <div className="text-[11px] sm:text-xs text-sky-600 dark:text-sky-400">Chats activos</div>
            </div>
            <div className="relative overflow-hidden text-center p-3 sm:p-4 rounded-2xl ring-1 bg-violet-50 ring-violet-200 dark:bg-violet-500/10 dark:ring-violet-500/20 shadow-md">
              <div className="w-10 h-10 mx-auto mb-2 rounded-2xl flex items-center justify-center bg-gradient-to-br from-violet-400 to-purple-600 text-white shadow-md"><Layers size={18} /></div>
              <div className="text-xl sm:text-2xl font-bold text-violet-700 dark:text-violet-300" style={{ fontFamily: 'Poppins, sans-serif' }}>{profile.max_concurrent_chats || 5}</div>
              <div className="text-[11px] sm:text-xs text-violet-600 dark:text-violet-400">Max. simultáneos</div>
            </div>
          </div>
        </div>
      </div>
      </div>
    </div>
  );
}
