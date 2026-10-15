import { useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import { useToast } from '../context/ToastContext'
import SkeletonLista from '../components/SkeletonLista'

// Comparar plantillas: las de football-data contra las nuestras, por el
// identificador de la API. Se consulta equipo a equipo (la API deja ~10
// consultas por minuto) y lo consultado se guarda unos días. Mirar no cambia
// nada; cada botón cambia solo ese jugador.

const CAMPO = 'font-body text-sm bg-fondo text-texto rounded border border-borde/40 px-2 py-1 focus:outline-none focus:border-acento'
const OPCION = 'bg-fondo text-texto'
const BOTON = 'font-body text-xs font-semibold bg-acento text-fondo rounded px-3 py-1.5 hover:brightness-110 disabled:opacity-40'
const BOTON_SEC = 'font-body text-xs text-texto border border-borde/40 rounded px-3 py-1.5 hover:bg-borde/10 disabled:opacity-40'
const BOTON_PELIGRO = 'font-body text-xs text-red-500 border border-red-500/40 rounded px-3 py-1.5 hover:bg-red-500/10 disabled:opacity-40'

const hoy = () => new Date().toISOString().slice(0, 10)

function cuando(iso) {
  if (!iso) return null
  const d = new Date(iso)
  return `${d.getDate()}/${d.getMonth() + 1} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

function useAccion(alTerminar) {
  const toast = useToast()
  const [ocupado, setOcupado] = useState(null)

  async function lanzar(clave, url, cuerpo = {}) {
    setOcupado(clave)
    try {
      const respuesta = await client.post(url, cuerpo)
      toast.exito(respuesta.data.message ?? 'Hecho.')
      await alTerminar()
    } catch (error) {
      toast.error(error.response?.data?.message ?? 'No se pudo hacer.')
    } finally {
      setOcupado(null)
    }
  }

  return { lanzar, ocupado }
}

function Seccion({ titulo, explicacion, cantidad, gravedad = 'normal', children, abiertaDeInicio }) {
  const [abierta, setAbierta] = useState(abiertaDeInicio ?? cantidad > 0)
  const borde = { aviso: 'border-l-premio', error: 'border-l-red-500', normal: 'border-l-acento' }[gravedad]

  return (
    <div className={`bg-fondo border border-borde/30 border-l-4 ${cantidad > 0 ? borde : 'border-l-borde/30'} rounded-lg overflow-hidden mb-3`}>
      <button
        type="button"
        onClick={() => cantidad > 0 && setAbierta(!abierta)}
        className={`w-full text-left px-4 py-3 flex items-start justify-between gap-3 ${cantidad > 0 ? 'hover:bg-borde/5' : 'cursor-default'}`}
      >
        <span className="min-w-0">
          <span className="font-body text-sm font-semibold text-texto">
            {cantidad > 0 && <span className="text-borde text-xs mr-1.5">{abierta ? '▾' : '▸'}</span>}
            {titulo}
          </span>
          <span className="font-body text-xs text-borde block mt-0.5">{explicacion}</span>
        </span>
        <span className={`font-body text-xs font-semibold whitespace-nowrap ${cantidad > 0 ? 'text-texto' : 'text-acento'}`}>
          {cantidad > 0 ? cantidad : '✓ Nada'}
        </span>
      </button>
      {abierta && cantidad > 0 && <div className="border-t border-borde/10 divide-y divide-borde/10">{children}</div>}
    </div>
  )
}

function Fila({ titulo, detalle, children }) {
  return (
    <div className="px-4 py-3">
      <p className="font-body text-sm text-texto">{titulo}</p>
      {detalle && <p className="font-body text-[11px] text-borde mt-0.5">{detalle}</p>}
      <div className="flex flex-wrap items-center gap-2 mt-2">{children}</div>
    </div>
  )
}

function CampoFecha({ valor, onCambiar, etiqueta = 'Desde' }) {
  return (
    <label className="font-body text-[11px] text-borde flex items-center gap-1.5">
      {etiqueta}
      <input type="date" value={valor} onChange={(e) => onCambiar(e.target.value)} className={CAMPO} />
    </label>
  )
}

function CampoDorsal({ valor, onCambiar }) {
  return (
    <label className="font-body text-[11px] text-borde flex items-center gap-1.5">
      Dorsal
      <input type="number" min="1" max="99" value={valor} onChange={(e) => onCambiar(e.target.value)} placeholder="—" className={`${CAMPO} w-16`} />
    </label>
  )
}

const dorsalONulo = (d) => (d === '' || d === null || d === undefined ? null : Number(d))

// ---------------------------------------------------------------- Filas

function FilaTraspaso({ t, accion }) {
  const [fecha, setFecha] = useState(hoy())
  const [dorsal, setDorsal] = useState('')
  const clave = `traspaso-${t.id_jugador}`
  return (
    <Fila titulo={<><Link to={`/admin/jugadores/detalle/${t.id_jugador}`} className="hover:underline">{t.jugador}</Link> · {t.equipo} → <strong>{t.equipo_destino}</strong></>}>
      <CampoFecha valor={fecha} onCambiar={setFecha} />
      <CampoDorsal valor={dorsal} onCambiar={setDorsal} />
      <button
        type="button"
        className={BOTON}
        disabled={accion.ocupado === clave}
        onClick={() => accion.lanzar(clave, `/api/v1/admin/comparador-plantillas/jugadores/${t.id_jugador}/traspaso`, { id_equipo_destino: t.id_equipo_destino, fecha, dorsal: dorsalONulo(dorsal) })}
      >
        Aplicar traspaso
      </button>
    </Fila>
  )
}

function FilaBaja({ b, accion }) {
  const [fecha, setFecha] = useState(hoy())
  const url = `/api/v1/admin/comparador-plantillas/jugadores/${b.id_jugador}/salida`
  return (
    <Fila
      titulo={<><Link to={`/admin/jugadores/detalle/${b.id_jugador}`} className="hover:underline">{b.jugador}</Link> · {b.equipo}</>}
      detalle="Ya no aparece en ningún equipo de LaLiga (venta al extranjero, cesión fuera, retirada…)."
    >
      <CampoFecha valor={fecha} onCambiar={setFecha} etiqueta="Fecha" />
      <button type="button" className={BOTON_SEC} disabled={!!accion.ocupado} onClick={() => accion.lanzar(`salida-${b.id_jugador}`, url, { fecha, dar_de_baja: false })}>
        Cerrar su ficha
      </button>
      <button type="button" className={BOTON_PELIGRO} disabled={!!accion.ocupado} onClick={() => accion.lanzar(`baja-${b.id_jugador}`, url, { fecha, dar_de_baja: true })}>
        Cerrar y dar de baja
      </button>
    </Fila>
  )
}

function FilaAlta({ a, posiciones, accion }) {
  const [elegido, setElegido] = useState(a.candidatos[0]?.id_jugador ?? '')
  const [formulario, setFormulario] = useState(a.candidatos.length === 0)
  const [posicion, setPosicion] = useState(a.posicion ?? '')
  const [dorsal, setDorsal] = useState('')
  const [fecha, setFecha] = useState(hoy())

  const detalle = [a.posicion_api, a.fecha_nacimiento && `nacido el ${a.fecha_nacimiento.split('-').reverse().join('/')}`, a.nacionalidad, `id API ${a.id_api}`]
    .filter(Boolean)
    .join(' · ')

  return (
    <Fila titulo={<><strong>{a.nombre_api}</strong> · {a.equipo}</>} detalle={detalle}>
      {a.candidatos.length > 0 && (
        <>
          <span className="font-body text-[11px] text-borde">¿Es alguno que ya tienes?</span>
          <select className={`${CAMPO} max-w-[16rem]`} value={elegido} onChange={(e) => setElegido(e.target.value)}>
            {a.candidatos.map((c) => (
              <option className={OPCION} key={c.id_jugador} value={c.id_jugador}>
                {c.nombre} · {c.parecido}%{c.de_su_equipo ? '' : ' (otro equipo)'}
              </option>
            ))}
          </select>
          <button
            type="button"
            className={BOTON}
            disabled={!elegido || !!accion.ocupado}
            onClick={() => accion.lanzar(`vincular-${a.id_api}`, `/api/v1/admin/comparador-plantillas/jugadores/${elegido}/vincular`, { id_api: a.id_api })}
          >
            Es este
          </button>
          {!formulario && (
            <button type="button" className={BOTON_SEC} onClick={() => setFormulario(true)}>
              No, es nuevo
            </button>
          )}
        </>
      )}

      {formulario && (
        <div className="w-full flex flex-wrap items-center gap-2 bg-borde/5 border border-borde/10 rounded px-3 py-2">
          <span className="font-body text-[11px] text-borde">Dar de alta:</span>
          <select className={CAMPO} value={posicion} onChange={(e) => setPosicion(e.target.value)}>
            <option className={OPCION} value="">Posición…</option>
            {posiciones.map((p) => <option className={OPCION} key={p} value={p}>{p}</option>)}
          </select>
          <CampoDorsal valor={dorsal} onCambiar={setDorsal} />
          <CampoFecha valor={fecha} onCambiar={setFecha} />
          <button
            type="button"
            className={BOTON}
            disabled={!posicion || !!accion.ocupado}
            onClick={() => accion.lanzar(`alta-${a.id_api}`, '/api/v1/admin/comparador-plantillas/alta', {
              id_api: a.id_api, id_equipo: a.id_equipo, posicion, dorsal: dorsalONulo(dorsal), fecha,
            })}
          >
            Dar de alta
          </button>
        </div>
      )}
    </Fila>
  )
}

function FilaDudoso({ d, accion }) {
  return (
    <Fila
      titulo={<><Link to={`/admin/jugadores/detalle/${d.id_jugador}`} className="hover:underline">{d.jugador}</Link> · {d.equipo}</>}
      detalle={`En football-data, su identificador (${d.id_api}) es de «${d.nombre_api}» · parecido ${d.parecido}%`}
    >
      <button type="button" className={BOTON_SEC} disabled={!!accion.ocupado}
        onClick={() => accion.lanzar(`ok-${d.id_jugador}`, `/api/v1/admin/comparador-plantillas/jugadores/${d.id_jugador}/confirmar-identificador`)}>
        Está bien, es él
      </button>
      <button type="button" className={BOTON_PELIGRO} disabled={!!accion.ocupado}
        onClick={() => accion.lanzar(`quitar-${d.id_jugador}`, `/api/v1/admin/comparador-plantillas/jugadores/${d.id_jugador}/quitar-identificador`)}>
        Está mal: quitarlo
      </button>
    </Fila>
  )
}

function FilaSinId({ s, accion }) {
  const [elegido, setElegido] = useState(s.candidatos[0]?.id_api ?? '')
  return (
    <Fila
      titulo={<><Link to={`/admin/jugadores/detalle/${s.id_jugador}`} className="hover:underline">{s.jugador}</Link> · {s.equipo}{s.dorsal ? ` · ${s.dorsal}` : ''}</>}
      detalle={s.candidatos.length === 0 ? 'En su equipo de football-data no queda nadie sin enlazar (suele pasar con canteranos que la API no tiene).' : null}
    >
      {s.candidatos.length > 0 && (
        <>
          <select className={`${CAMPO} max-w-[16rem]`} value={elegido} onChange={(e) => setElegido(e.target.value)}>
            {s.candidatos.map((c) => (
              <option className={OPCION} key={c.id_api} value={c.id_api}>{c.nombre} · {c.parecido}%</option>
            ))}
          </select>
          <button type="button" className={BOTON} disabled={!elegido || !!accion.ocupado}
            onClick={() => accion.lanzar(`enlazar-${s.id_jugador}`, `/api/v1/admin/comparador-plantillas/jugadores/${s.id_jugador}/vincular`, { id_api: Number(elegido) })}>
            Enlazar
          </button>
        </>
      )}
    </Fila>
  )
}

// ---------------------------------------------------------------- Consulta

function Consulta({ datos, alTerminar }) {
  const toast = useToast()
  const [progreso, setProgreso] = useState(null) // {hechos,total,actual,errores}
  const parar = useRef(false)

  async function consultar(equipos) {
    parar.current = false
    const errores = []
    setProgreso({ hechos: 0, total: equipos.length, actual: equipos[0]?.nombre, errores })
    for (let i = 0; i < equipos.length; i++) {
      if (parar.current) break
      setProgreso({ hechos: i, total: equipos.length, actual: equipos[i].nombre, errores: [...errores] })
      try {
        await client.post(`/api/v1/admin/comparador-plantillas/equipos/${equipos[i].id}/consultar`)
      } catch (error) {
        errores.push(`${equipos[i].nombre}: ${error.response?.data?.message ?? 'falló'}`)
        if (error.response?.status === 422 && /esperar/i.test(error.response?.data?.message ?? '')) break
      }
    }
    setProgreso(null)
    await alTerminar()
    if (errores.length) toast.error(`No se pudieron consultar ${errores.length}: ${errores.join(' · ')}`)
    else if (!parar.current) toast.exito('Plantillas consultadas.')
  }

  const pendientes = datos.equipos.filter((e) => !e.consultado_en)
  const masAntigua = datos.equipos.map((e) => e.consultado_en).filter(Boolean).sort()[0]

  return (
    <div className="bg-fondo border border-borde/30 rounded-lg px-4 py-3 mb-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="font-body text-sm text-texto">
            <strong>{datos.consultados}</strong> de {datos.total} equipos consultados
          </p>
          {masAntigua && <p className="font-body text-[11px] text-borde">La consulta más antigua es del {cuando(masAntigua)}. Se guardan 7 días.</p>}
        </div>
        {!progreso ? (
          <div className="flex gap-2">
            {pendientes.length > 0 && (
              <button type="button" className={BOTON} onClick={() => consultar(pendientes)}>
                Consultar los que faltan ({pendientes.length})
              </button>
            )}
            <button type="button" className={pendientes.length > 0 ? BOTON_SEC : BOTON} onClick={() => consultar(datos.equipos)}>
              Consultar todos de nuevo
            </button>
          </div>
        ) : (
          <button type="button" className={BOTON_SEC} onClick={() => { parar.current = true }}>Parar</button>
        )}
      </div>

      {progreso && (
        <div className="mt-3">
          <div className="h-2 bg-borde/10 rounded-full overflow-hidden">
            <div className="h-full bg-acento transition-all" style={{ width: `${(progreso.hechos / progreso.total) * 100}%` }} />
          </div>
          <p className="font-body text-xs text-borde mt-1.5">
            Consultando {progreso.hechos + 1} de {progreso.total}: <span className="text-texto">{progreso.actual}</span> · unos 7 segundos por equipo (límite de football-data)
          </p>
        </div>
      )}

      <div className="flex flex-wrap gap-1.5 mt-3">
        {datos.equipos.map((e) => (
          <span key={e.id} title={e.consultado_en ? `Consultado el ${cuando(e.consultado_en)}` : 'Sin consultar'}
            className={`font-body text-[11px] rounded-full px-2 py-0.5 border ${e.consultado_en ? 'border-acento/30 text-texto' : 'border-borde/30 text-borde'}`}>
            {e.consultado_en ? '✓' : '○'} {e.nombre}
          </span>
        ))}
      </div>
    </div>
  )
}

// ---------------------------------------------------------------- Página

function AdminComparadorPlantillasPage() {
  const queryClient = useQueryClient()
  const { data, isLoading, error } = useQuery({
    queryKey: ['admin-comparador-plantillas'],
    queryFn: async () => (await client.get('/api/v1/admin/comparador-plantillas')).data.data,
  })
  const recargar = () => queryClient.invalidateQueries({ queryKey: ['admin-comparador-plantillas'] })
  const accion = useAccion(recargar)

  return (
    <div className="max-w-3xl">
      <h1 className="font-display text-2xl text-texto mb-1">Comparar plantillas</h1>
      <p className="font-body text-sm text-borde mb-4">
        Las plantillas oficiales de football-data contra las tuyas, por el identificador de cada jugador. Sirve para ir por delante de
        las alineaciones (fichajes, traspasos, salidas), sobre todo en los mercados. Mirar no cambia nada; cada botón cambia solo ese jugador.
        Lo que llega por las alineaciones sigue en <Link to="/admin/cambios-plantilla" className="text-acento hover:underline">Cambios de plantilla</Link>.
      </p>

      {isLoading && <SkeletonLista />}
      {error && <p className="font-body text-sm text-red-500">{error.response?.data?.message ?? 'No se pudo cargar.'}</p>}

      {data && (
        <>
          <Consulta datos={data} alTerminar={recargar} />

          {data.consultados > 0 && (
            <>
              <p className="font-body text-xs text-borde mb-3">✓ {data.sin_cambios} jugadores coinciden con football-data, sin cambios.</p>

              {!data.todos && (
                <p className="font-body text-xs text-premio bg-premio/10 border border-premio/30 rounded-lg px-3 py-2 mb-3">
                  Faltan equipos por consultar: los traspasos y las salidas solo se pueden saber con todos consultados.
                </p>
              )}

              <Seccion titulo="Identificadores que no cuadran" gravedad="error" cantidad={data.dudosos.length}
                explicacion="Su identificador de football-data es de alguien con un nombre muy distinto. Revísalos primero: el resto de listas se fían de ellos.">
                {data.dudosos.map((d) => <FilaDudoso key={d.id_jugador} d={d} accion={accion} />)}
              </Seccion>

              {data.todos && (
                <Seccion titulo="Traspasos" gravedad="aviso" cantidad={data.traspasos.length}
                  explicacion="En football-data ya figuran en otro equipo de LaLiga. Cierra su ficha el día antes y abre la nueva.">
                  {data.traspasos.map((t) => <FilaTraspaso key={t.id_jugador} t={t} accion={accion} />)}
                </Seccion>
              )}

              {data.todos && (
                <Seccion titulo="Ya no están en LaLiga" gravedad="aviso" cantidad={data.bajas.length}
                  explicacion="No aparecen en ningún equipo. Cerrar la ficha basta si puede volver (una cesión); dar de baja, si se fue del todo.">
                  {data.bajas.map((b) => <FilaBaja key={b.id_jugador} b={b} accion={accion} />)}
                </Seccion>
              )}

              <Seccion titulo="Nuevos en football-data" gravedad="aviso" cantidad={data.altas.length}
                explicacion="Están en la plantilla oficial y aquí nadie tiene su identificador: o es un fichaje, o es alguien que ya tienes sin enlazar.">
                {data.altas.map((a) => <FilaAlta key={a.id_api} a={a} posiciones={data.posiciones} accion={accion} />)}
              </Seccion>

              <Seccion titulo="Tuyos sin identificador" cantidad={data.sin_id.length} abiertaDeInicio={false}
                explicacion="Están en tus plantillas sin identificador de football-data, así que el comparador no puede seguirlos. Muchos son canteranos que la API no tiene.">
                {data.sin_id.map((s) => <FilaSinId key={s.id_jugador} s={s} accion={accion} />)}
              </Seccion>
            </>
          )}
        </>
      )}
    </div>
  )
}

export default AdminComparadorPlantillasPage
