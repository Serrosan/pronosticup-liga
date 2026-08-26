import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider, useAuth } from './context/AuthContext'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'
import ForgotPasswordPage from './pages/ForgotPasswordPage'
import ResetPasswordPage from './pages/ResetPasswordPage'
import MatchdayPage from './pages/MatchdayPage'
import StandingsPage from './pages/StandingsPage'
import AdminUsersPage from './pages/AdminUsersPage'
import AdminEquiposPage from './pages/AdminEquiposPage'
import AdminJugadoresPage from './pages/AdminJugadoresPage'
import AdminEstadiosPage from './pages/AdminEstadiosPage'
import AdminArbitrosPage from './pages/AdminArbitrosPage'
import AdminTrofeosPage from './pages/AdminTrofeosPage'
import NavBar from './components/NavBar'
import AdminLayout from './components/AdminLayout'
import HomePage from './pages/HomePage'
import OnboardingPage from './pages/OnboardingPage'
import JoinByLinkPage from './pages/JoinByLinkPage'
import AdminLigasPage from './pages/AdminLigasPage'
import DashboardPage from './pages/DashboardPage'

function RutaProtegida({ children }) {
  const { usuario, cargando } = useAuth()
  if (cargando) return <p className="font-body text-texto p-4">Cargando...</p>
  if (!usuario) return <Navigate to="/login" replace />
  return children
}

function RutaAdmin({ children }) {
  const { usuario, cargando } = useAuth()
  if (cargando) return <p className="font-body text-texto p-4">Cargando...</p>
  if (!usuario) return <Navigate to="/login" replace />
  if (!usuario.es_superadmin) return <Navigate to="/" replace />
  return children
}

function Layout({ children }) {
  return (
    <div className="min-h-screen bg-fondo">
      <NavBar />
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
          <Route path="/register" element={<RegisterPage />} />
          <Route path="/forgot-password" element={<ForgotPasswordPage />} />
          <Route path="/reset-password" element={<ResetPasswordPage />} />

          <Route path="/jornadas/:jornada" element={
            <RutaProtegida><Layout><MatchdayPage /></Layout></RutaProtegida>
          } />
          <Route path="/dashboard" element={
            <RutaProtegida><Layout><DashboardPage /></Layout></RutaProtegida>
          } />
          <Route path="clasificacion" element={
            <RutaProtegida><Layout><StandingsPage /></Layout></RutaProtegida>
          } />

          <Route path="/admin" element={<Navigate to="/admin/usuarios" replace />} />
          <Route path="/admin/usuarios" element={
            <RutaAdmin><Layout><AdminLayout><AdminUsersPage /></AdminLayout></Layout></RutaAdmin>
          } />
          <Route path="/admin/ligas" element={
            <RutaAdmin><Layout><AdminLayout><AdminLigasPage /></AdminLayout></Layout></RutaAdmin>
          } />
          <Route path="/admin/equipos" element={
            <RutaAdmin><Layout><AdminLayout><AdminEquiposPage /></AdminLayout></Layout></RutaAdmin>
          } />
          <Route path="/admin/jugadores" element={
            <RutaAdmin><Layout><AdminLayout><AdminJugadoresPage /></AdminLayout></Layout></RutaAdmin>
          } />
          <Route path="/admin/estadios" element={
            <RutaAdmin><Layout><AdminLayout><AdminEstadiosPage /></AdminLayout></Layout></RutaAdmin>
          } />
          <Route path="/admin/arbitros" element={
            <RutaAdmin><Layout><AdminLayout><AdminArbitrosPage /></AdminLayout></Layout></RutaAdmin>
          } />
          <Route path="/admin/trofeos" element={
            <RutaAdmin><Layout><AdminLayout><AdminTrofeosPage /></AdminLayout></Layout></RutaAdmin>
          } />
                    <Route path="/unirse/:codigo" element={<JoinByLinkPage />} />

          <Route path="/" element={<RutaProtegida><HomePage /></RutaProtegida>} />
          <Route path="/onboarding" element={
            <RutaProtegida><Layout><OnboardingPage /></Layout></RutaProtegida>
          } />

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}

export default App