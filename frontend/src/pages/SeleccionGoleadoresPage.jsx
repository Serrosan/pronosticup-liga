import { useState, useEffect, useMemo } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery, useMutation } from '@tanstack/react-query'
import client from '../api/client'
import { useToast } from '../context/ToastContext'
import useTitulo from '../hooks/useTitulo'
import SelectTema from '../components/SelectTema'

const MAXIMO_GOLEADORES = 5
const MAXIMO_RESULTADOS_BUSQUEDA = 12
const MAXIMO_SUGERIDOS = 15
const ATACANTES_POR_EQUIPO = 6

const ORDEN_POSICIONES = ['Delantero', 'Centrocampista', 'Defensa', 'Portero']
const TITULO_POSICION = {
  Delantero: 'Delanteros',
  Centrocampista: 'Centrocampistas',
  Defensa: 'Defensas',
  Portero: 'Porteros',
}

const PESTANAS = [
  { id: 'racha', etiqueta: '🔥 En racha' },
  { id: 'pichichi', etiqueta: '👟 Pichichi' },
  { id: 'partidos', etiqueta: '🆚 Por partido' },
]

function FotoJugador({ url, nombre }) {
  if (url) return <img src={url} alt={nombre} className="w-9 h-9 rounded-full object-cover shrink-0" />
  return (
    <span className="w-9 h-9 rounded-full bg-acento/10 border border-acento/20 flex items-center justify-center text-xs font-semibold shrink-0 text-acento">
      {nombre?.[0]}
    </span>
  )
}

