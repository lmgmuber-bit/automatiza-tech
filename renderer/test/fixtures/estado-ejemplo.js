// «Estado del proyecto» de ejemplo para las pruebas del renderer. Datos inventados: el repositorio es público.
const COD = 'AbCdEf123456';

function bloque(nombre, fase, plan_desde, plan_hasta, estado, inicio_real = '', fin_real = '') {
  return { nombre, fase, plan_desde, plan_hasta, entregable: '', estado, inicio_real, fin_real };
}

function ev(tipo, titulo, extra = {}) {
  return Object.assign({ tipo, titulo, url: '', poster: '', valor: '', unidad: '', fuente: '', fecha: '' }, extra);
}

function estadoCorto(cambios = {}) {
  return Object.assign(
    {
      document_type: 'estado',
      unique_id: `${COD}-estado`,
      draft: false,
      company_name: '[PRUEBA] Panadería',
      client_name: 'Cliente Prueba',
      proyecto: 'Sitio de la panadería',
      actualizado: '2026-10-14',
      plan_url: `https://automatizatech.cl/ver-plan.php?id=${COD}`,
      agenda: { whatsapp_url: 'https://wa.me/56900000000?text=prueba', web_url: `https://automatizatech.cl/ver-plan.php?id=${COD}&agendar=1` },
      cronograma: { inicio: '2026-10-05', fin: '2026-11-20', semanas: 7 },
      bloques: [
        bloque('Diseño', 'diseno_desarrollo', '2026-10-05', '2026-10-09', 'listo', '2026-10-03', '2026-10-08'),
        bloque('Desarrollo', 'diseno_desarrollo', '2026-10-12', '2026-10-21', 'listo', '2026-10-08', '2026-10-10'),
        bloque('Puesta en marcha', 'implementacion', '2026-10-22', '2026-10-30', 'en_curso', '2026-10-13'),
        bloque('Acompañamiento', 'soporte', '2026-11-02', '2026-11-20', 'por_venir'),
      ],
      cierres: [
        {
          id: 'cabc123',
          titulo: 'Sitio web',
          bloques: ['Diseño', 'Desarrollo'],
          fecha_real: '2026-10-10',
          borrador: false,
          entregable_url: 'https://automatizatech.cl/ver-entregable.php?id=XyZ123456789',
          antes: {
            texto: 'El sitio usaba una plantilla genérica y tenía páginas de demostración.',
            puntos: ['Plantilla genérica', 'Páginas de demostración indexadas'],
            evidencias: [ev('cifra', 'Lighthouse celular', { valor: '56', unidad: 'puntos', fuente: 'PageSpeed Insights', fecha: '2026-10-06' })],
          },
          durante: {
            texto: 'Rediseñamos y publicamos el sitio.',
            actividades: [{ bloque: 'Diseño', nombre: 'Maqueta de la portada', plan_desde: '2026-10-05', plan_hasta: '2026-10-06', real: '2026-10-03', nota: '' }],
          },
          despues: {
            texto: 'Sitio nuevo publicado.',
            puntos: ['Publicado el 10 de octubre'],
            evidencias: [
              ev('cifra', 'Lighthouse celular', { valor: '98', unidad: 'puntos', fuente: 'medición local', fecha: '2026-10-13' }),
              ev('captura', 'Portada nueva', { url: 'https://automatizatech.cl/wp-content/uploads/at-estado/7/aaaaaaaaaaaaaaaaaaaa.png' }),
              ev('video', 'Recorrido del sitio', { url: 'https://automatizatech.cl/wp-content/uploads/at-estado/7/bbbbbbbbbbbbbbbbbbbb.mp4' }),
            ],
          },
        },
      ],
      cambios_plan: [{ fecha: '2026-10-13', titulo: 'La publicidad pasa al servicio mensual', detalle: 'Lo acordamos por correo.', bloques: ['Puesta en marcha'] }],
      necesitamos: ['Fecha de inicio del servicio mensual'],
      alcance: { fase1: ['Sitio web publicado', 'Ficha de Google'], mensual: ['Publicidad y visibilidad'] },
      image_briefs: [],
      images: {},
    },
    cambios
  );
}

