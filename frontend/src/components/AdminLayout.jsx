import { Link, NavLink } from 'react-router-dom'

const GRUPOS = [
  {
    titulo: 'General',
    items: [
      { to: '/admin/usuarios', label: 'Usuarios' },
      { to: '/admin/ligas', label: 'Ligas' },
      { to: '/admin/novedades', label: 'Novedades' },
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
      { to: '/admin/calendario', label: 'Partidos' },
      { to: '/admin/eventos-partido', label: 'Eventos' },
      { to: '/admin/eventos-calendario', label: 'Calendario' },
    ],
  },
]

function AdminLayout({ children }) {
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
        <aside className="md:w-48 shrink-0">
          <h1 className="font-display text-lg text-texto mb-3">Administración</h1>
          <nav className="flex flex-col gap-4">
            {GRUPOS.map((grupo) => (
              <div key={grupo.titulo}>
                <p className="font-body text-[10px] uppercase tracking-widest text-borde mb-1.5 px-3">{grupo.titulo}</p>
                <div className="flex md:flex-col gap-1 overflow-x-auto">
                  {grupo.items.map((s) => (
                    <NavLink key={s.to} to={s.to} className={enlaceClase}>{s.label}</NavLink>
                  ))}
                </div>
              </div>
            ))}
          </nav>
        </aside>
        <div className="flex-1 min-w-0">{children}</div>
      </div>
    </div>
  )
}

export default AdminLayout