import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import AdminResourceTable from '../components/AdminResourceTable'
import CartaJuego from '../components/CartaJuego'

const COLUMNAS = [
  { key: 'nombre', label: 'Nombre' },
  { key: 'categoria_nombre', label: 'Categoría' },
  { key: 'rareza', label: 'Rareza' },
  { key: 'codigo_efecto', label: 'Código efecto' },
  { key: 'activa', label: 'Activa' },
]

const RAREZAS = [
  { value: 'Comun', label: 'Común' },
  { value: 'PocoComun', label: 'Poco común' },
  { value: 'Rara', label: 'Rara' },
  { value: 'Legendaria', label: 'Legendaria' },
]

function VistaPrevia({ tipos }) {
  const [mostrar, setMostrar] = useState(true)

  if (!tipos || tipos.length === 0) return null

  return (
    <div className="mb-5 bg-borde/5 border border-borde/20 rounded-lg p-4">
      <button
        onClick={() => setMostrar(!mostrar)}
        className="font-body text-sm font-semibold text-texto flex items-center gap-2"
      >
        🃏 Vista previa de las cartas {mostrar ? '▲' : '▼'}
      </button>

      {mostrar && (
        <div className="flex flex-wrap gap-4 mt-4">
          {tipos.map((carta) => (
            <CartaJuego key={carta.id} carta={carta} categoriaNombre={carta.categoria_nombre} tamano="mini" />
          ))}
        </div>
      )}
    </div>
  )
}

function AdminTiposCartaPage() {
  const { data: categorias } = useQuery({
    queryKey: ['admin', 'categorias-carta'],
    queryFn: async () => (await client.get('/api/v1/admin/categorias-carta')).data.data,
  })

  const { data: tipos } = useQuery({
    queryKey: ['admin', 'tipos-carta'],
    queryFn: async () => (await client.get('/api/v1/admin/tipos-carta')).data.data,
  })

  const campos = [
    {
      name: 'id_categoria', label: 'Categoría', type: 'select',
      options: (categorias ?? []).map((c) => ({ value: c.id, label: c.nombre })),
    },
    { name: 'rareza', label: 'Rareza', type: 'select', options: RAREZAS },
    { name: 'nombre', label: 'Nombre' },
    { name: 'descripcion', label: 'Descripción' },
    { name: 'imagen_url', label: 'Imagen (ilustración central)', type: 'imagen', carpeta: 'cartas' },
    { name: 'insignia_corta', label: 'Insignia corta (ej. +1, x2 — opcional)' },
    { name: 'codigo_efecto', label: 'Código efecto (identificador técnico)' },
    { name: 'activa', label: 'Activa', type: 'boolean' },
  ]

  return (
    <div>
      <VistaPrevia tipos={tipos} />
      <AdminResourceTable resource="tipos-carta" title="Tipos de Carta" columns={COLUMNAS} fields={campos} />
    </div>
  )
}

export default AdminTiposCartaPage
