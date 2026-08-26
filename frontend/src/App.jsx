import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { AuthProvider, useAuth } from './context/AuthContext'
import { useTheme } from './context/ThemeContext'
import FormularioLogin from './components/FormularioLogin'
import cliente from './api/cliente'

function TarjetaPartido({ partido }) {
  const [golesLocal, setGolesLocal] = useState('')
  const [golesVisitante, setGolesVisitante] = useState('')
  const queryClient = useQueryClient()

  const mutacion = useMutation({
    mutationFn: async ({ resultado1x2, golesLocal, golesVisitante }) => {
      const respuesta = await cliente.post('/api/v1/ligas/1/pronosticos', {
        id_partido: partido.id,
        resultado_1x2: resultado1x2,
        goles_local_predicho: Number(golesLocal),
        goles_visitante_predicho: Number(golesVisitante),
      })
      return respuesta.data.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['partidos-jornada-1'] })
    },
  })

  function pronosticar(resultado1x2) {
    if (golesLocal === '' || golesVisitante === '') {
      alert('Rellena primero el marcador que crees que habrá.')
      return
    }
    mutacion.mutate({ resultado1x2, golesLocal, golesVisitante })
  }

  return (
    <div className="bg-fondo border border-borde/40 rounded-lg p-3 sm:p-4 w-full">
      <span className="font-body text-xs uppercase tracking-wider text-borde">
        {partido.estado}
      </span>

      <div className="flex items-center justify-between gap-2 mt-2">
        <span className="font-body font-medium text-texto text-sm sm:text-base flex-1 text-right truncate">
          {partido.equipo_local.nombre}
        </span>
        <span className="font-marcador text-xl sm:text-2xl text-acento tabular-nums px-2 shrink-0">
          {partido.goles_casa ?? '–'} : {partido.goles_fuera ?? '–'}
        </span>
        <span className="font-body font-medium text-texto text-sm sm:text-base flex-1 truncate">
          {partido.equipo_visitante.nombre}
        </span>
      </div>

      {partido.estado === 'Programado' && (
        <div className="mt-4 pt-4 border-t border-borde/40">
          <div className="flex gap-2 justify-center">
            <input
              type="number"
              min="0"
              placeholder="0"
              value={golesLocal}
              onChange={(e) => setGolesLocal(e.target.value)}
              className="font-marcador w-14 text-center bg-borde/20 text-texto rounded border border-borde/40 py-1"
            />
            <span className="font-marcador text-borde self-center">-</span>
            <input
              type="number"
              min="0"
              placeholder="0"
              value={golesVisitante}
              onChange={(e) => setGolesVisitante(e.target.value)}
              className="font-marcador w-14 text-center bg-borde/20 text-texto rounded border border-borde/40 py-1"
            />
          </div>
          <div className="flex flex-col sm:flex-row gap-2 mt-3">
            <button
              onClick={() => pronosticar('Local')}
              disabled={mutacion.isPending}
              className="flex-1 bg-acento text-fondo font-body font-semibold text-sm rounded py-2 hover:brightness-110 disabled:opacity-50"
            >
              Local
            </button>
            <button
              onClick={() => pronosticar('Empate')}
              disabled={mutacion.isPending}
              className="flex-1 bg-borde text-fondo font-body font-semibold text-sm rounded py-2 hover:brightness-110 disabled:opacity-50"
            >
              Empate
            </button>
            <button
              onClick={() => pronosticar('Visitante')}
              disabled={mutacion.isPending}
              className="flex-1 bg-acento text-fondo font-body font-semibold text-sm rounded py-2 hover:brightness-110 disabled:opacity-50"
            >
              Visitante
            </button>
          </div>
          {mutacion.isSuccess && (
            <p className="font-body text-xs text-acento mt-2">✓ Pronóstico guardado</p>
          )}
          {mutacion.isError && (
            <p className="font-body text-xs text-red-500 mt-2">
              {mutacion.error.response?.data?.message ?? 'Algo falló'}
            </p>
          )}
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

  if (isLoading) return <p className="font-body text-texto p-4">Cargando partidos...</p>
  if (error) return <p className="font-body text-red-500 p-4">Error al cargar: {error.message}</p>

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 p-4 max-w-6xl mx-auto">
      {partidos.map((partido) => (
        <TarjetaPartido key={partido.id} partido={partido} />
      ))}
    </div>
  )
}

function BotonTema() {
  const { tema, alternarTema } = useTheme()

  return (
    <button
      onClick={alternarTema}
      className="font-body text-xs text-borde hover:text-texto border border-borde/40 rounded px-2 py-1"
    >
      {tema === 'oscuro' ? '☀️ Claro' : '🌙 Oscuro'}
    </button>
  )
}

function ContenidoApp() {
  const { usuario, logout } = useAuth()

  return (
    <div className="min-h-screen bg-fondo">
      <header className="flex flex-wrap items-center justify-between gap-3 px-4 py-4 border-b border-borde/40">
        <h1 className="font-display text-xl sm:text-2xl text-acento tracking-wide">
          PronostiCup Liga
        </h1>
        <div className="flex items-center gap-3">
          <BotonTema />
          {usuario && (
            <>
              <span className="font-body text-sm text-texto hidden sm:inline">
                {usuario.nombre}
              </span>
              <button
                onClick={logout}
                className="font-body text-xs text-borde hover:text-texto"
              >
                Salir
              </button>
            </>
          )}
        </div>
      </header>
      {usuario ? <ListaPartidos /> : <FormularioLogin />}
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