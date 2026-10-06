import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import { useToast } from '../context/ToastContext'
import TicketHeader from '../components/TicketHeader'
import SkeletonLista from '../components/SkeletonLista'
import SelectorJornada from '../components/SelectorJornada'

// Estado de cada paso: siempre icono + palabra, el color solo acompaña.
const ESTADOS = {
  hecho: { icono: '✓', texto: 'Hecho', clase: 'text-acento', borde: 'border-l-acento' },
  listo: { icono: '▶', texto: 'Toca ahora', clase: 'text-premio', borde: 'border-l-premio' },
  aviso: { icono: '!', texto: 'Con aviso', clase: 'text-premio', borde: 'border-l-premio' },
  bloqueado: { icono: '🔒', texto: 'Aún no', clase: 'text-borde', borde: 'border-l-borde/40' },
  no_aplica: { icono: '–', texto: 'No aplica', clase: 'text-borde', borde: 'border-l-borde/20' },
}

const BOTONES = {
  cerrar: { texto: 'Cerrar jornada', repetir: null, confirmar: (liga, jornada) => `¿Cerrar la jornada ${jornada} en ${liga}? Se calculan los puntos y se avisa a los jugadores. No se puede deshacer.` },
  goleadores: { texto: 'Calcular goleadores', repetir: 'Volver a calcular', confirmar: null },
  repartir: { texto: 'Repartir cartas', repetir: null, confirmar: (liga, jornada) => `¿Repartir en ${liga} las cartas de la jornada ${jornada}? Cada jugador recibe su tanda y se le avisa. Solo se puede hacer una vez por jornada.` },
}

const BOTON_PRINCIPAL = 'font-body text-xs font-semibold bg-acento text-fondo rounded px-3 py-1.5 hover:brightness-110 disabled:opacity-40 disabled:cursor-not-allowed'
const BOTON_SECUNDARIO = 'font-body text-xs text-texto border border-borde/40 rounded px-3 py-1.5 hover:bg-borde/10 disabled:opacity-40 disabled:cursor-not-allowed'

