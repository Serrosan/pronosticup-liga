import { useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import TicketHeader from '../components/TicketHeader'
import SkeletonLista from '../components/SkeletonLista'
import { CONSULTA_ACTUALIZACIONES, marcarActualizacionesVistas } from '../components/BotonActualizaciones'
import { Proximamente, EntradaHistorial, mesDe } from '../components/VistaActualizaciones'

// "Actualizaciones": lo que va a cambiar y lo que ya ha cambiado, explicado.
// Se escribe desde Admin → Actualizaciones.
export default function ActualizacionesPage() {
  const { data, isLoading, error } = useQuery({ ...CONSULTA_ACTUALIZACIONES, staleTime: 0 })

  useEffect(() => {
    if (data?.marca) marcarActualizacionesVistas(data.marca)
  }, [data?.marca])

  if (isLoading) return <div className="max-w-3xl mx-auto px-4 py-8"><SkeletonLista /></div>
  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'No se han podido cargar las actualizaciones.'}</p>

  const entradas = data?.data ?? []
  const proximas = entradas.filter((e) => e.tipo === 'proximamente')
  const hechas = entradas.filter((e) => e.tipo !== 'proximamente')

  const porMes = []
  for (const entrada of hechas) {
    const mes = mesDe(entrada.fecha)
    const grupo = porMes.at(-1)
    if (grupo?.mes === mes) grupo.entradas.push(entrada)
    else porMes.push({ mes, entradas: [entrada] })
  }

  return (
    <div className="max-w-3xl mx-auto px-4 py-8 flex flex-col gap-6">
      <div>
        <h1 className="font-display text-3xl text-texto">📜 Actualizaciones</h1>
        <p className="font-body text-sm text-borde mt-1">
          Lo que va a cambiar y lo que ya ha cambiado en PronostiCup, explicado. Si algo no te cuadra o tienes una idea, dilo en el chat.
        </p>
      </div>

      {proximas.map((e) => <Proximamente key={e.id} entrada={e} />)}

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        <TicketHeader titulo="Lo que ya ha cambiado" />
        <div className="p-4 flex flex-col gap-6">
          {porMes.length === 0 && <p className="font-body text-sm text-borde">Todavía no hay nada aquí.</p>}
          {porMes.map((grupo) => (
            <section key={grupo.mes}>
              <h2 className="font-body text-[11px] uppercase tracking-widest text-borde font-semibold mb-3 border-b border-borde/20 pb-1">{grupo.mes}</h2>
              <ul className="flex flex-col gap-4">
                {grupo.entradas.map((e) => <EntradaHistorial key={e.id} entrada={e} />)}
              </ul>
            </section>
          ))}
        </div>
      </div>
    </div>
  )
}
