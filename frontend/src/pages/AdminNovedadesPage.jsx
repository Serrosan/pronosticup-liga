import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import AdminResourceTable from '../components/AdminResourceTable'

const COLUMNAS = [
  { key: 'emoji', label: 'Emoji' },
  { key: 'titulo', label: 'Título' },
  { key: 'liga_nombre', label: 'Liga' },
  { key: 'activa', label: 'Activa' },
]

function AdminNovedadesPage() {
  const { data: ligas } = useQuery({
    queryKey: ['admin', 'ligas'],
    queryFn: async () => (await client.get('/api/v1/admin/ligas')).data.data,
  })

  const campos = [
    {
      name: 'id_liga',
      label: 'Para qué liga',
      type: 'select',
      placeholder: 'Global (todas las ligas)',
      options: (ligas ?? []).map((l) => ({ value: l.id, label: l.nombre })),
    },
    {
      name: 'emoji', label: 'Emoji', type: 'select',
      options: ['🆕', '⚡', '🎉', '🔧', '📊', '🏆', '⚽', '🔥', '📅', '💬'],
    },
    { name: 'titulo', label: 'Título' },
  ]

  return <AdminResourceTable resource="novedades" title="Novedades" columns={COLUMNAS} fields={campos} />
}

export default AdminNovedadesPage