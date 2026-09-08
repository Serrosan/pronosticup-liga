import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import TicketHeader from '../components/TicketHeader'
import SkeletonLista from '../components/SkeletonLista'
import useTitulo from '../hooks/useTitulo'

function Seccion({ icono, titulo, children }) {
  return (
    <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
      <div className="px-5 py-4 bg-borde/10 border-b border-borde/20 flex items-center gap-2">
        <span className="text-xl">{icono}</span>
        <h2 className="font-display text-lg text-texto">{titulo}</h2>
      </div>
      <div className="p-5">{children}</div>
    </div>
  )
}

function Ejemplo({ children }) {
  return (
    <div className="bg-premio/5 border border-premio/20 rounded-lg px-4 py-3 mt-3">
      <p className="font-body text-[10px] uppercase tracking-widest text-premio font-semibold mb-1">Ejemplo</p>
      <p className="font-body text-sm text-texto">{children}</p>
    </div>
  )
}

function FilaPuntos({ etiqueta, puntos, color = 'text-acento' }) {
  return (
    <div className="flex items-center justify-between py-2 border-b border-borde/10 last:border-0">
      <p className="font-body text-sm text-texto">{etiqueta}</p>
      <span className={`font-marcador text-lg font-bold ${color}`}>+{puntos}</span>
    </div>
  )
}

function PuntuacionesPage() {
  useTitulo('Puntuaciones')

  const { data: config, isLoading } = useQuery({
    queryKey: ['configuracion-puntos'],
    queryFn: async () => (await client.get('/api/v1/configuracion-puntos')).data.data,
  })

  if (isLoading || !config) {
    return <div className="max-w-3xl mx-auto px-4 py-8"><SkeletonLista /></div>
  }

  return (
    <div className="max-w-3xl mx-auto px-4 py-8">
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
        <TicketHeader titulo="Puntuaciones" />
        <div className="px-5 py-4">
          <p className="font-body text-sm text-borde">
            Así se reparten los puntos cada jornada. Cuanto más difícil es acertar algo, más puntos vale — para que ningún sistema se sienta más "regalado" que otro.
          </p>
        </div>
      </div>

      <Seccion icono="⚽" titulo="Pronóstico de cada partido">
        <FilaPuntos etiqueta="Aciertas el signo (gana local / empate / gana visitante)" puntos={config.puntos_signo} />
        <FilaPuntos etiqueta="Aciertas el signo + la diferencia exacta de goles" puntos={config.puntos_diferencia} color="text-premio" />
        <FilaPuntos etiqueta="Aciertas el resultado exacto" puntos={config.puntos_exacto} color="text-premio" />

        <Ejemplo>
          El partido acaba <strong>3-1</strong>. Si predijiste "gana el local" (sin más), <strong>{config.puntos_signo}pt</strong>.
          Si predijiste cualquier marcador con 2 goles de diferencia a favor del local (ej. 2-0, 4-2), <strong>{config.puntos_diferencia}pt</strong>.
          Si predijiste exactamente 3-1, <strong>{config.puntos_exacto}pt</strong>.
        </Ejemplo>
      </Seccion>

      <Seccion icono="🎯" titulo="Bonus por buena jornada">
        <p className="font-body text-sm text-borde mb-3">
          Si aciertas el signo de muchos partidos en la misma jornada, ganas puntos extra sobre lo que ya sumaste partido a partido.
        </p>
        <FilaPuntos etiqueta="Aciertas el 70% o más de los signos de la jornada" puntos={config.bonus_pleno_7} />
        <FilaPuntos etiqueta="Aciertas el 80% o más" puntos={config.bonus_pleno_8} />
        <FilaPuntos etiqueta="Aciertas el 90% o más" puntos={config.bonus_pleno_9} color="text-premio" />
        <FilaPuntos etiqueta="Pleno — aciertas todos los signos de la jornada" puntos={config.bonus_pleno_10} color="text-premio" />

        <Ejemplo>
          En una jornada de 10 partidos, aciertas el signo de 9. Eso es el 90%, así que sumas <strong>+{config.bonus_pleno_9}pt</strong> extra, además de los puntos que ya llevabas por cada partido acertado.
        </Ejemplo>
      </Seccion>

      <Seccion icono="🥅" titulo="Tus 5 goleadores de la jornada">
        <p className="font-body text-sm text-borde mb-3">
          Antes de que empiece la jornada, eliges 5 jugadores de cualquier equipo de LaLiga. Por cada gol real que marquen, sumas puntos.
        </p>
        <FilaPuntos etiqueta="Por cada gol de uno de tus 5 elegidos" puntos={config.puntos_gol_goleador} />

        <div className="bg-borde/5 rounded-lg px-4 py-3 mt-3">
          <p className="font-body text-xs text-borde">
            <strong>Importante:</strong> ninguno de los 5 puede repetirse en la jornada siguiente — toca variar, no puedes quedarte siempre con los mismos.
          </p>
        </div>

        <Ejemplo>
          Eliges a 5 jugadores. Dos de ellos marcan esa jornada: uno mete 1 gol, otro mete 2. Sumas <strong>{3 * config.puntos_gol_goleador}pt</strong> en total (3 goles × {config.puntos_gol_goleador}pt cada uno).
        </Ejemplo>
      </Seccion>

      <Seccion icono="🏆" titulo="Quinielas de posiciones de LaLiga">
        <p className="font-body text-sm text-borde mb-4">
          Además de los pronósticos semanales, hay 3 quinielas donde predices cómo quedará la clasificación de LaLiga. Cada una premia un tipo distinto de acierto.
        </p>

        <div className="flex flex-col gap-4">
          <div>
            <p className="font-body text-sm font-semibold text-texto mb-1">Quiniela completa</p>
            <p className="font-body text-xs text-borde">
              Predices la clasificación final de toda la temporada, desde el principio. Por cada equipo: posición exacta = 5pt, y vas perdiendo 1 punto por cada puesto de diferencia (hasta un máximo de 4 puestos, a partir de ahí 0).
            </p>
          </div>

          <div>
            <p className="font-body text-sm font-semibold text-texto mb-1">Primera mitad (jornadas 1-18)</p>
            <p className="font-body text-xs text-borde">
              Igual que la completa, pero solo hasta la mitad de la temporada. Por cada equipo: posición exacta = 3pt, 1 puesto de diferencia = 2pt, 2 puestos = 1pt.
            </p>
          </div>

          <div>
            <p className="font-body text-sm font-semibold text-texto mb-1">Segunda mitad (jornada 19 al final)</p>
            <p className="font-body text-xs text-borde">
              Se predice justo antes de que empiece la segunda vuelta, ya con la clasificación real de la primera mitad delante — un pronóstico más informado que las otras 2. Misma puntuación que la de primera mitad.
            </p>
          </div>
        </div>

        <Ejemplo>
          Predices que el Barça terminará 1º, y termina 3º. Falla por 2 puestos: en la Quiniela completa, eso son <strong>3pt</strong> (5 - 2). En la de primera o segunda mitad, serían <strong>1pt</strong> (3 - 2).
        </Ejemplo>
      </Seccion>

      <p className="font-body text-xs text-borde text-center">
        Todos estos puntos se suman entre sí cada jornada — no compiten unos con otros.
      </p>
    </div>
  )
}

export default PuntuacionesPage
