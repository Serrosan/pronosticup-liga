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

const QUINIELAS = [
  { to: '/quinielas/completa', label: 'Completa', sublabel: 'Toda la temporada' },
  { to: '/quinielas/primera_mitad', label: 'Primera mitad', sublabel: 'Jornadas 1-18' },
  { to: '/quinielas/segunda_mitad', label: 'Segunda mitad', sublabel: 'Jornada 19 al final' },
]

function MenuQuinielas({ activo }) {
  const [abierto, setAbierto] = useState(false)

  return (
    <div className="relative">
      <button
        onClick={() => setAbierto(!abierto)}
        className={`font-body text-sm px-3 py-2 rounded transition whitespace-nowrap flex items-center gap-1 ${
          activo ? 'bg-acento text-fondo font-semibold' : 'text-texto hover:bg-borde/10'
        }`}
      >
        Quinielas <span className="text-xs">▾</span>
      </button>

      {abierto && (
        <>
          <div className="fixed inset-0 z-30" onClick={() => setAbierto(false)} />
          <div className="absolute left-0 mt-1 w-56 bg-fondo border border-borde/30 rounded-lg shadow-lg z-40 overflow-hidden">
            {QUINIELAS.map((q) => (
              <Link
                key={q.to}
                to={q.to}
                onClick={() => setAbierto(false)}
                className="block px-4 py-2.5 hover:bg-borde/10 border-b border-borde/10 last:border-0"
              >
                <p className="font-body text-sm text-texto">{q.label}</p>
                <p className="font-body text-[11px] text-borde">{q.sublabel}</p>
              </Link>
            ))}
          </div>
        </>
      )}
    </div>
  )
}

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

  const enQuiniela = location.pathname.startsWith('/quinielas')

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
            <MenuQuinielas activo={enQuiniela} />
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
            <p className="font-body text-[10px] uppercase tracking-widest text-borde px-3 pt-3 pb-1">Quinielas</p>
            {QUINIELAS.map((q) => (
              <Link key={q.to} to={q.to} onClick={() => setMenuAbierto(false)} className={claseEnlace(q.to)}>
                {q.label}
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