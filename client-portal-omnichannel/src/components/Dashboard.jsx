import React, { useState } from 'react';
import { MessageSquare, Users, Settings, LogOut, Search, Send, Menu, X, PlusCircle, ArrowRight, Sun, Moon } from 'lucide-react';
import { MOCK_CHATS, CURRENT_USER } from '../data/mockData';
import './Dashboard.css';

const PlatformIcon = ({ platform }) => {
    switch (platform) {
        case 'whatsapp': return <div className="platform-badge wa">WA</div>;
        case 'instagram': return <div className="platform-badge ig">IG</div>;
        case 'facebook': return <div className="platform-badge fb">FB</div>;
        case 'telegram': return <div className="platform-badge tg">TG</div>;
        default: return <div className="platform-badge default">--</div>;
    }
};

export default function Dashboard({ onLogout, theme, toggleTheme }) {
    const [activeChatId, setActiveChatId] = useState(MOCK_CHATS[0].id);
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
    const [replyText, setReplyText] = useState('');
    const [isLoggingOut, setIsLoggingOut] = useState(false);

    const activeChat = MOCK_CHATS.find(c => c.id === activeChatId);

    const handleSendReply = (e) => {
        e.preventDefault();
        if (replyText.trim() !== '') {
            // Mock de envío
            console.log('Mensaje enviado:', replyText);
            setReplyText('');
        }
    };

    const handleLogoutClick = () => {
        setIsLoggingOut(true);
        setTimeout(() => {
            onLogout();
        }, 500); // Wait for log out animation
    };

    return (
        <div className={`dashboard-layout ${isLoggingOut ? 'dashboard-log-out' : ''}`}>
            {/* Sidebar Desktop / Overlay Mobile */}
            <aside className={`sidebar ${isMobileMenuOpen ? 'mobile-open' : ''}`}>
                <div className="sidebar-header">
                    <div className="logo-abbr">AT</div>
                    <span className="brand-name">Automatiza<span className="text-secondary">tech</span></span>
                    <button className="mobile-close-btn" onClick={() => setIsMobileMenuOpen(false)}>
                        <X size={24} />
                    </button>
                </div>

                <nav className="sidebar-nav">
                    <a href="#" className="nav-item active"><MessageSquare size={20} /> <span className="nav-label">Bandeja de Entrada</span></a>
                    <a href="#" className="nav-item"><Users size={20} /> <span className="nav-label">Contactos</span></a>
                    <a href="#" className="nav-item"><Settings size={20} /> <span className="nav-label">Configuración</span></a>
                </nav>

                <div className="sidebar-footer">
                    <div className="user-profile">
                        <img src={CURRENT_USER.avatar} alt="User Avatar" />
                        <div className="user-info">
                            <span className="user-name">{CURRENT_USER.name}</span>
                            <span className="user-role">{CURRENT_USER.role}</span>
                        </div>
                    </div>
                    <div className="sidebar-actions">
                        <button className="theme-toggle-btn" onClick={toggleTheme} title="Cambiar Tema">
                            {theme === 'light' ? <Moon size={20} /> : <Sun size={20} />}
                        </button>
                        <button className="logout-btn" onClick={handleLogoutClick} title="Cerrar Sesión">
                            <LogOut size={20} />
                        </button>
                    </div>
                </div>
            </aside>

            {/* Main Content Area */}
            <main className="main-content">

                {/* Chats List Panel */}
                <section className={`inbox-panel ${activeChatId && window.innerWidth <= 768 ? 'mobile-hidden' : ''}`}>
                    <div className="inbox-header">
                        <div className="inbox-title">
                            <button className="mobile-menu-btn" onClick={() => setIsMobileMenuOpen(true)}>
                                <Menu size={24} />
                            </button>
                            <h2>Mensajes</h2>
                            <span className="badge-count">12</span>
                        </div>
                        <div className="search-bar">
                            <Search size={18} className="search-icon" />
                            <input type="text" placeholder="Buscar conversaciones..." />
                        </div>
                    </div>

                    <div className="chats-list">
                        {MOCK_CHATS.map(chat => (
                            <div
                                key={chat.id}
                                className={`chat-item ${activeChatId === chat.id ? 'active' : ''}`}
                                onClick={() => setActiveChatId(chat.id)}
                            >
                                <div className="chat-avatar-container">
                                    <img src={chat.avatar} alt={chat.leadName} className="chat-avatar" />
                                    <PlatformIcon platform={chat.platform} />
                                </div>
                                <div className="chat-info">
                                    <div className="chat-info-top">
                                        <h4>{chat.leadName}</h4>
                                        <span className="chat-time">{chat.timestamp}</span>
                                    </div>
                                    <div className="chat-info-bottom">
                                        <p className="chat-excerpt">{chat.lastMessage}</p>
                                        {chat.unreadCount > 0 && (
                                            <span className="unread-badge">{chat.unreadCount}</span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                {/* Chat Window Panel */}
                <section className={`chat-window-panel ${!activeChatId && window.innerWidth <= 768 ? 'mobile-hidden' : ''}`}>
                    {activeChat ? (
                        <>
                            <div className="chat-window-header">
                                {/* Back button for mobile */}
                                <button className="back-btn-mobile" onClick={() => setActiveChatId(null)}>
                                    <ArrowRight size={20} style={{ transform: 'rotate(180deg)' }} />
                                </button>
                                <div className="active-chat-user">
                                    <img src={activeChat.avatar} alt={activeChat.leadName} />
                                    <div>
                                        <h3>{activeChat.leadName}</h3>
                                        <span className="contact-detail">{activeChat.phone || activeChat.handle || activeChat.username || 'Cliente Activo'}</span>
                                    </div>
                                </div>
                                <div className="chat-actions">
                                    <button className="btn-outline-small">Finalizar Chat</button>
                                </div>
                            </div>

                            <div className="chat-messages-container">
                                <div className="chat-disclaimer">
                                    Estás visualizando el flujo de trabajo automatizado. Responde abajo para tomar control humano.
                                </div>

                                {activeChat.messages.map(msg => (
                                    <div key={msg.id} className={`message-bubble ${msg.sender}`}>
                                        <div className="message-content">
                                            <p>{msg.text}</p>
                                            <span className="message-time">{msg.time}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="chat-input-area">
                                <button className="attach-btn"><PlusCircle size={24} /></button>
                                <form onSubmit={handleSendReply} className="input-form">
                                    <input
                                        type="text"
                                        placeholder={`Escribir respuesta o tomar control humano de ${activeChat.leadName}...`}
                                        value={replyText}
                                        onChange={(e) => setReplyText(e.target.value)}
                                    />
                                    <button type="submit" className="send-btn" disabled={!replyText.trim()}>
                                        <Send size={20} />
                                    </button>
                                </form>
                            </div>
                        </>
                    ) : (
                        <div className="empty-chat-state">
                            <MessageSquare size={64} className="empty-icon" />
                            <h3>Selecciona una conversación</h3>
                            <p>Elige un chat de la lista para ver los detalles e iniciar o continuar la atención humana.</p>
                        </div>
                    )}
                </section>

            </main>
        </div>
    );
}