function normalizar(texto) {
  return String(texto ?? '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
}

function ordenarPorRelevancia(jugadores, normalizada) {
  const rango = (nombreNormalizado) => {
    if (nombreNormalizado === normalizada) return 0
    if (nombreNormalizado.startsWith(normalizada)) return 1
    return 2
  }

  return [...jugadores].sort((a, b) => {
    const nombreA = normalizar(a.nombre)
    const nombreB = normalizar(b.nombre)
    const rangoA = rango(nombreA)
    const rangoB = rango(nombreB)

    if (rangoA !== rangoB) return rangoA - rangoB
    return nombreA.length - nombreB.length
  })
}

/** Mejor primero: goles de la temporada, luego recientes, luego titularidades. */
function ordenarPorForma(jugadores, forma) {
  return [...jugadores].sort((a, b) => {
    const fa = forma?.[a.id] ?? {}
    const fb = forma?.[b.id] ?? {}
    return (fb.goles ?? 0) - (fa.goles ?? 0)
      || (fb.goles_recientes ?? 0) - (fa.goles_recientes ?? 0)
      || (fb.titular ?? 0) - (fa.titular ?? 0)
      || normalizar(a.nombre).localeCompare(normalizar(b.nombre))
  })
}

function porMedia(goles, jugados) {
  if (!jugados) return null
  return (goles / jugados).toLocaleString('es-ES', { minimumFractionDigits: 1, maximumFractionDigits: 1 })
}

/**
 * Cómo viene el jugador, en una línea: goles de la temporada y titularidades
 * en los últimos partidos de su equipo. Sin datos (aún no ha entrado en
 * ninguna convocatoria importada ni ha marcado) no se enseña nada.
 */
function FormaJugador({ forma }) {
  if (!forma) return null

  const trozos = []

  trozos.push(
    forma.goles > 0
      ? `⚽ ${forma.goles} gol${forma.goles === 1 ? '' : 'es'}${forma.goles_recientes > 0 ? ` (${forma.goles_recientes} reciente${forma.goles_recientes === 1 ? '' : 's'})` : ''}`
      : '⚽ Sin goles'
  )

  if (forma.de) {
    trozos.push(
      forma.titular > 0
        ? `Titular en ${forma.titular} de los últimos ${forma.de}`
        : `Suplente en los últimos ${forma.de}`
    )
  }

  return <p className="font-body text-[11px] text-borde truncate">{trozos.join(' · ')}</p>
}

/**
 * Una fila de jugador que se puede tocar para elegirlo. Si está bloqueado
 * (repetido, Falta...) sale deshabilitado con el motivo en texto; si su equipo
 * no juega esta jornada se avisa, pero se deja elegir.
 */
function FilaJugador({ jugador, forma, motivo, aviso, onElegir, mostrarEquipo = true }) {
  return (
    <button
      type="button"
      onClick={() => onElegir(jugador)}
      disabled={!!motivo}
      className={`w-full flex items-center gap-3 px-3 py-2 text-left ${
        motivo ? 'opacity-50 cursor-not-allowed' : 'hover:bg-borde/10'
      }`}
    >
      <FotoJugador url={jugador.foto_url} nombre={jugador.nombre} />
      <div className="min-w-0 flex-1">
        <p className="font-body text-sm text-texto truncate">
          {jugador.dorsal ? <span className="text-borde">{jugador.dorsal} · </span> : null}
          {jugador.nombre}
        </p>
        {motivo ? (
          <p className="font-body text-[11px] text-red-500 truncate">⚠️ {motivo}</p>
        ) : (
          <>
            {mostrarEquipo && jugador.equipo && (
              <p className="font-body text-[11px] text-borde truncate">{jugador.equipo}</p>
            )}
            <FormaJugador forma={forma} />
            {aviso && <p className="font-body text-[11px] text-premio truncate">ℹ️ {aviso}</p>}
          </>
        )}
      </div>
      {!motivo && <span className="font-body text-xs text-acento shrink-0" aria-hidden="true">+ Elegir</span>}
    </button>
  )
}

function ListaJugadores({ children }) {
  return (
    <div className="bg-fondo border border-borde/30 rounded-lg max-h-[28rem] overflow-y-auto divide-y divide-borde/10">
      {children}
    </div>
  )
}

function TituloGrupo({ children }) {
  return (
    <p className="sticky top-0 bg-fondo font-body text-[11px] uppercase tracking-widest text-borde px-3 pt-3 pb-1">
      {children}
    </p>
  )
}

function SeleccionGoleadoresPage() {
  const { jornada } = useParams()
  const toast = useToast()
  const [busqueda, setBusqueda] = useState('')
  const [equipoFiltro, setEquipoFiltro] = useState('')
  const [seleccionados, setSeleccionados] = useState([])
  const [pestana, setPestana] = useState('racha')
  const [partidoAbierto, setPartidoAbierto] = useState(null)

  useTitulo(`Goleadores — Jornada ${jornada}`)

  const { data: jugadores, isLoading: cargandoJugadores } = useQuery({
    queryKey: ['jugadores-buscables'],
    queryFn: async () => (await client.get('/api/v1/jugadores-buscables')).data.data,
  })

  const { data: equipos } = useQuery({
    queryKey: ['equipos-lista'],
    queryFn: async () => (await client.get('/api/v1/equipos-lista')).data.data,
  })

  const { data: actual, isLoading: cargandoActual } = useQuery({
    queryKey: ['goleadores', jornada],
    queryFn: async () => (await client.get(`/api/v1/jornadas/${jornada}/goleadores`)).data.data,
  })

  const { data: infoJornada, isLoading: cargandoInfoJornada } = useQuery({
    queryKey: ['partidos', jornada],
    queryFn: async () => {
      const respuesta = await client.get(`/api/v1/jornadas/${jornada}/partidos`)
      return { jornadaBloqueada: respuesta.data.meta?.jornada_bloqueada ?? false }
    },
  })

  // Motivos de bloqueo (repetido de la jornada anterior, Falta activa...) —
  // para avisar mientras buscas, no solo al fallar el guardado.
  const { data: motivosBloqueo } = useQuery({
    queryKey: ['goleadores-bloqueados', jornada],
    queryFn: async () => (await client.get(`/api/v1/jornadas/${jornada}/goleadores-bloqueados`)).data.data,
  })

  // Goles y titularidades recientes de cada jugador. Es una ayuda: si la
  // petición falla o tarda, la pantalla funciona igual, solo sin esa línea.
  const { data: forma } = useQuery({
    queryKey: ['jugadores-forma'],
    queryFn: async () => (await client.get('/api/v1/jugadores-forma')).data.data,
    staleTime: 10 * 60 * 1000,
    retry: false,
  })

  // Partidos de la jornada con lo que marca y encaja cada equipo. También es
  // solo una ayuda: sin ella desaparecen la pestaña "Por partido" y los avisos.
  const { data: ayuda } = useQuery({
    queryKey: ['goleadores-ayuda', jornada],
    queryFn: async () => (await client.get(`/api/v1/jornadas/${jornada}/goleadores-ayuda`)).data.data,
    staleTime: 10 * 60 * 1000,
    retry: false,
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

  const jugadoresPorId = useMemo(
    () => Object.fromEntries((jugadores ?? []).map((j) => [j.id, j])),
    [jugadores]
  )

  // Solo los equipos que tienen a alguien elegible, por nombre.
  const opcionesEquipo = useMemo(() => {
    const conJugadores = new Set((jugadores ?? []).map((j) => j.id_equipo))
    return (equipos ?? [])
      .filter((eq) => conJugadores.has(eq.id))
      .map((eq) => ({ value: String(eq.id), label: eq.nombre_corto ?? eq.nombre }))
      .sort((a, b) => a.label.localeCompare(b.label, 'es'))
  }, [equipos, jugadores])

  // Para cada equipo: si juega esta jornada, si tiene el partido aplazado o si no tiene partido.
  const estadoEquipo = useMemo(() => {
    const estados = {}
    for (const partido of ayuda?.partidos ?? []) {
      for (const lado of [partido.local, partido.visitante]) {
        if (partido.estado === 'Aplazado') {
          estados[lado.id] ??= 'aplazado'
        } else {
          estados[lado.id] = 'juega'
        }
      }
    }
    return estados
  }, [ayuda])

  function avisoDe(jugador) {
    const idEquipo = jugador.id_equipo ?? jugadoresPorId[jugador.id]?.id_equipo
    if (!ayuda?.partidos?.length || !idEquipo) return null
    if (estadoEquipo[idEquipo] === 'aplazado') return 'Su partido de esta jornada está aplazado'
    if (!estadoEquipo[idEquipo]) return 'Su equipo no juega esta jornada'
    return null
  }

  function agregar(jugador) {
    if (seleccionados.length >= MAXIMO_GOLEADORES) return
    if (seleccionados.some((j) => j.id === jugador.id)) return
    if (motivosBloqueo?.[jugador.id]) return // bloqueado — no se añade, el botón ya lo muestra deshabilitado
    setSeleccionados((prev) => [...prev, jugador])
    setBusqueda('')
  }

  function quitar(idJugador) {
    setSeleccionados((prev) => prev.filter((j) => j.id !== idJugador))
  }

  function verPlantilla(idEquipo) {
    setEquipoFiltro(String(idEquipo))
    setBusqueda('')
  }

  const idsElegidos = new Set(seleccionados.map((j) => j.id))
  const disponibles = (jugadores ?? []).filter((j) => !idsElegidos.has(j.id))

  // Con texto: por nombre (o dorsal, si hay equipo elegido), dentro del equipo si lo hay.
  const resultadosBusqueda = (() => {
    const normalizada = normalizar(busqueda.trim())
    const minimo = equipoFiltro ? 1 : 2
    if (normalizada.length < minimo) return []

    const candidatos = disponibles.filter((j) => {
      if (equipoFiltro && String(j.id_equipo) !== equipoFiltro) return false
      if (equipoFiltro && /^\d+$/.test(normalizada) && String(j.dorsal ?? '') === normalizada) return true
      return normalizar(j.nombre).includes(normalizada)
    })

    const ordenados = ordenarPorRelevancia(candidatos, normalizada)
    return equipoFiltro ? ordenados : ordenados.slice(0, MAXIMO_RESULTADOS_BUSQUEDA)
  })()

  // Con equipo y sin texto: la plantilla entera, por posición y mejores primero.
  const plantillaPorPosicion = (() => {
    if (!equipoFiltro || busqueda.trim()) return []
    const delEquipo = disponibles.filter((j) => String(j.id_equipo) === equipoFiltro)
    const grupos = ORDEN_POSICIONES.map((posicion) => ({
      titulo: TITULO_POSICION[posicion],
      jugadores: ordenarPorForma(delEquipo.filter((j) => j.posicion === posicion), forma),
    }))
    const sinPosicion = delEquipo.filter((j) => !ORDEN_POSICIONES.includes(j.posicion))
    if (sinPosicion.length) grupos.push({ titulo: 'Otros', jugadores: ordenarPorForma(sinPosicion, forma) })
    return grupos.filter((g) => g.jugadores.length > 0)
  })()

  const enRacha = forma
    ? disponibles
        .filter((j) => (forma[j.id]?.goles_recientes ?? 0) > 0)
        .sort((a, b) => (forma[b.id].goles_recientes - forma[a.id].goles_recientes) || (forma[b.id].goles - forma[a.id].goles))
        .slice(0, MAXIMO_SUGERIDOS)
    : []

  const pichichi = forma
    ? disponibles
        .filter((j) => (forma[j.id]?.goles ?? 0) > 0)
        .sort((a, b) => (forma[b.id].goles - forma[a.id].goles) || (forma[b.id].goles_recientes - forma[a.id].goles_recientes))
        .slice(0, MAXIMO_SUGERIDOS)
    : []

  function atacantesDe(idEquipo) {
    const delEquipo = disponibles.filter((j) => j.id_equipo === idEquipo)
    const atacantes = delEquipo.filter((j) => ['Delantero', 'Centrocampista'].includes(j.posicion))
    return ordenarPorForma(atacantes.length ? atacantes : delEquipo, forma).slice(0, ATACANTES_POR_EQUIPO)
  }

  const cargando = cargandoJugadores || cargandoActual || cargandoInfoJornada
  const jornadaBloqueada = infoJornada?.jornadaBloqueada ?? false
  const hayPartidos = (ayuda?.partidos?.length ?? 0) > 0
  const pestanas = PESTANAS.filter((p) => p.id !== 'partidos' || hayPartidos)
  const pestanaActiva = pestanas.some((p) => p.id === pestana) ? pestana : pestanas[0]?.id
  const nombreEquipoFiltro = opcionesEquipo.find((o) => o.value === equipoFiltro)?.label

  const fila = (j, extra = {}) => (
    <FilaJugador
      key={j.id}
      jugador={j}
      forma={forma?.[j.id]}
      motivo={motivosBloqueo?.[j.id]}
      aviso={avisoDe(j)}
      onElegir={agregar}
      {...extra}
    />
  )

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
          {jornadaBloqueada && (
            <div className="bg-red-500/10 border border-red-500/30 rounded-lg p-4 mb-4">
              <p className="font-body text-sm text-red-500 font-semibold">
                🔒 Esta jornada ya no admite cambios
              </p>
              <p className="font-body text-xs text-red-500/80 mt-1">
                Ya ha empezado al menos un partido de esta jornada.
              </p>
            </div>
          )}

          <div className="bg-fondo border border-borde/30 rounded-lg p-5 mb-4">
            <p className="font-body text-xs uppercase tracking-widest text-borde mb-3">
              Elegidos ({seleccionados.length}/{MAXIMO_GOLEADORES})
            </p>

            {seleccionados.length === 0 ? (
              <p className="font-body text-sm text-borde">Aún no has elegido a nadie.</p>
            ) : (
              <div className="flex flex-col gap-2">
                {seleccionados.map((j) => {
                  const aviso = avisoDe(j)
                  return (
                    <div key={j.id} className="flex items-center gap-3 bg-borde/5 rounded-lg p-2">
                      <FotoJugador url={j.foto_url} nombre={j.nombre} />
                      <div className="min-w-0 flex-1">
                        <p className="font-body text-sm text-texto truncate">{j.nombre}</p>
                        {j.equipo && <p className="font-body text-[11px] text-borde truncate">{j.equipo}</p>}
                        <FormaJugador forma={forma?.[j.id]} />
                        {aviso && <p className="font-body text-[11px] text-premio truncate">ℹ️ {aviso}</p>}
                      </div>
                      {!jornadaBloqueada && (
                        <button onClick={() => quitar(j.id)} className="font-body text-xs text-red-500 hover:underline shrink-0">
                          Quitar
                        </button>
                      )}
                    </div>
                  )
                })}
              </div>
            )}
          </div>

          {!jornadaBloqueada && seleccionados.length < MAXIMO_GOLEADORES && (
            <div className="mb-6">
              <div className="flex gap-2 mb-2">
                <input
                  value={busqueda}
                  onChange={(e) => setBusqueda(e.target.value)}
                  placeholder={equipoFiltro ? `Busca en ${nombreEquipoFiltro ?? 'el equipo'} (nombre o dorsal)...` : 'Busca un jugador por nombre...'}
                  className="flex-1 min-w-0 font-body bg-borde/10 text-texto rounded border border-borde/40 px-3 py-2.5 focus:outline-none focus:border-acento"
                />
                <SelectTema
                  value={equipoFiltro}
                  onChange={(e) => setEquipoFiltro(e.target.value)}
                  placeholder="Equipo"
                  options={opcionesEquipo}
                  className="text-sm"
                />
              </div>

              {equipoFiltro && (
                <div className="flex items-center justify-between mb-2">
                  <p className="font-body text-[11px] text-borde">
                    Plantilla de {nombreEquipoFiltro}: delanteros primero y, dentro, los que más marcan.
                  </p>
                  <button
                    type="button"
                    onClick={() => setEquipoFiltro('')}
                    className="font-body text-xs text-acento hover:underline shrink-0 ml-2"
                  >
                    ✕ Todos los equipos
                  </button>
                </div>
              )}

              {busqueda.trim() ? (
                resultadosBusqueda.length > 0 ? (
                  <ListaJugadores>
                    {resultadosBusqueda.map((j) => fila(j, { mostrarEquipo: !equipoFiltro }))}
                  </ListaJugadores>
                ) : (
                  normalizar(busqueda.trim()).length >= (equipoFiltro ? 1 : 2) && (
                    <p className="font-body text-sm text-borde px-1">
                      No hay nadie con ese nombre{equipoFiltro ? ` en ${nombreEquipoFiltro}` : ''}.
                    </p>
                  )
                )
              ) : equipoFiltro ? (
                plantillaPorPosicion.length > 0 ? (
                  <ListaJugadores>
                    {plantillaPorPosicion.map((grupo) => (
                      <div key={grupo.titulo}>
                        <TituloGrupo>{grupo.titulo}</TituloGrupo>
                        {grupo.jugadores.map((j) => fila(j, { mostrarEquipo: false }))}
                      </div>
                    ))}
                  </ListaJugadores>
                ) : (
                  <p className="font-body text-sm text-borde px-1">No queda nadie de este equipo por elegir.</p>
                )
              ) : (
                <div>
                  <p className="font-body text-[11px] text-borde mb-2">
                    ¿No se te ocurre nadie? Mira quién está marcando, o elige un equipo para ver su plantilla.
                  </p>
                  <div role="tablist" className="flex gap-1 mb-2 border-b border-borde/20">
                    {pestanas.map((p) => {
                      const activa = p.id === pestanaActiva
                      return (
                        <button
                          key={p.id}
                          type="button"
                          role="tab"
                          aria-selected={activa}
                          onClick={() => setPestana(p.id)}
                          className={`font-body text-sm px-3 py-2 -mb-px border-b-2 ${
                            activa ? 'border-acento text-texto font-semibold' : 'border-transparent text-borde hover:text-texto'
                          }`}
                        >
                          {p.etiqueta}
                        </button>
                      )
                    })}
                  </div>

                  {pestanaActiva === 'racha' && (
                    enRacha.length > 0 ? (
                      <>
                        <p className="font-body text-[11px] text-borde mb-2">Los que más han marcado en los últimos 5 partidos de su equipo.</p>
                        <ListaJugadores>{enRacha.map((j) => fila(j))}</ListaJugadores>
                      </>
                    ) : (
                      <p className="font-body text-sm text-borde px-1">Todavía no hay goles recientes que enseñar.</p>
                    )
                  )}

                  {pestanaActiva === 'pichichi' && (
                    pichichi.length > 0 ? (
                      <>
                        <p className="font-body text-[11px] text-borde mb-2">Los máximos goleadores de la temporada que puedes elegir.</p>
                        <ListaJugadores>{pichichi.map((j) => fila(j))}</ListaJugadores>
                      </>
                    ) : (
                      <p className="font-body text-sm text-borde px-1">Todavía no hay goles esta temporada.</p>
                    )
                  )}

                  {pestanaActiva === 'partidos' && (
                    <div className="flex flex-col gap-2">
                      <p className="font-body text-[11px] text-borde">
                        Toca un partido para ver los atacantes de los dos equipos y cuánto encaja cada rival.
                      </p>
                      {ayuda.partidos.map((partido) => {
                        const abierto = partidoAbierto === partido.id
                        const aplazado = partido.estado === 'Aplazado'
                        return (
                          <div key={partido.id} className={`rounded-lg border ${abierto ? 'border-acento border-2' : 'border-borde/30'}`}>
                            <button
                              type="button"
                              aria-expanded={abierto}
                              onClick={() => setPartidoAbierto(abierto ? null : partido.id)}
                              className="w-full flex items-center justify-between gap-2 px-3 py-2.5 text-left"
                            >
                              <span className="font-body text-sm text-texto truncate">
                                {partido.local.nombre} – {partido.visitante.nombre}
                                {aplazado && <span className="text-premio"> · ⏸ aplazado</span>}
                              </span>
                              <span className="font-body text-xs text-borde shrink-0">{abierto ? '▲ Cerrar' : '▼ Ver'}</span>
                            </button>

                            {abierto && (
                              <div className="border-t border-borde/20">
                                {[[partido.local, partido.visitante], [partido.visitante, partido.local]].map(([equipo, rival]) => {
                                  const media = porMedia(rival.encajados, rival.jugados)
                                  const atacantes = atacantesDe(equipo.id)
                                  return (
                                    <div key={equipo.id} className="pb-1">
                                      <div className="flex items-baseline justify-between gap-2 px-3 pt-3 pb-1">
                                        <p className="font-body text-xs uppercase tracking-widest text-borde truncate">
                                          {equipo.nombre}
                                          {media && <span className="normal-case tracking-normal"> · {rival.nombre} encaja {media} por partido</span>}
                                        </p>
                                        <button
                                          type="button"
                                          onClick={() => verPlantilla(equipo.id)}
                                          className="font-body text-xs text-acento hover:underline shrink-0"
                                        >
                                          Plantilla →
                                        </button>
                                      </div>
                                      {atacantes.length > 0 ? (
                                        atacantes.map((j) => fila(j, { mostrarEquipo: false }))
                                      ) : (
                                        <p className="font-body text-sm text-borde px-3 pb-2">Nadie por elegir.</p>
                                      )}
                                    </div>
                                  )
                                })}
                              </div>
                            )}
                          </div>
                        )
                      })}
                    </div>
                  )}
                </div>
              )}
            </div>
          )}

          {!jornadaBloqueada && (
            <button
              onClick={() => guardar.mutate()}
              disabled={seleccionados.length === 0 || guardar.isPending}
              className="w-full font-body text-sm font-semibold bg-acento text-fondo rounded py-2.5 hover:brightness-110 disabled:opacity-50"
            >
              {guardar.isPending ? 'Guardando...' : `Guardar (${seleccionados.length}/${MAXIMO_GOLEADORES})`}
            </button>
          )}
        </>
      )}
    </div>
  )
}

export default SeleccionGoleadoresPage
