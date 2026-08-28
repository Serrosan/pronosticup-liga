import AdminResourceTable from '../components/AdminResourceTable'

const COLUMNAS = [
  { key: 'emoji', label: 'Emoji' },
  { key: 'titulo', label: 'Título' },
  { key: 'activa', label: 'Activa' },
]

const CAMPOS = [
  {
    name: 'emoji', label: 'Emoji', type: 'select',
    options: ['🆕', '⚡', '🎉', '🔧', '📊', '🏆', '⚽', '🔥', '📅', '💬'],
  },
  { name: 'titulo', label: 'Título' },
]

function AdminNovedadesPage() {
  return <AdminResourceTable resource="novedades" title="Novedades" columns={COLUMNAS} fields={CAMPOS} />
}

export default AdminNovedadesPage