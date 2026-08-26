import { BrowserRouter, Routes, Route, Navigate, Link, useParams } from 'react-router-dom'
import { AuthProvider, useAuth } from './context/AuthContext'
import { useTheme } from './context/ThemeContext'
import LoginPage from './pages/LoginPage'
import MatchdayPage from './pages/MatchdayPage'
import StandingsPage from './pages/StandingsPage'

function RutaProtegida({ children }) {
  const { usuario } = useAuth()
  if (!usuario) return <Navigate to="/login" replace />
  return children
}

function BotonTema() {
  const { tema, alternarTema } = useTheme()
  return (
    <button onClick={alternarTema} className="font-body text-xs text-borde hover:text-texto border border-borde/40 rounded px-2 py-1">
      {tema === 'oscuro' ? '☀️ Claro' : '🌙 Oscuro'}
    </button>
  )
}

function Cabecera() {
  const { usuario, logout } = useAuth()
  const { idLiga } = useParams()

  return (
    <header className="flex flex-wrap items-center justify-between gap-3 px-4 py-4 border-b border-borde/40">
      <h1 className="font-display text-xl sm:text-2xl text-acento tracking-wide">PronostiCup Liga</h1>
      <nav className="flex items-center gap-4 font-body text-sm text-texto">
        {idLiga && (
          <>
            <Link to={`/ligas/${idLiga}/jornadas/1`} className="hover:text-acento">Jornada</Link>
            <Link to={`/ligas/${idLiga}/clasificacion`} className="hover:text-acento">Clasificación</Link>
          </>
        )}
      </nav>
      <div className="flex items-center gap-3">
        <BotonTema />
        {usuario && (
          <>
            <span className="font-body text-sm text-texto hidden sm:inline">{usuario.nombre}</span>
            <button onClick={logout} className="font-body text-xs text-borde hover:text-texto">Salir</button>
          </>
        )}
      </div>
    </header>
  )
}

function Layout({ children }) {
  return (
    <div className="min-h-screen bg-fondo">
      <Cabecera />
      {children}
    </div>
  )
}

function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/ligas/:idLiga/jornadas/:jornada" element={
            <RutaProtegida><Layout><MatchdayPage /></Layout></RutaProtegida>
          } />
          <Route path="/ligas/:idLiga/clasificacion" element={
            <RutaProtegida><Layout><StandingsPage /></Layout></RutaProtegida>
          } />
          <Route path="*" element={<Navigate to="/ligas/1/jornadas/1" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}

export default App