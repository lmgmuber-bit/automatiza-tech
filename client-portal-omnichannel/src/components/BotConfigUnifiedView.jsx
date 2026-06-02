import { useState } from 'react';
import { Bot, FileText, Eye } from 'lucide-react';
import BotsView from './BotsView';
import PromptsView from './PromptsView';
import PromptPreviewPanel from './PromptPreviewPanel';

const TABS = [
  { id: 'config', label: 'Configuración del Bot', icon: Bot },
  { id: 'prompts', label: 'Prompts', icon: FileText },
  { id: 'preview', label: 'Vista Previa Prompt', icon: Eye },
];

export default function BotConfigUnifiedView() {
  const [activeTab, setActiveTab] = useState('config');

  return (
    <div className="h-full flex flex-col overflow-hidden">
      {/* HERO FULL-BLEED */}
      <div className="relative overflow-hidden px-6 py-6 text-white shadow-lg" style={{ backgroundImage: 'linear-gradient(120deg, #0ea5e9, #2563eb)' }}>
        <div className="absolute -top-12 -right-8 w-52 h-52 rounded-full blur-3xl bg-white/20" />
        <div className="absolute inset-0 opacity-[0.06]" style={{ backgroundImage: 'radial-gradient(white 1px, transparent 1px)', backgroundSize: '20px 20px' }} />
        <div className="relative flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-2xl bg-white/15 backdrop-blur-sm ring-1 ring-white/25 flex items-center justify-center">
              <Bot size={26} />
            </div>
            <div>
              <h1 className="text-2xl font-bold tracking-tight" style={{ fontFamily: 'Poppins, sans-serif' }}>Configuración y Prompts del Bot</h1>
              <p className="text-sm text-white/75">Configura el comportamiento, prompts y vista previa del bot</p>
            </div>
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="shrink-0 bg-white dark:bg-slate-800 border-b border-gray-100 dark:border-slate-700/60">
        <div className="flex gap-1 px-3 pt-2 overflow-x-auto">
          {TABS.map(tab => {
            const Icon = tab.icon;
            const isActive = activeTab === tab.id;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold whitespace-nowrap rounded-t-xl border-b-2 transition-all duration-300 ${
                  isActive
                    ? 'text-white border-transparent bg-gradient-to-br from-violet-400 to-purple-600 shadow-md'
                    : 'text-gray-500 dark:text-slate-400 border-transparent hover:text-gray-700 dark:hover:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-700/40'
                }`}
              >
                <Icon size={16} />
                <span className="hidden sm:inline">{tab.label}</span>
                <span className="sm:hidden">{tab.id === 'config' ? 'Config' : tab.id === 'prompts' ? 'Prompts' : 'Preview'}</span>
              </button>
            );
          })}
        </div>
      </div>

      {/* Tab content */}
      <div className="flex-1 min-h-0 overflow-hidden">
        {activeTab === 'config' && <BotsView />}
        {activeTab === 'prompts' && <PromptsView />}
        {activeTab === 'preview' && <PromptPreviewPanel />}
      </div>
    </div>
  );
}
