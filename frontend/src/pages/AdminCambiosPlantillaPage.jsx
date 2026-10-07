import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import { useToast } from '../context/ToastContext'
import TicketHeader from '../components/TicketHeader'
import SkeletonLista from '../components/SkeletonLista'
import EstadoVacio from '../components/EstadoVacio'

const PISTAS = {
  no_existe: { icono: '＋', texto: 'No está dado de alta' },
  podria_ser: { icono: '?', texto: 'Dudoso' },
  fuera_de_plantilla: { icono: '↔', texto: 'Existe, pero no en esta plantilla' },
}

const CAMPO = 'w-full font-body text-sm bg-borde/10 text-texto rounded border border-borde/40 px-2.5 py-1.5 focus:outline-none focus:border-acento'
// Los desplegables llevan fondo sólido (no translúcido) y sus opciones también: si no,
// en el tema oscuro la lista se abre en blanco con el texto claro y no se lee.
const DESPLEGABLE = CAMPO.replace('bg-borde/10', 'bg-fondo')
const OPCION = 'bg-fondo text-texto'
const BOTON_PRINCIPAL = 'font-body text-xs font-semibold bg-acento text-fondo rounded px-3 py-1.5 hover:brightness-110 disabled:opacity-40 disabled:cursor-not-allowed'
const BOTON_SECUNDARIO = 'font-body text-xs text-texto border border-borde/40 rounded px-3 py-1.5 hover:bg-borde/10 disabled:opacity-40 disabled:cursor-not-allowed'

// Lo que el formulario manda vacío va como null, no como '' (el backend valida fechas y números).
function sinVacios(objeto) {
  const limpio = {}
  Object.entries(objeto).forEach(([clave, valor]) => {
    limpio[clave] = valor === '' ? null : valor
  })
  return limpio
}

function Etiqueta({ texto, children }) {
  return (
    <label className="block">
      <span className="font-body text-[10px] uppercase tracking-widest text-borde">{texto}</span>
      <div className="mt-1">{children}</div>
    </label>
  )
}

function FotoLaliga({ url, nombre, tamano = 'w-12 h-12' }) {
  if (!url) {
    return (
      <span className={`${tamano} rounded-full bg-acento/10 border border-acento/20 flex items-center justify-center shrink-0 font-display text-sm text-acento`}>
        {nombre?.[0]}
      </span>
    )
  }
  return <img src={url} alt={nombre} className={`${tamano} rounded-full object-cover shrink-0 bg-borde/10`} />
}

