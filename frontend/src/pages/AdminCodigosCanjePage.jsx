import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import AdminResourceTable from '../components/AdminResourceTable'

function AdminCodigosCanjePage() {
  // Reutiliza el catálogo público (ya filtra solo cartas activas) para el
  // desplegable de "qué carta exacta dar" — sin necesitar un endpoint propio.
  const { data: cartas } = useQuery({
    queryKey: ['catalogo-cartas'],
    queryFn: async () => (await client.get('/api/v1/catalogo-cartas')).data.data,
  })

  const opcionesCarta = [
    { value: '', label: '— Solo si el premio es "carta específica" —' },
    ...(cartas ?? []).map((c) => ({ value: c.id, label: `${c.nombre} (${c.rareza})` })),
  ]

  return (
    <AdminResourceTable
      resource="codigos-canje"
      title="Códigos de canje"
      columns={[
        { key: 'codigo', label: 'Código' },
        { key: 'nombre', label: 'Nombre' },
        { key: 'tipo_premio', label: 'Premio' },
        { key: 'carta', label: 'Carta' },
        { key: 'usos_maximos', label: 'Máx. usos' },
        { key: 'total_canjes', label: 'Canjeados' },
        { key: 'activo', label: 'Activo' },
      ]}
      fields={[
        { name: 'codigo', label: 'Código (lo que se teclea)' },
        { name: 'nombre', label: 'Nombre (solo para ti, no lo ve nadie más)' },
        {
          name: 'tipo_premio',
          label: 'Tipo de premio',
          type: 'select',
          options: [
            { value: 'tirada_aleatoria', label: 'Tirada aleatoria' },
            { value: 'carta_especifica', label: 'Carta específica' },
          ],
        },
        { name: 'id_tipo_carta', label: 'Carta exacta (si aplica)', type: 'select', options: opcionesCarta },
        { name: 'usos_maximos', label: 'Máx. usos (vacío = ilimitado)', type: 'number' },
        { name: 'activo', label: 'Activo', type: 'boolean' },
      ]}
    />
  )
}

export default AdminCodigosCanjePage