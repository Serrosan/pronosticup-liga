// Contenido de la sección "Actualizaciones" (/actualizaciones).
//
// Se edita aquí, a mano, con cada cambio que se sube:
// - PROXIMAMENTE: lo que va a cambiar y todavía no ha entrado.
// - HISTORIAL: lo que ya ha cambiado, lo más reciente arriba.
//
// Cada entrada lleva un `id` único y una `fecha` (AAAA-MM-DD). El puntito de
// "nuevo" del menú sale cuando hay una entrada (de cualquiera de las dos
// listas) que la persona aún no ha visto.

export const PROXIMAMENTE = [
  {
    id: 'balance-cartas-1',
    fecha: '2026-10-28',
    emoji: '⚖️',
    titulo: 'Primer balance de las cartas',
    entraEnVigor: 'Fecha por confirmar: se avisará antes',
    intro:
      'Con tres jornadas de cartas ya tenemos datos de cuáles se juegan, cuáles se quedan muertas en la mano y cuáles casi no cambian nada. Estos son los ajustes.',
    cambios: [
      {
        carta: 'Silbato y Amigo Invisible (Faltas comunes)',
        antes: 'Solo mandaban un mensaje',
        despues: 'Se retiran del juego',
        porque:
          'De 17 que han salido, no se ha jugado ninguna, y cualquier Falta ya permite mandar mensaje. Si tienes alguna en la mano, recibirás a cambio una Falta común al azar.',
      },
      {
        carta: 'Todo queda en casa (victorias locales)',
        antes: 'Común 2 · Poco común 3 · Legendaria 5',
        despues: 'Común 3 · Poco común 4 · Rara 5 (nueva) · Legendaria 6',
        porque: 'Casi todo el mundo pone ya 2 victorias locales sin que se lo pidan: la versión común no obligaba a nada.',
      },
      {
        carta: 'Visita Obligada (victorias visitantes)',
        antes: 'Común 2 · Poco común 3 · Rara 4 · Legendaria 5',
        despues: 'Común 3 · Poco común 4 · Rara 5 · Legendaria 6',
        porque: 'El mismo motivo: tiene que notarse.',
      },
      {
        carta: 'Ojo de Halcón (resultado exacto)',
        antes: 'Común +2 · Poco común +4',
        despues: 'Común +3 · Poco común +5',
        porque: 'Acertar el exacto es difícil y la recompensa se quedaba corta.',
      },
      {
        carta: 'Crack (Legendaria)',
        antes: '+4 si aciertas 2 resultados exactos',
        despues: '+6 si aciertas 2 resultados exactos',
        porque: 'Para ser legendaria, daba poco.',
      },
      {
        carta: 'Resultado Gafas (empates)',
        antes: '—',
        despues: 'Sin cambios',
        porque: 'Funciona bien: muerde a quien nunca pone empates.',
      },
      {
        carta: 'Rarezas del reparto semanal',
        antes: 'Común 65 % · Poco común 25 % · Rara 8 % · Legendaria 2 %',
        despues: 'Común 55 % · Poco común 28 % · Rara 13 % · Legendaria 4 %',
        porque:
          'En tres jornadas solo ha salido una legendaria, y hay 7 que no ha visto nadie. Con el cambio, cada uno verá unas 2 legendarias y 8 raras en lo que queda de temporada.',
      },
      {
        carta: 'Bonus del Top 3 de la jornada',
        antes: '1.º 35/35/22/8 · 2.º 45/33/17/5 · 3.º 55/30/12/3',
        despues: '1.º 25/35/28/12 · 2.º 35/35/22/8 · 3.º 45/33/17/5',
        porque: 'Quedar arriba tiene que tener premio de verdad.',
      },
    ],
    notas: [
      'Las cartas que ya hayas jugado antes del cambio se resuelven con las reglas de antes. Nadie sale perjudicado por haber jugado una Falta con unas reglas y que se le aplique otra.',
      'Los porcentajes del bonus del Top 3 van en orden común / poco común / rara / legendaria.',
    ],
  },
]

