import { BrowserRouter, Routes, Route, Navigate, Outlet } from 'react-router-dom'
import { AuthProvider, useAuth } from './context/AuthContext'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'
import ForgotPasswordPage from './pages/ForgotPasswordPage'
import ResetPasswordPage from './pages/ResetPasswordPage'
import HomePage from './pages/HomePage'
import OnboardingPage from './pages/OnboardingPage'
import JoinByLinkPage from './pages/JoinByLinkPage'
import DashboardPage from './pages/DashboardPage'
import MatchdayPage from './pages/MatchdayPage'
import StandingsPage from './pages/StandingsPage'
import MyPredictionsPage from './pages/MyPredictionsPage'
import ProfilePage from './pages/ProfilePage'
import AdminUsersPage from './pages/AdminUsersPage'
import AdminLigasPage from './pages/AdminLigasPage'
import AdminEquiposPage from './pages/AdminEquiposPage'
import AdminJugadoresPage from './pages/AdminJugadoresPage'
import AdminEstadiosPage from './pages/AdminEstadiosPage'
import AdminArbitrosPage from './pages/AdminArbitrosPage'
import AdminTrofeosPage from './pages/AdminTrofeosPage'
import NavBar from './components/NavBar'
import AdminLayout from './components/AdminLayout'
import AdminCalendarioPage from './pages/AdminCalendarioPage'
import LaLigaStandingsPage from './pages/LaLigaStandingsPage'
import TeamMatchesPage from './pages/TeamMatchesPage'
import AdminEventosPartidoPage from './pages/AdminEventosPartidoPage'

function RutaProtegida() {
  const { usuario, cargando } = useAuth()
  if (cargando) return <p className="font-body text-texto p-4">Cargando...</p>
  if (!usuario) return <Navigate to="/login" replace />

  return (
    <div className="min-h-screen bg-lienzo">
      <NavBar />
      <Outlet />
    </div>
  )
}

function RutaAdmin() {
  const { usuario, cargando } = useAuth()
  if (cargando) return <p className="font-body text-texto p-4">Cargando...</p>
  if (!usuario) return <Navigate to="/login" replace />
  if (!usuario.es_superadmin) return <Navigate to="/" replace />

  return (
    <div className="min-h-screen bg-lienzo">
      <NavBar />
      <AdminLayout>
        <Outlet />
      </AdminLayout>
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
          <Route path="/unirse/:codigo" element={<JoinByLinkPage />} />

          {/* Todo lo que necesita sesión iniciada, comparte NavBar automáticamente */}
          <Route element={<RutaProtegida />}>
            <Route path="/" element={<HomePage />} />
            <Route path="/onboarding" element={<OnboardingPage />} />
            <Route path="/dashboard" element={<DashboardPage />} />
            <Route path="/jornadas/:jornada" element={<MatchdayPage />} />
            <Route path="/clasificacion-liga" element={<LaLigaStandingsPage />} />
            <Route path="/equipos/:idEquipo" element={<TeamMatchesPage />} />
            <Route path="/clasificacion" element={<StandingsPage />} />
            <Route path="/pronosticos" element={<MyPredictionsPage />} />
            <Route path="/perfil" element={<ProfilePage />} />
          </Route>

          {/* Todo lo de /admin/*, comparte NavBar + AdminLayout automáticamente */}
          <Route path="/admin" element={<RutaAdmin />}>
            <Route index element={<Navigate to="usuarios" replace />} />
            <Route path="usuarios" element={<AdminUsersPage />} />
            <Route path="ligas" element={<AdminLigasPage />} />
            <Route path="calendario" element={<AdminCalendarioPage />} />
            <Route path="eventos-partido" element={<AdminEventosPartidoPage />} />
            <Route path="equipos" element={<AdminEquiposPage />} />
            <Route path="jugadores" element={<AdminJugadoresPage />} />
            <Route path="estadios" element={<AdminEstadiosPage />} />
            <Route path="arbitros" element={<AdminArbitrosPage />} />
            <Route path="trofeos" element={<AdminTrofeosPage />} />
          </Route>

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}

export default App