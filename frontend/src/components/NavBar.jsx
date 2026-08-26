import { useState } from 'react'
import { NavLink } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { useTheme } from '../context/ThemeContext'
import LeagueSwitcher from './LeagueSwitcher'

function NavBar() {
  const { usuario, logout } = useAuth()
  const { tema, alternarTema } = useTheme()
  const [menuAbierto, setMenuAbierto] = useState(false)

  const enlaceClase = ({ isActive }) =>
    `font-body text-sm px-3 py-2 rounded transition ${
      isActive ? 'bg-acento text-fondo font-semibold' : 'text-texto hover:bg-borde/10'
    }`

  const enlaces = [
    { to: '/dashboard', label: 'Inicio' },
    { to: '/jornadas/1', label: 'Jornada' },
    { to: '/pronosticos', label: 'Mis Pronósticos' },
    { to: '/clasificacion', label: 'Clasificación' },
  ]

  return (
    <header className="bg-fondo border-b border-borde/30 sticky top-0 z-30">
      <div className="max-w-6xl mx-auto px-4">
        <div className="flex items-center justify-between h-16">
          <div className="flex items-center gap-2">
            <span className="font-display text-lg text-texto tracking-wide">PronostiCup</span>
            <span className="font-body font-bold text-[10px] bg-acento text-fondo rounded px-1.5 py-0.5 tracking-widest">
              LIGA
            </span>
          </div>

          <nav className="hidden md:flex items-center gap-1">
            {enlaces.map((enlace) => (
              <NavLink key={enlace.to} to={enlace.to} className={enlaceClase}>
                {enlace.label}
              </NavLink>
            ))}
            {usuario?.es_superadmin && (
              <NavLink to="/admin" className={enlaceClase}>Admin</NavLink>
            )}
          </nav>

          <div className="hidden md:flex items-center gap-3">
            <LeagueSwitcher />
            <button onClick={alternarTema} className="font-body text-xs text-borde hover:text-texto border border-borde/40 rounded px-2 py-1">
              {tema === 'oscuro' ? '☀️' : '🌙'}
            </button>
            <span className="font-body text-sm text-texto">{usuario?.nombre}</span>
            <button onClick={logout} className="font-body text-xs text-borde hover:text-texto">Salir</button>
          </div>

          <button onClick={() => setMenuAbierto(!menuAbierto)} className="md:hidden font-body text-texto p-2" aria-label="Abrir menú">
            {menuAbierto ? '✕' : '☰'}
          </button>
        </div>

        {menuAbierto && (
          <nav className="md:hidden flex flex-col gap-1 pb-4">
            {enlaces.map((enlace) => (
              <NavLink key={enlace.to} to={enlace.to} onClick={() => setMenuAbierto(false)} className={enlaceClase}>
                {enlace.label}
              </NavLink>
            ))}
            {usuario?.es_superadmin && (
              <NavLink to="/admin" onClick={() => setMenuAbierto(false)} className={enlaceClase}>Admin</NavLink>
            )}
            <div className="px-3 py-2 mt-2 border-t border-borde/20 pt-3">
              <LeagueSwitcher />
            </div>
            <div className="flex items-center justify-between px-3 py-2">
              <span className="font-body text-sm text-texto">{usuario?.nombre}</span>
              <div className="flex items-center gap-3">
                <button onClick={alternarTema} className="font-body text-xs border border-borde/40 rounded px-2 py-1">
                  {tema === 'oscuro' ? '☀️' : '🌙'}
                </button>
                <button onClick={logout} className="font-body text-xs text-borde">Salir</button>
              </div>
            </div>
          </nav>
        )}
      </div>
    </header>
  )
}

export default NavBar