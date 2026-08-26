import AdminResourceTable from '../components/AdminResourceTable'

const columnas = [
  { key: 'nombre', label: 'Nombre' },
  { key: 'apellidos', label: 'Apellidos' },
  { key: 'posicion', label: 'Posición' },
  { key: 'nacionalidad', label: 'Nacionalidad' },
]

const campos = [
  { name: 'nombre', label: 'Nombre' },
  { name: 'apellidos', label: 'Apellidos' },
  { name: 'nombre_camiseta', label: 'Nombre en camiseta' },
  { name: 'posicion', label: 'Posición' },
  { name: 'nacionalidad', label: 'Nacionalidad' },
  { name: 'fecha_nacimiento', label: 'Fecha de nacimiento', type: 'date' },
]

function AdminJugadoresPage() {
  return <AdminResourceTable resource="jugadores" title="Jugadores" columns={columnas} fields={campos} />
}

export default AdminJugadoresPage