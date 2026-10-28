import { useEffect, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { marcaMasReciente } from '../data/actualizaciones'

// Enlace a /actualizaciones junto a la campana, con un puntito si hay algo que no
// has visto. "Visto" se guarda en este navegador (si no se puede, no pasa nada:
// el puntito simplemente sale).
const CLAVE = 'actualizaciones_vistas'

export function marcarActualizacionesVistas() {
  try {
    localStorage.setItem(CLAVE, marcaMasReciente())
  } catch {
    // navegador sin almacenamiento: da igual
  }
}

function hayNovedades() {
  try {
    return localStorage.getItem(CLAVE) !== marcaMasReciente()
  } catch {
    return false
  }
}

export default function BotonActualizaciones({ onNavegar }) {
  const location = useLocation()
  const [nuevo, setNuevo] = useState(hayNovedades)

  // Al volver de la sección (o cambiar de pantalla) se vuelve a mirar.
  useEffect(() => {
    setNuevo(hayNovedades())
  }, [location.pathname])

  return (
    <Link
      to="/actualizaciones"
      onClick={onNavegar}
      className="relative font-body text-lg leading-none p-1.5 rounded hover:bg-borde/10 transition"
      aria-label={nuevo ? 'Actualizaciones: hay novedades sin ver' : 'Actualizaciones'}
      title={nuevo ? 'Actualizaciones (hay novedades)' : 'Actualizaciones'}
    >
      <span aria-hidden="true">📜</span>
      {nuevo && (
        <span className="absolute -top-1 -right-1.5 bg-premio text-fondo font-body text-[9px] font-bold rounded-full px-1 leading-4">
          NUEVO
        </span>
      )}
    </Link>
  )
}