export const HISTORIAL = [
  {
    id: '2026-10-27',
    fecha: '2026-10-27',
    emoji: '⚽',
    titulo: 'Resultado de cada partido en el móvil',
    cambios: [
      'Nuevo aviso opcional: al acabar cada partido te llega el resultado y lo que pronosticaste. Se activa en Perfil → Avisos en el móvil → "Resultado de cada partido".',
      'Cuando se sube una versión nueva de la app, te sale abajo una franja para actualizar cuando quieras.',
      'Nueva sección de Actualizaciones: esta misma, con lo que cambia y lo que está por venir.',
    ],
  },
  {
    id: '2026-10-26',
    fecha: '2026-10-26',
    emoji: '🔒',
    titulo: 'Tus dispositivos y Mis pronósticos',
    cambios: [
      'Perfil → Tus dispositivos: cerrar la sesión en todos los demás (por si pierdes el móvil). Cambiar la contraseña también lo hace.',
      'Mis pronósticos: la última jornada sale arriba y abierta.',
      'Mensajes de error más claros cuando algo no se puede hacer.',
      'Revisión de seguridad y librerías al día.',
    ],
  },
  {
    id: '2026-10-21',
    fecha: '2026-10-21',
    emoji: '📧',
    titulo: 'Correo de puntos con desglose',
    cambios: [
      'El correo de la jornada trae ahora el desglose: pronósticos, goleadores, cartas y si subes o bajas en la clasificación.',
      '"Mantener la sesión iniciada" ya funciona como debe.',
      'El chat se abre por el último mensaje.',
      'Parche de seguridad urgente del servidor (PHP).',
    ],
  },
  {
    id: '2026-10-19',
    fecha: '2026-10-19',
    emoji: '🚀',
    titulo: 'Más rápida y sesión de 30 días',
    cambios: [
      'La app carga bastante más rápido: cada pantalla se descarga solo cuando la abres.',
      'Casilla "Mantener la sesión iniciada" en el login (30 días).',
      'En la liga con cartas, la jornada se abre al terminar la anterior, para que veas antes las Faltas que te han jugado.',
      'Si una Falta te llega con la jornada ya rellena, puedes corregir partido a partido.',
    ],
  },
  {
    id: '2026-10-17',
    fecha: '2026-10-17',
    emoji: '📱',
    titulo: 'Avisos en el móvil',
    cambios: [
      'Los avisos de la campana llegan al móvil como los de cualquier app, y eliges cuáles quieres en tu perfil.',
      'Resultado Gafas ya se aplica bien, y se pueden tener varias Faltas a la vez.',
      'Iconos y texto donde antes solo había color.',
    ],
  },
  {
    id: '2026-10-16',
    fecha: '2026-10-16',
    emoji: '🎯',
    titulo: 'Elegir goleadores, más fácil',
    cambios: ['Plantilla por equipo y sugerencias al elegir goleadores: en racha, pichichi y por partido.'],
  },
  {
    id: '2026-10-13',
    fecha: '2026-10-13',
    emoji: '🏟️',
    titulo: 'La jornada en tu liga',
    cambios: [
      'En cada jornada, el interruptor "La liga": pronósticos, goleadores y cartas de todos en cuanto ya no se pueden cambiar.',
      'Pestaña Resumen de cada jornada cerrada.',
      'La app se puede instalar en el móvil como una app más (14/10).',
    ],
  },
  {
    id: '2026-10-05',
    fecha: '2026-10-05',
    emoji: '📋',
    titulo: 'Datos de LaLiga en cada partido',
    cambios: [
      'Alineaciones, estadísticas, goles y tarjetas de cada partido, cargados solos al terminar.',
      'Al elegir goleadores, goles y titularidades recientes de cada jugador.',
      'Tarjetas del árbitro en la página del partido.',
      'Mis pronósticos rediseñado y los tipos de acierto iguales en todas las pantallas.',
    ],
  },
  {
    id: '2026-09-29',
    fecha: '2026-09-29',
    emoji: '🃏',
    titulo: 'Cartas: avisos, historial y colección',
    cambios: [
      'Avisos de reparto, de cartas sin jugar y de cartas resueltas.',
      'Historial de cartas, motivo cuando una carta no hizo nada y contador de sobres en la barra.',
      'Catálogo con filtros y tu colección.',
    ],
  },
  {
    id: '2026-09-23',
    fecha: '2026-09-23',
    emoji: '⚔️',
    titulo: 'Llegan las Faltas',
    cambios: [
      'Faltas para jugar contra un rival: Escudo, Expulsión, Todo queda en casa, Visita Obligada, Resultado Gafas, Fallo clamoroso y Sin Comodines.',
      'Jugadas nuevas: Palomitas, Madrugador, Filo del Descanso y Tiempo Añadido.',
      'Catálogo público de todas las cartas.',
    ],
  },
  {
    id: '2026-09-20',
    fecha: '2026-09-20',
    emoji: '🃏',
    titulo: 'Nace el modo Cartas',
    cambios: [
      'Tu mano de cartas, reparto semanal y bonus para el Top 3 de cada jornada.',
      'Primeras Jugadas: Chute Extra, Ojo de Halcón, Doblete, Pleno Garantizado, Amuleto, Amigo del Árbitro y Crack.',
      'Menciones en el chat: @nombre y @todos.',
    ],
  },
  {
    id: '2026-09-13',
    fecha: '2026-09-13',
    emoji: '📊',
    titulo: 'Estadísticas y logros',
    cambios: [
      'Página de estadísticas de la liga con curiosidades de la temporada.',
      'Logros.',
      'Reacciones y respuestas en el chat, con aviso.',
      'Clasificación por jornada y colores por tipo de acierto.',
    ],
  },
  {
    id: '2026-09-09',
    fecha: '2026-09-09',
    emoji: '⚽',
    titulo: 'Goleadores, quinielas y recordatorios',
    cambios: [
      'Los 5 goleadores de la jornada.',
      'Las 3 quinielas de posiciones de la temporada.',
      'Recordatorios automáticos si te falta algo antes de la jornada.',
      'Copiar pronósticos de una liga a otra si estás en varias.',
      'Página de Puntuaciones con los valores de tu liga.',
    ],
  },
  {
    id: '2026-09-08',
    fecha: '2026-09-08',
    emoji: '🎉',
    titulo: 'PronostiCup Liga abre sus puertas',
    cambios: [
      'La app sale a internet en pronosticup.es.',
      'Detrás: casi un mes montando la base de datos con datos reales (380 partidos, más de 500 jugadores, estadios, entrenadores…), el diseño "noche de partido" y el chat con audios y stickers.',
    ],
  },
]

/** La marca más reciente de las dos listas: si cambia, hay algo nuevo que ver. */
export function marcaMasReciente() {
  return [...PROXIMAMENTE, ...HISTORIAL].map((e) => `${e.fecha}|${e.id}`).sort().at(-1) ?? ''
}
