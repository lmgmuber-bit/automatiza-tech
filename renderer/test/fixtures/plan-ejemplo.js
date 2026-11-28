// Cuerpos de ejemplo de POST /render con document_type 'plan' (la forma que arma at_pt_armar_render en
// WordPress). Datos inventados: el repositorio es público. Las fechas salen de las reglas del plan (días
// hábiles, revisión de 5 días hábiles después de cada entrega, feriados 12-oct, 8-dic, 25-dic y 1-ene).
// Cada función devuelve una copia nueva para que una prueba no le cambie los datos a la siguiente.

function act(nombre, responsable, dias_habiles, desde, hasta, extra = {}) {
  return {
    nombre,
    detalle: '',
    responsable,
    dias_habiles,
    servicio: '',
    etapa: '',
    origen: 'tabla',
    en_paralelo: false,
    desde,
    hasta,
    ...extra,
  };
}

function barra(fase, etiqueta, tipo, responsable, desde, hasta) {
  return { fase, etiqueta, tipo, responsable, desde, hasta };
}

function base(unique_id, proyecto) {
  return {
    document_type: 'plan',
    unique_id,
    draft: false,
    company_name: 'Cliente Prueba SpA',
    client_name: 'Cliente Prueba',
    proyecto,
    fecha_firma_larga: '28 de septiembre de 2026',
    metodo: {
      hechas: ['diagnostico', 'priorizacion'],
      actual: 'propuesta',
      proximas: ['diseno_desarrollo', 'implementacion', 'soporte'],
    },
    necesitamos_de_ti: ['Logo y colores de tu marca', 'Textos e imágenes de tu negocio', 'Accesos al dominio y al hosting'],
    reuniones: [
      { nombre: 'Reunión de inicio', detalle: 'Revisamos juntos este plan y los insumos.' },
      { nombre: 'Llamada de seguimiento del plan', detalle: 'Resolvemos tus dudas del cronograma.' },
      { nombre: 'Entrega y capacitación', detalle: '' },
    ],
    soporte: { garantia_meses: 3, mensuales: [] },
    portal_url: 'https://automatizatech.cl/?crm_view=timeline&cid=999&token=prueba-token',
    agenda: {
      whatsapp_url: `https://wa.me/56927002984?text=Hola%20Tech%2C%20quiero%20agendar%20la%20llamada%20de%20seguimiento%20de%20mi%20plan%20de%20trabajo%20(c%C3%B3digo%20${unique_id})`,
      web_url: '',
    },
    image_briefs: [],
    images: {},
  };
}

// Sitio de una página: 3 fases, 7 barras, 6 semanas.
function planCorto() {
  return {
    ...base('PlanPrueba01', 'Sitio de una página'),
    fecha_inicio: '2026-10-05',
    fecha_fin: '2026-11-10',
    semanas: 6,
    fases: [
      {
        clave: 'diseno_desarrollo',
        titulo: 'Diseño y desarrollo',
        descripcion: 'Diseñamos y construimos tu sitio de una página.',
        bloques: [
          {
            nombre: 'Arranque',
            entregable: '',
            entrega: false,
            actividades: [
              act('Reunión de inicio', 'ambos', 1, '2026-10-05', '2026-10-05', { etapa: 'arranque' }),
              act('Entrega de logo, textos y accesos', 'cliente', 3, '2026-10-06', '2026-10-08', { etapa: 'arranque' }),
            ],
          },
          {
            nombre: 'Diseño',
            entregable: 'Diseño de la página',
            entrega: true,
            actividades: [
              act('Diseño de la página', 'at', 3, '2026-10-09', '2026-10-14', { etapa: 'diseno', servicio: 'sitio_una_pagina' }),
            ],
          },
          {
            nombre: 'Desarrollo',
            entregable: 'Sitio en ambiente de prueba',
            entrega: true,
            actividades: [
              act('Construcción del sitio', 'at', 5, '2026-10-22', '2026-10-28', { etapa: 'desarrollo', servicio: 'sitio_una_pagina' }),
              act('Pruebas en celular y computador', 'at', 2, '2026-10-29', '2026-10-30', { etapa: 'pruebas', servicio: 'sitio_una_pagina' }),
            ],
          },
        ],
      },
      {
        clave: 'implementacion',
        titulo: 'Implementación',
        descripcion: 'Lo publicamos en tu dominio.',
        bloques: [
          {
            nombre: 'Publicación',
            entregable: '',
            entrega: false,
            actividades: [
              act('Publicación en tu dominio', 'at', 1, '2026-11-09', '2026-11-09', { etapa: 'implementacion', servicio: 'sitio_una_pagina' }),
            ],
          },
        ],
      },
      {
        clave: 'soporte',
        titulo: 'Soporte y mejora continua',
        descripcion: 'Te acompañamos después de publicar.',
        bloques: [
          {
            nombre: 'Garantía',
            entregable: '',
            entrega: false,
            actividades: [act('Revisión a las dos semanas', 'ambos', 1, '2026-11-10', '2026-11-10', { etapa: 'soporte' })],
          },
        ],
      },
    ],
    cronograma: {
      inicio: '2026-10-05',
      fin: '2026-11-10',
      semanas: 6,
      barras: [
        barra('diseno_desarrollo', 'Arranque', 'trabajo', 'ambos', '2026-10-05', '2026-10-08'),
        barra('diseno_desarrollo', 'Diseño', 'trabajo', 'at', '2026-10-09', '2026-10-14'),
        barra('diseno_desarrollo', 'Tu revisión', 'revision', 'cliente', '2026-10-15', '2026-10-21'),
        barra('diseno_desarrollo', 'Desarrollo', 'trabajo', 'at', '2026-10-22', '2026-10-30'),
        barra('diseno_desarrollo', 'Tu revisión', 'revision', 'cliente', '2026-11-02', '2026-11-06'),
        barra('implementacion', 'Publicación', 'trabajo', 'at', '2026-11-09', '2026-11-09'),
        barra('soporte', 'Garantía', 'trabajo', 'ambos', '2026-11-10', '2026-11-10'),
      ],
      hitos: [
        { nombre: 'Diseño aprobado', fecha: '2026-10-21' },
        { nombre: 'Entrega estimada', fecha: '2026-11-09' },
      ],
    },
  };
}

