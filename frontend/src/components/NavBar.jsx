import { useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import UserMenu from './UserMenu'
import CampanaNotificaciones from './CampanaNotificaciones'

const ENLACES = [
  { to: '/dashboard', match: '/dashboard', label: 'Inicio' },
  { to: '/jornadas/1', match: '/jornadas', label: 'Jornada' },
  { to: '/pronosticos', match: '/pronosticos', label: 'Pronósticos' },
  { to: '/clasificacion', match: '/clasificacion', label: 'Clasificación' },
  { to: '/clasificacion-liga', match: '/clasificacion-liga', label: 'LaLiga' },
  { to: '/calendario', match: '/calendario', label: 'Calendario' },
  { to: '/estadios', match: '/estadios', label: 'Estadios' },
  { to: '/chat', match: '/chat', label: 'Chat' },
]

function NavBar() {
  const { usuario } = useAuth()
  const location = useLocation()
  const [menuAbierto, setMenuAbierto] = useState(false)

  function claseEnlace(match) {
    const activo = location.pathname === match || location.pathname.startsWith(match + '/')
    return `font-body text-sm px-3 py-2 rounded transition whitespace-nowrap ${
      activo ? 'bg-acento text-fondo font-semibold' : 'text-texto hover:bg-borde/10'
    }`
  }

  return (
    <header className="bg-fondo border-b border-borde/30 sticky top-0 z-30">
      <div className="max-w-7xl mx-auto px-4">
        <div className="flex items-center justify-between h-16">
          <div className="flex items-center gap-2 shrink-0">
            <span className="font-display text-lg text-texto tracking-wide">PronostiCup</span>
            <span className="font-body font-bold text-[10px] bg-acento text-fondo rounded px-1.5 py-0.5 tracking-widest">
              LIGA
            </span>
          </div>
          <nav className="hidden md:flex items-center gap-1">
            {ENLACES.map((enlace) => (
              <Link key={enlace.to} to={enlace.to} className={claseEnlace(enlace.match)}>
                {enlace.label}
              </Link>
            ))}
            {usuario?.es_superadmin && (
              <Link to="/admin" className={claseEnlace('/admin')}>Admin</Link>
            )}
          </nav>
          <div className="hidden md:flex items-center gap-4 shrink-0">
            <CampanaNotificaciones />
            <div className="w-px h-6 bg-borde/20" />
            <UserMenu />
          </div>
          <button onClick={() => setMenuAbierto(!menuAbierto)} className="md:hidden font-body text-texto p-2" aria-label="Abrir menú">
            {menuAbierto ? '✕' : '☰'}
          </button>
        </div>
        {menuAbierto && (
          <nav className="md:hidden flex flex-col gap-1 pb-4">
            {ENLACES.map((enlace) => (
              <Link key={enlace.to} to={enlace.to} onClick={() => setMenuAbierto(false)} className={claseEnlace(enlace.match)}>
                {enlace.label}
              </Link>
            ))}
            {usuario?.es_superadmin && (
              <Link to="/admin" onClick={() => setMenuAbierto(false)} className={claseEnlace('/admin')}>Admin</Link>
            )}
            <div className="flex items-center gap-4 px-3 py-2 mt-2 border-t border-borde/20 pt-3">
              <UserMenu />
              <CampanaNotificaciones />
            </div>
          </nav>
        )}
      </div>
    </header>
  )
}

export default NavBar