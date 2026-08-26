import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'

function LeagueSwitcher() {
  const [abierto, setAbierto] = useState(false)
  const { usuario } = useAuth()
  const queryClient = useQueryClient()

  const { data: ligas } = useQuery({
    queryKey: ['mis-ligas'],
    queryFn: async () => (await client.get('/api/v1/ligas')).data.data,
    enabled: abierto,
  })

  const cambiar = useMutation({
    mutationFn: (ligaId) => client.patch('/api/v1/liga-activa', { liga_id: ligaId }),
    onSuccess: () => {
      queryClient.invalidateQueries()
      window.location.href = '/dashboard'
    },
  })

  return (
    <div className="relative">
      <button
        onClick={() => setAbierto(!abierto)}
        className="font-body text-sm text-texto flex items-center gap-1 border border-borde/40 rounded px-3 py-1.5 hover:bg-borde/10"
      >
        🏆 {usuario?.liga_activa?.nombre ?? 'Sin liga'} ▾
      </button>

      {abierto && (
        <div className="absolute right-0 mt-2 w-56 bg-fondo border border-borde/30 rounded-lg shadow-lg z-40 overflow-hidden">
          {ligas?.map((liga) => (
            <button
              key={liga.id}
              onClick={() => { setAbierto(false); cambiar.mutate(liga.id) }}
              className={`w-full text-left font-body text-sm px-3 py-2 hover:bg-acento/10 ${
                liga.id === usuario?.liga_activa?.id ? 'text-acento font-semibold' : 'text-texto'
              }`}
            >
              {liga.nombre} {liga.id === usuario?.liga_activa?.id && '✓'}
            </button>
          ))}
        </div>
      )}
    </div>
  )
}

export default LeagueSwitcher