// Plataforma a medida con soporte: 14 semanas, 16 barras (6 de revisión), con feriados en medio.
function planLargo() {
  const plataforma = { servicio: 'plataforma' };
  return {
    ...base('PlanPrueba02', 'Plataforma de reservas y pagos'),
    fecha_inicio: '2026-10-05',
    fecha_fin: '2027-01-08',
    semanas: 14,
    fases: [
      {
        clave: 'diseno_desarrollo',
        titulo: 'Diseño y desarrollo',
        descripcion:
          'Diseñamos las pantallas contigo y construimos la plataforma por partes, para que veas avances reales cada pocas semanas.',
        bloques: [
          {
            nombre: 'Arranque',
            entregable: '',
            entrega: false,
            actividades: [
              act('Reunión de inicio', 'ambos', 1, '2026-10-05', '2026-10-05', { etapa: 'arranque' }),
              act('Entrega de logo, textos y accesos', 'cliente', 3, '2026-10-06', '2026-10-08', { etapa: 'arranque' }),
            ],
          },
          {
            nombre: 'Diseño',
            entregable: 'Diseño de todas las pantallas',
            entrega: true,
            actividades: [
              act('Mapa de pantallas y flujos', 'at', 3, '2026-10-09', '2026-10-14', { etapa: 'diseno', ...plataforma }),
              act('Diseño visual de las pantallas', 'at', 4, '2026-10-15', '2026-10-20', { etapa: 'diseno', ...plataforma }),
            ],
          },
          {
            nombre: 'Núcleo de la plataforma',
            entregable: 'Usuarios y panel funcionando en ambiente de prueba',
            entrega: true,
            actividades: [
              act('Base de datos y usuarios', 'at', 4, '2026-10-28', '2026-11-02', { etapa: 'desarrollo', ...plataforma }),
              act('Panel de administración', 'at', 3, '2026-11-03', '2026-11-05', { etapa: 'desarrollo', ...plataforma }),
            ],
          },
          {
            nombre: 'Agenda y pagos',
            entregable: 'Reservas y cobro en línea en ambiente de prueba',
            entrega: true,
            actividades: [
              act('Módulo de agenda', 'at', 4, '2026-11-13', '2026-11-18', { etapa: 'desarrollo', ...plataforma }),
              act('Módulo de pagos', 'at', 4, '2026-11-13', '2026-11-18', { etapa: 'desarrollo', en_paralelo: true, ...plataforma }),
            ],
          },
          {
            nombre: 'Integraciones',
            entregable: 'WhatsApp y correos automáticos conectados',
            entrega: true,
            actividades: [
              act('Conexión con WhatsApp', 'at', 2, '2026-11-26', '2026-11-27', { etapa: 'desarrollo', origen: 'ia', ...plataforma }),
              act('Correos automáticos', 'at', 2, '2026-11-26', '2026-11-27', {
                etapa: 'desarrollo',
                origen: 'ia',
                en_paralelo: true,
                ...plataforma,
              }),
            ],
          },
          {
            nombre: 'Pruebas y revisión',
            entregable: 'Plataforma completa lista para publicar',
            entrega: true,
            actividades: [
              act('Pruebas internas', 'at', 2, '2026-12-07', '2026-12-09', { etapa: 'pruebas', ...plataforma }),
              act('Pruebas con tu equipo', 'ambos', 2, '2026-12-10', '2026-12-11', { etapa: 'pruebas', ...plataforma }),
            ],
          },
        ],
      },
      {
        clave: 'implementacion',
        titulo: 'Implementación',
        descripcion: 'La dejamos funcionando en tu dominio, con tus datos reales y tu equipo capacitado.',
        bloques: [
          {
            nombre: 'Puesta en marcha',
            entregable: '',
            entrega: false,
            actividades: [
              act('Carga de datos iniciales', 'cliente', 1, '2026-12-21', '2026-12-21', { etapa: 'implementacion', ...plataforma }),
              act('Publicación en tu dominio', 'at', 1, '2026-12-22', '2026-12-22', { etapa: 'implementacion', ...plataforma }),
            ],
          },
          {
            nombre: 'Capacitación',
            entregable: 'Equipo capacitado y manual de uso',
            entrega: true,
            actividades: [
              act('Capacitación del equipo', 'ambos', 1, '2026-12-23', '2026-12-23', { etapa: 'implementacion', ...plataforma }),
              act('Manual de uso', 'at', 1, '2026-12-23', '2026-12-23', { etapa: 'implementacion', en_paralelo: true, ...plataforma }),
            ],
          },
        ],
      },
      {
        clave: 'soporte',
        titulo: 'Soporte y mejora continua',
        descripcion: 'No desaparecemos: acompañamos las primeras semanas, medimos y ajustamos contigo.',
        bloques: [
          {
            nombre: 'Acompañamiento',
            entregable: '',
            entrega: false,
            actividades: [act('Acompañamiento posterior al lanzamiento', 'at', 3, '2027-01-04', '2027-01-06', { etapa: 'soporte' })],
          },
          {
            nombre: 'Medición y mejoras',
            entregable: '',
            entrega: false,
            actividades: [act('Informe de uso y mejoras propuestas', 'at', 2, '2027-01-07', '2027-01-08', { etapa: 'soporte' })],
          },
        ],
      },
    ],
    cronograma: {
      inicio: '2026-10-05',
      fin: '2027-01-08',
      semanas: 14,
      barras: [
        barra('diseno_desarrollo', 'Arranque', 'trabajo', 'ambos', '2026-10-05', '2026-10-08'),
        barra('diseno_desarrollo', 'Diseño', 'trabajo', 'at', '2026-10-09', '2026-10-20'),
        barra('diseno_desarrollo', 'Tu revisión', 'revision', 'cliente', '2026-10-21', '2026-10-27'),
        barra('diseno_desarrollo', 'Núcleo de la plataforma', 'trabajo', 'at', '2026-10-28', '2026-11-05'),
        barra('diseno_desarrollo', 'Tu revisión', 'revision', 'cliente', '2026-11-06', '2026-11-12'),
        barra('diseno_desarrollo', 'Agenda y pagos', 'trabajo', 'at', '2026-11-13', '2026-11-18'),
        barra('diseno_desarrollo', 'Tu revisión', 'revision', 'cliente', '2026-11-19', '2026-11-25'),
        barra('diseno_desarrollo', 'Integraciones', 'trabajo', 'at', '2026-11-26', '2026-11-27'),
        barra('diseno_desarrollo', 'Tu revisión', 'revision', 'cliente', '2026-11-30', '2026-12-04'),
        barra('diseno_desarrollo', 'Pruebas y revisión', 'trabajo', 'ambos', '2026-12-07', '2026-12-11'),
        barra('diseno_desarrollo', 'Tu revisión', 'revision', 'cliente', '2026-12-14', '2026-12-18'),
        barra('implementacion', 'Puesta en marcha', 'trabajo', 'ambos', '2026-12-21', '2026-12-22'),
        barra('implementacion', 'Capacitación', 'trabajo', 'ambos', '2026-12-23', '2026-12-23'),
        barra('implementacion', 'Tu revisión', 'revision', 'cliente', '2026-12-24', '2026-12-31'),
        barra('soporte', 'Acompañamiento', 'trabajo', 'at', '2027-01-04', '2027-01-06'),
        barra('soporte', 'Medición y mejoras', 'trabajo', 'at', '2027-01-07', '2027-01-08'),
      ],
      hitos: [
        { nombre: 'Diseño aprobado', fecha: '2026-10-27' },
        { nombre: 'Plataforma aprobada', fecha: '2026-12-18' },
        { nombre: 'Entrega estimada', fecha: '2026-12-23' },
      ],
    },
  };
}

module.exports = { planCorto, planLargo };
