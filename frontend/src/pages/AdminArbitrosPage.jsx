import AdminResourceTable from '../components/AdminResourceTable'

const columnas = [
  { key: 'nombre', label: 'Nombre' },
  { key: 'apellidos', label: 'Apellidos' },
  { key: 'comunidad_autonoma', label: 'Comunidad' },
]

const campos = [
  { name: 'nombre', label: 'Nombre' },
  { name: 'apellidos', label: 'Apellidos' },
  { name: 'comunidad_autonoma', label: 'Comunidad autónoma' },
]

function AdminArbitrosPage() {
  return <AdminResourceTable resource="arbitros" title="Árbitros" columns={columnas} fields={campos} />
}

export default AdminArbitrosPage