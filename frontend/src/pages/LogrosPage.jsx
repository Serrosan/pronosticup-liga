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
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
        {data.map((logro) => <Logro key={logro.id} logro={logro} />)}
      </div>
    </div>
  )
}

export default LogrosPage
