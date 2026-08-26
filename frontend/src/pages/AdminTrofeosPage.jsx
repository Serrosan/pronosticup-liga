import AdminResourceTable from '../components/AdminResourceTable'

const columnas = [
  { key: 'nombre', label: 'Nombre' },
  { key: 'tipo', label: 'Tipo' },
  { key: 'ambito', label: 'Ámbito' },
]

const campos = [
  { name: 'nombre', label: 'Nombre' },
  { name: 'tipo', label: 'Tipo' },
  { name: 'ambito', label: 'Ámbito' },
]

function AdminTrofeosPage() {
  return <AdminResourceTable resource="trofeos" title="Trofeos" columns={columnas} fields={campos} />
}

export default AdminTrofeosPage