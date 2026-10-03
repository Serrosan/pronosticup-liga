import { useQuery } from '@tanstack/react-query'
import client from '../api/client'

function AdminCanjesCodigoPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['admin-canjes-codigo'],
    queryFn: async () => (await client.get('/api/v1/admin/canjes-codigo')).data.data,
  })

  if (isLoading) return <p className="font-body text-texto p-4">Cargando...</p>

  return (
    <div>
      <h2 className="font-display text-xl text-texto mb-4">Canjes de códigos</h2>
      <p className="font-body text-xs text-borde mb-3">
        Solo lectura — quién ha canjeado qué código y cuándo. Para crear o desactivar códigos, ve a "Códigos de canje".
      </p>

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-x-auto">
        <table className="w-full text-left">
          <thead>
            <tr className="border-b border-borde/30">
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Código</th>
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Nombre</th>
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Usuario</th>
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Fecha</th>
            </tr>
          </thead>
          <tbody>
            {data.map((c) => (
              <tr key={c.id} className="border-b border-borde/10 last:border-0 odd:bg-borde/5">
                <td className="font-marcador text-sm text-texto px-4 py-2">{c.codigo}</td>
                <td className="font-body text-sm text-texto px-4 py-2">{c.nombre_codigo}</td>
                <td className="font-body text-sm text-texto px-4 py-2">{c.usuario}</td>
                <td className="font-body text-sm text-borde px-4 py-2">{c.canjeado_en}</td>
              </tr>
            ))}
            {data.length === 0 && (
              <tr>
                <td colSpan={4} className="font-body text-sm text-borde text-center px-4 py-6">
                  Nadie ha canjeado nada todavía.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}

export default AdminCanjesCodigoPage