// Paso de revisión: llega relleno con lo que trae LaLiga y no se guarda nada hasta confirmar.
function FormularioAlta({ cambio, posiciones, dorsalCanterano, onGuardar, onCancelar, guardando }) {
  const [datos, setDatos] = useState({
    nombre: cambio.nombre_pila_laliga ?? cambio.nombre_laliga ?? '',
    apellidos: cambio.apellidos_laliga ?? '',
    nombre_camiseta: cambio.apodo_laliga ?? '',
    posicion: '',
    dorsal: cambio.dorsal ?? '',
    pie: '',
    nacionalidad: '',
    fecha_nacimiento: '',
    lugar_nacimiento: '',
    altura: '',
    fecha_fin_contrato: '',
    club_anterior: '',
    usar_foto_laliga: Boolean(cambio.foto_laliga),
  })

  function cambiar(campo, valor) {
    setDatos((previo) => ({ ...previo, [campo]: valor }))
  }

  const esCanterano = datos.dorsal !== '' && Number(datos.dorsal) >= dorsalCanterano
  const faltaLoBasico = datos.nombre.trim() === '' || datos.posicion === ''

  return (
    <div className="mt-3 bg-borde/5 border border-borde/15 rounded-lg p-4">
      <p className="font-body text-xs text-borde mb-3">
        Revisa y corrige antes de guardar. LaLiga suele mandar el nombre legal completo: déjalo como quieras verlo en la app,
        el scraper lo seguirá reconociendo igualmente.
      </p>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Etiqueta texto="Nombre *">
          <input className={CAMPO} value={datos.nombre} onChange={(e) => cambiar('nombre', e.target.value)} />
        </Etiqueta>
        <Etiqueta texto="Apellidos">
          <input className={CAMPO} value={datos.apellidos} onChange={(e) => cambiar('apellidos', e.target.value)} />
        </Etiqueta>
        <Etiqueta texto="Nombre de camiseta">
          <input className={CAMPO} value={datos.nombre_camiseta} onChange={(e) => cambiar('nombre_camiseta', e.target.value)} />
        </Etiqueta>
        <Etiqueta texto="Posición *">
          <select className={DESPLEGABLE} value={datos.posicion} onChange={(e) => cambiar('posicion', e.target.value)}>
            <option className={OPCION} value="">Elige…</option>
            {posiciones.map((p) => <option className={OPCION} key={p} value={p}>{p}</option>)}
          </select>
        </Etiqueta>
        <Etiqueta texto="Dorsal">
          <input className={CAMPO} type="number" min="1" max="99" value={datos.dorsal} onChange={(e) => cambiar('dorsal', e.target.value)} />
        </Etiqueta>
        <Etiqueta texto="Pie">
          <input className={CAMPO} list="opciones-pie" value={datos.pie} onChange={(e) => cambiar('pie', e.target.value)} />
        </Etiqueta>
        <Etiqueta texto="Nacionalidad">
          <input className={CAMPO} value={datos.nacionalidad} onChange={(e) => cambiar('nacionalidad', e.target.value)} />
        </Etiqueta>
        <Etiqueta texto="Fecha de nacimiento">
          <input className={CAMPO} type="date" value={datos.fecha_nacimiento} onChange={(e) => cambiar('fecha_nacimiento', e.target.value)} />
        </Etiqueta>
        <Etiqueta texto="Lugar de nacimiento">
          <input className={CAMPO} value={datos.lugar_nacimiento} onChange={(e) => cambiar('lugar_nacimiento', e.target.value)} />
        </Etiqueta>
        <Etiqueta texto="Altura (cm)">
          <input className={CAMPO} type="number" min="140" max="220" value={datos.altura} onChange={(e) => cambiar('altura', e.target.value)} />
        </Etiqueta>
        <Etiqueta texto="Fin de contrato">
          <input className={CAMPO} type="date" value={datos.fecha_fin_contrato} onChange={(e) => cambiar('fecha_fin_contrato', e.target.value)} />
        </Etiqueta>
        <Etiqueta texto="Club anterior">
          <input className={CAMPO} value={datos.club_anterior} onChange={(e) => cambiar('club_anterior', e.target.value)} />
        </Etiqueta>
      </div>

      <datalist id="opciones-pie">
        <option className={OPCION} value="Derecho" />
        <option className={OPCION} value="Izquierdo" />
        <option className={OPCION} value="Ambidiestro" />
      </datalist>

      {cambio.foto_laliga && (
        <label className="flex items-center gap-3 mt-4 cursor-pointer">
          <input type="checkbox" checked={datos.usar_foto_laliga} onChange={(e) => cambiar('usar_foto_laliga', e.target.checked)} />
          <FotoLaliga url={cambio.foto_laliga} nombre={cambio.nombre_laliga} />
          <span className="font-body text-xs text-borde">
            Usar la foto de LaLiga (se intenta bajar a mayor tamaño). Si no te convence, desmárcala y súbela luego desde su ficha.
          </span>
        </label>
      )}

      <p className="font-body text-[11px] text-borde mt-3">
        {esCanterano
          ? `Con el dorsal ${datos.dorsal} se mostrará en el bloque de canteranos de la plantilla.`
          : 'Los datos que dejes en blanco se pueden completar después desde su ficha.'}
      </p>

      <div className="flex gap-2 mt-4">
        <button className={BOTON_PRINCIPAL} disabled={faltaLoBasico || guardando} onClick={() => onGuardar(sinVacios(datos))}>
          {guardando ? 'Guardando…' : 'Dar de alta'}
        </button>
        <button className={BOTON_SECUNDARIO} onClick={onCancelar}>Cancelar</button>
        {faltaLoBasico && <span className="font-body text-[11px] text-borde self-center">Faltan el nombre o la posición.</span>}
      </div>
    </div>
  )
}

