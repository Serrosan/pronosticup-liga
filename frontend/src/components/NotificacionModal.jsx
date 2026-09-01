import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import useCerrarConEscape from '../hooks/useCerrarConEscape'

function NotificacionModal() {
  const [cerrado, setCerrado] = useState(null)
  const queryClient = useQueryClient()

  const { data } = useQuery({
    queryKey: ['notificaciones-no-leidas'],
    queryFn: async () => (await client.get('/api/v1/notificaciones/no-leidas')).data,
    refetchInterval: 15000,
  })

  const marcarLeida = useMutation({
    mutationFn: (id) => client.post(`/api/v1/notificaciones/${id}/leer`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['notificaciones-no-leidas'] }),
  })

  const pendiente = data?.importante_pendiente
  const mostrar = !!pendiente && cerrado !== pendiente.id

  function cerrar() {
    if (pendiente) {
      marcarLeida.mutate(pendiente.id)
      setCerrado(pendiente.id)
    }
  }

  useCerrarConEscape(mostrar, cerrar)

  useEffect(() => {
    if (pendiente && cerrado !== pendiente.id && Notification?.permission === 'granted' && document.hidden) {
      new Notification(pendiente.titulo, { body: pendiente.mensaje })
    }
  }, [data, cerrado])

  if (!mostrar) return null

  return (
    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
      <div className="bg-fondo border-2 border-acento rounded-lg p-6 max-w-sm w-full text-center">
        <p className="text-3xl mb-3">🔔</p>
        <h2 className="font-display text-lg text-texto mb-2">{pendiente.titulo}</h2>
        <p className="font-body text-sm text-borde mb-5">{pendiente.mensaje}</p>
        <button
          onClick={cerrar}
          className="bg-acento text-fondo font-body font-semibold text-sm rounded px-6 py-2.5 hover:brightness-110"
        >
          Entendido
        </button>
      </div>
    </div>
  )
}

export default NotificacionModal