import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import TicketHeader from '../components/TicketHeader'
import SkeletonLista from '../components/SkeletonLista'
import useTitulo from '../hooks/useTitulo'

function Logro({ logro }) {
  return (
    <div
      className={`rounded-lg border p-4 text-center transition ${
        logro.conseguido ? 'bg-premio/10 border-premio/40' : 'bg-borde/5 border-borde/20 opacity-40 grayscale'
      }`}
    >
      <p className="text-3xl mb-1">{logro.icono}</p>
      <p className="font-body text-xs font-semibold text-texto">{logro.titulo}</p>
      <p className="font-body text-[10px] text-borde mt-1">{logro.descripcion}</p>
      {logro.conseguido && (
        <p className="font-body text-[9px] text-premio font-bold mt-1.5 tracking-widest">✓ CONSEGUIDO</p>
      )}
    </div>
  )
}

// Estado de un logro secreto: siempre icono + palabra, el color solo acompaña.
const ESTADOS_SECRETO = {
  en_construccion: { texto: '🚧 EN CONSTRUCCIÓN', clase: 'text-borde' },
  bloqueado: { texto: '🔒 POR DESCUBRIR', clase: 'text-borde' },
  conseguido: { texto: '✓ CONSEGUIDO', clase: 'text-premio' },
}

function LogroSecreto({ logro }) {
  const estado = ESTADOS_SECRETO[logro.estado] ?? ESTADOS_SECRETO.bloqueado
  const conseguido = logro.estado === 'conseguido'

  return (
    <div className={`rounded-lg border border-dashed p-4 text-center ${conseguido ? 'bg-premio/10 border-premio/40' : 'bg-borde/5 border-borde/30'}`}>
      <p className={`text-3xl mb-1 ${conseguido ? '' : 'grayscale opacity-50'}`}>{conseguido ? logro.icono : '🔒'}</p>
      <p className="font-body text-xs font-semibold text-texto">{logro.titulo}</p>
      <p className={`font-body text-[9px] font-bold mt-1.5 tracking-widest ${estado.clase}`}>{estado.texto}</p>
    </div>
  )
}

function LogrosSecretos() {
  // Es un añadido: si esta petición fallara, los logros de siempre se siguen viendo.
  const { data } = useQuery({
    queryKey: ['logros-secretos'],
    queryFn: async () => (await client.get('/api/v1/logros-secretos')).data,
    retry: false,
  })

  if (!data || data.data.length === 0) return null

  const todosEnConstruccion = data.data.every((l) => l.estado === 'en_construccion')
  const conseguidos = data.data.filter((l) => l.estado === 'conseguido').length

  return (
    <div className="mt-8">
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-4">
        <TicketHeader
          titulo="Logros secretos"
          accion={<span className="font-marcador text-sm text-premio font-bold">{conseguidos}/{data.data.length}</span>}
        />
        <div className="px-5 py-4">
          <p className="font-body text-sm text-borde">
            De estos solo verás el título, y el título es la pista.
            {data.meta.con_cartas && ' Cada uno que consigas te dará una carta.'}
          </p>
          {todosEnConstruccion && (
            <p className="font-body text-xs text-premio font-semibold mt-2">
              🚧 En construcción: todavía no se puede conseguir ninguno. Irán llegando poco a poco.
            </p>
          )}
        </div>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {data.data.map((logro) => <LogroSecreto key={logro.clave} logro={logro} />)}
      </div>
    </div>
  )
}

function LogrosPage() {
  useTitulo('Logros')

  const { data, isLoading, error } = useQuery({
    queryKey: ['logros'],
    queryFn: async () => (await client.get('/api/v1/logros')).data.data,
  })

  if (isLoading) return <div className="max-w-3xl mx-auto px-4 py-8"><SkeletonLista /></div>
  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>

  const conseguidos = data.filter((l) => l.conseguido).length

  return (
    <div className="max-w-3xl mx-auto px-4 py-8">
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
        <TicketHeader
          titulo="Logros"
          accion={<span className="font-marcador text-sm text-premio font-bold">{conseguidos}/{data.length}</span>}
        />
        <div className="px-5 py-4">
          <p className="font-body text-sm text-borde">Cosas curiosas que puedes conseguir a lo largo de la temporada.</p>
        </div>
        <div className="px-5 py-3 border-t border-borde/10 bg-premio/5">
          <p className="font-body text-sm text-premio font-semibold">🚧 Los logros están en obras</p>
          <p className="font-body text-xs text-borde mt-1">
            Estos son los de prueba. Llegan logros nuevos, más difíciles y pensados para toda la temporada:
            algunos darán premio, y habrá un distintivo para quien los consiga todos.
          </p>
        </div>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
        {data.map((logro) => <Logro key={logro.id} logro={logro} />)}
      </div>

      <LogrosSecretos />
    </div>
  )
}

export default LogrosPage
