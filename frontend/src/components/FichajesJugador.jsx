import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import SelectTema from './SelectTema'

function Escudo({ url, alt }) {
  if (!url) return <span className="w-6 h-6 rounded-full bg-borde/15 flex items-center justify-center text-xs shrink-0">⚽</span>
  return <img src={url} alt={alt} className="w-6 h-6 object-contain shrink-0" />
}

function FichajesJugador({ jugadorId, dadoDeBaja }) {
  const queryClient = useQueryClient()
  const [equipoDestino, setEquipoDestino] = useState('')
  const [fechaFichaje, setFechaFichaje] = useState(new Date().toISOString().slice(0, 10))
  const [dorsal, setDorsal] = useState('')
  const [nuevoDorsal, setNuevoDorsal] = useState('')
  const [mensaje, setMensaje] = useState(null)

  const { data: historial } = useQuery({
    queryKey: ['admin', 'jugador-historial', jugadorId],
    queryFn: async () => (await client.get(`/api/v1/admin/jugadores/${jugadorId}/historial`)).data.data,
  })

  const { data: equipos } = useQuery({
    queryKey: ['admin', 'equipos'],
    queryFn: async () => (await client.get('/api/v1/admin/equipos')).data.data,
  })

  function invalidarTodo() {
    queryClient.invalidateQueries({ queryKey: ['admin', 'jugador-historial', jugadorId] })
    queryClient.invalidateQueries({ queryKey: ['admin', 'jugadores', String(jugadorId)] })
    queryClient.invalidateQueries({ queryKey: ['admin', 'jugadores'] })
  }

  const fichar = useMutation({
    mutationFn: () => client.post(`/api/v1/admin/jugadores/${jugadorId}/fichar`, {
      id_equipo_nuevo: equipoDestino,
      fecha_fichaje: fechaFichaje,
      dorsal: dorsal || null,
    }),
    onSuccess: () => {
      setMensaje({ tipo: 'exito', texto: 'Fichaje registrado correctamente.' })
      setEquipoDestino('')
      setDorsal('')
      invalidarTodo()
    },
    onError: (err) => setMensaje({ tipo: 'error', texto: err.response?.data?.message ?? 'Error al registrar el fichaje.' }),
  })

  const darDeBaja = useMutation({
    mutationFn: () => client.post(`/api/v1/admin/jugadores/${jugadorId}/dar-de-baja`),
    onSuccess: () => { setMensaje({ tipo: 'exito', texto: 'Jugador dado de baja.' }); invalidarTodo() },
  })

  const reactivar = useMutation({
    mutationFn: () => client.post(`/api/v1/admin/jugadores/${jugadorId}/reactivar`),
    onSuccess: () => { setMensaje({ tipo: 'exito', texto: 'Jugador reactivado.' }); invalidarTodo() },
  })

  const cambiarDorsal = useMutation({
    mutationFn: () => client.post(`/api/v1/admin/jugadores/${jugadorId}/cambiar-dorsal`, { dorsal: nuevoDorsal }),
    onSuccess: () => {
      setMensaje({ tipo: 'exito', texto: 'Dorsal actualizado.' })
      setNuevoDorsal('')
      invalidarTodo()
    },
    onError: (err) => setMensaje({ tipo: 'error', texto: err.response?.data?.message ?? 'Error al cambiar el dorsal.' }),
  })

  function handleFichar(event) {
    event.preventDefault()
    setMensaje(null)
    if (!equipoDestino) return
    fichar.mutate()
  }

  const equipoActual = historial?.find((h) => h.actual)

  return (
    <div className="bg-fondo border border-borde/30 rounded-lg p-6 mt-4">
      <h3 className="font-display text-lg text-texto mb-4">Fichajes y estado</h3>

      {mensaje && (
        <p className={`font-body text-sm mb-4 px-3 py-2 rounded ${mensaje.tipo === 'exito' ? 'bg-acento/10 text-acento' : 'bg-red-500/10 text-red-500'}`}>
          {mensaje.texto}
        </p>
      )}

      <div className="flex items-center justify-between mb-4 pb-4 border-b border-borde/20 flex-wrap gap-3">
        <div className="flex items-center gap-2">
          {equipoActual ? (
            <>
              <Escudo url={equipoActual.escudo_url} alt={equipoActual.equipo} />
              <div>
                <p className="font-body text-sm text-texto">{equipoActual.equipo}</p>
                <p className="font-body text-xs text-borde">Dorsal {equipoActual.dorsal ?? '—'}</p>
              </div>
            </>
          ) : (
            <p className="font-body text-sm text-borde">Sin equipo activo</p>
          )}
        </div>

        <button
          onClick={() => (dadoDeBaja ? reactivar.mutate() : darDeBaja.mutate())}
          disabled={darDeBaja.isPending || reactivar.isPending}
          className={`font-body text-xs font-semibold rounded px-3 py-1.5 disabled:opacity-50 ${
            dadoDeBaja ? 'bg-acento text-fondo hover:brightness-110' : 'bg-red-500/15 text-red-500 hover:bg-red-500/25'
          }`}
        >
          {dadoDeBaja ? 'Reactivar jugador' : 'Dar de baja'}
        </button>
      </div>

      {equipoActual && !dadoDeBaja && (
        <div className="mb-4 pb-4 border-b border-borde/20">
          <p className="font-body text-xs uppercase tracking-widest text-borde mb-2">Cambiar solo el dorsal</p>
          <div className="flex items-center gap-2">
            <input
              type="number"
              min="1"
              max="99"
              placeholder="Nuevo dorsal"
              value={nuevoDorsal}
              onChange={(e) => setNuevoDorsal(e.target.value)}
              className="w-32 font-body text-sm bg-borde/10 text-texto rounded border border-borde/40 px-3 py-1.5"
            />
            <button
              onClick={() => cambiarDorsal.mutate()}
              disabled={!nuevoDorsal || cambiarDorsal.isPending}
              className="font-body text-sm font-semibold bg-premio text-fondo rounded px-4 py-1.5 hover:brightness-110 disabled:opacity-50"
            >
              {cambiarDorsal.isPending ? 'Guardando...' : 'Cambiar dorsal'}
            </button>
          </div>
        </div>
      )}

      {!dadoDeBaja && (
        <form onSubmit={handleFichar} className="flex flex-wrap items-end gap-3 mb-4 pb-4 border-b border-borde/20">
          <div className="flex-1 min-w-[160px]">
            <label className="font-body text-xs text-borde block mb-1">Fichar por</label>
            <SelectTema
              value={equipoDestino}
              onChange={(e) => setEquipoDestino(e.target.value)}
              options={equipos?.map((e) => ({ value: e.id, label: e.nombre })) ?? []}
              className="w-full bg-borde/10"
            />
          </div>
          <div>
            <label className="font-body text-xs text-borde block mb-1">Fecha</label>
            <input
              type="date"
              value={fechaFichaje}
              onChange={(e) => setFechaFichaje(e.target.value)}
              className="font-body bg-borde/10 text-texto rounded border border-borde/40 px-3 py-1.5"
            />
          </div>
          <div className="w-20">
            <label className="font-body text-xs text-borde block mb-1">Dorsal</label>
            <input
              type="number"
              min="1"
              max="99"
              value={dorsal}
              onChange={(e) => setDorsal(e.target.value)}
              className="w-full font-body bg-borde/10 text-texto rounded border border-borde/40 px-3 py-1.5"
            />
          </div>
          <button
            type="submit"
            disabled={fichar.isPending || !equipoDestino}
            className="font-body text-sm font-semibold bg-acento text-fondo rounded px-4 py-1.5 hover:brightness-110 disabled:opacity-50"
          >
            {fichar.isPending ? 'Fichando...' : 'Fichar'}
          </button>
        </form>
      )}

      <p className="font-body text-[10px] uppercase tracking-widest text-borde mb-2">Historial</p>
      {!historial || historial.length === 0 ? (
        <p className="font-body text-sm text-borde">Sin historial de equipos.</p>
      ) : (
        <div className="flex flex-col gap-1.5">
          {historial.map((h, i) => (
            <div key={i} className="flex items-center gap-2 text-sm">
              <Escudo url={h.escudo_url} alt={h.equipo} />
              <span className="font-body text-texto">{h.equipo}</span>
              <span className="font-body text-xs text-borde">
                {h.fecha_incorporacion && `${h.fecha_incorporacion} `}→ {h.fecha_salida ?? 'actual'}
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

export default FichajesJugador