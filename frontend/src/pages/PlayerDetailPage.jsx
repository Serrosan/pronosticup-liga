import { useParams, useLocation, Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import SkeletonLista from '../components/SkeletonLista'
import useTitulo from '../hooks/useTitulo'

function calcularEdad(fechaNacimiento) {
  if (!fechaNacimiento) return null
  const nacimiento = new Date(fechaNacimiento)
  const hoy = new Date()
  let edad = hoy.getFullYear() - nacimiento.getFullYear()
  const aunNoCumplidos = hoy.getMonth() < nacimiento.getMonth() ||
    (hoy.getMonth() === nacimiento.getMonth() && hoy.getDate() < nacimiento.getDate())
  if (aunNoCumplidos) edad--
  return edad
}

function Pastilla({ label, valor }) {
  if (!valor) return null
  return (
    <div className="bg-borde/10 rounded-full px-3 py-1.5 flex items-baseline gap-1.5">
      <span className="font-body text-[10px] uppercase tracking-wide text-borde">{label}</span>
      <span className="font-body text-xs font-semibold text-texto">{valor}</span>
    </div>
  )
}

function BarraStat({ label, valor, maximo, color }) {
  const porcentaje = maximo > 0 ? Math.max(4, Math.round((valor / maximo) * 100)) : 4
  return (
    <div className="flex items-center gap-3">
      <span className="font-body text-xs text-borde w-20 shrink-0">{label}</span>
      <div className="flex-1 h-2 bg-borde/10 rounded-full overflow-hidden">
        <div className="h-full rounded-full transition-all" style={{ width: `${porcentaje}%`, backgroundColor: color }} />
      </div>
      <span className="font-marcador text-sm font-bold text-texto w-6 text-right shrink-0">{valor}</span>
    </div>
  )
}

function DatoPersonal({ label, valor }) {
  if (!valor) return null
  return (
    <div>
      <p className="font-body text-[10px] uppercase tracking-widest text-borde">{label}</p>
      <p className="font-body text-sm text-texto mt-0.5">{valor}</p>
    </div>
  )
}

function PlayerDetailPage() {
  const { idJugador } = useParams()
  const location = useLocation()
  const infoInicial = location.state

  const { data, isLoading, error } = useQuery({
    queryKey: ['jugador-detalle', idJugador],
    queryFn: async () => (await client.get(`/api/v1/jugadores/${idJugador}`)).data.data,
  })

  useTitulo(data?.nombre_camiseta || infoInicial?.nombre || 'Jugador')

  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>
  if (isLoading || !data) return <div className="max-w-2xl mx-auto px-4 py-8"><SkeletonLista /></div>

  const nombreCompleto = `${data.nombre} ${data.apellidos ?? ''}`.trim()
  const nombreMostrado = data.nombre_camiseta || nombreCompleto
  const edad = calcularEdad(data.fecha_nacimiento)

  const stats = data.stats
  const maximoStat = Math.max(stats.goles, stats.asistencias, stats.tarjetas_amarillas, stats.tarjetas_rojas, 1)

  return (
    <div className="max-w-2xl mx-auto px-4 py-8">
      {data.equipo_actual && (
        <Link
          to={`/equipos/${data.equipo_actual.id}`}
          state={{ nombre: data.equipo_actual.nombre, escudo_url: data.equipo_actual.escudo_url }}
          className="font-body text-sm text-acento hover:underline mb-4 inline-block"
        >
          ← Volver a {data.equipo_actual.nombre}
        </Link>
      )}

      {/* Cabecera en 2 columnas */}
      <div className="flex gap-5 mb-6">
        {data.foto_url ? (
          <img src={data.foto_url} alt={nombreMostrado} className="w-28 h-28 rounded-2xl object-cover shrink-0" />
        ) : (
          <div className="w-28 h-28 rounded-2xl bg-acento/10 border border-acento/20 flex items-center justify-center shrink-0">
            <span className="font-display text-3xl text-acento">{data.nombre?.[0]}</span>
          </div>
        )}

        <div className="min-w-0 flex-1 flex flex-col justify-center">
          {data.dado_de_baja && (
            <span className="font-body text-[10px] font-semibold uppercase tracking-widest text-red-500 mb-1">De baja</span>
          )}
          <h1 className="font-display text-2xl sm:text-3xl text-texto leading-tight">{nombreMostrado}</h1>
          {nombreMostrado !== nombreCompleto && (
            <p className="font-body text-sm text-borde mb-2">{nombreCompleto}</p>
          )}
          <div className="flex flex-wrap gap-1.5 mt-2">
            {data.equipo_actual?.dorsal && <Pastilla label="Dorsal" valor={`#${data.equipo_actual.dorsal}`} />}
            <Pastilla label="Pos." valor={data.posicion} />
            {data.equipo_actual && (
              <div className="bg-borde/10 rounded-full pl-1.5 pr-3 py-1 flex items-center gap-1.5">
                {data.equipo_actual.escudo_url && <img src={data.equipo_actual.escudo_url} alt="" className="w-4 h-4 object-contain" />}
                <span className="font-body text-xs font-semibold text-texto">{data.equipo_actual.nombre}</span>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Estadísticas como barras horizontales */}
      <div className="bg-fondo border border-borde/30 rounded-lg p-5 mb-4">
        <p className="font-body text-xs uppercase tracking-widest text-borde mb-4">Estadísticas de la temporada</p>
        <div className="flex flex-col gap-3">
          <BarraStat label="Goles" valor={stats.goles} maximo={maximoStat} color="var(--color-acento)" />
          <BarraStat label="Asistencias" valor={stats.asistencias} maximo={maximoStat} color="var(--color-acento)" />
          <BarraStat label="Amarillas" valor={stats.tarjetas_amarillas} maximo={maximoStat} color="#D9A400" />
          <BarraStat label="Rojas" valor={stats.tarjetas_rojas} maximo={maximoStat} color="#DC2626" />
        </div>
      </div>

      {/* Datos personales, en lista simple */}
      <div className="bg-fondo border border-borde/30 rounded-lg p-5 mb-4">
        <p className="font-body text-xs uppercase tracking-widest text-borde mb-4">Datos personales</p>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <DatoPersonal label="Nacionalidad" valor={data.nacionalidad} />
          <DatoPersonal label="Lugar de nacimiento" valor={data.lugar_nacimiento} />
          <DatoPersonal label="Edad" valor={edad ? `${edad} años` : null} />
          <DatoPersonal label="Altura" valor={data.altura ? `${data.altura} cm` : null} />
          <DatoPersonal label="Pie" valor={data.pie} />
          <DatoPersonal label="Selección" valor={data.seleccion} />
          <DatoPersonal label="Posición detallada" valor={data.posicion_detallada} />
          <DatoPersonal label="Club anterior" valor={data.club_anterior} />
        </div>
      </div>

      {/* Historial de equipos */}
      <div className="bg-fondo border border-borde/30 rounded-lg p-5">
        <p className="font-body text-xs uppercase tracking-widest text-borde mb-4">Historial de equipos</p>
        {data.historial.length === 0 ? (
          <p className="font-body text-sm text-borde">Sin historial registrado.</p>
        ) : (
          <div className="flex flex-col gap-3">
            {data.historial.map((h, i) => (
              <div key={i} className="flex items-center gap-3">
                <div className="flex flex-col items-center shrink-0">
                  <span className={`w-2.5 h-2.5 rounded-full ${h.actual ? 'bg-acento' : 'bg-borde/40'}`} />
                  {i < data.historial.length - 1 && <span className="w-px h-8 bg-borde/20 mt-1" />}
                </div>
                <div className="flex items-center gap-2 flex-1 min-w-0 pb-1">
                  {h.escudo_url && <img src={h.escudo_url} alt={h.equipo} className="w-6 h-6 object-contain shrink-0" />}
                  <div className="min-w-0">
                    <p className="font-body text-sm text-texto truncate">
                      {h.equipo} {h.dorsal && <span className="text-borde">· #{h.dorsal}</span>}
                    </p>
                    <p className="font-body text-[11px] text-borde">
                      {h.fecha_incorporacion ? h.fecha_incorporacion : '—'} → {h.fecha_salida ?? (h.actual ? 'actualidad' : '—')}
                    </p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

export default PlayerDetailPage