// "Es este jugador": elegir entre la plantilla del equipo o buscar por nombre en todos.
function FormularioAsignar({ cambio, onGuardar, onCancelar, guardando }) {
  const [busqueda, setBusqueda] = useState('')
  const [idElegido, setIdElegido] = useState(cambio.sugerido?.id ? String(cambio.sugerido.id) : '')
  const [actualizarDorsal, setActualizarDorsal] = useState(cambio.dorsal !== null)

  const { data: candidatos } = useQuery({
    queryKey: ['admin-cambios-candidatos', cambio.id, busqueda],
    queryFn: async () => (await client.get(`/api/v1/admin/cambios-plantilla/${cambio.id}/candidatos`, { params: { q: busqueda } })).data.data,
    placeholderData: (anteriores) => anteriores,
  })

  return (
    <div className="mt-3 bg-borde/5 border border-borde/15 rounded-lg p-4">
      <p className="font-body text-xs text-borde mb-3">
        Elige a quién corresponde «{cambio.nombre_laliga}». Queda recordado: la próxima vez que LaLiga mande ese nombre, se empareja solo.
      </p>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Etiqueta texto="Buscar por nombre (opcional)">
          <input className={CAMPO} value={busqueda} onChange={(e) => setBusqueda(e.target.value)} placeholder="Deja vacío para ver la plantilla" />
        </Etiqueta>
        <Etiqueta texto="Jugador">
          <select className={DESPLEGABLE} value={idElegido} onChange={(e) => setIdElegido(e.target.value)}>
            <option className={OPCION} value="">Elige…</option>
            {cambio.sugerido && !(candidatos ?? []).some((c) => c.id === cambio.sugerido.id) && (
              <option className={OPCION} value={cambio.sugerido.id}>{cambio.sugerido.nombre}</option>
            )}
            {(candidatos ?? []).map((c) => <option className={OPCION} key={c.id} value={c.id}>{c.nombre} · {c.detalle}</option>)}
          </select>
        </Etiqueta>
      </div>

      {cambio.dorsal !== null && (
        <label className="flex items-center gap-2 mt-3 cursor-pointer">
          <input type="checkbox" checked={actualizarDorsal} onChange={(e) => setActualizarDorsal(e.target.checked)} />
          <span className="font-body text-xs text-borde">Ponerle también el dorsal {cambio.dorsal}, que es el que trae LaLiga</span>
        </label>
      )}

      <div className="flex gap-2 mt-4">
        <button
          className={BOTON_PRINCIPAL}
          disabled={idElegido === '' || guardando}
          onClick={() => onGuardar({ id_jugador: Number(idElegido), actualizar_dorsal: cambio.dorsal !== null && actualizarDorsal })}
        >
          {guardando ? 'Guardando…' : 'Es este jugador'}
        </button>
        <button className={BOTON_SECUNDARIO} onClick={onCancelar}>Cancelar</button>
      </div>
    </div>
  )
}

