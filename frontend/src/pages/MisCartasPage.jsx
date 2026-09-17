import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import TicketHeader from '../components/TicketHeader'
import CartaJuego from '../components/CartaJuego'
import SelectTema from '../components/SelectTema'
import SkeletonLista from '../components/SkeletonLista'
import useTitulo from '../hooks/useTitulo'
import { useToast } from '../context/ToastContext'

function SelectorPartido({ carta, partidos, onJugar, onCancelar, jugando }) {
  const [idPartido, setIdPartido] = useState('')

  return (
    <div className="bg-borde/10 border border-borde/30 rounded-lg p-3 mt-2 w-56">
      <SelectTema
        value={idPartido}
        onChange={(e) => setIdPartido(e.target.value)}
        options={[
          { value: '', label: 'Elige un partido...' },
          ...partidos.map((p) => ({ value: String(p.id), label: `${p.equipo_local} vs ${p.equipo_visitante}` })),
        ]}
        className="w-full bg-fondo text-xs"
      />
      <div className="flex gap-2 mt-2">
        <button
          onClick={() => onJugar(carta.id, idPartido)}
          disabled={!idPartido || jugando}
          className="font-body text-xs font-semibold bg-acento text-fondo rounded px-3 py-1.5 hover:brightness-110 disabled:opacity-50 flex-1"
        >
          {jugando ? 'Jugando...' : 'Confirmar'}
        </button>
        <button onClick={onCancelar} className="font-body text-xs text-borde hover:text-texto px-2">
          Cancelar
        </button>
      </div>
    </div>
  )
}

function MisCartasPage() {
  useTitulo('Mis Cartas')
  const toast = useToast()
  const queryClient = useQueryClient()
  const [idCartaEligiendoPartido, setIdCartaEligiendoPartido] = useState(null)

  const { data, isLoading, error } = useQuery({
    queryKey: ['mis-cartas'],
    queryFn: async () => (await client.get('/api/v1/mis-cartas')).data.data,
  })

  const { data: dashboard } = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => (await client.get('/api/v1/dashboard')).data.data,
    enabled: data?.activo === true,
  })

  const descartar = useMutation({
    mutationFn: (id) => client.post(`/api/v1/mis-cartas/${id}/descartar`),
    onSuccess: () => {
      toast.exito('Carta descartada.')
      queryClient.invalidateQueries({ queryKey: ['mis-cartas'] })
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo descartar.'),
  })

  const jugar = useMutation({
    mutationFn: ({ idCarta, idPartido }) => client.post(`/api/v1/mis-cartas/${idCarta}/jugar`, { id_partido: idPartido }),
    onSuccess: () => {
      toast.exito('Carta jugada. Se resolverá cuando se cierre la jornada.')
      setIdCartaEligiendoPartido(null)
      queryClient.invalidateQueries({ queryKey: ['mis-cartas'] })
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo jugar la carta.'),
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
  const partidosDisponibles = dashboard?.proxima_jornada?.partidos?.filter((p) => p.estado === 'Programado') ?? []

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
          {data.cartas.map((carta) => {
            const esJugada = carta.tipo_carta.categoria.nombre === 'Jugadas'

            return (
              <div key={carta.id} className="flex flex-col items-center gap-2">
                <CartaJuego carta={carta.tipo_carta} categoriaNombre={carta.tipo_carta.categoria.nombre} />
                <div className="flex gap-3">
                  {esJugada && partidosDisponibles.length > 0 && idCartaEligiendoPartido !== carta.id && (
                    <button
                      onClick={() => setIdCartaEligiendoPartido(carta.id)}
                      className="font-body text-xs text-acento hover:underline"
                    >
                      Jugar
                    </button>
                  )}
                  <button
                    onClick={() => descartar.mutate(carta.id)}
                    disabled={descartar.isPending}
                    className="font-body text-xs text-borde hover:text-red-500 underline"
                  >
                    Descartar
                  </button>
                </div>
                {idCartaEligiendoPartido === carta.id && (
                  <SelectorPartido
                    carta={carta}
                    partidos={partidosDisponibles}
                    onJugar={(idCarta, idPartido) => jugar.mutate({ idCarta, idPartido })}
                    onCancelar={() => setIdCartaEligiendoPartido(null)}
                    jugando={jugar.isPending}
                  />
                )}
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}

export default MisCartasPage