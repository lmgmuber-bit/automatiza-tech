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
      <div className="shrink-0 bg-white dark:bg-slate-800 border-b border-gray-100 dark:border-slate-700/60">
        <div className="px-4 pt-4">
          <div className="flex items-center gap-3 mb-1">
            <span className="w-11 h-11 rounded-2xl flex items-center justify-center bg-gradient-to-br from-violet-400 to-purple-600 text-white shadow-md shrink-0">
              <Bot size={22} />
            </span>
            <div>
              <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-gray-900 dark:text-white" style={{ fontFamily: 'Poppins, sans-serif' }}>Configuración y Prompts del Bot</h1>
              <p className="text-sm text-gray-500 dark:text-slate-400 hidden sm:block">
                Configura el comportamiento, prompts y vista previa del bot
              </p>
            </div>
          </div>
        </div>
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
