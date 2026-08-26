import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'

function AdminResourceTable({ resource, title, columns, fields }) {
  const [editando, setEditando] = useState(null)
  const queryClient = useQueryClient()

  const { data: items, isLoading, error } = useQuery({
    queryKey: ['admin', resource],
    queryFn: async () => {
      const respuesta = await client.get(`/api/v1/admin/${resource}`)
      return respuesta.data.data
    },
  })

  const guardar = useMutation({
    mutationFn: (datos) =>
      datos.id
        ? client.put(`/api/v1/admin/${resource}/${datos.id}`, datos)
        : client.post(`/api/v1/admin/${resource}`, datos),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', resource] })
      setEditando(null)
    },
  })

  const eliminar = useMutation({
    mutationFn: (id) => client.delete(`/api/v1/admin/${resource}/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', resource] }),
  })

  function handleSubmit(event) {
    event.preventDefault()
    const datos = Object.fromEntries(new FormData(event.target).entries())
    if (editando?.id) datos.id = editando.id
    guardar.mutate(datos)
  }

  if (isLoading) return <p className="font-body text-texto p-4">Cargando {title}...</p>
  if (error) return <p className="font-body text-red-500 p-4">Error al cargar {title}.</p>

  return (
    <div>
      <div className="flex items-center justify-between mb-4">
        <h2 className="font-display text-xl text-texto">{title}</h2>
        <button
          onClick={() => setEditando({})}
          className="font-body text-sm font-semibold bg-acento text-fondo rounded px-3 py-1.5 hover:brightness-110"
        >
          + Añadir
        </button>
      </div>

      {editando && (
        <form onSubmit={handleSubmit} className="bg-borde/10 border border-borde/30 rounded-lg p-4 mb-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {fields.map((field) => (
              <div key={field.name}>
                <label className="font-body text-xs text-borde block mb-1">{field.label}</label>
                <input
                  name={field.name}
                  type={field.type ?? 'text'}
                  defaultValue={editando[field.name] ?? ''}
                  className="w-full font-body bg-fondo text-texto rounded border border-borde/40 px-3 py-1.5 focus:outline-none focus:border-acento"
                />
              </div>
            ))}
          </div>
          <div className="flex gap-2 mt-3">
            <button
              type="submit"
              disabled={guardar.isPending}
              className="font-body text-sm font-semibold bg-acento text-fondo rounded px-3 py-1.5 hover:brightness-110 disabled:opacity-50"
            >
              Guardar
            </button>
            <button type="button" onClick={() => setEditando(null)} className="font-body text-sm text-borde hover:text-texto">
              Cancelar
            </button>
          </div>
        </form>
      )}

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-x-auto">
        <table className="w-full text-left">
          <thead>
            <tr className="border-b border-borde/30">
              {columns.map((col) => (
                <th key={col.key} className="font-body text-xs text-borde uppercase px-4 py-2">{col.label}</th>
              ))}
              <th className="px-4 py-2" />
            </tr>
          </thead>
          <tbody>
            {items.map((item) => (
              <tr key={item.id} className="border-b border-borde/10 last:border-0">
                {columns.map((col) => (
                  <td key={col.key} className="font-body text-sm text-texto px-4 py-2">{item[col.key]}</td>
                ))}
                <td className="px-4 py-2 text-right whitespace-nowrap">
                  <button onClick={() => setEditando(item)} className="font-body text-xs text-acento hover:underline mr-3">
                    Editar
                  </button>
                  <button
                    onClick={() => { if (confirm('¿Seguro que quieres eliminarlo?')) eliminar.mutate(item.id) }}
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

export default AdminResourceTable