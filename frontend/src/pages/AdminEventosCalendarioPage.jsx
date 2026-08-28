import AdminResourceTable from '../components/AdminResourceTable'

const COLUMNAS = [
  { key: 'titulo', label: 'Título' },
  { key: 'fecha_inicio', label: 'Desde' },
  { key: 'fecha_fin', label: 'Hasta' },
  { key: 'color', label: 'Color' },
]

const CAMPOS = [
  { name: 'titulo', label: 'Título' },
  { name: 'fecha_inicio', label: 'Fecha de inicio', type: 'date' },
  { name: 'fecha_fin', label: 'Fecha de fin', type: 'date' },
  { name: 'color', label: 'Color', type: 'color' },
]

function AdminEventosCalendarioPage() {
  return (
    <AdminResourceTable
      resource="eventos-calendario"
      title="Notas de calendario"
      columns={COLUMNAS}
      fields={CAMPOS}
    />
  )
}

export default AdminEventosCalendarioPage