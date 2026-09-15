import AdminResourceTable from '../components/AdminResourceTable'

const COLUMNAS = [
  { key: 'nombre', label: 'Nombre' },
  { key: 'tipos_carta_count', label: 'Tipos de carta' },
  { key: 'activa', label: 'Activa' },
]

const CAMPOS = [
  { name: 'nombre', label: 'Nombre' },
]

function AdminCategoriasCartaPage() {
  return <AdminResourceTable resource="categorias-carta" title="Categorías de Carta" columns={COLUMNAS} fields={CAMPOS} />
}

export default AdminCategoriasCartaPage