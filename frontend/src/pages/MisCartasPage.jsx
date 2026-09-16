import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import TicketHeader from '../components/TicketHeader'
import CartaJuego from '../components/CartaJuego'
import SkeletonLista from '../components/SkeletonLista'
import useTitulo from '../hooks/useTitulo'
import { useToast } from '../context/ToastContext'

function MisCartasPage() {
  useTitulo('Mis Cartas')
  const toast = useToast()
  const queryClient = useQueryClient()

  const { data, isLoading, error } = useQuery({
    queryKey: ['mis-cartas'],
    queryFn: async () => (await client.get('/api/v1/mis-cartas')).data.data,
  })

  const descartar = useMutation({
    mutationFn: (id) => client.post(`/api/v1/mis-cartas/${id}/descartar`),
    onSuccess: () => {
      toast.exito('Carta descartada.')
      queryClient.invalidateQueries({ queryKey: ['mis-cartas'] })
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo descartar.'),
  })

  if (isLoading) return <div className="max-w-4xl mx-auto px-4 py-8"><SkeletonLista /></div>
  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>

  if (!data.activo) {
    return (
      <div className="max-w-2xl mx-auto px-4 py-16 text-center">
        <p className="text-4xl mb-3">🃏</p>
        <p className="font-display text-lg text-texto mb-1">Esta liga no tiene el modo Cartas activado</p>
        <p className="font-body text-sm text-borde">Pregúntale al admin de tu liga si quiere activarlo.</p>
      </div>
    )
  }

  const sobreTope = data.cartas.length > data.tope_mano_cartas

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
        <TicketHeader
          titulo="Mis Cartas"
          accion={
            <span className={`font-marcador text-sm font-bold ${sobreTope ? 'text-red-500' : 'text-acento'}`}>
              {data.cartas.length}/{data.tope_mano_cartas}
            </span>
          }
        />
        {sobreTope && (
          <div className="bg-red-500/10 border-t border-red-500/20 px-4 py-2.5">
            <p className="font-body text-xs text-red-500 font-semibold">
              ⚠️ Tienes más cartas de las que caben en tu mano — descarta alguna para bajar del límite.
            </p>
          </div>
        )}
      </div>

      {data.cartas.length === 0 ? (
        <p className="font-body text-sm text-borde text-center py-12">
          Aún no tienes ninguna carta. Llegarán cuando el admin reparta la próxima jornada.
        </p>
      ) : (
        <div className="flex flex-wrap gap-5 justify-center">
          {data.cartas.map((carta) => (
            <div key={carta.id} className="flex flex-col items-center gap-2">
              <CartaJuego carta={carta.tipo_carta} categoriaNombre={carta.tipo_carta.categoria.nombre} />
              <button
                onClick={() => descartar.mutate(carta.id)}
                disabled={descartar.isPending}
                className="font-body text-xs text-borde hover:text-red-500 underline"
              >
                Descartar
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

export default MisCartasPage
