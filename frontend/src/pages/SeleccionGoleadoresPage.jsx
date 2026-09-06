import { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery, useMutation } from '@tanstack/react-query'
import client from '../api/client'
import { useToast } from '../context/ToastContext'
import useTitulo from '../hooks/useTitulo'

const MAXIMO_GOLEADORES = 5

function FotoJugador({ url, nombre }) {
  if (url) return <img src={url} alt={nombre} className="w-9 h-9 rounded-full object-cover shrink-0" />
  return (
    <span className="w-9 h-9 rounded-full bg-acento/10 border border-acento/20 flex items-center justify-center text-xs font-semibold shrink-0 text-acento">
      {nombre?.[0]}
    </span>
  )
}

function SeleccionGoleadoresPage() {
  const { jornada } = useParams()
  const toast = useToast()
  const [busqueda, setBusqueda] = useState('')
  const [seleccionados, setSeleccionados] = useState([])

  useTitulo(`Goleadores — Jornada ${jornada}`)

  const { data: jugadores, isLoading: cargandoJugadores } = useQuery({
    queryKey: ['jugadores-buscables'],
    queryFn: async () => (await client.get('/api/v1/jugadores-buscables')).data.data,
  })

  const { data: actual, isLoading: cargandoActual } = useQuery({
    queryKey: ['goleadores', jornada],
    queryFn: async () => (await client.get(`/api/v1/jornadas/${jornada}/goleadores`)).data.data,
  })

  useEffect(() => {
    if (actual) setSeleccionados(actual)
  }, [actual])

  const guardar = useMutation({
    mutationFn: () => client.post(`/api/v1/jornadas/${jornada}/goleadores`, {
      jugadores: seleccionados.map((j) => j.id),
    }),
    onSuccess: () => toast.exito('Goleadores guardados correctamente.'),
    onError: (err) => toast.error(err.response?.data?.message ?? 'Error al guardar.'),
  })

  function agregar(jugador) {
    if (seleccionados.length >= MAXIMO_GOLEADORES) return
    if (seleccionados.some((j) => j.id === jugador.id)) return
    setSeleccionados((prev) => [...prev, jugador])
    setBusqueda('')
  }

  function quitar(idJugador) {
    setSeleccionados((prev) => prev.filter((j) => j.id !== idJugador))
  }

  const normalizar = (texto) => String(texto ?? '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')

  const resultadosBusqueda = busqueda.length >= 2 && jugadores
    ? jugadores.filter((j) => {
        const yaElegido = seleccionados.some((s) => s.id === j.id)
        if (yaElegido) return false
        return normalizar(j.nombre).includes(normalizar(busqueda))
      }).slice(0, 8)
    : []

  const cargando = cargandoJugadores || cargandoActual

  return (
    <div className="max-w-lg mx-auto px-4 py-8">
      <Link to={`/jornadas/${jornada}`} className="font-body text-sm text-acento hover:underline mb-4 inline-block">← Volver a la jornada</Link>

      <h1 className="font-display text-2xl text-texto mb-1">Tus 5 goleadores</h1>
      <p className="font-body text-sm text-borde mb-6">
        Jornada {jornada} — +1 punto por cada gol que marquen. Ninguno puede repetirse en la jornada siguiente.
      </p>

      {cargando ? (
        <p className="font-body text-texto p-4">Cargando...</p>
      ) : (
        <>
          <div className="bg-fondo border border-borde/30 rounded-lg p-5 mb-4">
            <p className="font-body text-xs uppercase tracking-widest text-borde mb-3">
              Elegidos ({seleccionados.length}/{MAXIMO_GOLEADORES})
            </p>

            {seleccionados.length === 0 ? (
              <p className="font-body text-sm text-borde">Aún no has elegido a nadie.</p>
            ) : (
              <div className="flex flex-col gap-2">
                {seleccionados.map((j) => (
                  <div key={j.id} className="flex items-center gap-3 bg-borde/5 rounded-lg p-2">
                    <FotoJugador url={j.foto_url} nombre={j.nombre} />
                    <p className="font-body text-sm text-texto flex-1 truncate">{j.nombre}</p>
                    <button onClick={() => quitar(j.id)} className="font-body text-xs text-red-500 hover:underline shrink-0">
                      Quitar
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>

          {seleccionados.length < MAXIMO_GOLEADORES && (
            <div className="relative mb-6">
              <input
                value={busqueda}
                onChange={(e) => setBusqueda(e.target.value)}
                placeholder="Busca un jugador por nombre..."
                className="w-full font-body bg-borde/10 text-texto rounded border border-borde/40 px-3 py-2.5 focus:outline-none focus:border-acento"
              />
              {resultadosBusqueda.length > 0 && (
                <div className="absolute top-full left-0 right-0 mt-1 bg-fondo border border-borde/30 rounded-lg shadow-lg z-10 max-h-64 overflow-y-auto">
                  {resultadosBusqueda.map((j) => (
                    <button
                      key={j.id}
                      onClick={() => agregar(j)}
                      className="w-full flex items-center gap-3 px-3 py-2 hover:bg-borde/10 text-left"
                    >
                      <FotoJugador url={j.foto_url} nombre={j.nombre} />
                      <div className="min-w-0">
                        <p className="font-body text-sm text-texto truncate">{j.nombre}</p>
                        <p className="font-body text-[11px] text-borde truncate">{j.equipo}</p>
                      </div>
                    </button>
                  ))}
                </div>
              )}
            </div>
          )}

          <button
            onClick={() => guardar.mutate()}
            disabled={seleccionados.length !== MAXIMO_GOLEADORES || guardar.isPending}
            className="w-full font-body text-sm font-semibold bg-acento text-fondo rounded py-2.5 hover:brightness-110 disabled:opacity-50"
          >
            {guardar.isPending ? 'Guardando...' : `Guardar (${seleccionados.length}/${MAXIMO_GOLEADORES})`}
          </button>
        </>
      )}
    </div>
  )
}

export default SeleccionGoleadoresPage