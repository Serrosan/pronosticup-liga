import { useQuery } from '@tanstack/react-query'
import client from '../api/client'

function MomentoDecisivo({ jornada }) {
  const { data } = useQuery({
    queryKey: ['momento-decisivo', jornada],
    queryFn: async () => (await client.get(`/api/v1/jornadas/${jornada}/momento-decisivo`)).data.data,
  })

  if (!data) return null

  function flecha(antes, despues) {
    if (despues < antes) return <span className="text-acento">▲ {antes - despues}</span>
    if (despues > antes) return <span className="text-red-500">▼ {despues - antes}</span>
    return <span className="text-borde">–</span>
  }

  return (
    <div className="max-w-md mx-auto mb-6 bg-premio/10 border border-premio/30 rounded-lg p-4">
      <p className="font-body text-[10px] uppercase tracking-widest text-premio font-semibold text-center mb-3">
        🔥 Partido decisivo de la jornada
      </p>
      <div className="flex items-center justify-center gap-3 mb-2">
        <div className="flex flex-col items-center gap-1">
          {data.escudo_local && <img src={data.escudo_local} alt={data.equipo_local} className="w-8 h-8 object-contain" />}
          <p className="font-body text-xs text-texto">{data.equipo_local}</p>
          <p className="font-body text-[10px] text-borde">
            {data.local_posicion_antes}º → {data.local_posicion_despues}º {flecha(data.local_posicion_antes, data.local_posicion_despues)}
          </p>
        </div>
        <span className="font-marcador text-xl font-bold text-texto px-2">{data.goles_casa}-{data.goles_fuera}</span>
        <div className="flex flex-col items-center gap-1">
          {data.escudo_visitante && <img src={data.escudo_visitante} alt={data.equipo_visitante} className="w-8 h-8 object-contain" />}
          <p className="font-body text-xs text-texto">{data.equipo_visitante}</p>
          <p className="font-body text-[10px] text-borde">
            {data.visitante_posicion_antes}º → {data.visitante_posicion_despues}º {flecha(data.visitante_posicion_antes, data.visitante_posicion_despues)}
          </p>
        </div>
      </div>
    </div>
  )
}

export default MomentoDecisivo