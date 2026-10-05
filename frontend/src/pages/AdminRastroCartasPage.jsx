import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import TicketHeader from '../components/TicketHeader'
import SkeletonLista from '../components/SkeletonLista'
import SelectorJornada from '../components/SelectorJornada'
import SelectTema from '../components/SelectTema'
import CartaJuego from '../components/CartaJuego'

// Estado de la carta: siempre icono + palabra, el color solo acompaña.
function estadoDe(carta) {
  switch (carta.estado) {
    case 'en_mano': return { icono: '✋', texto: 'En la mano', clase: 'text-borde' }
    case 'jugada': return { icono: '⏳', texto: 'Esperando', clase: 'text-premio' }
    case 'resuelta_cumplida':
      return { icono: '✓', texto: carta.puntos ? `Cumplida ${carta.puntos > 0 ? '+' : ''}${carta.puntos}` : 'Cumplida', clase: 'text-acento' }
    case 'resuelta_no_cumplida': return { icono: '✗', texto: 'Sin efecto', clase: 'text-red-400' }
    case 'descartada': return { icono: '–', texto: 'Descartada', clase: 'text-borde' }
    default: return { icono: '–', texto: carta.estado.replaceAll('_', ' '), clase: 'text-borde' }
  }
}

function fechaHora(iso) {
  if (!iso) return null
  return new Date(iso).toLocaleString('es-ES', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function Rastro({ carta }) {
  return (
    <div className="px-4 pb-4 pt-1 flex flex-col sm:flex-row gap-4">
      {carta.tipo_carta && (
        <div className="shrink-0 self-center sm:self-start">
          <CartaJuego
            carta={carta.tipo_carta}
            categoriaNombre={carta.tipo_carta.categoria?.nombre}
            categoriaIcono={carta.tipo_carta.categoria?.icono}
            tamano="pequena"
          />
        </div>
      )}

      <div className="flex-1 min-w-0">
        {carta.tipo_carta?.descripcion && (
          <p className="font-body text-xs text-borde mb-3">{carta.tipo_carta.descripcion}</p>
        )}

        <ol className="flex flex-col gap-2.5">
          {carta.pasos.map((paso, i) => (
            <li key={i} className="flex gap-2.5">
              <span className="font-body text-xs text-borde w-4 shrink-0 text-right">{i + 1}.</span>
              <div className="min-w-0">
                <p className="font-body text-sm text-texto">
                  {paso.titulo}
                  {paso.cuando && <span className="text-borde text-xs"> · {fechaHora(paso.cuando)}</span>}
                </p>
                {paso.detalle && <p className="font-body text-xs text-borde mt-0.5">{paso.detalle}</p>}
              </div>
            </li>
          ))}
        </ol>

        {carta.datos.length > 0 && (
          <div className="mt-3 border border-borde/20 rounded-md overflow-hidden">
            <p className="font-body text-[11px] text-borde px-3 py-1.5 bg-borde/10">Los datos detrás del resultado</p>
            {carta.datos.map((dato, i) => (
              <div key={i} className="px-3 py-1.5 border-t border-borde/10 flex flex-col sm:flex-row sm:gap-3">
                <span className="font-body text-xs text-borde sm:w-44 shrink-0">{dato.etiqueta}</span>
                <span className="font-body text-xs text-texto">{dato.valor}</span>
              </div>
            ))}
          </div>
        )}

        {carta.id_partido && (
          <p className="mt-3">
            <Link to={`/partidos/${carta.id_partido}`} className="font-body text-xs text-acento hover:underline">Ver el partido →</Link>
          </p>
        )}
      </div>
    </div>
  )
}

function FilaCarta({ carta, abierta, onAlternar }) {
  const estado = estadoDe(carta)

  return (
    <div className="border-b border-borde/10 last:border-0">
      <button onClick={onAlternar} className="w-full text-left px-4 py-3 flex items-center gap-3 hover:bg-borde/5">
        <span className="font-body text-xs text-borde w-3 shrink-0">{abierta ? '▾' : '▸'}</span>
        <div className="flex-1 min-w-0">
          <p className="font-body text-sm font-semibold text-texto truncate">{carta.nombre}</p>
          <p className="font-body text-[11px] text-borde truncate">
            {[carta.categoria, carta.rareza, carta.sobre].filter(Boolean).join(' · ')}
          </p>
        </div>
        <span className={`font-body text-xs font-semibold whitespace-nowrap ${estado.clase}`}>{estado.icono} {estado.texto}</span>
      </button>
      {abierta && <Rastro carta={carta} />}
    </div>
  )
}

function AdminRastroCartasPage() {
  // Se puede llegar con la liga y la jornada ya elegidas (p. ej. desde Cierre de jornada)
  const [parametros] = useSearchParams()
  const [idLiga, setIdLiga] = useState(parametros.get('liga'))
  const [vista, setVista] = useState('jornada')
  const [jornada, setJornada] = useState(parametros.get('jornada') ? Number(parametros.get('jornada')) : null)
  const [abiertas, setAbiertas] = useState({})

  const { data, isLoading, error } = useQuery({
    queryKey: ['admin-rastro-cartas', idLiga, vista, jornada],
    queryFn: async () => {
      const params = { vista }
      if (idLiga) params.id_liga = idLiga
      if (jornada) params.jornada = jornada
      return (await client.get('/api/v1/admin/rastro-cartas', { params })).data.data
    },
    placeholderData: (anteriores) => anteriores,
  })

  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>
  if (isLoading || !data) return <div className="max-w-4xl mx-auto px-4 py-8"><SkeletonLista /></div>

  if (data.ligas.length === 0) {
    return (
      <div className="max-w-4xl mx-auto px-4 py-8">
        <p className="font-body text-sm text-borde">No hay ninguna liga con el modo Cartas activado.</p>
      </div>
    )
  }

  const jornadas = Array.from({ length: data.total_jornadas }, (_, i) => i + 1)
  const cartas = data.cartas

  // Agrupadas por jugador, en el orden en que llegan
  const grupos = []
  const indice = {}
  cartas.forEach((carta) => {
    if (indice[carta.usuario.id] === undefined) {
      indice[carta.usuario.id] = grupos.length
      grupos.push({ usuario: carta.usuario, cartas: [] })
    }
    grupos[indice[carta.usuario.id]].cartas.push(carta)
  })

  const cuenta = (estado) => cartas.filter((c) => c.estado === estado).length
  const pestana = (activa) => `font-body text-sm px-4 py-2 border-b-2 ${activa ? 'border-acento text-acento font-semibold' : 'border-transparent text-borde hover:text-texto'}`

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
        <TicketHeader titulo="Rastro de cartas" />
        <div className="px-4 py-4">
          <p className="font-body text-sm text-borde mb-3">
            La historia de cada carta: cómo llegó, sobre qué se jugó, qué pasó y por qué. Esta pantalla solo enseña; no cambia nada.
          </p>

          {data.ligas.length > 1 && (
            <div className="max-w-xs mb-3">
              <SelectTema
                value={String(data.id_liga)}
                onChange={(e) => { setIdLiga(e.target.value); setJornada(null); setAbiertas({}) }}
                options={data.ligas.map((l) => ({ value: String(l.id), label: l.nombre }))}
                className="w-full bg-fondo"
              />
            </div>
          )}
        </div>

        <div className="flex border-t border-borde/20">
          <button className={pestana(vista === 'jornada')} onClick={() => { setVista('jornada'); setAbiertas({}) }}>Jugadas para una jornada</button>
          <button className={pestana(vista === 'mano')} onClick={() => { setVista('mano'); setAbiertas({}) }}>En la mano ahora</button>
        </div>

        {vista === 'jornada' && (
          <div className="px-4 py-3 border-t border-borde/10 flex items-center justify-between gap-3 flex-wrap">
            <SelectorJornada jornadas={jornadas} valor={data.jornada} onCambiar={(j) => { setJornada(j); setAbiertas({}) }} />
            <Link to={`/admin/cierre-jornada?jornada=${data.jornada}`} className="font-body text-xs text-acento hover:underline">
              Ir al cierre de la jornada {data.jornada} →
            </Link>
          </div>
        )}
      </div>

      {cartas.length === 0 ? (
        <p className="font-body text-sm text-borde">
          {vista === 'jornada'
            ? `Nadie ha jugado ninguna carta para la jornada ${data.jornada}.`
            : 'Nadie tiene cartas en la mano ahora mismo.'}
        </p>
      ) : (
        <>
          <p className="font-body text-xs text-borde mb-3">
            {cartas.length} carta(s) de {grupos.length} jugador(es)
            {vista === 'jornada' && (
              <> · ✓ {cuenta('resuelta_cumplida')} cumplida(s) · ✗ {cuenta('resuelta_no_cumplida')} sin efecto · ⏳ {cuenta('jugada')} esperando</>
            )}
          </p>

          <div className="flex flex-col gap-4">
            {grupos.map((grupo) => (
              <div key={grupo.usuario.id} className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
                <div className="px-4 py-2.5 bg-borde/10 flex items-center justify-between">
                  <p className="font-display text-base text-texto">{grupo.usuario.nombre}</p>
                  <p className="font-body text-[11px] text-borde">
                    {grupo.cartas.length} carta(s) ·{' '}
                    <Link to={`/clasificacion/usuarios/${grupo.usuario.id}`} className="text-acento hover:underline">Ver sus puntos →</Link>
                  </p>
                </div>
                {grupo.cartas.map((carta) => (
                  <FilaCarta
                    key={carta.id}
                    carta={carta}
                    abierta={!!abiertas[carta.id]}
                    onAlternar={() => setAbiertas((a) => ({ ...a, [carta.id]: !a[carta.id] }))}
                  />
                ))}
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  )
}

export default AdminRastroCartasPage
