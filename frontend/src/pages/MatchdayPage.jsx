import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import MatchCard from '../components/MatchCard'

function MatchdayPage() {
  const { idLiga, jornada } = useParams()

  const { data: partidos, isLoading, error } = useQuery({
    queryKey: ['partidos', idLiga, jornada],
    queryFn: async () => {
      const respuesta = await client.get(`/api/v1/ligas/${idLiga}/jornadas/${jornada}/partidos`)
      return respuesta.data.data
    },
  })

  if (isLoading) return <p className="font-body text-texto p-4">Cargando partidos...</p>
  if (error) return <p className="font-body text-red-500 p-4">Error al cargar: {error.message}</p>

  return (
    <div>
      <h2 className="font-display text-xl text-texto px-4 pt-4">Jornada {jornada}</h2>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 p-4 max-w-6xl mx-auto">
        {partidos.map((partido) => (
          <MatchCard key={partido.id} partido={{ ...partido, jornada }} idLiga={idLiga} />
        ))}
      </div>
    </div>
  )
}

export default MatchdayPage