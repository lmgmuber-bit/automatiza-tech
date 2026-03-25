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
      {/* Tabs */}
      <div className="shrink-0 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
        <div className="px-4 pt-3">
          <h1 className="text-base sm:text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2" style={{ fontFamily: 'Poppins, sans-serif' }}>
            🤖 <span>Configuración y Prompts del Bot</span>
          </h1>
          <p className="text-xs text-slate-400 mt-0.5 mb-2 hidden sm:block">
            Configura el comportamiento, prompts y vista previa del bot
          </p>
        </div>
        <div className="flex overflow-x-auto">
          {TABS.map(tab => {
            const Icon = tab.icon;
            const isActive = activeTab === tab.id;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors ${
                  isActive
                    ? 'text-blue-600 dark:text-blue-400 border-blue-600 dark:border-blue-400 bg-blue-50/50 dark:bg-blue-500/10'
                    : 'text-slate-500 dark:text-slate-400 border-transparent hover:text-slate-700 dark:hover:text-slate-200 hover:border-slate-300'
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
