import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import TicketHeader from '../components/TicketHeader'
import CartaJuego from '../components/CartaJuego'
import AperturaCarta from '../components/AperturaCarta'
import SelectTema from '../components/SelectTema'
import SkeletonLista from '../components/SkeletonLista'
import useTitulo from '../hooks/useTitulo'
import { useToast } from '../context/ToastContext'

function Escudo({ url, alt }) {
  if (!url) return <span className="w-5 h-5 rounded-full bg-borde/15 flex items-center justify-center text-xs shrink-0">⚽</span>
  return <img src={url} alt={alt} className="w-5 h-5 object-contain shrink-0" />
}

function SelectorRival({ miembros, onJugar, onCancelar, jugando }) {
  const [idRival, setIdRival] = useState('')
  const [mensaje, setMensaje] = useState('')

  return (
    <div className="bg-borde/10 border border-borde/30 rounded-lg p-3 mt-2 w-64">
      <p className="font-body text-[10px] uppercase tracking-widest text-premio mb-2">Elige a quién se la juegas</p>
      <SelectTema
        value={idRival}
        onChange={(e) => setIdRival(e.target.value)}
        options={[
          { value: '', label: 'Elige un rival...' },
          ...miembros.map((m) => ({ value: String(m.id), label: m.nombre })),
        ]}
        className="w-full bg-fondo text-xs"
      />
      <textarea
        value={mensaje}
        onChange={(e) => setMensaje(e.target.value)}
        placeholder="Mensaje de burla (opcional)"
        maxLength={200}
        rows={2}
        className="w-full font-body text-xs bg-fondo text-texto rounded border border-borde/40 px-2 py-1.5 mt-2"
      />
      <div className="flex gap-2 mt-2">
        <button
          onClick={() => onJugar(idRival, mensaje)}
          disabled={!idRival || jugando}
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

function SelectorPartido({ partidos, jornada, onJugar, onCancelar, jugando }) {
  const [idPartido, setIdPartido] = useState('')
  const partidoElegido = partidos.find((p) => String(p.id) === idPartido)

  return (
    <div className="bg-borde/10 border border-borde/30 rounded-lg p-3 mt-2 w-64">
      <p className="font-body text-[10px] uppercase tracking-widest text-premio mb-2">Jornada {jornada}</p>
      <SelectTema
        value={idPartido}
        onChange={(e) => setIdPartido(e.target.value)}
        options={[
          { value: '', label: 'Elige un partido...' },
          ...partidos.map((p) => ({ value: String(p.id), label: `${p.equipo_local} vs ${p.equipo_visitante} · ${p.horario_estimado}` })),
        ]}
        className="w-full bg-fondo text-xs"
      />

      {partidoElegido && (
        <div className="flex items-center justify-center gap-2 mt-2 py-2 bg-fondo rounded border border-borde/20">
          <Escudo url={partidoElegido.escudo_local} alt={partidoElegido.equipo_local} />
          <span className="font-body text-xs text-texto">{partidoElegido.equipo_local}</span>
          <span className="text-borde text-xs">vs</span>
          <span className="font-body text-xs text-texto">{partidoElegido.equipo_visitante}</span>
          <Escudo url={partidoElegido.escudo_visitante} alt={partidoElegido.equipo_visitante} />
        </div>
      )}

      <div className="flex gap-2 mt-2">
        <button
          onClick={() => onJugar(idPartido)}
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

function CartaJugada({ jugada }) {
  return (
    <div className="flex items-center gap-3 bg-fondo border border-borde/20 rounded-lg p-3">
      <CartaJuego carta={jugada.tipo_carta} categoriaNombre={jugada.tipo_carta.categoria.nombre} categoriaIcono={jugada.tipo_carta.categoria.icono} tamano="pequena" />
      <div className="min-w-0">
        <p className="font-body text-xs font-semibold text-premio">⏳ Esperando a que cierre la jornada</p>
        {jugada.partido ? (
          <p className="font-body text-xs text-texto mt-0.5">
            Jugada sobre: <span className="font-semibold">{jugada.partido.equipo_local} vs {jugada.partido.equipo_visitante}</span>
            <br />
            <span className="text-borde">Jornada {jugada.partido.jornada}</span>
          </p>
        ) : jugada.objetivo ? (
          <p className="font-body text-xs text-texto mt-0.5">
            {jugada.objetivo.es_uno_mismo ? (
              <span className="font-semibold">Te proteges a ti mismo</span>
            ) : (
              <>Jugada contra: <span className="font-semibold">{jugada.objetivo.nombre}</span></>
            )}
            <br />
            <span className="text-borde">Efecto en la jornada {jugada.jornada_efecto}</span>
            {jugada.mensaje_falta && <><br /><span className="italic text-borde">"{jugada.mensaje_falta}"</span></>}
          </p>
        ) : (
          <p className="font-body text-xs text-borde mt-0.5">Jugada para la jornada {jugada.jornada_efecto}</p>
        )}
      </div>
    </div>
  )
}

function ReversoCarta({ className = '' }) {
  return (
    <div
      className={`rounded-md border-2 border-acento bg-fondo relative flex items-center justify-center ${className}`}
      style={{ backgroundImage: 'repeating-linear-gradient(135deg, rgba(200,255,77,0.08) 0, rgba(200,255,77,0.08) 1px, transparent 1px, transparent 8px)' }}
    >
      <div className="w-6 h-6 rounded-full border-2 border-acento flex items-center justify-center">
        <span className="text-[10px]">⚽</span>
      </div>
    </div>
  )
}

function AbanicoMisterio() {
  return <ReversoCarta className="w-9 h-12 shrink-0" />
}

function BotonAbrirSobres({ sinAbrir, onAbrir, abriendo }) {
  if (sinAbrir === 0) return null

  return (
    <button
      onClick={onAbrir}
      disabled={abriendo}
      className="relative w-full mb-6 bg-fondo border-2 border-acento/50 rounded-xl overflow-visible flex items-center gap-4 pl-6 pr-5 py-4 hover:border-acento transition disabled:opacity-50"
      style={{ boxShadow: '0 0 24px rgba(200,255,77,0.15)' }}
    >
      {/* Recortes de ticket perforado, a juego con el resto de la app */}
      <div className="absolute -left-3 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-fondo border-2 border-acento/50" />
      <div className="absolute -right-3 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-fondo border-2 border-acento/50" />

      <AbanicoMisterio />

      <div className="text-left flex-1">
        <p className="font-display text-lg text-texto">
          {sinAbrir} carta{sinAbrir > 1 ? 's' : ''} te espera{sinAbrir > 1 ? 'n' : ''}
        </p>
        <p className="font-body text-xs text-premio uppercase tracking-widest font-semibold">Descúbrela{sinAbrir > 1 ? 's' : ''}</p>
      </div>

      <span className="font-display text-2xl text-acento">→</span>
    </button>
  )
}

function MisCartasPage() {
  useTitulo('Mis Cartas')
  const { usuario } = useAuth()
  const toast = useToast()
  const queryClient = useQueryClient()
  const [idCartaEligiendoPartido, setIdCartaEligiendoPartido] = useState(null)
  const [idCartaEligiendoRival, setIdCartaEligiendoRival] = useState(null)
  const [cartaAbriendose, setCartaAbriendose] = useState(null)

  const { data, isLoading, error } = useQuery({
    queryKey: ['mis-cartas'],
    queryFn: async () => (await client.get('/api/v1/mis-cartas')).data.data,
  })

  const { data: jornadaJugable } = useQuery({
    queryKey: ['proxima-jornada-jugable'],
    queryFn: async () => (await client.get('/api/v1/mis-cartas/proxima-jornada-jugable')).data.data,
    enabled: data?.activo === true,
  })

  const { data: miembros } = useQuery({
    queryKey: ['liga-activa-miembros'],
    queryFn: async () => (await client.get('/api/v1/liga-activa/miembros')).data.data,
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

  const jugarFalta = useMutation({
    mutationFn: ({ idCarta, idUsuarioObjetivo, mensaje }) =>
      client.post(`/api/v1/mis-cartas/${idCarta}/jugar-falta`, idUsuarioObjetivo ? { id_usuario_objetivo: idUsuarioObjetivo, mensaje: mensaje || undefined } : {}),
    onSuccess: (respuesta) => {
      toast.exito(respuesta.data.message)
      setIdCartaEligiendoRival(null)
      queryClient.invalidateQueries({ queryKey: ['mis-cartas'] })
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo jugar la Falta.'),
  })

  const abrirSiguiente = useMutation({
    mutationFn: () => client.post('/api/v1/mis-cartas/abrir-siguiente'),
    onSuccess: (respuesta) => setCartaAbriendose(respuesta.data.data),
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo abrir la carta.'),
  })

  function cerrarApertura() {
    setCartaAbriendose(null)
    queryClient.invalidateQueries({ queryKey: ['mis-cartas'] })
  }

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
  const partidosDisponibles = jornadaJugable?.partidos ?? []
  const hayJornadaJugable = jornadaJugable?.jornada != null && partidosDisponibles.length > 0

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      {cartaAbriendose && <AperturaCarta cartaGanada={cartaAbriendose} onCerrar={cerrarApertura} />}

      <BotonAbrirSobres sinAbrir={data.sin_abrir} onAbrir={() => abrirSiguiente.mutate()} abriendo={abrirSiguiente.isPending} />

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
              ⚠️ Tienes más cartas de las que caben en tu mano — descarta alguna para poder volver a jugar cartas nuevas.
            </p>
          </div>
        )}
      </div>

      <p className="font-body text-sm font-semibold text-texto mb-3">Tu mano</p>

      {data.cartas.length === 0 ? (
        <p className="font-body text-sm text-borde text-center py-8">
          {data.sin_abrir > 0 ? 'Abre tus sobres pendientes para ver tus cartas aquí.' : 'Aún no tienes ninguna carta. Llegarán cuando el admin reparta la próxima jornada.'}
        </p>
      ) : (
        <div className="flex flex-wrap gap-5 justify-center mb-8">
          {data.cartas.map((carta) => {
            const esJugada = carta.tipo_carta.categoria.nombre === 'Jugadas'
            const esFalta = carta.tipo_carta.categoria.nombre === 'Faltas'
            const esEscudo = carta.tipo_carta.codigo_efecto === 'FAL-PCOM-ESCUDO'

            return (
              <div key={carta.id} className="flex flex-col items-center gap-2">
                <CartaJuego carta={carta.tipo_carta} categoriaNombre={carta.tipo_carta.categoria.nombre} categoriaIcono={carta.tipo_carta.categoria.icono} />
                <div className="flex gap-3">
                  {esJugada && carta.requiere_partido && hayJornadaJugable && (
                    <button
                      onClick={() => setIdCartaEligiendoPartido(carta.id)}
                      disabled={sobreTope}
                      className="font-body text-xs text-acento hover:underline disabled:opacity-40 disabled:no-underline disabled:cursor-not-allowed"
                      title={sobreTope ? 'Descarta alguna carta primero para poder jugar' : undefined}
                    >
                      Jugar
                    </button>
                  )}
                  {esJugada && !carta.requiere_partido && (
                    <button
                      onClick={() => jugar.mutate({ idCarta: carta.id, idPartido: null })}
                      disabled={sobreTope || jugar.isPending}
                      className="font-body text-xs text-acento hover:underline disabled:opacity-40 disabled:no-underline disabled:cursor-not-allowed"
                      title={sobreTope ? 'Descarta alguna carta primero para poder jugar' : undefined}
                    >
                      Jugar
                    </button>
                  )}
                  {esFalta && esEscudo && (
                    <button
                      onClick={() => jugarFalta.mutate({ idCarta: carta.id, idUsuarioObjetivo: null, mensaje: null })}
                      disabled={sobreTope || jugarFalta.isPending}
                      className="font-body text-xs text-acento hover:underline disabled:opacity-40 disabled:no-underline disabled:cursor-not-allowed"
                      title={sobreTope ? 'Descarta alguna carta primero para poder jugar' : undefined}
                    >
                      {jugarFalta.isPending ? 'Activando...' : 'Activar'}
                    </button>
                  )}
                  {esFalta && !esEscudo && miembros && (
                    <button
                      onClick={() => setIdCartaEligiendoRival(carta.id)}
                      disabled={sobreTope}
                      className="font-body text-xs text-acento hover:underline disabled:opacity-40 disabled:no-underline disabled:cursor-not-allowed"
                      title={sobreTope ? 'Descarta alguna carta primero para poder jugar' : undefined}
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
                    partidos={partidosDisponibles}
                    jornada={jornadaJugable.jornada}
                    onJugar={(idPartido) => jugar.mutate({ idCarta: carta.id, idPartido })}
                    onCancelar={() => setIdCartaEligiendoPartido(null)}
                    jugando={jugar.isPending}
                  />
                )}
                {idCartaEligiendoRival === carta.id && (
                  <SelectorRival
                    miembros={miembros.filter((m) => m.id !== usuario?.id)}
                    onJugar={(idRival, mensaje) => jugarFalta.mutate({ idCarta: carta.id, idUsuarioObjetivo: idRival, mensaje })}
                    onCancelar={() => setIdCartaEligiendoRival(null)}
                    jugando={jugarFalta.isPending}
                  />
                )}
              </div>
            )
          })}
        </div>
      )}

      {data.jugadas.length > 0 && (
        <>
          <p className="font-body text-sm font-semibold text-texto mb-3">Cartas jugadas, esperando resolución</p>
          <div className="flex flex-col gap-2">
            {data.jugadas.map((jugada) => <CartaJugada key={jugada.id} jugada={jugada} />)}
          </div>
        </>
      )}
    </div>
  )
}

export default MisCartasPage