function fechaHora(iso) {
  if (!iso) return 'sin fecha'
  return new Date(iso).toLocaleString('es-ES', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function Paso({ numero, paso, liga, jornada, onLanzar, ocupado }) {
  const estado = ESTADOS[paso.estado] ?? ESTADOS.bloqueado
  const boton = paso.accion ? BOTONES[paso.accion] : null
  const yaHecho = paso.estado === 'hecho'

  function pulsar() {
    const pregunta = boton.confirmar?.(liga.nombre, jornada)
    if (pregunta && !window.confirm(pregunta)) return
    onLanzar(liga, paso.accion)
  }

  return (
    <div className={`px-4 py-3 border-b border-borde/10 last:border-0 border-l-4 ${estado.borde}`}>
      <div className="flex items-start justify-between gap-3">
        <p className="font-body text-sm font-semibold text-texto">{numero}. {paso.titulo}</p>
        <span className={`font-body text-xs font-semibold whitespace-nowrap ${estado.clase}`}>{estado.icono} {estado.texto}</span>
      </div>
      <p className="font-body text-xs text-borde mt-1">{paso.detalle}</p>

      {boton && (yaHecho ? boton.repetir : true) && (
        <div className="mt-2.5">
          {liga.soy_admin ? (
            <button className={yaHecho ? BOTON_SECUNDARIO : BOTON_PRINCIPAL} disabled={ocupado} onClick={pulsar}>
              {yaHecho ? boton.repetir : boton.texto}
            </button>
          ) : (
            <p className="font-body text-[11px] text-borde">Solo puede hacerlo el admin de esta liga.</p>
          )}
        </div>
      )}
    </div>
  )
}

function ResultadoAuditoria({ resultado }) {
  if (!resultado.auditable) {
    return <p className="font-body text-xs text-borde">{resultado.motivo}</p>
  }

  if (resultado.cuadra) {
    return (
      <>
        <p className="font-body text-sm text-acento font-semibold">✓ Todo cuadra</p>
        <p className="font-body text-xs text-borde mt-0.5">
          Recalculados {resultado.total_despues} punto(s) de {resultado.usuarios} jugador(es){resultado.con_goleadores ? ', goleadores incluidos' : ' (los goleadores aún no están calculados)'}: coinciden con lo guardado.
        </p>
      </>
    )
  }

  return (
    <>
      <p className="font-body text-sm text-premio font-semibold">! Hay diferencias con lo guardado</p>
      <p className="font-body text-xs text-borde mt-0.5">
        Guardado: {resultado.total_antes} pt en total. Calculado ahora: {resultado.total_despues} pt. No se ha cambiado nada.
      </p>

      {resultado.diferencias.map((d) => (
        <div key={d.id_usuario} className="mt-2.5 border border-borde/20 rounded-md overflow-hidden">
          <p className="font-body text-xs text-texto font-semibold px-3 py-1.5 bg-borde/10">
            {d.nombre}: {d.antes} pt guardados → {d.despues} pt ahora ({d.despues - d.antes > 0 ? '+' : ''}{d.despues - d.antes})
          </p>
          {d.detalle.map((linea, i) => (
            <p key={i} className="font-body text-[11px] text-borde px-3 py-1.5 border-t border-borde/10">
              <span className="text-texto">{linea.concepto}:</span> {linea.antes} → {linea.despues}
            </p>
          ))}
        </div>
      ))}

      {resultado.cartas.length > 0 && (
        <div className="mt-2.5 border border-borde/20 rounded-md overflow-hidden">
          <p className="font-body text-xs text-texto font-semibold px-3 py-1.5 bg-borde/10">Cartas que cambiarían</p>
          {resultado.cartas.map((c) => (
            <p key={c.id} className="font-body text-[11px] text-borde px-3 py-1.5 border-t border-borde/10">
              <span className="text-texto">Carta de {c.nombre}:</span> {c.antes} → {c.despues}
            </p>
          ))}
        </div>
      )}

      <p className="font-body text-[11px] text-borde mt-2.5">
        Suele deberse a un evento corregido después de calcular, a un pronóstico que llegó tras el cierre o a un cambio en las reglas de puntos.
        Para dejarlo como "ahora": "Volver a calcular" goleadores aquí arriba, o "recalcular puntos" en la pantalla de Eventos.
      </p>
    </>
  )
}

function TarjetaLiga({ liga, jornada, onLanzar, ocupado, auditoria, onAuditar, auditando }) {
  const cerrada = liga.pasos.some((p) => p.clave === 'cerrar' && (p.estado === 'hecho' || p.estado === 'aviso'))

  return (
    <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
      <div className="px-4 py-3 bg-borde/10 flex items-center justify-between gap-3">
        <div className="min-w-0">
          <p className="font-display text-base text-texto truncate">{liga.nombre}</p>
          <p className="font-body text-[11px] text-borde">
            {!liga.con_cartas
              ? 'Sin cartas'
              : liga.cartas_desde_jornada
                ? `Con cartas desde la jornada ${liga.cartas_desde_jornada}`
                : 'Con cartas (aún sin repartir ninguna)'}
          </p>
        </div>
        <span className={`font-body text-xs font-semibold whitespace-nowrap ${liga.completa ? 'text-acento' : 'text-premio'}`}>
          {liga.completa ? '✓ Jornada completa' : '… Con pasos pendientes'}
        </span>
      </div>

      <div className="px-4 py-2.5 border-b border-borde/10 flex flex-wrap gap-x-4 gap-y-1">
        <p className="font-body text-[11px] text-borde">
          Pronosticaron <span className="text-texto font-semibold">{liga.con_pronostico}</span> de {liga.miembros}
        </p>
        <p className="font-body text-[11px] text-borde">
          Goleadores elegidos: <span className="text-texto font-semibold">{liga.goleadores_elegidos}</span>
        </p>
        {liga.con_cartas && (
          <p className="font-body text-[11px] text-borde">
            Cartas: <span className="text-texto font-semibold">{liga.cartas_resueltas}</span> resueltas
            {liga.cartas_esperando > 0 && <span className="text-premio font-semibold"> · {liga.cartas_esperando} esperando</span>}
            {' · '}
            <Link to={`/admin/rastro-cartas?liga=${liga.id}&jornada=${jornada}`} className="text-acento hover:underline">Ver las cartas →</Link>
          </p>
        )}
      </div>

      {liga.pasos.map((paso, i) => (
        <Paso key={paso.clave} numero={i + 1} paso={paso} liga={liga} jornada={jornada} onLanzar={onLanzar} ocupado={ocupado} />
      ))}

      {cerrada && (
        <div className="px-4 py-3 border-t border-borde/20 bg-borde/5">
          <div className="flex items-start justify-between gap-3 flex-wrap">
            <div className="min-w-0">
              <p className="font-body text-sm font-semibold text-texto">Comprobar los puntos</p>
              <p className="font-body text-xs text-borde mt-0.5">
                Los calcula otra vez sin guardar nada y los compara con los que hay apuntados.
              </p>
            </div>
            {liga.soy_admin ? (
              <button className={BOTON_SECUNDARIO} disabled={auditando} onClick={() => onAuditar(liga)}>
                {auditando ? 'Comprobando…' : auditoria ? 'Comprobar otra vez' : 'Comprobar'}
              </button>
            ) : (
              <p className="font-body text-[11px] text-borde">Solo el admin de esta liga.</p>
            )}
          </div>
          {auditoria && <div className="mt-3"><ResultadoAuditoria resultado={auditoria} /></div>}
        </div>
      )}
    </div>
  )
}

function BloquePartidos({ partidos, jornada, onReimportar, reimportando }) {
  const todosJugados = partidos.total > 0 && partidos.jugados === partidos.total
  const ningunoJugado = partidos.jugados === 0
  const faltanDatos = partidos.sin_datos.length > 0

  return (
    <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
      <TicketHeader titulo={`Los partidos de la jornada ${jornada}`} />

      <div className="px-4 py-3 border-b border-borde/10">
        <p className={`font-body text-sm font-semibold ${todosJugados ? 'text-acento' : 'text-premio'}`}>
          {todosJugados ? '✓' : '…'} Jugados {partidos.jugados} de {partidos.total}
        </p>
        {partidos.sin_jugar.length > 0 && (
          <ul className="mt-2 flex flex-col gap-1">
            {partidos.sin_jugar.map((p) => (
              <li key={p.id} className="font-body text-xs text-borde">
                <Link to={`/partidos/${p.id}`} className="text-texto hover:text-acento hover:underline">{p.partido}</Link> · {p.estado} · {fechaHora(p.horario)}
              </li>
            ))}
          </ul>
        )}
      </div>

      <div className="px-4 py-3 border-b border-borde/10">
        <div className="flex items-start justify-between gap-3 flex-wrap">
          {ningunoJugado ? (
            <p className="font-body text-sm text-borde">
              – Datos de LaLiga: aún no hay nada que comprobar, no se ha jugado ningún partido.
            </p>
          ) : (
            <p className={`font-body text-sm font-semibold ${faltanDatos ? 'text-premio' : 'text-acento'}`}>
              {faltanDatos
                ? `! Faltan datos de LaLiga en ${partidos.sin_datos.length} de los ${partidos.jugados} partido(s) jugado(s)`
                : `✓ Los ${partidos.jugados} partido(s) jugado(s) tienen sus datos de LaLiga`}
            </p>
          )}
          {faltanDatos && (
            <button className={BOTON_SECUNDARIO} disabled={reimportando} onClick={onReimportar}>
              Reimportar la jornada
            </button>
          )}
        </div>
        {faltanDatos && (
          <>
            <ul className="mt-2 flex flex-col gap-1">
              {partidos.sin_datos.map((p) => (
                <li key={p.id} className="font-body text-xs text-borde">
                  <Link to={`/partidos/${p.id}`} className="text-texto hover:text-acento hover:underline">{p.partido}</Link> · falta: {p.falta}
                </li>
              ))}
            </ul>
            <p className="font-body text-[11px] text-borde mt-2">
              Cada partido se importa solo unos 10 minutos después de terminar. Sin sus eventos, los goles de ese partido no cuentan para goleadores.
            </p>
          </>
        )}
      </div>

      <div className="px-4 py-3">
        {partidos.cambios_plantilla_pendientes > 0 ? (
          <p className="font-body text-sm text-premio font-semibold">
            ! {partidos.cambios_plantilla_pendientes} jugador(es) de estos partidos están sin emparejar.{' '}
            <Link to="/admin/cambios-plantilla" className="underline">Revisarlos</Link>
            <span className="block font-normal text-xs text-borde mt-0.5">
              Si alguno marcó, su gol no está guardado hasta que lo resuelvas y reimportes.
            </span>
          </p>
        ) : ningunoJugado ? (
          <p className="font-body text-sm text-borde">– Jugadores sin emparejar: se sabrá cuando se importen las alineaciones.</p>
        ) : (
          <p className="font-body text-sm text-acento font-semibold">✓ Ningún jugador de los partidos importados está sin emparejar</p>
        )}
      </div>
    </div>
  )
}

function AdminCierreJornadaPage() {
  const toast = useToast()
  const queryClient = useQueryClient()
  // Se puede llegar con la jornada ya elegida (p. ej. desde Rastro de cartas)
  const [parametros] = useSearchParams()
  const [jornadaElegida, setJornadaElegida] = useState(parametros.get('jornada') ? Number(parametros.get('jornada')) : null)

  const { data, isLoading, error } = useQuery({
    queryKey: ['admin-cierre-jornada', jornadaElegida],
    queryFn: async () => (await client.get('/api/v1/admin/cierre-jornada', { params: jornadaElegida ? { jornada: jornadaElegida } : {} })).data.data,
    placeholderData: (anteriores) => anteriores,
  })

  function refrescar() {
    queryClient.invalidateQueries({ queryKey: ['admin-cierre-jornada'] })
  }

  const accion = useMutation({
    mutationFn: ({ idLiga, jornada, paso }) => client.post(`/api/v1/admin/cierre-jornada/${idLiga}/${jornada}/${paso}`),
    onSuccess: (respuesta) => {
      toast.exito(respuesta.data.message ?? 'Hecho.')
      setAuditorias({}) // lo comprobado antes ya no vale: los puntos acaban de cambiar
      refrescar()
    },
    onError: (err) => {
      toast.error(err.response?.data?.message ?? 'No se pudo hacer.')
      refrescar()
    },
  })

  // Resultado de la última comprobación de puntos, por liga (se olvida al cambiar de jornada)
  const [auditorias, setAuditorias] = useState({})
  const auditar = useMutation({
    mutationFn: ({ idLiga, jornada }) => client.post(`/api/v1/admin/cierre-jornada/${idLiga}/${jornada}/auditar`),
    onSuccess: (respuesta, { idLiga }) => setAuditorias((a) => ({ ...a, [idLiga]: respuesta.data.data })),
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo comprobar.'),
  })

  const reimportar = useMutation({
    mutationFn: (jornada) => client.post('/api/v1/admin/tareas/reimportar-jornada/lanzar', { jornada }),
    onSuccess: () => toast.exito('Reimportación encargada. Tarda un par de minutos; vuelve a mirar después.'),
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo lanzar.'),
  })

  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>
  if (isLoading || !data) return <div className="max-w-5xl mx-auto px-4 py-8"><SkeletonLista /></div>

  const jornada = data.jornada
  const jornadas = Array.from({ length: data.total_jornadas }, (_, i) => i + 1)
  const otrasSinCerrar = data.sin_cerrar.filter((s) => s.jornada !== jornada)

  return (
    <div className="max-w-5xl mx-auto px-4 py-8">
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
        <TicketHeader titulo="Cierre de jornada" />
        <div className="px-4 py-4">
          <p className="font-body text-sm text-borde mb-3">
            Qué está hecho y qué falta, liga por liga. Los pasos van en orden: cerrar, calcular goleadores cuando estén los eventos y,
            en las ligas con cartas, repartir al final para que el Top 3 cuente todos los puntos.
          </p>
          <SelectorJornada jornadas={jornadas} valor={jornada} onCambiar={(j) => { setJornadaElegida(j); setAuditorias({}) }} />
        </div>

        {otrasSinCerrar.length > 0 && (
          <div className="px-4 py-3 border-t border-borde/10 bg-premio/5">
            <p className="font-body text-xs text-premio font-semibold mb-1.5">Otras jornadas ya empezadas que siguen sin cerrar:</p>
            <div className="flex flex-col gap-1">
              {otrasSinCerrar.map((s) => (
                <button key={s.jornada} onClick={() => { setJornadaElegida(s.jornada); setAuditorias({}) }} className="font-body text-xs text-left text-texto hover:text-acento">
                  <span className="font-semibold underline">Jornada {s.jornada}</span>
                  <span className="text-borde">
                    {' '}· {s.ligas.join(', ')}
                    {s.partidos_pendientes > 0 ? ` · ${s.partidos_pendientes} partido(s) por jugar` : ' · todos los partidos jugados'}
                  </span>
                </button>
              ))}
            </div>
          </div>
        )}
      </div>

      <BloquePartidos
        partidos={data.partidos}
        jornada={jornada}
        onReimportar={() => reimportar.mutate(jornada)}
        reimportando={reimportar.isPending}
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
        {data.ligas.map((liga) => (
          <TarjetaLiga
            key={liga.id}
            liga={liga}
            jornada={jornada}
            ocupado={accion.isPending}
            onLanzar={(l, paso) => accion.mutate({ idLiga: l.id, jornada, paso })}
            auditoria={auditorias[liga.id]}
            auditando={auditar.isPending && auditar.variables?.idLiga === liga.id}
            onAuditar={(l) => auditar.mutate({ idLiga: l.id, jornada })}
          />
        ))}
      </div>
    </div>
  )
}

export default AdminCierreJornadaPage
