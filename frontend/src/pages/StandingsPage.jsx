import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'

function StandingsPage() {
  const { idLiga } = useParams()

  const { data: clasificacion, isLoading, error } = useQuery({
    queryKey: ['clasificacion', idLiga],
    queryFn: async () => {
      const respuesta = await client.get(`/api/v1/ligas/${idLiga}/clasificacion`)
      return respuesta.data.data
    },
  })

  if (isLoading) return <p className="font-body text-texto p-4">Cargando clasificación...</p>
  if (error) return <p className="font-body text-red-500 p-4">Error al cargar: {error.message}</p>

  return (
    <div className="p-4 max-w-2xl mx-auto">
      <h2 className="font-display text-xl text-texto mb-4">Clasificación</h2>
      <div className="bg-fondo border border-borde/40 rounded-lg overflow-hidden">
        {clasificacion.map((fila, index) => (
          <div
            key={fila.usuario}
            className="flex items-center justify-between px-4 py-3 border-b border-borde/20 last:border-0"
          >
            <span className="font-body text-texto">
              <span className="text-borde mr-2">{index + 1}º</span>
              {fila.usuario}
            </span>
            <span className="font-marcador text-acento">{fila.puntos_totales} pts</span>
          </div>
        ))}
      </div>
    </div>
  )
}

export default StandingsPage