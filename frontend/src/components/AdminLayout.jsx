import { useState } from 'react'
import { Link, NavLink, useLocation } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'

// La entrada del menú que lleva el número de cambios de plantilla por revisar.
const RUTA_CAMBIOS = '/admin/cambios-plantilla'

const GRUPOS = [
  {
    titulo: 'General',
    items: [
      { to: '/admin/usuarios', label: 'Usuarios' },
      { to: '/admin/ligas', label: 'Ligas' },
      { to: '/admin/novedades', label: 'Novedades' },
      { to: '/admin/registro-actividad', label: 'Actividad' },
      { to: '/admin/importar-historico', label: 'Importar histórico' },
    ],
  },
  {
    titulo: 'Datos maestros',
    items: [
      { to: '/admin/equipos', label: 'Equipos' },
      { to: '/admin/jugadores', label: 'Jugadores' },
      { to: '/admin/entrenadores', label: 'Entrenadores' },
      { to: '/admin/estadios', label: 'Estadios' },
      { to: '/admin/arbitros', label: 'Árbitros' },
      { to: '/admin/trofeos', label: 'Trofeos' },
    ],
  },
  {
    titulo: 'Competición',
    items: [
      { to: '/admin/cierre-jornada', label: 'Cierre de jornada' },
      { to: '/admin/calendario', label: 'Partidos' },
      { to: '/admin/eventos-partido', label: 'Eventos' },
      { to: '/admin/eventos-calendario', label: 'Calendario' },
      { to: '/admin/configuracion-puntos', label: 'Puntos' },
      { to: '/admin/quinielas', label: 'Quinielas' },
    ],
  },
  {
    titulo: 'Cartas',
    items: [
      { to: '/admin/categorias-carta', label: 'Categorías de Carta' },
      { to: '/admin/tipos-carta', label: 'Tipos de Carta' },
      { to: '/admin/configuracion-cartas', label: 'Configuración por liga' },
      { to: '/admin/gestion-cartas-usuarios', label: 'Cartas de usuarios' },
      { to: '/admin/rastro-cartas', label: 'Rastro de cartas' },
      { to: '/admin/codigos-canje', label: 'Crear códigos' },
      { to: '/admin/canjes-codigo', label: 'Historial de canjes' },
    ],
  },
  {
    titulo: 'Sistema',
    items: [
      { to: '/admin/tareas', label: 'Tareas y reimportación' },
      { to: '/admin/sistema', label: 'Errores y versión' },
      { to: '/admin/cambios-plantilla', label: 'Cambios de plantilla' },
      { to: '/admin/avisos-scraper', label: 'Avisos del scraper' },
    ],
  },
]

function AdminLayout({ children }) {
  const location = useLocation()
  const grupoActivo = GRUPOS.find((g) => g.items.some((item) => location.pathname.startsWith(item.to)))?.titulo
  const [gruposAbiertos, setGruposAbiertos] = useState(() => new Set())

  // Cuántos cambios de plantilla hay por revisar. Si la consulta falla, el menú
  // sigue igual, solo que sin número.
  const { data: resumenCambios } = useQuery({
    queryKey: ['admin-cambios-plantilla-resumen'],
    queryFn: async () => (await client.get('/api/v1/admin/cambios-plantilla/resumen')).data.data,
    refetchInterval: 120000,
    retry: false,
  })
  const cambiosPendientes = resumenCambios?.pendientes ?? 0

  function alternarGrupo(titulo) {
    setGruposAbiertos((prev) => {
      const nuevo = new Set(prev)
      if (nuevo.has(titulo)) {
        nuevo.delete(titulo)
      } else {
        nuevo.add(titulo)
      }
      return nuevo
    })
  }

  const enlaceClase = ({ isActive }) =>
    `font-body text-sm px-3 py-2 rounded block whitespace-nowrap ${
      isActive ? 'bg-acento text-fondo font-semibold' : 'text-texto hover:bg-borde/10'
    }`

  return (
    <div>
      <div className="flex justify-center py-2 border-b border-borde/30">
        <div className="bg-premio/90 text-fondo rounded-full px-4 py-1.5 flex items-center gap-3">
          <span className="font-body text-xs font-semibold">🛠️ Modo administrador</span>
          <Link to="/" className="font-body text-xs underline hover:no-underline">Salir al área de usuarios</Link>
        </div>
      </div>
      <div className="max-w-6xl mx-auto px-4 py-6 flex flex-col md:flex-row gap-6">
        <aside className="md:w-56 shrink-0">
          <h1 className="font-display text-lg text-texto mb-3">Administración</h1>
          <nav className="flex flex-col gap-1">
            {GRUPOS.map((grupo) => {
              // Siempre abierto el grupo donde estás ahora mismo, aunque no lo
              // hayas abierto tú a mano — así nunca aterrizas en una página sin
              // ver dónde estás dentro del menú.
              const abierto = grupo.titulo === grupoActivo || gruposAbiertos.has(grupo.titulo)

              const esElActivo = grupo.titulo === grupoActivo
              const avisoDelGrupo = grupo.items.some((item) => item.to === RUTA_CAMBIOS) ? cambiosPendientes : 0

              return (
                <div key={grupo.titulo}>
                  <button
                    onClick={() => alternarGrupo(grupo.titulo)}
                    className={`w-full flex items-center justify-between px-3 py-2 rounded transition border-l-2 ${
                      esElActivo
                        ? 'bg-acento/10 border-acento'
                        : 'border-transparent hover:bg-borde/10'
                    }`}
                  >
                    <span
                      className={`font-body text-[10px] uppercase tracking-widest ${
                        esElActivo ? 'text-acento font-bold' : 'text-borde'
                      }`}
                    >
                      {esElActivo && '● '}{grupo.titulo}
                      {avisoDelGrupo > 0 && !abierto && (
                        <span className="ml-2 font-marcador text-[10px] font-bold bg-premio text-fondo rounded-full px-1.5 py-0.5 normal-case tracking-normal">
                          {avisoDelGrupo}
                        </span>
                      )}
                    </span>
                    <span className="text-borde text-[9px]">{abierto ? '▲' : '▼'}</span>
                  </button>
                  {abierto && (
                    <div className="flex md:flex-col overflow-x-auto pb-1 md:border md:border-borde/15 md:rounded-lg md:divide-y md:divide-borde/15">
                      {grupo.items.map((s, i) => (
                        <div key={s.to} className={i % 2 === 1 ? 'md:bg-borde/5' : ''}>
                          <NavLink to={s.to} className={enlaceClase}>
                            {s.label}
                            {s.to === RUTA_CAMBIOS && cambiosPendientes > 0 && (
                              <span
                                title={`${cambiosPendientes} por revisar`}
                                className="ml-2 font-marcador text-[10px] font-bold bg-premio text-fondo rounded-full px-1.5 py-0.5"
                              >
                                {cambiosPendientes}
                              </span>
                            )}
                          </NavLink>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )
            })}
          </nav>
        </aside>
        <div className="flex-1 min-w-0">{children}</div>
      </div>
    </div>
  )
}

export default AdminLayout
