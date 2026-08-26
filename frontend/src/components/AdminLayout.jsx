import { Link, NavLink } from 'react-router-dom'

const SECCIONES = [
  { to: '/admin/usuarios', label: 'Usuarios' },
  { to: '/admin/ligas', label: 'Ligas' },
  { to: '/admin/equipos', label: 'Equipos' },
  { to: '/admin/jugadores', label: 'Jugadores' },
  { to: '/admin/estadios', label: 'Estadios' },
  { to: '/admin/arbitros', label: 'Árbitros' },
  { to: '/admin/trofeos', label: 'Trofeos' },
]

function AdminLayout({ children }) {
  const enlaceClase = ({ isActive }) =>
    `font-body text-sm px-3 py-2 rounded block whitespace-nowrap ${
      isActive ? 'bg-acento text-fondo font-semibold' : 'text-texto hover:bg-borde/10'
    }`

  return (
    <div>
      <div className="bg-premio/90 text-fondo px-4 py-2 flex items-center justify-between">
        <span className="font-body text-sm font-semibold">🛠️ Modo administrador</span>
        <Link to="/" className="font-body text-sm underline hover:no-underline">
          Salir al área de usuarios
        </Link>
      </div>

      <div className="max-w-6xl mx-auto px-4 py-6 flex flex-col md:flex-row gap-6">
        <aside className="md:w-48 shrink-0">
          <h1 className="font-display text-lg text-texto mb-3">Administración</h1>
          <nav className="flex md:flex-col gap-1 overflow-x-auto">
            {SECCIONES.map((s) => (
              <NavLink key={s.to} to={s.to} className={enlaceClase}>{s.label}</NavLink>
            ))}
          </nav>
        </aside>
        <div className="flex-1 min-w-0">{children}</div>
      </div>
    </div>
  )
}

export default AdminLayout