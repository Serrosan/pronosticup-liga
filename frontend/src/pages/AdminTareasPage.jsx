import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import { useToast } from '../context/ToastContext'
import TicketHeader from '../components/TicketHeader'
import SkeletonLista from '../components/SkeletonLista'
import SelectorJornada from '../components/SelectorJornada'

// Estado de una ejecución: siempre icono + palabra, el color solo acompaña.
const ESTADOS = {
  en_cola: { icono: '⏳', texto: 'En cola', clase: 'text-borde' },
  en_curso: { icono: '⚙️', texto: 'En curso', clase: 'text-premio' },
  ok: { icono: '✓', texto: 'Bien', clase: 'text-acento' },
  fallo: { icono: '✕', texto: 'Falló', clase: 'text-red-500' },
}

function haceCuanto(iso) {
  if (!iso) return null
  const segundos = Math.round((Date.now() - new Date(iso).getTime()) / 1000)
  if (segundos < 0) return 'ahora mismo'
  if (segundos < 60) return `hace ${segundos} s`
  const minutos = Math.round(segundos / 60)
  if (minutos < 60) return `hace ${minutos} min`
  const horas = Math.round(minutos / 60)
  if (horas < 48) return `hace ${horas} h`
  return `hace ${Math.round(horas / 24)} días`
}

function dentroDe(iso) {
  if (!iso) return null
  const segundos = Math.round((new Date(iso).getTime() - Date.now()) / 1000)
  if (segundos <= 0) return 'ahora'
  if (segundos < 60) return `en ${segundos} s`
  const minutos = Math.round(segundos / 60)
  if (minutos < 60) return `en ${minutos} min`
  return `en ${Math.round(minutos / 60)} h`
}

