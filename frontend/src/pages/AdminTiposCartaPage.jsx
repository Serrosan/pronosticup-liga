import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import AdminResourceTable from '../components/AdminResourceTable'

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

function AdminTiposCartaPage() {
  const { data: categorias } = useQuery({
    queryKey: ['admin', 'categorias-carta'],
    queryFn: async () => (await client.get('/api/v1/admin/categorias-carta')).data.data,
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
  ]

  return <AdminResourceTable resource="tipos-carta" title="Tipos de Carta" columns={COLUMNAS} fields={campos} />
}

export default AdminTiposCartaPage