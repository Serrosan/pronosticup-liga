import AdminResourceTable from '../components/AdminResourceTable'

const columnas = [
  { key: 'nombre', label: 'Nombre' },
  { key: 'apellidos', label: 'Apellidos' },
  { key: 'equipo_actual', label: 'Equipo' },
  { key: 'dorsal_actual', label: 'Dorsal' },
  { key: 'posicion', label: 'Posición' },
  { key: 'estado', label: 'Estado' },
]

const campos = [
  { name: 'nombre', label: 'Nombre' },
  { name: 'apellidos', label: 'Apellidos' },
  { name: 'posicion', label: 'Posición', type: 'select', options: ['Portero', 'Defensa', 'Centrocampista', 'Delantero'] },
]

const filtros = [
  { campo: 'estado', label: 'Estado', type: 'select', opciones: ['Activo', 'De baja'] },
  { campo: 'posicion', label: 'Posición', type: 'select' },
  { campo: 'equipo_actual', label: 'Equipo', type: 'select' },
]

function AdminJugadoresPage() {
  return (
    <AdminResourceTable
      resource="jugadores"
      title="Jugadores"
      columns={columnas}
      fields={campos}
      irADetalleTrasCrear
      filtros={filtros}
    />
  )
}

export default AdminJugadoresPage