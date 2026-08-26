import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'

function AdminLigasPage() {
  const queryClient = useQueryClient()

  const { data: ligas, isLoading, error } = useQuery({
    queryKey: ['admin', 'ligas'],
    queryFn: async () => {
      const respuesta = await client.get('/api/v1/admin/ligas')
      return respuesta.data.data
    },
  })

  const eliminar = useMutation({
    mutationFn: (id) => client.delete(`/api/v1/admin/ligas/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'ligas'] }),
  })

  if (isLoading) return <p className="font-body text-texto p-4">Cargando ligas...</p>
  if (error) return <p className="font-body text-red-500 p-4">Error al cargar ligas.</p>

  return (
    <div>
      <h2 className="font-display text-xl text-texto mb-4">Ligas</h2>
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-x-auto">
        <table className="w-full text-left">
          <thead>
            <tr className="border-b border-borde/30">
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Nombre</th>
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Código</th>
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Tipo</th>
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Miembros</th>
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Creador</th>
              <th className="px-4 py-2" />
            </tr>
          </thead>
          <tbody>
            {ligas.map((liga) => (
              <tr key={liga.id} className="border-b border-borde/10 last:border-0">
                <td className="font-body text-sm text-texto px-4 py-2">{liga.nombre}</td>
                <td className="font-marcador text-sm text-acento px-4 py-2 tracking-widest">{liga.codigo_acceso}</td>
                <td className="font-body text-sm text-texto px-4 py-2">{liga.tipo}</td>
                <td className="font-body text-sm text-texto px-4 py-2">{liga.total_miembros}</td>
                <td className="font-body text-sm text-texto px-4 py-2">{liga.creador}</td>
                <td className="px-4 py-2 text-right">
                  <button
                    onClick={() => { if (confirm('¿Eliminar esta liga?')) eliminar.mutate(liga.id) }}
                    className="font-body text-xs text-red-500 hover:underline"
                  >
                    Eliminar
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

export default AdminLigasPage