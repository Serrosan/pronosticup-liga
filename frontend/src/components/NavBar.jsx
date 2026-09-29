import { useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import UserMenu from './UserMenu'
import CampanaNotificaciones from './CampanaNotificaciones'

const ENLACES = [
  { to: '/dashboard', match: '/dashboard', label: 'Inicio' },
  { to: '/jornadas', match: '/jornadas', label: 'Jornada' },
  { to: '/pronosticos', match: '/pronosticos', label: 'Pronósticos' },
  { to: '/clasificacion', match: '/clasificacion', label: 'Clasificación' },
  { to: '/chat', match: '/chat', label: 'Chat' },
]

const LALIGA = [
  { to: '/clasificacion-liga', label: 'Clasificación de LaLiga', sublabel: 'Tabla real, goleadores, tarjetas' },
  { to: '/calendario', label: 'Calendario', sublabel: 'Todos los partidos de la temporada' },
  { to: '/estadios', label: 'Estadios', sublabel: 'Ranking por capacidad' },
  { to: '/estadisticas-liga', label: 'Estadísticas de la liga', sublabel: 'Curiosidades de la temporada' },
]

const QUINIELAS = [
  { to: '/quinielas/completa', label: 'Completa', sublabel: 'Toda la temporada' },
  { to: '/quinielas/primera_mitad', label: 'Primera mitad', sublabel: 'Jornadas 1-18' },
  { to: '/quinielas/segunda_mitad', label: 'Segunda mitad', sublabel: 'Jornada 19 al final' },
]

function MenuDesplegable({ etiqueta, opciones, activo }) {
  const [abierto, setAbierto] = useState(false)

  return (
    <div className="relative">
      <button
        onClick={() => setAbierto(!abierto)}
        className={`font-body text-sm px-3 py-2 rounded transition whitespace-nowrap flex items-center gap-1 ${
          activo ? 'bg-acento text-fondo font-semibold' : 'text-texto hover:bg-borde/10'
        }`}
      >
        {etiqueta} <span className="text-xs">▾</span>
      </button>

      {abierto && (
        <>
          <div className="fixed inset-0 z-30" onClick={() => setAbierto(false)} />
          <div className="absolute left-0 mt-1 w-64 bg-fondo border border-borde/30 rounded-lg shadow-lg z-40 overflow-hidden">
            {opciones.map((op) => (
              <Link
                key={op.to}
                to={op.to}
                onClick={() => setAbierto(false)}
                className="block px-4 py-2.5 hover:bg-borde/10 border-b border-borde/10 last:border-0"
              >
                <p className="font-body text-sm text-texto">{op.label}</p>
                <p className="font-body text-[11px] text-borde">{op.sublabel}</p>
              </Link>
            ))}
          </div>
        </>
      )}
    </div>
  )
}

// Punto naranja/dorado con el nº de sobres sin abrir, o un punto rojo si solo hay una
// Amenaza (Falta recibida) pendiente. Prioriza el número: es la acción más concreta.
// Punto genérico con un número — para contadores simples como "partidos por pronosticar"
function BadgeContador({ numero }) {
  if (!numero || numero <= 0) return null

  return (
    <span className="absolute -top-1.5 -right-2 bg-premio text-fondo font-marcador text-[10px] font-bold rounded-full min-w-[16px] h-4 flex items-center justify-center px-1 leading-none">
      {numero > 9 ? '9+' : numero}
    </span>
  )
}

function BadgeCartas({ sinAbrir, hayAmenaza }) {
  if (sinAbrir === 0 && !hayAmenaza) return null

  if (sinAbrir > 0) {
    return (
      <span className="absolute -top-1.5 -right-2 bg-premio text-fondo font-marcador text-[10px] font-bold rounded-full min-w-[16px] h-4 flex items-center justify-center px-1 leading-none">
        {sinAbrir > 9 ? '9+' : sinAbrir}
      </span>
    )
  }

  return <span className="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-red-500" title="Tienes una Falta activa" />
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

  const enLaLiga = ['/clasificacion-liga', '/calendario', '/estadios'].some((p) => location.pathname.startsWith(p))
  const enQuiniela = location.pathname.startsWith('/quinielas')
  const tieneCartas = usuario?.liga_activa?.tipo === 'ConExtras'

  // Misma clave que usa Mis Cartas: si esa pantalla ya está cargada, React Query
  // reutiliza el dato en vez de pedirlo otra vez.
  const { data: misCartas } = useQuery({
    queryKey: ['mis-cartas'],
    queryFn: async () => (await client.get('/api/v1/mis-cartas')).data.data,
    enabled: tieneCartas,
    staleTime: 60_000,
  })

  const sinAbrir = misCartas?.sin_abrir ?? 0
  const hayAmenaza = (misCartas?.faltas_recibidas?.length ?? 0) > 0

  const { data: pronosticosPendientes } = useQuery({
    queryKey: ['pronosticos-pendientes-cuenta'],
    queryFn: async () => (await client.get('/api/v1/pronosticos/pendientes-cuenta')).data.data,
    staleTime: 60_000,
  })

  const pendientes = pronosticosPendientes?.pendientes ?? 0

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
              <Link
                key={enlace.to}
                to={enlace.to}
                className={enlace.to === '/pronosticos' ? `relative ${claseEnlace(enlace.match)}` : claseEnlace(enlace.match)}
              >
                {enlace.label}
                {enlace.to === '/pronosticos' && <BadgeContador numero={pendientes} />}
              </Link>
            ))}
            {tieneCartas && (
              <Link to="/mis-cartas" className={`relative ${claseEnlace('/mis-cartas')}`}>
                🃏 Cartas
                <BadgeCartas sinAbrir={sinAbrir} hayAmenaza={hayAmenaza} />
              </Link>
            )}
            {tieneCartas && (
              <Link to="/catalogo-cartas" className={claseEnlace('/catalogo-cartas')}>
                📖 Catálogo
              </Link>
            )}
            <MenuDesplegable etiqueta="LaLiga" opciones={LALIGA} activo={enLaLiga} />
            <MenuDesplegable etiqueta="Quinielas" opciones={QUINIELAS} activo={enQuiniela} />
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
              <Link
                key={enlace.to}
                to={enlace.to}
                onClick={() => setMenuAbierto(false)}
                className={enlace.to === '/pronosticos' ? `relative ${claseEnlace(enlace.match)}` : claseEnlace(enlace.match)}
              >
                {enlace.label}
                {enlace.to === '/pronosticos' && <BadgeContador numero={pendientes} />}
              </Link>
            ))}
            {tieneCartas && (
              <Link to="/mis-cartas" onClick={() => setMenuAbierto(false)} className={`relative ${claseEnlace('/mis-cartas')}`}>
                🃏 Cartas
                <BadgeCartas sinAbrir={sinAbrir} hayAmenaza={hayAmenaza} />
              </Link>
            )}
            <p className="font-body text-[10px] uppercase tracking-widest text-borde px-3 pt-3 pb-1">LaLiga</p>
            {LALIGA.map((op) => (
              <Link key={op.to} to={op.to} onClick={() => setMenuAbierto(false)} className={claseEnlace(op.to)}>
                {op.label}
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