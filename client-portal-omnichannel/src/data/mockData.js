export const MOCK_CHATS = [
    {
        id: 'chat-001',
        leadName: 'Dr. Roberto Clínicas',
        platform: 'whatsapp',
        avatar: 'https://i.pravatar.cc/150?u=roberto',
        lastMessage: 'Necesito cancelar mi demo de mañana.',
        timestamp: '10:45 AM',
        unreadCount: 1,
        status: 'pending_human',
        phone: '+56 9 8888 7777',
        messages: [
            { id: 'm1', sender: 'bot', text: '¡Hola! 🤖 Soy el Bot de Agendamiento de Automatizatech. Veo que tienes una cita programada para mañana a las 11:00 AM. Escribe "CONFIRMAR" o "CANCELAR".', time: '10:40 AM' },
            { id: 'm2', sender: 'lead', text: 'CANCELAR', time: '10:42 AM' },
            { id: 'm3', sender: 'bot', text: 'Entendido. Cita cancelada. ¿Te gustaría reagendar para la próxima semana?', time: '10:43 AM' },
            { id: 'm4', sender: 'lead', text: 'Mejor háblame por interno. Necesito cancelar mi demo de mañana.', time: '10:45 AM' },
        ]
    },
    {
        id: 'chat-002',
        leadName: 'Visitante Web #4912',
        platform: 'telegram',
        avatar: 'https://i.pravatar.cc/150?u=webvisitor',
        lastMessage: 'Quiero ver cómo funciona el Webhook de N8N.',
        timestamp: 'Ayer',
        unreadCount: 0,
        status: 'resolved',
        handle: 'automatizatech.cl',
        messages: [
            { id: 'm1', sender: 'lead', text: 'Hola, estoy viendo sus planes en Automatizatech.cl', time: '09:00 AM' },
            { id: 'm2', sender: 'bot', text: '¡Bienvenido a Automatizatech! 😎 ¿Sobre qué área te gustaría consultar: 1. Agentes de IA, 2. Chatbots WhatsApp, 3. Integración N8N?', time: '09:00 AM' },
            { id: 'm3', sender: 'lead', text: '3.', time: '09:02 AM' },
            { id: 'm4', sender: 'human', text: 'Hola, soy agente de soporte. ¡Claro! Trabajamos fuertemente con N8N. ¿Qué necesitas conectar?', time: '09:15 AM' },
            { id: 'm5', sender: 'lead', text: 'Quiero ver cómo funciona el Webhook de N8N.', time: '09:21 AM' },
        ]
    },
    {
        id: 'chat-003',
        leadName: 'Emprendimientos SA',
        platform: 'instagram',
        avatar: 'https://i.pravatar.cc/150?u=emprendimientos',
        lastMessage: 'Respondió a tu historia 📱 (Solicitud de Demo)',
        timestamp: '11:30 AM',
        unreadCount: 3,
        status: 'pending_human',
        url: 'ig.com/emprendimientossa',
        messages: [
            { id: 'm1', sender: 'lead', text: 'Respondió a tu historia: "Crea tu primer Chatbot en 10 min"', time: '11:29 AM' },
            { id: 'm2', sender: 'bot', text: '¡Hola! Qué bueno verte por acá. ¿Quieres agendar una DEMO gratuita para ver cómo implementamos esto en tu negocio?', time: '11:30 AM' },
            { id: 'm3', sender: 'lead', text: 'Sí, pero mi negocio es una tienda física de ropa', time: '11:32 AM' },
            { id: 'm4', sender: 'lead', text: '¿También funciona para eso?', time: '11:33 AM' },
        ]
    },
    {
        id: 'chat-004',
        leadName: 'Juan Pérez',
        platform: 'facebook',
        avatar: 'https://i.pravatar.cc/150?u=juan',
        lastMessage: 'Quiero los precios de la implementación de CRM',
        timestamp: 'Hace 5 min',
        unreadCount: 1,
        status: 'bot_active',
        username: 'juan.perez123',
        messages: [
            { id: 'm1', sender: 'lead', text: 'Quiero los precios de la implementación de CRM', time: '11:40 AM' },
            { id: 'm2', sender: 'bot', text: '¡Hola Juan! Para integraciones de CRM (HubSpot, Pipedrive) solemos hacer una reunión técnica previa. ¿Te enviamos el link de agendamiento?', time: '11:41 AM' },
        ]
    }
];

export const CURRENT_USER = {
    name: 'Agente Admin',
    role: 'Administrador Tech',
    avatar: 'https://ui-avatars.com/api/?name=Agente+Admin&background=1e40af&color=fff'
};
