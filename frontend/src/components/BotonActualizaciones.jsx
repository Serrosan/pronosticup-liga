import { useEffect, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'

// Enlace a /actualizaciones junto a la campana, con "NUEVO" si se ha publicado o
// cambiado algo desde la última vez que entraste. "Visto" se guarda en este
// navegador; si no se puede guardar, simplemente no sale el aviso.
const CLAVE = 'actualizaciones_vistas'

export const CONSULTA_ACTUALIZACIONES = {
  queryKey: ['actualizaciones'],
  queryFn: async () => (await client.get('/api/v1/actualizaciones')).data,
  staleTime: 5 * 60_000,
}

export function marcarActualizacionesVistas(marca) {
  try {
    if (marca) localStorage.setItem(CLAVE, marca)
  } catch {
    // sin almacenamiento: da igual
  }
}

function vista() {
  try {
    return localStorage.getItem(CLAVE)
  } catch {
    return undefined
  }
}

export default function BotonActualizaciones({ onNavegar }) {
  const location = useLocation()
  const { data } = useQuery(CONSULTA_ACTUALIZACIONES)
  const [ultimaVista, setUltimaVista] = useState(vista)

  // Al volver de la sección (o cambiar de pantalla) se vuelve a mirar.
  useEffect(() => {
    setUltimaVista(vista())
  }, [location.pathname])

  const nuevo = Boolean(data?.marca) && ultimaVista !== undefined && ultimaVista !== data.marca

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
