import AdminResourceTable from '../components/AdminResourceTable'

const columnas = [
  { key: 'nombre', label: 'Nombre' },
  { key: 'nombre_corto', label: 'Corto' },
  { key: 'siglas', label: 'Siglas' },
  { key: 'ciudad', label: 'Ciudad' },
]

const campos = [
  { name: 'nombre', label: 'Nombre' },
  { name: 'nombre_corto', label: 'Nombre corto' },
  { name: 'siglas', label: 'Siglas' },
  { name: 'ciudad', label: 'Ciudad' },
  { name: 'escudo_url', label: 'URL del escudo' },
  { name: 'color_primario', label: 'Color primario' },
  { name: 'color_secundario', label: 'Color secundario' },
]

function AdminEquiposPage() {
  return <AdminResourceTable resource="equipos" title="Equipos" columns={columnas} fields={campos} />
}

export default AdminEquiposPage