function TarjetaJugador({ cambio, posiciones, dorsalCanterano, accion, ocupado }) {
  const [abierto, setAbierto] = useState(null) // 'alta' | 'asignar' | null
  const pista = PISTAS[cambio.pista] ?? PISTAS.no_existe

  function ejecutar(ruta, cuerpo) {
    accion({ id: cambio.id, ruta, cuerpo }, () => setAbierto(null))
  }

  return (
    <div className="px-4 py-3 border-b border-borde/10 last:border-0 odd:bg-borde/5">
      <div className="flex items-start gap-3">
        <FotoLaliga url={cambio.foto_laliga} nombre={cambio.nombre_laliga} />
        <div className="min-w-0 flex-1">
          <p className="font-body text-sm font-semibold text-texto">
            {cambio.nombre_laliga}
            {cambio.dorsal !== null && <span className="font-marcador text-xs text-borde ml-2">#{cambio.dorsal}</span>}
          </p>
          <p className="font-body text-[11px] text-borde mt-0.5 flex flex-wrap gap-x-2">
            <span className="font-semibold text-texto">{pista.icono} {pista.texto}</span>
            {cambio.apodo_laliga && cambio.apodo_laliga !== cambio.nombre_laliga && <span>· apodo «{cambio.apodo_laliga}»</span>}
            <span>· visto en {cambio.veces_visto} partido{cambio.veces_visto === 1 ? '' : 's'}</span>
            {cambio.es_canterano && <span>· dorsal de canterano</span>}
          </p>
          {cambio.sugerido && (
            <p className="font-body text-[11px] text-borde mt-1">
              {cambio.pista === 'fuera_de_plantilla' ? 'Ya existe como ' : 'Podría ser '}
              <Link to={`/admin/jugadores/detalle/${cambio.sugerido.id}`} className="text-acento hover:underline">{cambio.sugerido.nombre}</Link>
              {cambio.pista === 'fuera_de_plantilla' && ', pero no está en la plantilla de este equipo en esas fechas: fíchalo o corrige las fechas en su ficha.'}
            </p>
          )}
        </div>
      </div>

      {abierto === null && (
        <div className="flex flex-wrap gap-2 mt-3">
          {cambio.pista !== 'fuera_de_plantilla' && (
            <button className={BOTON_PRINCIPAL} disabled={ocupado} onClick={() => setAbierto('alta')}>Dar de alta</button>
          )}
          <button className={cambio.pista === 'fuera_de_plantilla' ? BOTON_PRINCIPAL : BOTON_SECUNDARIO} disabled={ocupado} onClick={() => setAbierto('asignar')}>
            Es este jugador
          </button>
          {cambio.pista === 'fuera_de_plantilla' && (
            <button className={BOTON_SECUNDARIO} disabled={ocupado} onClick={() => ejecutar('arreglado')}>Ya lo he arreglado</button>
          )}
          <button className={BOTON_SECUNDARIO} disabled={ocupado} onClick={() => ejecutar('ignorar')}>Ignorar</button>
        </div>
      )}

      {abierto === 'alta' && (
        <FormularioAlta
          cambio={cambio}
          posiciones={posiciones}
          dorsalCanterano={dorsalCanterano}
          guardando={ocupado}
          onGuardar={(datos) => ejecutar('alta', datos)}
          onCancelar={() => setAbierto(null)}
        />
      )}

      {abierto === 'asignar' && (
        <FormularioAsignar
          cambio={cambio}
          guardando={ocupado}
          onGuardar={(datos) => ejecutar('asignar', datos)}
          onCancelar={() => setAbierto(null)}
        />
      )}
    </div>
  )
}

function FilaDorsal({ cambio, accion, ocupado }) {
  const nombre = cambio.sugerido?.nombre ?? cambio.nombre_laliga

  return (
    <div className="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-borde/10 last:border-0 odd:bg-borde/5">
      <div className="min-w-0">
        <p className="font-body text-sm text-texto truncate">
          {nombre} <span className="font-marcador text-xs font-bold text-acento ml-1">→ #{cambio.dorsal}</span>
        </p>
        <p className="font-body text-[11px] text-borde">{cambio.motivo}</p>
      </div>
      <div className="flex gap-2 shrink-0">
        <button className={BOTON_PRINCIPAL} disabled={ocupado} onClick={() => accion({ id: cambio.id, ruta: 'dorsal' })}>Aplicar</button>
        <button className={BOTON_SECUNDARIO} disabled={ocupado} onClick={() => accion({ id: cambio.id, ruta: 'ignorar' })}>Ignorar</button>
      </div>
    </div>
  )
}

function agruparPorEquipo(cambios) {
  const grupos = []
  const indice = {}
  cambios.forEach((cambio) => {
    if (indice[cambio.equipo.id] === undefined) {
      indice[cambio.equipo.id] = grupos.length
      grupos.push({ equipo: cambio.equipo, cambios: [] })
    }
    grupos[indice[cambio.equipo.id]].cambios.push(cambio)
  })
  return grupos
}

function CabeceraEquipo({ equipo, total }) {
  return (
    <div className="flex items-center gap-2 px-4 py-2 bg-borde/10">
      {equipo.escudo_url && <img src={equipo.escudo_url} alt={equipo.nombre} className="w-5 h-5 object-contain" />}
      <span className="font-display text-sm text-texto">{equipo.nombre}</span>
      <span className="font-marcador text-xs text-borde bg-borde/10 rounded-full px-2 py-0.5">{total}</span>
    </div>
  )
}

function AdminCambiosPlantillaPage() {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [pestana, setPestana] = useState('jugador')

  const { data, isLoading, error } = useQuery({
    queryKey: ['admin-cambios-plantilla'],
    queryFn: async () => (await client.get('/api/v1/admin/cambios-plantilla')).data.data,
  })

  function refrescar() {
    queryClient.invalidateQueries({ queryKey: ['admin-cambios-plantilla'] })
    queryClient.invalidateQueries({ queryKey: ['admin-cambios-plantilla-resumen'] })
  }

  const resolver = useMutation({
    mutationFn: ({ id, ruta, cuerpo }) => client.post(`/api/v1/admin/cambios-plantilla/${id}/${ruta}`, cuerpo ?? {}),
    onSuccess: (respuesta) => {
      toast.exito(respuesta.data.message ?? 'Hecho.')
      refrescar()
    },
    onError: (err) => {
      const errores = err.response?.data?.errors
      const primero = errores ? Object.values(errores)[0]?.[0] : null
      toast.error(primero ?? err.response?.data?.message ?? 'No se pudo guardar.')
    },
  })

  const todosLosDorsales = useMutation({
    mutationFn: () => client.post('/api/v1/admin/cambios-plantilla/dorsales'),
    onSuccess: (respuesta) => {
      toast.exito(respuesta.data.message)
      refrescar()
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo aplicar.'),
  })

  const aplicarAPartidos = useMutation({
    mutationFn: () => client.post('/api/v1/admin/tareas/reimportar-cambios/lanzar'),
    onSuccess: (respuesta) => {
      toast.exito(respuesta.data.message ?? 'Encargado.')
      refrescar()
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo lanzar.'),
  })

  if (isLoading) return <div className="max-w-3xl mx-auto px-4 py-8"><SkeletonLista /></div>
  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>

  // Cada acción avisa al terminar bien, para que la tarjeta cierre su formulario.
  function accion(variables, alTerminar) {
    resolver.mutate(variables, { onSuccess: () => alTerminar?.() })
  }

  const jugadores = data.pendientes.filter((c) => c.tipo === 'jugador')
  const dorsales = data.pendientes.filter((c) => c.tipo === 'dorsal')
  const visibles = pestana === 'jugador' ? jugadores : dorsales
  const grupos = agruparPorEquipo(visibles)

  const PESTANAS = [
    { clave: 'jugador', texto: 'Jugadores sin emparejar', total: jugadores.length },
    { clave: 'dorsal', texto: 'Dorsales distintos', total: dorsales.length },
  ]

  return (
    <div className="max-w-3xl mx-auto px-4 py-8">
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
        <TicketHeader titulo="Cambios de plantilla" />
        <div className="px-4 py-4">
          <p className="font-body text-sm text-borde">
            Lo que LaLiga dice y tu base de datos no. Cada vez que se importa un partido, quien no se puede emparejar
            y los dorsales que no coinciden quedan aquí para que los revises. Nada se cambia solo.
          </p>
          {data.ignorados > 0 && (
            <p className="font-body text-[11px] text-borde mt-2">{data.ignorados} ignorado(s), que ya no generan avisos.</p>
          )}
        </div>
      </div>

      {data.partidos_por_actualizar > 0 && (
        <div className="mb-6 bg-premio/10 border border-premio/30 rounded-lg px-4 py-3 flex items-center justify-between gap-3 flex-wrap">
          <p className="font-body text-sm text-premio font-semibold">
            Hay {data.partidos_por_actualizar} partido(s) que mejorarían con lo que ya has resuelto.
            <span className="block font-normal text-xs mt-0.5">
              Se vuelven a importar de LaLiga para completar sus alineaciones. Los puntos no se recalculan.
            </span>
          </p>
          <button className={BOTON_PRINCIPAL} disabled={aplicarAPartidos.isPending} onClick={() => aplicarAPartidos.mutate()}>
            Aplicar a los partidos
          </button>
        </div>
      )}

      <div className="flex gap-2 mb-4 flex-wrap">
        {PESTANAS.map((p) => (
          <button
            key={p.clave}
            onClick={() => setPestana(p.clave)}
            className={`font-body text-sm px-3 py-1.5 rounded-full transition ${
              pestana === p.clave ? 'bg-acento text-fondo font-semibold' : 'text-texto border border-borde/40 hover:bg-borde/10'
            }`}
          >
            {p.texto} <span className="font-marcador text-xs">({p.total})</span>
          </button>
        ))}
      </div>

      {pestana === 'dorsal' && dorsales.length > 1 && (
        <div className="flex justify-end mb-3">
          <button
            className={BOTON_SECUNDARIO}
            disabled={todosLosDorsales.isPending}
            onClick={() => {
              if (window.confirm(`¿Aplicar los ${dorsales.length} dorsales de LaLiga de una vez? Los que choquen con otro jugador se quedan pendientes.`)) {
                todosLosDorsales.mutate()
              }
            }}
          >
            Aplicar todos los que no choquen
          </button>
        </div>
      )}

      {grupos.length === 0 ? (
        <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
          <EstadoVacio
            icono="✅"
            titulo="Nada pendiente"
            texto={pestana === 'jugador' ? 'Todos los jugadores de las alineaciones importadas están emparejados.' : 'Los dorsales coinciden con los de LaLiga.'}
          />
        </div>
      ) : (
        grupos.map((grupo) => (
          <div key={grupo.equipo.id} className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-4">
            <CabeceraEquipo equipo={grupo.equipo} total={grupo.cambios.length} />
            {grupo.cambios.map((cambio) =>
              pestana === 'jugador' ? (
                <TarjetaJugador
                  key={cambio.id}
                  cambio={cambio}
                  posiciones={data.posiciones}
                  dorsalCanterano={data.dorsal_canterano}
                  accion={accion}
                  ocupado={resolver.isPending}
                />
              ) : (
                <FilaDorsal key={cambio.id} cambio={cambio} accion={accion} ocupado={resolver.isPending} />
              )
            )}
          </div>
        ))
      )}
    </div>
  )
}

export default AdminCambiosPlantillaPage
