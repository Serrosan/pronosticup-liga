export const CAMPOS_ADMIN = {
  jugadores: {
    titulo: 'jugador',
    volverA: '/admin/jugadores',
    campos: [
      { name: 'nombre', label: 'Nombre' },
      { name: 'apellidos', label: 'Apellidos' },
      { name: 'nombre_camiseta', label: 'Nombre de camiseta' },
      { name: 'posicion', label: 'Posición', type: 'select', options: ['Portero', 'Defensa', 'Centrocampista', 'Delantero'] },
      { name: 'posicion_detallada', label: 'Posición detallada' },
      { name: 'pie', label: 'Pie', type: 'select', options: ['Izquierdo', 'Derecho', 'Ambidiestro'] },
      { name: 'fecha_nacimiento', label: 'Fecha de nacimiento', type: 'date' },
      { name: 'lugar_nacimiento', label: 'Lugar de nacimiento' },
      { name: 'nacionalidad', label: 'Nacionalidad' },
      { name: 'seleccion', label: 'Selección' },
      { name: 'altura', label: 'Altura (cm)', type: 'number' },
      { name: 'fecha_fin_contrato', label: 'Fecha fin de contrato', type: 'date' },
      { name: 'club_anterior', label: 'Club anterior' },
      { name: 'id_externo_api', label: 'ID externo (API)' },
      { name: 'foto_url', label: 'URL de la foto', type: 'imagen' },
    ],
  },

  equipos: {
    titulo: 'equipo',
    volverA: '/admin/equipos',
    campos: [
      { name: 'escudo_url', label: 'URL del escudo', type: 'imagen' },
      { name: 'nombre', label: 'Nombre' },
      { name: 'nombre_corto', label: 'Nombre corto' },
      { name: 'apodo', label: 'Apodo' },
      { name: 'siglas', label: 'Siglas' },
      { name: 'ciudad', label: 'Ciudad' },
      { name: 'año_fundacion', label: 'Año de fundación', type: 'number' },
      {
        name: 'id_estadio', label: 'Estadio', type: 'select',
        optionsFrom: { resource: 'estadios', labelKey: 'nombre' },
      },
      { name: 'num_socios', label: 'Nº de socios', type: 'number' },
      { name: 'num_abonados', label: 'Nº de abonados', type: 'number' },
      { name: 'color_primario', label: 'Color primario', type: 'color' },
      { name: 'color_secundario', label: 'Color secundario', type: 'color' },
      { name: 'camiseta_1', label: 'Camiseta 1 (URL)' },
      { name: 'camiseta_2', label: 'Camiseta 2 (URL)' },
      { name: 'camiseta_3', label: 'Camiseta 3 (URL)' },
      { name: 'id_externo_api', label: 'ID externo API' },
      { name: 'id_equipo_api', label: 'ID equipo API' },
    ],
  },

  arbitros: {
    titulo: 'árbitro',
    volverA: '/admin/arbitros',
    campos: [
      { name: 'imagen', label: 'Imagen', type: 'imagen', carpeta: 'arbitros' },
      { name: 'nombre', label: 'Nombre' },
      { name: 'apellidos', label: 'Apellidos' },
      { name: 'nacionalidad', label: 'Nacionalidad' },
      { name: 'comunidad_autonoma', label: 'Comunidad autónoma' },
      { name: 'anio_debut', label: 'Año de debut', type: 'number' },
      { name: 'promedio_tarjetas_amarillas', label: 'Promedio tarjetas amarillas', type: 'number' },
      { name: 'promedio_tarjetas_rojas', label: 'Promedio tarjetas rojas', type: 'number' },
    ],
  },

  trofeos: {
    titulo: 'trofeo',
    volverA: '/admin/trofeos',
    campos: [
      { name: 'imagen', label: 'Imagen', type: 'imagen', carpeta: 'trofeos' },
      { name: 'logo', label: 'Logo', type: 'imagen', carpeta: 'trofeos' },
      { name: 'nombre', label: 'Nombre' },
      { name: 'tipo', label: 'Tipo', type: 'select', options: ['Colectivo', 'Individual'] },
      { name: 'ambito', label: 'Ámbito', type: 'select', options: ['Nacional', 'Internacional'] },
    ],
  },

  estadios: {
    titulo: 'estadio',
    volverA: '/admin/estadios',
    campos: [
      { name: 'nombre', label: 'Nombre' },
      { name: 'ciudad', label: 'Ciudad' },
      { name: 'capacidad', label: 'Capacidad', type: 'number' },
      { name: 'tamanio_campo', label: 'Tamaño del campo' },
      { name: 'anio_construccion', label: 'Año de construcción', type: 'number' },
      { name: 'anio_ult_remodelacion', label: 'Año última remodelación', type: 'number' },
    ],
  },

  ligas: {
    titulo: 'liga',
    volverA: '/admin/ligas',
    campos: [
      { name: 'logo_url', label: 'Logo/foto de la liga', type: 'imagen', carpeta: 'ligas' },
      { name: 'nombre', label: 'Nombre' },
      { name: 'lema', label: 'Lema' },
      { name: 'codigo_acceso', label: 'Código de acceso' },
    ],
  },

  'eventos-calendario': {
    titulo: 'nota de calendario',
    volverA: '/admin/eventos-calendario',
    campos: [
      { name: 'titulo', label: 'Título' },
      { name: 'fecha_inicio', label: 'Fecha de inicio', type: 'date' },
      { name: 'fecha_fin', label: 'Fecha de fin', type: 'date' },
      { name: 'color', label: 'Color', type: 'color' },
    ],
  },
}