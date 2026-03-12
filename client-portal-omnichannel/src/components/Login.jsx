import React, { useState, useEffect } from 'react';
import { Mail, Lock, ArrowRight, Loader2 } from 'lucide-react';
import './Login.css';

export default function Login({ onLogin }) {
    const [email, setEmail] = useState('admin@automatizatech.com');
    const [password, setPassword] = useState('admin123');
    const [errorMsg, setErrorMsg] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [isClosing, setIsClosing] = useState(false);
    const [robotState, setRobotState] = useState('idle'); // idle, waving, thinking, success, celebrating

    // Robot cambia de estado periódicamente para mantener la atención
    useEffect(() => {
        const states = ['idle', 'waving', 'idle', 'waving'];
        let i = 0;
        const interval = setInterval(() => {
            i = (i + 1) % states.length;
            setRobotState(states[i]);
        }, 3000);
        return () => clearInterval(interval);
    }, []);

    const handleSubmit = (e) => {
        e.preventDefault();
        setErrorMsg('');
        setIsLoading(true);
        setRobotState('thinking');

        setTimeout(() => {
            setIsLoading(false);
            if (email === 'admin@automatizatech.com' && password === 'admin123') {
                setRobotState('celebrating');
                setTimeout(() => {
                    setIsClosing(true);
                    setTimeout(() => {
                        onLogin();
                    }, 800);
                }, 1200);
            } else {
                setRobotState('idle');
                setErrorMsg('Credenciales incorrectas. (Usa: admin@automatizatech.com / admin123)');
            }
        }, 1500);
    };

    return (
        <div className={`login-page ${isClosing ? 'login-exit' : 'login-enter'}`}>

            {/* Partículas decorativas de fondo */}
            <div className="login-particles">
                <div className="particle p1"></div>
                <div className="particle p2"></div>
                <div className="particle p3"></div>
                <div className="particle p4"></div>
                <div className="particle p5"></div>
                <div className="particle p6"></div>
            </div>

            {/* Contenedor principal centrado */}
            <div className="login-centered-wrapper">

                {/* Robot Realista Animado */}
                <div className={`robot-container robot-${robotState}`}>
                    <div className="robot-speech-bubble">
                        {robotState === 'idle' && '¡Hola! Estoy listo para ayudarte 🤖'}
                        {robotState === 'waving' && '👋 ¡Bienvenido! Ingresa tus datos'}
                        {robotState === 'thinking' && '🔍 Verificando credenciales...'}
                        {robotState === 'celebrating' && '🎉 ¡Acceso concedido! Preparando tu portal...'}
                    </div>

                    <div className="robot-image-wrapper">
                        <img src="./robot-assistant.png" alt="Asistente IA" className="robot-img" />
                        <div className="robot-glow"></div>
                    </div>
                </div>

                {/* Tarjeta de Login */}
                <div className="login-card">
                    <div className="login-card-header">
                        <img src="./logo-automatiza-tech.svg" alt="Automatizatech" className="login-logo" />
                        <h1>Automatiza<span>tech</span></h1>
                        <p>Portal Omnicanal para Clientes</p>
                    </div>

                    <form onSubmit={handleSubmit} className="login-form">
                        <div className="input-group">
                            <label>Correo Electrónico</label>
                            <div className="input-with-icon">
                                <Mail className="input-icon" size={20} />
                                <input
                                    type="email"
                                    placeholder="ejemplo@empresa.com"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    required
                                />
                            </div>
                        </div>

                        <div className="input-group">
                            <label>Contraseña</label>
                            <div className="input-with-icon">
                                <Lock className="input-icon" size={20} />
                                <input
                                    type="password"
                                    placeholder="••••••••"
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    required
                                />
                            </div>
                        </div>

                        {errorMsg && (
                            <div className="login-error">
                                {errorMsg}
                            </div>
                        )}

                        <button type="submit" className="btn-login" disabled={isLoading}>
                            {isLoading ? (
                                <>
                                    Verificando... <Loader2 size={20} className="spin-animation" />
                                </>
                            ) : (
                                <>
                                    Ingresar al Portal <ArrowRight size={20} />
                                </>
                            )}
                        </button>

                        <div className="login-credentials-hint">
                            Acceso rápido: <strong>admin@automatizatech.com</strong> / <strong>admin123</strong>
                        </div>
                    </form>

                    <div className="login-footer">
                        <p>¿Problemas para ingresar? <a href="#">Contactar Soporte</a></p>
                    </div>
                </div>

            </div>

            {/* Branding inferior */}
            <div className="login-branding">
                <p>Powered by <strong>Automatiza<span>tech</span></strong> · Automatización Inteligente</p>
            </div>
        </div>
    );
}
