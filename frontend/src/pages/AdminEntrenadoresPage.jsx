import AdminResourceTable from '../components/AdminResourceTable'

const COLUMNAS = [
  { key: 'nombre', label: 'Nombre' },
  { key: 'nacionalidad', label: 'Nacionalidad' },
  { key: 'equipo_actual', label: 'Equipo actual' },
]

const CAMPOS = [
  { name: 'nombre', label: 'Nombre' },
  { name: 'nacionalidad', label: 'Nacionalidad' },
]

function AdminEntrenadoresPage() {
  return <AdminResourceTable resource="entrenadores" title="Entrenadores" columns={COLUMNAS} fields={CAMPOS} />
}

export default AdminEntrenadoresPage