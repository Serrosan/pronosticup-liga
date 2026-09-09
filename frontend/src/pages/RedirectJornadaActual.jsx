import { useQuery } from '@tanstack/react-query'
import { Navigate } from 'react-router-dom'
import client from '../api/client'

function RedirectJornadaActual() {
  const { data, isLoading } = useQuery({
    queryKey: ['jornada-actual'],
    queryFn: async () => (await client.get('/api/v1/jornada-actual')).data.data,
  })

  if (isLoading) return null

  return <Navigate to={`/jornadas/${data?.jornada ?? 1}`} replace />
}

export default RedirectJornadaActual