// El peor caso que acepta WordPress (topes de at_av_validar_import): 14 bloques, textos de 2.000 caracteres, 8 puntos
// de 200, 30 actividades, un cierre con 13 evidencias de títulos y fuentes largos, 30 cambios, 30 ítems y 20+20 de alcance.
function estadoLargo() {
  const largo = (n, base = 'texto largo de prueba ') => base.repeat(Math.ceil(n / base.length)).slice(0, n);
  const fases = ['diseno_desarrollo', 'diseno_desarrollo', 'diseno_desarrollo', 'diseno_desarrollo', 'diseno_desarrollo',
    'implementacion', 'implementacion', 'implementacion', 'implementacion', 'implementacion', 'soporte', 'soporte', 'soporte', 'soporte'];
  const bloques = fases.map((f, i) => {
    const d = new Date(Date.UTC(2026, 9, 5 + i * 7)).toISOString().slice(0, 10);
    const h = new Date(Date.UTC(2026, 9, 9 + i * 7)).toISOString().slice(0, 10);
    const estado = i < 5 ? 'listo' : i === 5 ? 'en_curso' : 'por_venir';
    return bloque(largo(80, `Bloque ${i + 1} con un nombre largo `), f, d, h, estado, i <= 5 ? d : '', i < 5 ? h : '');
  });
  const evs = Array.from({ length: 13 }, (_, i) =>
    i % 4 === 0 ? ev('cifra', largo(120), { valor: '1.234.567', unidad: largo(60), fuente: largo(200), fecha: '2026-10-13' })
      : i % 4 === 1 ? ev('captura', largo(120), { url: `https://automatizatech.cl/wp-content/uploads/at-estado/7/${'c'.repeat(19)}${i % 10}.png`, fuente: largo(200), fecha: '2026-10-13' })
        : i % 4 === 2 ? ev('video', largo(120), { url: `https://automatizatech.cl/wp-content/uploads/at-estado/7/${'v'.repeat(19)}${i % 10}.mp4` })
          : ev('enlace', largo(120), { url: `https://example.com/${'ruta-larga/'.repeat(30)}` })
  );
  const parte = (evidencias) => ({ texto: largo(2000), puntos: Array.from({ length: 8 }, () => largo(200)), evidencias });
  const cierre = (i, evidencias) => ({
    id: `c00000${i}`, titulo: largo(120, `Cierre ${i} con un título muy largo `), bloques: [bloques[i].nombre], fecha_real: `2026-10-1${i}`, borrador: false,
    entregable_url: 'https://automatizatech.cl/ver-entregable.php?id=XyZ123456789',
    antes: parte(evidencias.slice(0, 5)),
    durante: { texto: largo(2000), actividades: Array.from({ length: 30 }, (_, k) => ({ bloque: bloques[i].nombre, nombre: largo(120), plan_desde: '2026-10-05', plan_hasta: '2026-10-09', real: '2026-10-08', nota: largo(300) })) },
    despues: parte(evidencias.slice(5)),
  });
  return estadoCorto({
    proyecto: largo(120, 'Proyecto con un nombre muy largo '),
    company_name: largo(120, 'Empresa con un nombre largo '),
    cronograma: { inicio: '2026-10-05', fin: '2027-01-29', semanas: 17 },
    bloques,
    cierres: [cierre(1, evs), cierre(2, []), cierre(3, evs.slice(0, 2))],
    cambios_plan: Array.from({ length: 30 }, (_, i) => ({ fecha: '2026-10-13', titulo: largo(120, `Cambio ${i + 1} `), detalle: largo(600), bloques: [] })),
    necesitamos: Array.from({ length: 30 }, (_, i) => largo(200, `Necesitamos ${i + 1} `)),
    alcance: { fase1: Array.from({ length: 20 }, () => largo(200)), mensual: Array.from({ length: 20 }, () => largo(200)) },
  });
}

module.exports = { estadoCorto, estadoLargo, COD };
