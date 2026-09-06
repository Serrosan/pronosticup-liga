import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import { useToast } from '../context/ToastContext'

const NOMBRES = {
  completa: 'Quiniela completa (temporada entera)',
  primera_mitad: 'Primera mitad (jornadas 1-18)',
  segunda_mitad: 'Segunda mitad (jornada 19-final)',
}

function AdminQuinielasPage() {
  const toast = useToast()
  const queryClient = useQueryClient()

  const { data: quinielas, isLoading } = useQuery({
    queryKey: ['admin', 'quinielas'],
    queryFn: async () => (await client.get('/api/v1/admin/quinielas')).data.data,
  })

  function invalidar() {
    queryClient.invalidateQueries({ queryKey: ['admin', 'quinielas'] })
  }

  const abrir = useMutation({
    mutationFn: (id) => client.post(`/api/v1/admin/quinielas/${id}/abrir`),
    onSuccess: () => { toast.exito('Quiniela abierta.'); invalidar() },
  })

  const cerrar = useMutation({
    mutationFn: (id) => client.post(`/api/v1/admin/quinielas/${id}/cerrar`),
    onSuccess: () => { toast.exito('Quiniela cerrada.'); invalidar() },
  })

  const resolver = useMutation({
    mutationFn: (id) => client.post(`/api/v1/admin/quinielas/${id}/resolver`),
    onSuccess: () => { toast.exito('Quiniela resuelta y puntos calculados.'); invalidar() },
    onError: (err) => toast.error(err.response?.data?.message ?? 'Error al resolver.'),
  })

  if (isLoading) return <p className="font-body text-texto p-4">Cargando...</p>

  return (
    <div className="max-w-2xl">
      <h2 className="font-display text-xl text-texto mb-4">Quinielas de posiciones</h2>

      <div className="flex flex-col gap-3">
        {quinielas.map((q) => (
          <div key={q.id} className="bg-fondo border border-borde/30 rounded-lg p-4">
            <div className="flex items-center justify-between mb-2">
              <p className="font-display text-base text-texto">{NOMBRES[q.tipo]}</p>
              <span className={`font-body text-xs font-semibold rounded-full px-2.5 py-1 ${
                q.resuelta ? 'bg-acento/15 text-acento' : q.abierta ? 'bg-premio/15 text-premio' : 'bg-borde/15 text-borde'
              }`}>
                {q.resuelta ? 'Resuelta' : q.abierta ? 'Abierta' : 'Cerrada'}
              </span>
            </div>

            <p className="font-body text-xs text-borde mb-3">{q.total_predicciones} usuarios han predicho</p>

            <div className="flex gap-2">
              {!q.resuelta && !q.abierta && (
                <button onClick={() => abrir.mutate(q.id)} className="font-body text-xs font-semibold bg-acento text-fondo rounded px-3 py-1.5 hover:brightness-110">
                  Abrir para predicciones
                </button>
              )}
              {!q.resuelta && q.abierta && (
                <button onClick={() => cerrar.mutate(q.id)} className="font-body text-xs font-semibold text-borde border border-borde/40 rounded px-3 py-1.5 hover:bg-borde/10">
                  Cerrar (sin resolver)
                </button>
              )}
              {!q.resuelta && (
                <button
                  onClick={() => { if (confirm('¿Resolver esta quiniela ahora, con la clasificación real actual? No se puede deshacer.')) resolver.mutate(q.id) }}
                  className="font-body text-xs font-semibold bg-premio text-fondo rounded px-3 py-1.5 hover:brightness-110"
                >
                  Resolver ahora
                </button>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}

export default AdminQuinielasPage