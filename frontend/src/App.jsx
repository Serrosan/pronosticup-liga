import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AuthProvider, useAuth } from './context/AuthContext'
import FormularioLogin from './components/FormularioLogin'
import cliente from './api/cliente'

function TarjetaPartido({ equipoLocal, equipoVisitante, golesLocal, golesVisitante, estado }) {
  const [pronostico, setPronostico] = useState(null)

  return (
    <div style={{ border: '1px solid #ccc', borderRadius: '8px', padding: '16px', margin: '8px', maxWidth: '300px' }}>
      <p>{estado}</p>
      <p>
        <strong>{equipoLocal}</strong>
        {' '}
        {golesLocal ?? '-'} - {golesVisitante ?? '-'}
        {' '}
        <strong>{equipoVisitante}</strong>
      </p>

      {estado === 'Programado' && (
        <div>
          <button onClick={() => setPronostico('Local')}>
            {pronostico === 'Local' ? '✅ ' : ''}Local
          </button>
          <button onClick={() => setPronostico('Empate')}>
            {pronostico === 'Empate' ? '✅ ' : ''}Empate
          </button>
          <button onClick={() => setPronostico('Visitante')}>
            {pronostico === 'Visitante' ? '✅ ' : ''}Visitante
          </button>
        </div>
      )}
    </div>
  )
}

function ListaPartidos() {
  const { data: partidos, isLoading, error } = useQuery({
    queryKey: ['partidos-jornada-1'],
    queryFn: async () => {
      const respuesta = await cliente.get('/api/v1/ligas/1/jornadas/1/partidos')
      return respuesta.data.data
    },
  })

  if (isLoading) return <p>Cargando partidos...</p>
  if (error) return <p>Error al cargar: {error.message}</p>

  return (
    <div>
      {partidos.map((partido) => (
        <TarjetaPartido
          key={partido.id}
          equipoLocal={partido.equipo_local.nombre}
          equipoVisitante={partido.equipo_visitante.nombre}
          golesLocal={partido.goles_casa}
          golesVisitante={partido.goles_fuera}
          estado={partido.estado}
        />
      ))}
    </div>
  )
}

function ContenidoApp() {
  const { usuario, logout } = useAuth()

  return (
    <div>
      <h1>PronostiCup Liga</h1>
      {usuario ? (
        <div>
          <p>Hola, {usuario.nombre} <button onClick={logout}>Salir</button></p>
          <ListaPartidos />
        </div>
      ) : (
        <FormularioLogin />
      )}
    </div>
  )
}

function App() {
  return (
    <AuthProvider>
      <ContenidoApp />
    </AuthProvider>
  )
}

export default App