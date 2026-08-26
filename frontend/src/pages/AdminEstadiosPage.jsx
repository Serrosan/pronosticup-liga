import AdminResourceTable from '../components/AdminResourceTable'

const columnas = [
  { key: 'nombre', label: 'Nombre' },
  { key: 'ciudad', label: 'Ciudad' },
  { key: 'capacidad', label: 'Capacidad' },
]

const campos = [
  { name: 'nombre', label: 'Nombre' },
  { name: 'ciudad', label: 'Ciudad' },
  { name: 'capacidad', label: 'Capacidad', type: 'number' },
  { name: 'tamanio_campo', label: 'Tamaño del campo' },
]

function AdminEstadiosPage() {
  return <AdminResourceTable resource="estadios" title="Estadios" columns={columnas} fields={campos} />
}

export default AdminEstadiosPage