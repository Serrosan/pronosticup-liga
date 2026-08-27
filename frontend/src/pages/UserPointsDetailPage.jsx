import { useParams, useLocation, Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'

function Escudo({ url, alt }) {
  if (!url) return <span className="w-5 h-5 rounded-full bg-borde/15 flex items-center justify-center text-xs shrink-0">⚽</span>
  return <img src={url} alt={alt} className="w-5 h-5 object-contain shrink-0" />
}

function Avatar({ url, nombre, tamano = 'w-10 h-10' }) {
  if (url) return <img src={url} alt={nombre} className={`${tamano} rounded-full object-cover shrink-0`} />
  return (
    <div className={`${tamano} rounded-full bg-acento/15 flex items-center justify-center shrink-0`}>
      <span className="font-display text-sm text-acento">{nombre?.[0]?.toUpperCase()}</span>
    </div>
  )
}

const MARCA_1X2 = { Local: '1', Empate: 'X', Visitante: '2' }

function calcularResultadoReal(golesCasa, golesFuera) {
  if (golesCasa === null || golesFuera === null) return null
  if (golesCasa > golesFuera) return '1'
  if (golesCasa < golesFuera) return '2'
  return 'X'
}

function Marcador1X2({ marcado, real }) {
  return (
    <div className="flex gap-1">
      {['1', 'X', '2'].map((valor) => {
        const esMiEleccion = valor === marcado
        const esResultadoReal = real !== null && valor === real
        const acerte = esMiEleccion && esResultadoReal

        let clase = 'border-borde/30 text-borde/40'
        if (esMiEleccion && real === null) clase = 'bg-acento text-fondo border-acento font-bold'
        else if (acerte) clase = 'bg-acento text-fondo border-acento font-bold'
        else if (esMiEleccion) clase = 'bg-red-500/80 text-white border-red-500 font-bold'
        else if (esResultadoReal) clase = 'border-2 border-premio text-premio font-semibold'

        return (
          <span key={valor} className={`w-5 h-5 rounded-full flex items-center justify-center font-marcador text-[10px] border ${clase}`}>
            {valor}
          </span>
        )
      })}
    </div>
  )
}

function MarcadorExacto({ prediccion, golesCasa, golesFuera, exacto }) {
  const resuelto = golesCasa !== null && golesFuera !== null
  return (
    <div className="text-center w-14 shrink-0">
      <p className={`font-marcador text-xs tabular-nums ${resuelto && !exacto ? 'text-borde/50 line-through' : 'text-texto'}`}>
        {prediccion}
      </p>
      {resuelto && (
        <p className={`font-marcador text-xs tabular-nums ${exacto ? 'text-premio' : 'text-borde'}`}>
          {golesCasa}-{golesFuera}
        </p>
      )}
    </div>
  )
}

function UserPointsDetailPage() {
  const { idUsuario } = useParams()
  const location = useLocation()
  const infoInicial = location.state

  const { data, isLoading, error } = useQuery({
    queryKey: ['clasificacion-detalle', idUsuario],
    queryFn: async () => (await client.get(`/api/v1/clasificacion/usuarios/${idUsuario}/detalle`)).data.data,
  })

  const nombre = data?.usuario.nombre ?? infoInicial?.nombre
  const avatarUrl = data?.usuario.avatar_url ?? infoInicial?.avatar_url

  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>

  return (
    <div className="max-w-2xl mx-auto px-4 py-8">
      <Link to="/clasificacion" className="font-body text-sm text-acento hover:underline mb-4 inline-block">← Volver a la clasificación</Link>

      <div className="bg-borde/10 border border-borde/30 rounded-t-2xl px-6 py-4 text-center">
        <div className="flex items-center justify-center gap-3">
          <Avatar url={avatarUrl} nombre={nombre} />
          <h1 className="font-display text-xl text-texto">{nombre ?? 'Cargando...'}</h1>
        </div>
      </div>

      <div className="relative border-t-2 border-dashed border-borde/30">
        <div className="absolute -left-3 -top-3 w-6 h-6 rounded-full bg-fondo border border-borde/30" />
        <div className="absolute -right-3 -top-3 w-6 h-6 rounded-full bg-fondo border border-borde/30" />
      </div>

      {isLoading ? (
        <p className="font-body text-texto text-center py-8 bg-fondo border-x border-b border-borde/30 rounded-b-2xl">Cargando...</p>
      ) : (
        <>
          <div className="bg-fondo border-x border-borde/30 px-6 py-4 flex items-center justify-between">
            {[
              { label: 'TOTAL', valor: data.stats.total, color: 'var(--color-texto)' },
              { label: 'PUNTOS', valor: data.stats.puntos_totales, color: 'var(--color-acento)' },
              { label: 'ACIERTOS', valor: data.stats.aciertos, color: 'var(--color-texto)' },
              { label: 'EXACTOS', valor: data.stats.exactos, color: 'var(--color-premio)' },
            ].map((item, i) => (
              <div key={item.label} className={`text-center flex-1 ${i > 0 ? 'border-l border-dotted border-borde/30' : ''}`}>
                <p className="font-marcador text-xl tabular-nums" style={{ color: item.color, textShadow: `0 0 10px ${item.color}55` }}>
                  {item.valor}
                </p>
                <p className="font-body text-[9px] tracking-widest text-borde mt-0.5">{item.label}</p>
              </div>
            ))}
          </div>

          <div className="relative border-t-2 border-dashed border-borde/30">
            <div className="absolute -left-3 -top-3 w-6 h-6 rounded-full bg-fondo border border-borde/30" />
            <div className="absolute -right-3 -top-3 w-6 h-6 rounded-full bg-fondo border border-borde/30" />
          </div>

          <div className="bg-fondo border-x border-b border-borde/30 rounded-b-2xl overflow-hidden">
            {data.pronosticos.map((p, i) => {
              const resultadoReal = calcularResultadoReal(p.goles_casa, p.goles_fuera)
              return (
                <div key={p.id} className="flex items-center gap-3 px-4 py-4 border-b border-borde/10 last:border-0 odd:bg-borde/5">
                  <span className="font-marcador text-xs text-borde/50 w-6 shrink-0">J{p.jornada}</span>
                  <div className="flex items-center gap-1.5 min-w-0 flex-1">
                    <Escudo url={p.escudo_local} alt={p.equipo_local} />
                    <p className="font-body text-xs text-texto truncate">{p.equipo_local}</p>
                    <span className="text-borde text-xs shrink-0">–</span>
                    <p className="font-body text-xs text-texto truncate">{p.equipo_visitante}</p>
                    <Escudo url={p.escudo_visitante} alt={p.equipo_visitante} />
                  </div>
                  <Marcador1X2 marcado={MARCA_1X2[p.resultado_1x2]} real={resultadoReal} />
                  <MarcadorExacto prediccion={p.mi_pronostico} golesCasa={p.goles_casa} golesFuera={p.goles_fuera} exacto={p.tipo_evento === 'AciertoExacto'} />
                  <div className="w-16 text-right shrink-0">
                    {p.estado_partido === 'Jugado' ? (
                      <span className={`font-marcador text-xs font-semibold ${
                        p.tipo_evento === 'AciertoExacto' ? 'text-premio' : p.tipo_evento === 'Acierto1x2' ? 'text-acento' : 'text-red-500'
                      }`}>
                        {p.puntos}pt
                      </span>
                    ) : (
                      <span className="font-body text-[10px] text-borde">pendiente</span>
                    )}
                  </div>
                </div>
              )
            })}
          </div>
        </>
      )}
    </div>
  )
}

export default UserPointsDetailPage