function fechaHora(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('es-ES', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function duracion(segundos) {
  if (segundos === null || segundos === undefined) return null
  if (segundos < 60) return `${segundos} s`
  return `${Math.floor(segundos / 60)} min ${Math.round(segundos % 60)} s`
}

function EtiquetaEstado({ estado }) {
  const e = ESTADOS[estado] ?? { icono: '?', texto: estado, clase: 'text-borde' }
  return (
    <span className={`font-body text-xs font-semibold whitespace-nowrap ${e.clase}`}>
      {e.icono} {e.texto}
    </span>
  )
}

function Salida({ texto }) {
  if (!texto) return null
  return (
    <pre className="mt-2 font-marcador text-[11px] leading-relaxed text-texto bg-borde/10 border border-borde/15 rounded px-3 py-2 whitespace-pre-wrap break-words max-h-64 overflow-y-auto">
      {texto}
    </pre>
  )
}

// Una línea "Última vez…" con su estado, y la salida desplegable si la hay.
function UltimaVez({ titulo, ejecucion, vacio }) {
  const [verSalida, setVerSalida] = useState(false)

  if (!ejecucion) {
    return (
      <p className="font-body text-xs text-borde">
        <span className="text-texto">{titulo}:</span> {vacio}
      </p>
    )
  }

  const cuando = ejecucion.terminada_en ?? ejecucion.iniciada_en ?? ejecucion.encargada_en
  const tardo = duracion(ejecucion.duracion_segundos)

  return (
    <div>
      <p className="font-body text-xs text-borde flex flex-wrap items-center gap-x-2 gap-y-0.5">
        <span className="text-texto">{titulo}:</span>
        <EtiquetaEstado estado={ejecucion.estado} />
        <span title={fechaHora(cuando)}>{haceCuanto(cuando)}</span>
        {tardo && <span>· tardó {tardo}</span>}
        {ejecucion.lanzada_por && <span>· por {ejecucion.lanzada_por}</span>}
        {ejecucion.salida && (
          <button onClick={() => setVerSalida(!verSalida)} className="underline hover:text-texto">
            {verSalida ? 'ocultar salida' : 'ver salida'}
          </button>
        )}
      </p>
      {verSalida && <Salida texto={ejecucion.salida} />}
    </div>
  )
}

function TarjetaTarea({ tarea, onLanzar, lanzando }) {
  return (
    <div className="px-4 py-4 border-b border-borde/10 last:border-0 odd:bg-borde/5">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="font-body text-sm font-semibold text-texto">{tarea.nombre}</p>
          <p className="font-body text-[11px] text-borde mt-0.5">
            {tarea.frecuencia ? `${tarea.frecuencia} · próxima ${dentroDe(tarea.proxima) ?? '—'}` : 'Solo cuando la lanzas tú'}
          </p>
        </div>
        <button
          onClick={() => onLanzar(tarea)}
          disabled={tarea.activa || lanzando}
          className="shrink-0 font-body text-xs font-semibold bg-acento text-fondo rounded px-3 py-1.5 hover:brightness-110 disabled:opacity-40 disabled:cursor-not-allowed"
        >
          {tarea.activa ? 'En marcha…' : 'Lanzar ahora'}
        </button>
      </div>

      {tarea.descripcion && <p className="font-body text-xs text-borde mt-2">{tarea.descripcion}</p>}

      <div className="mt-2.5 flex flex-col gap-1">
        {tarea.frecuencia && (
          <UltimaVez titulo="Última automática" ejecucion={tarea.ultima_programada} vacio="aún no se ha registrado ninguna" />
        )}
        <UltimaVez titulo="Último lanzamiento a mano" ejecucion={tarea.ultima_manual} vacio="nunca" />
      </div>
    </div>
  )
}

function SeccionScraper({ scraper, onLanzar, lanzando }) {
  const [jornadaElegida, setJornadaElegida] = useState(null)
  const jornada = jornadaElegida ?? scraper.jornada_sugerida
  const jornadas = Array.from({ length: scraper.total_jornadas }, (_, i) => i + 1)
  const ocupado = Boolean(scraper.activa) || lanzando

  const { data: partidos } = useQuery({
    queryKey: ['admin-tareas-partidos', jornada],
    queryFn: async () => (await client.get(`/api/v1/admin/tareas/jornadas/${jornada}/partidos`)).data.data,
  })

  const terminados = partidos ? partidos.filter((p) => p.terminado).length : 0

  function reimportarJornada() {
    const aviso = `Se van a reimportar de LaLiga.com los ${terminados} partido(s) terminados de la jornada ${jornada}.\n\nSus alineaciones, estadísticas y eventos se reemplazan por lo que diga LaLiga. Los puntos ya repartidos no cambian mientras no recalcules la jornada.\n\n¿Seguir?`
    if (!window.confirm(aviso)) return
    onLanzar('reimportar-jornada', { jornada })
  }

  function reimportarPartido(partido) {
    if (!window.confirm(`¿Reimportar de LaLiga.com ${partido.local} - ${partido.visitante}? Sus alineaciones, estadísticas y eventos se reemplazan.`)) return
    onLanzar('reimportar-partido', { id_partido: partido.id })
  }

  return (
    <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
      <TicketHeader titulo="Reimportar de LaLiga.com" />

      <div className="px-4 py-4">
        <p className="font-body text-xs text-borde mb-3">
          Vuelve a traer alineaciones, estadísticas y eventos. Útil después de dar de alta jugadores o corregir dorsales:
          lo que antes no se pudo emparejar, ahora sí. Cada partido terminado ya se importa solo unos 10 minutos después de acabar.
        </p>

        {scraper.activa && (
          <div className="mb-3 bg-premio/10 border border-premio/30 rounded px-3 py-2">
            <p className="font-body text-xs text-premio font-semibold">
              <EtiquetaEstado estado={scraper.activa.estado} /> · {scraper.activa.etiqueta ?? scraper.activa.nombre} — esta página se actualiza sola.
            </p>
          </div>
        )}

        <div className="flex items-center justify-between gap-3 flex-wrap mb-3">
          <SelectorJornada jornadas={jornadas} valor={jornada} onCambiar={setJornadaElegida} />
          <button
            onClick={reimportarJornada}
            disabled={ocupado || terminados === 0}
            className="font-body text-xs font-semibold bg-acento text-fondo rounded px-3 py-1.5 hover:brightness-110 disabled:opacity-40 disabled:cursor-not-allowed"
          >
            Reimportar la jornada entera
          </button>
        </div>

        {!partidos ? (
          <p className="font-body text-xs text-borde">Cargando partidos…</p>
        ) : partidos.length === 0 ? (
          <p className="font-body text-xs text-borde">No hay partidos en esa jornada.</p>
        ) : (
          <div className="border border-borde/15 rounded-lg overflow-hidden">
            {partidos.map((p) => (
              <div key={p.id} className="flex items-center justify-between gap-3 px-3 py-2 border-b border-borde/10 last:border-0 odd:bg-borde/5">
                <div className="min-w-0">
                  <p className="font-body text-sm text-texto truncate">{p.local} - {p.visitante}</p>
                  <p className="font-body text-[10px] text-borde">
                    {fechaHora(p.horario)} · {p.terminado ? 'terminado' : 'aún no ha terminado'}
                  </p>
                </div>
                <button
                  onClick={() => reimportarPartido(p)}
                  disabled={ocupado || !p.terminado}
                  className="shrink-0 font-body text-xs text-texto border border-borde/40 rounded px-2.5 py-1 hover:bg-borde/10 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  Reimportar
                </button>
              </div>
            ))}
          </div>
        )}

        <p className="font-body text-[11px] text-borde mt-3">
          Lo que no se pueda emparejar queda en{' '}
          <Link to="/admin/avisos-scraper" className="text-acento hover:underline">Avisos del scraper</Link>.
        </p>
      </div>
    </div>
  )
}

function FilaHistorial({ ejecucion }) {
  const [abierta, setAbierta] = useState(false)
  const cuando = ejecucion.terminada_en ?? ejecucion.iniciada_en ?? ejecucion.encargada_en
  const tardo = duracion(ejecucion.duracion_segundos)

  return (
    <div className="border-b border-borde/10 last:border-0 odd:bg-borde/5">
      <button
        onClick={() => setAbierta(!abierta)}
        disabled={!ejecucion.salida}
        className="w-full flex items-center justify-between gap-3 px-4 py-2.5 text-left disabled:cursor-default"
      >
        <div className="min-w-0">
          <p className="font-body text-sm text-texto truncate">
            {ejecucion.nombre}{ejecucion.etiqueta ? ` · ${ejecucion.etiqueta}` : ''}
          </p>
          <p className="font-body text-[10px] text-borde">
            {fechaHora(cuando)}{tardo ? ` · tardó ${tardo}` : ''}{ejecucion.lanzada_por ? ` · por ${ejecucion.lanzada_por}` : ''}
          </p>
        </div>
        <div className="flex items-center gap-2 shrink-0">
          <EtiquetaEstado estado={ejecucion.estado} />
          {ejecucion.salida && <span className="text-borde text-xs">{abierta ? '▲' : '▼'}</span>}
        </div>
      </button>
      {abierta && <div className="px-4 pb-3"><Salida texto={ejecucion.salida} /></div>}
    </div>
  )
}

function AdminTareasPage() {
  const toast = useToast()
  const queryClient = useQueryClient()

  const { data, isLoading, error } = useQuery({
    queryKey: ['admin-tareas'],
    queryFn: async () => (await client.get('/api/v1/admin/tareas')).data.data,
    // Mientras haya algo en marcha se refresca cada 4 s; si no, cada 30 s.
    // (El primer argumento es la consulta en React Query 5 y los datos en la 4.)
    refetchInterval: (primero) => {
      const datos = primero?.state ? primero.state.data : primero
      return datos?.hay_activas ? 4000 : 30000
    },
  })

  const lanzar = useMutation({
    mutationFn: ({ clave, parametros }) => client.post(`/api/v1/admin/tareas/${clave}/lanzar`, parametros ?? {}),
    onSuccess: (respuesta) => {
      toast.exito(respuesta.data.message ?? 'Encargada.')
      queryClient.invalidateQueries({ queryKey: ['admin-tareas'] })
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo lanzar.'),
  })

  if (isLoading) return <div className="max-w-3xl mx-auto px-4 py-8"><SkeletonLista /></div>
  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>

  function lanzarTarea(tarea) {
    if (tarea.confirmar && !window.confirm(tarea.confirmar)) return
    lanzar.mutate({ clave: tarea.clave })
  }

  const { programador, cola } = data

  return (
    <div className="max-w-3xl mx-auto px-4 py-8">
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
        <TicketHeader titulo="Tareas" />
        <div className="px-4 py-4">
          <p className="font-body text-sm text-borde mb-3">
            Lo que la web hace sola y cuándo lo hizo por última vez. Al pulsar un botón la tarea no corre al instante:
            queda encargada y empieza en menos de un minuto.
          </p>
          <div className="flex flex-wrap gap-x-5 gap-y-1.5">
            <p className={`font-body text-xs font-semibold ${programador.parado ? 'text-red-500' : 'text-acento'}`}>
              {programador.parado ? '✕ Programador parado' : '✓ Programador en marcha'}
              <span className="font-normal text-borde">
                {' '}· {programador.ultima_senal ? `última señal ${haceCuanto(programador.ultima_senal)}` : 'sin señales todavía'}
              </span>
            </p>
            <p className="font-body text-xs text-borde">
              Cola: <span className="text-texto">{cola.pendientes ?? '—'}</span> pendiente(s)
              {' · '}
              <span className={cola.fallidos > 0 ? 'text-red-500 font-semibold' : 'text-texto'}>{cola.fallidos ?? '—'}</span> fallido(s)
            </p>
          </div>
          {programador.parado && programador.ultima_senal === null && (
            <p className="font-body text-[11px] text-borde mt-2">
              Es normal justo después de instalar esta página: la primera señal llega con la próxima sincronización (cada 2 minutos).
            </p>
          )}
        </div>
      </div>

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
        <TicketHeader titulo="Tareas programadas" />
        {data.tareas.map((tarea) => (
          <TarjetaTarea key={tarea.clave} tarea={tarea} onLanzar={lanzarTarea} lanzando={lanzar.isPending} />
        ))}
      </div>

      {data.herramientas?.length > 0 && (
        <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
          <TicketHeader titulo="Herramientas" />
          {data.herramientas.map((tarea) => (
            <TarjetaTarea key={tarea.clave} tarea={tarea} onLanzar={lanzarTarea} lanzando={lanzar.isPending} />
          ))}
        </div>
      )}

      <SeccionScraper
        scraper={data.scraper}
        onLanzar={(clave, parametros) => lanzar.mutate({ clave, parametros })}
        lanzando={lanzar.isPending}
      />

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        <TicketHeader titulo="Últimos lanzamientos a mano" />
        {data.historial.length === 0 ? (
          <p className="font-body text-sm text-borde px-4 py-4">Todavía no se ha lanzado nada a mano.</p>
        ) : (
          data.historial.map((ejecucion) => <FilaHistorial key={ejecucion.id} ejecucion={ejecucion} />)
        )}
      </div>
    </div>
  )
}

export default AdminTareasPage
