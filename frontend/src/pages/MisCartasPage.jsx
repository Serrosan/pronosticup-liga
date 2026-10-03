import { useState, useEffect } from 'react'
import { useLocation } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient, useInfiniteQuery } from '@tanstack/react-query'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import TicketHeader from '../components/TicketHeader'
import CartaJuego from '../components/CartaJuego'
import AperturaCarta from '../components/AperturaCarta'
import SelectTema from '../components/SelectTema'
import SkeletonLista from '../components/SkeletonLista'
import useTitulo from '../hooks/useTitulo'
import { useToast } from '../context/ToastContext'

const CLAVE_GUIA_VISTA = 'pronosticup_guia_cartas_vista'
const CLAVE_ORDEN_MANO = 'pronosticup_orden_mano_cartas'

const ORDEN_RAREZA = { Legendaria: 0, Rara: 1, PocoComun: 2, Comun: 3 }
const ORDEN_CATEGORIA = { Jugadas: 0, Faltas: 1 }

function ordenarMano(cartas, criterio) {
  const copia = [...cartas]

  if (criterio === 'categoria') {
    return copia.sort((a, b) => {
      const cat = (ORDEN_CATEGORIA[a.tipo_carta.categoria.nombre] ?? 9) - (ORDEN_CATEGORIA[b.tipo_carta.categoria.nombre] ?? 9)
      return cat !== 0 ? cat : (ORDEN_RAREZA[a.tipo_carta.rareza] ?? 9) - (ORDEN_RAREZA[b.tipo_carta.rareza] ?? 9)
    })
  }

  // 'rareza' por defecto: las más raras primero
  return copia.sort((a, b) => (ORDEN_RAREZA[a.tipo_carta.rareza] ?? 9) - (ORDEN_RAREZA[b.tipo_carta.rareza] ?? 9))
}

function formatearInicio(iso) {
  return new Date(iso).toLocaleString('es-ES', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}

function Escudo({ url, alt }) {
  if (!url) return <span className="w-5 h-5 rounded-full bg-borde/15 flex items-center justify-center text-xs shrink-0">⚽</span>
  return <img src={url} alt={alt} className="w-5 h-5 object-contain shrink-0" />
}

const COLOR_RAREZA_PANEL = {
  Comun: '#C8FF4D',
  PocoComun: '#4DA6FF',
  Rara: '#B44DFF',
  Legendaria: '#FFB238',
}

// Contenedor compartido de los paneles flotantes de acción (confirmar, elegir
// partido, elegir rival) — con el color de la propia carta como acento, para que
// se sienta parte de ella en vez de una caja gris genérica sin relación con nada.
// peligro fuerza rojo (acciones destructivas), independientemente de la rareza.
function PanelFlotante({ rareza, icono, titulo, peligro, children }) {
  const color = peligro ? '#EF4444' : (COLOR_RAREZA_PANEL[rareza] ?? COLOR_RAREZA_PANEL.Comun)

  return (
    <div
      className="bg-fondo rounded-xl border-2 p-3.5 mt-2 w-72"
      style={{ borderColor: color, boxShadow: `0 0 16px ${color}2E` }}
    >
      <p className="font-body text-xs font-semibold mb-2.5 flex items-center gap-1.5" style={{ color }}>
        <span>{icono}</span> {titulo}
      </p>
      {children}
    </div>
  )
}

function SelectorPartido({ partidos, jornada, rareza, onJugar, onCancelar, jugando }) {
  const [idPartido, setIdPartido] = useState('')
  const partidoElegido = partidos.find((p) => String(p.id) === idPartido)
  const color = COLOR_RAREZA_PANEL[rareza] ?? COLOR_RAREZA_PANEL.Comun

  return (
    <PanelFlotante rareza={rareza} icono="⚽" titulo={`Elige el partido — Jornada ${jornada}`}>
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
        <div
          className="flex items-center justify-center gap-2 mt-2.5 py-2 rounded-lg border"
          style={{ borderColor: `${color}55`, backgroundColor: `${color}0D` }}
        >
          <Escudo url={partidoElegido.escudo_local} alt={partidoElegido.equipo_local} />
          <span className="font-body text-xs text-texto">{partidoElegido.equipo_local}</span>
          <span className="text-borde text-xs">vs</span>
          <span className="font-body text-xs text-texto">{partidoElegido.equipo_visitante}</span>
          <Escudo url={partidoElegido.escudo_visitante} alt={partidoElegido.equipo_visitante} />
        </div>
      )}
      <div className="flex gap-2 mt-2.5">
        <button
          onClick={() => onJugar(idPartido)}
          disabled={!idPartido || jugando}
          className="font-body text-xs font-semibold text-fondo rounded-full px-3.5 py-1.5 hover:brightness-110 disabled:opacity-50 flex-1 transition"
          style={{ backgroundColor: color }}
        >
          {jugando ? 'Jugando...' : 'Confirmar'}
        </button>
        <button onClick={onCancelar} className="font-body text-xs text-borde hover:text-texto px-2">
          Cancelar
        </button>
      </div>
    </PanelFlotante>
  )
}

