function retomarEjemplo(overrides = {}) {
  return { document_type: 'retomar', unique_id: 'ejemplo123-retomar', company_name: 'Negocio de ejemplo', client_name: 'Equipo de ejemplo',
    proyecto: 'Sitio y agenda del negocio', quedamos: 'Revisamos cómo simplificar las consultas y reservas.',
    proponemos: 'Un sitio conectado con una agenda sencilla para el equipo.', partir: '',
    demo_urls: ['https://example.com/demo'], demo_imagen: 'https://example.com/demo.png',
    propuesta_url: 'https://example.com/propuesta', agenda: { web_url: 'https://example.com/agenda' },
    fotos: { portada: 'https://example.com/portada.jpg', cierre: 'https://example.com/cierre.jpg' }, ...overrides };
}
module.exports = { retomarEjemplo };
