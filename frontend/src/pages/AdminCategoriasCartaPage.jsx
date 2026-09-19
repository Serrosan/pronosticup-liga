import AdminResourceTable from '../components/AdminResourceTable'

const COLUMNAS = [
  { key: 'nombre', label: 'Nombre' },
  { key: 'icono', label: 'Icono' },
  { key: 'tipos_carta_count', label: 'Tipos de carta' },
  { key: 'activa', label: 'Activa' },
]

const CAMPOS = [
  { name: 'nombre', label: 'Nombre' },
  { name: 'icono', label: 'Icono (emoji, ej. ⚔️ 🛡️ 🎭 — marca de agua de fondo)' },
  { name: 'activa', label: 'Activa', type: 'boolean' },
]

function AdminCategoriasCartaPage() {
  return <AdminResourceTable resource="categorias-carta" title="Categorías de Carta" columns={COLUMNAS} fields={CAMPOS} />
}

export default AdminCategoriasCartaPage