function SelectorRival({ miembros, motivosBloqueo, rareza, onJugar, onCancelar, jugando }) {
  const [idRival, setIdRival] = useState('')
  const [mensaje, setMensaje] = useState('')
  const motivo = idRival ? motivosBloqueo[idRival] : null
  const color = COLOR_RAREZA_PANEL[rareza] ?? COLOR_RAREZA_PANEL.Comun

  return (
    <PanelFlotante rareza={rareza} icono="⚔️" titulo="Elige a quién se la juegas">
      <SelectTema
        value={idRival}
        onChange={(e) => setIdRival(e.target.value)}
        options={[
          { value: '', label: 'Elige un rival...' },
          ...miembros.map((m) => ({
            value: String(m.id),
            label: motivosBloqueo[m.id] ? `${m.nombre} (no disponible)` : m.nombre,
          })),
        ]}
        className="w-full bg-fondo text-xs"
      />
      {motivo && (
        <p className="font-body text-xs text-red-500 mt-2">⚠️ {motivo}</p>
      )}
      <textarea
        value={mensaje}
        onChange={(e) => setMensaje(e.target.value)}
        placeholder="Mensaje de burla (opcional)"
        maxLength={200}
        rows={2}
        className="w-full font-body text-xs bg-fondo text-texto rounded border border-borde/40 px-2 py-1.5 mt-2.5"
      />
      <div className="flex gap-2 mt-2.5">
        <button
          onClick={() => onJugar(idRival, mensaje)}
          disabled={!idRival || !!motivo || jugando}
          className="font-body text-xs font-semibold text-fondo rounded-full px-3.5 py-1.5 hover:brightness-110 disabled:opacity-50 flex-1 transition"
          style={{ backgroundColor: color }}
        >
          {jugando ? 'Jugando...' : 'Confirmar'}
        </button>
        <button onClick={onCancelar} className="font-body text-xs text-borde hover:text-texto px-2">
          Cancelar
        </button>
      </div>
    </PanelFlotante>
  )
}

// Confirmación en línea para las acciones de un solo toque que no se pueden deshacer
function ConfirmacionInline({ texto, textoBoton, icono = '❓', titulo = 'Confirmar', rareza, onConfirmar, onCancelar, cargando, peligro }) {
  const color = peligro ? '#EF4444' : (COLOR_RAREZA_PANEL[rareza] ?? COLOR_RAREZA_PANEL.Comun)

  return (
    <PanelFlotante rareza={rareza} icono={icono} titulo={titulo} peligro={peligro}>
      <p className="font-body text-xs text-texto mb-3 leading-snug">{texto}</p>
      <div className="flex gap-2">
        <button
          onClick={onConfirmar}
          disabled={cargando}
          className={`font-body text-xs font-semibold rounded-full px-3.5 py-1.5 hover:brightness-110 disabled:opacity-50 flex-1 transition ${peligro ? 'text-white' : 'text-fondo'}`}
          style={{ backgroundColor: color }}
        >
          {cargando ? '...' : textoBoton}
        </button>
        <button onClick={onCancelar} className="font-body text-xs text-borde hover:text-texto px-2">
          Cancelar
        </button>
      </div>
    </PanelFlotante>
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
            {jugada.partido_pronosticado === false && (
              <>
                <br />
                <span className="text-red-500 font-semibold">⚠️ Aún no has pronosticado este partido: si no lo haces, la carta se pierde.</span>
              </>
            )}
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

// Una carta que ya se resolvió: qué hizo al final
function CartaHistorial({ item }) {
  const esFalta = item.tipo_carta.categoria.nombre === 'Faltas'
  const cumplida = item.estado === 'resuelta_cumplida'

  let resultado
  let claseResultado

  if (esFalta) {
    resultado = cumplida ? 'Efecto aplicado' : 'No llegó a usarse'
    claseResultado = cumplida ? 'text-premio border-premio/40 bg-premio/10' : 'text-borde border-borde/25'
  } else if (cumplida) {
    resultado = item.puntos_generados > 0 ? `+${item.puntos_generados} pts` : 'Activada'
    claseResultado = 'text-acento border-acento/40 bg-acento/10'
  } else {
    resultado = 'Sin efecto'
    claseResultado = 'text-borde border-borde/25'
  }

  return (
    <div className="flex items-center gap-3 bg-fondo border border-borde/20 rounded-lg p-3">
      <CartaJuego carta={item.tipo_carta} categoriaNombre={item.tipo_carta.categoria.nombre} categoriaIcono={item.tipo_carta.categoria.icono} tamano="pequena" />
      <div className="min-w-0 flex-1">
        <p className="font-body text-xs font-semibold text-texto">{item.tipo_carta.nombre}</p>
        {item.partido ? (
          <p className="font-body text-xs text-borde mt-0.5">
            {item.partido.equipo_local} vs {item.partido.equipo_visitante} · Jornada {item.partido.jornada}
          </p>
        ) : item.objetivo ? (
          <p className="font-body text-xs text-borde mt-0.5">
            {item.objetivo.es_uno_mismo ? 'Te protegiste a ti mismo' : `Contra ${item.objetivo.nombre}`} · Jornada {item.jornada_efecto}
          </p>
        ) : (
          <p className="font-body text-xs text-borde mt-0.5">Jornada {item.jornada_efecto}</p>
        )}
        {item.mensaje_falta && <p className="font-body text-xs italic text-borde mt-0.5">"{item.mensaje_falta}"</p>}
        {!cumplida && !esFalta && item.motivo_sin_efecto && (
          <p className="font-body text-xs text-borde mt-0.5">{item.motivo_sin_efecto}</p>
        )}
      </div>
      <span className={`font-body text-[11px] font-semibold rounded-full px-2.5 py-1 border shrink-0 ${claseResultado}`}>
        {resultado}
      </span>
    </div>
  )
}

function FaltaRecibida({ falta }) {
  return (
    <div className="flex items-center gap-3 bg-red-500/5 border border-red-500/30 rounded-lg p-3">
      <CartaJuego carta={falta.tipo_carta} categoriaNombre={falta.tipo_carta.categoria.nombre} categoriaIcono={falta.tipo_carta.categoria.icono} tamano="pequena" />
      <div className="min-w-0">
        <p className="font-body text-xs font-semibold text-red-500">⚠️ {falta.atacante} te ha jugado esta Falta</p>
        <p className="font-body text-xs text-texto mt-0.5">{falta.tipo_carta.descripcion}</p>
        <p className="font-body text-xs text-borde mt-0.5">Afecta a la jornada {falta.jornada_efecto}</p>
        {falta.mensaje_falta && <p className="font-body text-xs italic text-borde mt-1">"{falta.mensaje_falta}"</p>}
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

  const acumulados = sinAbrir >= 15

  return (
    <div className="mb-4">
      <button
        onClick={onAbrir}
        disabled={abriendo}
        className="relative w-full bg-fondo border-2 border-acento/50 rounded-xl overflow-visible flex items-center gap-4 pl-6 pr-5 py-4 hover:border-acento transition disabled:opacity-50"
        style={{ boxShadow: '0 0 24px rgba(200,255,77,0.15)' }}
      >
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
      {acumulados && (
        <p className="font-body text-xs text-borde text-center mt-1.5">
          Llevas {sinAbrir} sobres acumulados — ábrelos de uno en uno para no perderte ninguna carta.
        </p>
      )}
    </div>
  )
}

function GuiaCartas({ tope }) {
  const puntos = [
    { icono: '🎁', titulo: 'Cómo llegan', texto: 'Cuando se cierra cada jornada se reparten cartas nuevas, y los 3 primeros de esa jornada reciben una extra. Llegan en sobres cerrados: ábrelos desde aquí.' },
    { icono: '⚡', titulo: 'Jugadas', texto: 'Las juegas sobre ti mismo, para la próxima jornada y antes de que empiece. Algunas piden elegir un partido (acuérdate de pronosticarlo: si no, la carta no cuenta); otras se aplican solas a toda la jornada. Se resuelven cuando la jornada se cierra.' },
    { icono: '🎯', titulo: 'Faltas', texto: 'Se las juegas a un rival y afectan a la jornada siguiente. Solo puedes jugar 1 Falta por jornada, y no puedes repetir rival dos semanas seguidas.' },
    { icono: '✋', titulo: 'Tu mano', texto: `Puedes guardar hasta ${tope} cartas. Si te pasas, no podrás jugar hasta descartar. Una carta jugada o descartada no se puede recuperar.` },
  ]

  return (
    <div className="border-t border-borde/20 bg-borde/5 px-4 py-4 grid gap-3 sm:grid-cols-2">
      {puntos.map((p) => (
        <div key={p.titulo} className="flex gap-2.5">
          <span className="text-lg shrink-0">{p.icono}</span>
          <div>
            <p className="font-body text-xs font-semibold text-texto">{p.titulo}</p>
            <p className="font-body text-xs text-borde leading-snug mt-0.5">{p.texto}</p>
          </div>
        </div>
      ))}
    </div>
  )
}

function EstadoJornadaCartas({ jornadaJugable }) {
  if (!jornadaJugable) return null

  const { jornada, bloqueada, cierra_en: cierraEn } = jornadaJugable

  let texto
  if (jornada == null) {
    texto = 'Ahora mismo no hay ninguna jornada por jugar.'
  } else if (bloqueada) {
    texto = `La jornada ${jornada} ya ha empezado: las Jugadas se juegan antes de que empiece cada jornada. Las Faltas sí puedes jugarlas ahora, y afectarán a la jornada ${jornada + 1}.`
  } else {
    texto = `Puedes jugar Jugadas para la jornada ${jornada}${cierraEn ? ` hasta el ${formatearInicio(cierraEn)}` : ''}. Las Faltas que juegues afectarán a la jornada ${jornada + 1}.`
  }

  const clases = bloqueada || jornada == null ? 'bg-premio/5 border-premio/25' : 'bg-acento/5 border-acento/20'

  return (
    <div className={`border rounded-lg px-3 py-2 mb-4 ${clases}`}>
      <p className="font-body text-xs text-texto">🕒 {texto}</p>
    </div>
  )
}

function Pestana({ activa, onClick, children, contador, colorContador }) {
  return (
    <button
      onClick={onClick}
      className={`flex-1 font-body text-xs sm:text-sm font-semibold px-2 sm:px-3 py-2.5 border-b-2 transition ${
        activa ? 'border-acento text-texto' : 'border-transparent text-borde hover:text-texto'
      }`}
    >
      {children}
      {contador > 0 && (
        <span className={`ml-1.5 text-xs font-bold ${colorContador ?? 'text-borde'}`}>({contador})</span>
      )}
    </button>
  )
}

function MisCartasPage() {
  useTitulo('Mis Cartas')
  const { usuario } = useAuth()
  const toast = useToast()
  const queryClient = useQueryClient()
  const location = useLocation()
  const [pestana, setPestana] = useState(() => location.state?.pestana ?? 'mano')
  // Un solo panel abierto a la vez en toda la página: { tipo, idCarta }
  const [panel, setPanel] = useState(null)
  const [cartaAbriendose, setCartaAbriendose] = useState(null)
  const [codigoTexto, setCodigoTexto] = useState('')
  const [guiaAbierta, setGuiaAbierta] = useState(() => {
    try {
      return !localStorage.getItem(CLAVE_GUIA_VISTA)
    } catch {
      return true
    }
  })
  const [ordenMano, setOrdenMano] = useState(() => {
    try {
      return localStorage.getItem(CLAVE_ORDEN_MANO) ?? 'rareza'
    } catch {
      return 'rareza'
    }
  })

  function cambiarOrden(criterio) {
    setOrdenMano(criterio)
    try {
      localStorage.setItem(CLAVE_ORDEN_MANO, criterio)
    } catch {
      // sin almacenamiento disponible: no pasa nada, se queda solo para esta sesión
    }
  }

  // La guía se enseña abierta solo la primera vez que entras
  useEffect(() => {
    try {
      localStorage.setItem(CLAVE_GUIA_VISTA, '1')
    } catch {
      // sin almacenamiento disponible: no pasa nada
    }
  }, [])

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

  const { data: rivalesBloqueados } = useQuery({
    queryKey: ['rivales-bloqueados'],
    queryFn: async () => (await client.get('/api/v1/mis-cartas/rivales-bloqueados')).data.data,
    enabled: data?.activo === true,
  })

  const descartar = useMutation({
    mutationFn: (id) => client.post(`/api/v1/mis-cartas/${id}/descartar`),
    onSuccess: () => {
      toast.exito('Carta descartada.')
      setPanel(null)
      queryClient.invalidateQueries({ queryKey: ['mis-cartas'] })
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo descartar.'),
  })

  const jugar = useMutation({
    mutationFn: ({ idCarta, idPartido }) => client.post(`/api/v1/mis-cartas/${idCarta}/jugar`, { id_partido: idPartido }),
    onSuccess: () => {
      toast.exito('Carta jugada. Se resolverá cuando se cierre la jornada.')
      setPanel(null)
      queryClient.invalidateQueries({ queryKey: ['mis-cartas'] })
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo jugar la carta.'),
  })

  const jugarFalta = useMutation({
    mutationFn: ({ idCarta, idUsuarioObjetivo, mensaje }) =>
      client.post(`/api/v1/mis-cartas/${idCarta}/jugar-falta`, idUsuarioObjetivo ? { id_usuario_objetivo: idUsuarioObjetivo, mensaje: mensaje || undefined } : {}),
    onSuccess: (respuesta) => {
      toast.exito(respuesta.data.message)
      setPanel(null)
      queryClient.invalidateQueries({ queryKey: ['mis-cartas'] })
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo jugar la Falta.'),
  })

  const abrirSiguiente = useMutation({
    mutationFn: () => client.post('/api/v1/mis-cartas/abrir-siguiente'),
    onSuccess: (respuesta) => setCartaAbriendose(respuesta.data.data),
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo abrir la carta.'),
  })

  const canjearCodigo = useMutation({
    mutationFn: (codigo) => client.post('/api/v1/codigos/canjear', { codigo }),
    onSuccess: (respuesta) => {
      setCartaAbriendose(respuesta.data.data)
      setCodigoTexto('')
      queryClient.invalidateQueries({ queryKey: ['mis-cartas'] })
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo canjear ese código.'),
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

  const {
    data: paginasHistorial,
    fetchNextPage: cargarMasHistorial,
    hasNextPage: hayMasHistorial,
    isFetchingNextPage: cargandoMasHistorial,
  } = useInfiniteQuery({
    queryKey: ['mis-cartas-historial'],
    queryFn: async ({ pageParam = 1 }) => (await client.get('/api/v1/mis-cartas/historial', { params: { pagina: pageParam } })).data,
    getNextPageParam: (ultimaPagina) => (ultimaPagina.meta.hay_mas ? ultimaPagina.meta.pagina + 1 : undefined),
    initialPageParam: 1,
    enabled: pestana === 'historial',
  })

  const historial = paginasHistorial?.pages.flatMap((p) => p.data) ?? []
  const sobreTope = data.cartas.length > data.tope_mano_cartas
  const partidosDisponibles = jornadaJugable?.partidos ?? []
  const cargandoJornada = !jornadaJugable
  const sinJornada = jornadaJugable?.jornada == null
  const jornadaEmpezada = jornadaJugable?.bloqueada === true

  const abrir = (tipo, idCarta) => setPanel({ tipo, idCarta })
  const cerrar = () => setPanel(null)
  const panelAbierto = (tipo, idCarta) => panel?.tipo === tipo && panel?.idCarta === idCarta

  const clasesBoton = 'font-body text-xs text-acento hover:underline disabled:opacity-40 disabled:no-underline disabled:cursor-not-allowed'

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      {cartaAbriendose && <AperturaCarta cartaGanada={cartaAbriendose} onCerrar={cerrarApertura} />}

      <BotonAbrirSobres sinAbrir={data.sin_abrir} onAbrir={() => abrirSiguiente.mutate()} abriendo={abrirSiguiente.isPending} />

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        <TicketHeader
          titulo="Mis Cartas"
          accion={
            <span className={`font-marcador text-sm font-bold ${sobreTope ? 'text-red-500' : 'text-acento'}`}>
              {data.cartas.length}/{data.tope_mano_cartas}
            </span>
          }
        />

        <div className="border-t border-borde/20 px-4 py-2 flex items-center justify-between flex-wrap gap-2">
          <button onClick={() => setGuiaAbierta(!guiaAbierta)} className="font-body text-xs text-acento hover:underline">
            {guiaAbierta ? '▲ Ocultar cómo funciona' : '❓ Cómo funciona'}
          </button>
          <form
            onSubmit={(e) => {
              e.preventDefault()
              if (codigoTexto.trim()) canjearCodigo.mutate(codigoTexto.trim())
            }}
            className="flex items-center gap-1.5"
          >
            <input
              value={codigoTexto}
              onChange={(e) => setCodigoTexto(e.target.value)}
              placeholder="¿Tienes un código?"
              maxLength={100}
              className="font-body text-xs bg-borde/10 text-texto rounded-full border border-borde/30 px-3 py-1 w-36 focus:outline-none focus:border-acento placeholder:text-borde/60"
            />
            <button
              type="submit"
              disabled={!codigoTexto.trim() || canjearCodigo.isPending}
              className="font-body text-xs text-acento hover:underline disabled:opacity-40 disabled:no-underline"
            >
              {canjearCodigo.isPending ? '...' : 'Canjear'}
            </button>
          </form>
        </div>

        {guiaAbierta && <GuiaCartas tope={data.tope_mano_cartas} />}

        {sobreTope && (
          <div className="bg-red-500/10 border-t border-red-500/20 px-4 py-2.5">
            <p className="font-body text-xs text-red-500 font-semibold">
              ⚠️ Tienes más cartas de las que caben en tu mano — descarta alguna para poder volver a jugar cartas nuevas.
            </p>
          </div>
        )}

        <div className="flex border-t border-b border-borde/20">
          <Pestana activa={pestana === 'mano'} onClick={() => setPestana('mano')} contador={data.cartas.length}>
            Mano
          </Pestana>
          <Pestana activa={pestana === 'jugadas'} onClick={() => setPestana('jugadas')} contador={data.jugadas.length} colorContador="text-premio">
            Jugadas
          </Pestana>
          <Pestana activa={pestana === 'amenazas'} onClick={() => setPestana('amenazas')} contador={data.faltas_recibidas.length} colorContador="text-red-500">
            Amenazas
          </Pestana>
          <Pestana activa={pestana === 'historial'} onClick={() => setPestana('historial')}>
            Historial
          </Pestana>
        </div>

        <div className="p-5">
          {pestana === 'mano' && (
            <>
              <EstadoJornadaCartas jornadaJugable={jornadaJugable} />

              {data.cartas.length === 0 ? (
                <p className="font-body text-sm text-borde text-center py-8">
                  {data.sin_abrir > 0 ? 'Abre tus sobres pendientes para ver tus cartas aquí.' : 'Aún no tienes ninguna carta. Llegarán cuando el admin reparta la próxima jornada.'}
                </p>
              ) : (
                <>
                  <div className="flex items-center justify-end gap-2 mb-3">
                    <span className="font-body text-[11px] text-borde">Ordenar por:</span>
                    {[
                      { valor: 'rareza', etiqueta: 'Rareza' },
                      { valor: 'categoria', etiqueta: 'Categoría' },
                    ].map((op) => (
                      <button
                        key={op.valor}
                        onClick={() => cambiarOrden(op.valor)}
                        className={`font-body text-[11px] rounded-full px-2.5 py-1 border transition ${
                          ordenMano === op.valor ? 'bg-acento text-fondo border-acento font-semibold' : 'text-borde border-borde/30 hover:text-texto'
                        }`}
                      >
                        {op.etiqueta}
                      </button>
                    ))}
                  </div>
                  <div className="flex flex-wrap gap-5 justify-center">
                    {ordenarMano(data.cartas, ordenMano).map((carta) => {
                    const esJugada = carta.tipo_carta.categoria.nombre === 'Jugadas'
                    const esFalta = carta.tipo_carta.categoria.nombre === 'Faltas'
                    const esEscudo = carta.tipo_carta.codigo_efecto === 'FAL-PCOM-ESCUDO'

                    // Las Jugadas no se pueden jugar con la jornada ya empezada; las Faltas sí (afectan a la siguiente)
                    const noDisponible = sobreTope || cargandoJornada || sinJornada || (esJugada && jornadaEmpezada)
                    const sinPartidos = esJugada && carta.requiere_partido && partidosDisponibles.length === 0
                    const titulo = sobreTope ? 'Descarta alguna carta primero para poder jugar' : undefined

                    return (
                      <div key={carta.id} className="flex flex-col items-center gap-2">
                        <CartaJuego carta={carta.tipo_carta} categoriaNombre={carta.tipo_carta.categoria.nombre} categoriaIcono={carta.tipo_carta.categoria.icono} />
                        <div className="flex gap-3">
                          {esJugada && carta.requiere_partido && (
                            <button
                              onClick={() => abrir('partido', carta.id)}
                              disabled={noDisponible || sinPartidos}
                              className={clasesBoton}
                              title={titulo}
                            >
                              Jugar
                            </button>
                          )}
                          {esJugada && !carta.requiere_partido && (
                            <button
                              onClick={() => abrir('confirmar-jugar', carta.id)}
                              disabled={noDisponible || jugar.isPending}
                              className={clasesBoton}
                              title={titulo}
                            >
                              Jugar
                            </button>
                          )}
                          {esFalta && esEscudo && (
                            <button
                              onClick={() => abrir('confirmar-escudo', carta.id)}
                              disabled={noDisponible || jugarFalta.isPending}
                              className={clasesBoton}
                              title={titulo}
                            >
                              Activar
                            </button>
                          )}
                          {esFalta && !esEscudo && (
                            <button
                              onClick={() => abrir('rival', carta.id)}
                              disabled={noDisponible || !miembros}
                              className={clasesBoton}
                              title={titulo}
                            >
                              Jugar
                            </button>
                          )}
                          <button
                            onClick={() => abrir('confirmar-descartar', carta.id)}
                            disabled={descartar.isPending}
                            className="font-body text-xs text-borde hover:text-red-500 underline"
                          >
                            Descartar
                          </button>
                        </div>

                        {panelAbierto('partido', carta.id) && (
                          <SelectorPartido
                            partidos={partidosDisponibles}
                            jornada={jornadaJugable.jornada}
                            rareza={carta.tipo_carta.rareza}
                            onJugar={(idPartido) => jugar.mutate({ idCarta: carta.id, idPartido })}
                            onCancelar={cerrar}
                            jugando={jugar.isPending}
                          />
                        )}
                        {panelAbierto('rival', carta.id) && (
                          <SelectorRival
                            miembros={miembros.filter((m) => m.id !== usuario?.id)}
                            motivosBloqueo={rivalesBloqueados?.motivos ?? {}}
                            rareza={carta.tipo_carta.rareza}
                            onJugar={(idRival, mensaje) => jugarFalta.mutate({ idCarta: carta.id, idUsuarioObjetivo: idRival, mensaje })}
                            onCancelar={cerrar}
                            jugando={jugarFalta.isPending}
                          />
                        )}
                        {panelAbierto('confirmar-jugar', carta.id) && (
                          <ConfirmacionInline
                            icono="▶️"
                            titulo="Jugar esta carta"
                            rareza={carta.tipo_carta.rareza}
                            texto={`¿Jugar esta carta ahora? Se aplicará a la jornada ${jornadaJugable?.jornada} y no se puede deshacer.`}
                            textoBoton="Sí, jugar"
                            onConfirmar={() => jugar.mutate({ idCarta: carta.id, idPartido: null })}
                            onCancelar={cerrar}
                            cargando={jugar.isPending}
                          />
                        )}
                        {panelAbierto('confirmar-escudo', carta.id) && (
                          <ConfirmacionInline
                            icono="🛡️"
                            titulo="Activar Escudo"
                            rareza={carta.tipo_carta.rareza}
                            texto={`¿Activar el Escudo? Te protegerá de la primera Falta que te lleguen en la jornada ${(jornadaJugable?.jornada ?? 0) + 1}. No se puede deshacer.`}
                            textoBoton="Sí, activar"
                            onConfirmar={() => jugarFalta.mutate({ idCarta: carta.id, idUsuarioObjetivo: null, mensaje: null })}
                            onCancelar={cerrar}
                            cargando={jugarFalta.isPending}
                          />
                        )}
                        {panelAbierto('confirmar-descartar', carta.id) && (
                          <ConfirmacionInline
                            icono="🗑️"
                            titulo="Descartar carta"
                            texto="¿Descartar esta carta? Se pierde y no se puede recuperar."
                            textoBoton="Sí, descartar"
                            peligro
                            onConfirmar={() => descartar.mutate(carta.id)}
                            onCancelar={cerrar}
                            cargando={descartar.isPending}
                          />
                        )}
                      </div>
                    )
                  })}
                  </div>
                </>
              )}
            </>
          )}

          {pestana === 'jugadas' && (
            data.jugadas.length === 0 ? (
              <p className="font-body text-sm text-borde text-center py-8">No tienes ninguna carta jugada esperando resolución ahora mismo.</p>
            ) : (
              <div className="flex flex-col gap-2">
                {data.jugadas.map((jugada) => <CartaJugada key={jugada.id} jugada={jugada} />)}
              </div>
            )
          )}

          {pestana === 'amenazas' && (
            data.faltas_recibidas.length === 0 ? (
              <p className="font-body text-sm text-borde text-center py-8">Nadie te ha jugado ninguna Falta activa ahora mismo. 🍀</p>
            ) : (
              <div className="flex flex-col gap-2">
                {data.faltas_recibidas.map((falta) => <FaltaRecibida key={falta.id} falta={falta} />)}
              </div>
            )
          )}

          {pestana === 'historial' && (
            historial.length === 0 ? (
              <p className="font-body text-sm text-borde text-center py-8">Aquí verás qué pasó con cada carta que juegues, cuando se cierre su jornada.</p>
            ) : (
              <div className="flex flex-col gap-2">
                {historial.map((item) => <CartaHistorial key={item.id} item={item} />)}
                {hayMasHistorial && (
                  <button
                    onClick={() => cargarMasHistorial()}
                    disabled={cargandoMasHistorial}
                    className="w-full font-body text-xs text-acento hover:underline py-2.5 disabled:opacity-50"
                  >
                    {cargandoMasHistorial ? 'Cargando...' : 'Cargar más'}
                  </button>
                )}
              </div>
            )
          )}
        </div>
      </div>
    </div>
  )
}

export default MisCartasPage