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
import MatchDetailPage from './pages/MatchDetailPage'
import StandingsPage from './pages/StandingsPage'
import UserPointsDetailPage from './pages/UserPointsDetailPage'
import MyPredictionsPage from './pages/MyPredictionsPage'
import ProfilePage from './pages/ProfilePage'
import TeamMatchesPage from './pages/TeamMatchesPage'
import LaLigaStandingsPage from './pages/LaLigaStandingsPage'
import CalendarPage from './pages/CalendarPage'
import ChatPage from './pages/ChatPage'
import AdminUsersPage from './pages/AdminUsersPage'
import AdminLigasPage from './pages/AdminLigasPage'
import AdminCalendarioPage from './pages/AdminCalendarioPage'
import AdminEventosPartidoPage from './pages/AdminEventosPartidoPage'
import AdminEquiposPage from './pages/AdminEquiposPage'
import AdminJugadoresPage from './pages/AdminJugadoresPage'
import AdminEstadiosPage from './pages/AdminEstadiosPage'
import AdminArbitrosPage from './pages/AdminArbitrosPage'
import AdminTrofeosPage from './pages/AdminTrofeosPage'
import AdminEventosCalendarioPage from './pages/AdminEventosCalendarioPage'
import AdminNovedadesPage from './pages/AdminNovedadesPage'
import AdminResourceDetailPage from './pages/AdminResourceDetailPage'
import NavBar from './components/NavBar'
import AdminLayout from './components/AdminLayout'
import NotificacionModal from './components/NotificacionModal'
import AdminEntrenadoresPage from './pages/AdminEntrenadoresPage'
import ErrorBoundary from './components/ErrorBoundary'
import NotFoundPage from './pages/NotFoundPage'
import AvisoSinConexion from './components/AvisoSinConexion'
import AvisoSesionCaducada from './components/AvisoSesionCaducada'
import { ToastProvider } from './context/ToastContext'
import PrivacyPage from './pages/PrivacyPage'
import AdminRegistroActividadPage from './pages/AdminRegistroActividadPage'
import StadiumsPage from './pages/StadiumsPage'

function RutaProtegida() {
  const { usuario, cargando } = useAuth()
  if (cargando) return <p className="font-body text-texto p-4">Cargando...</p>
  if (!usuario) return <Navigate to="/login" replace />

  return (
    <div className="min-h-screen bg-lienzo">
      <NavBar />
      <NotificacionModal />
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
    <ErrorBoundary>
      <ToastProvider>
        <AuthProvider>
          <AvisoSinConexion />
          <BrowserRouter>
            <AvisoSesionCaducada />
            <Routes>
              <Route path="/login" element={<LoginPage />} />
              <Route path="/register" element={<RegisterPage />} />
              <Route path="/forgot-password" element={<ForgotPasswordPage />} />
              <Route path="/reset-password" element={<ResetPasswordPage />} />
              <Route path="/unirse/:codigo" element={<JoinByLinkPage />} />

              <Route element={<RutaProtegida />}>
                <Route path="/" element={<HomePage />} />
                <Route path="/onboarding" element={<OnboardingPage />} />
                <Route path="/dashboard" element={<DashboardPage />} />
                <Route path="/jornadas/:jornada" element={<MatchdayPage />} />
                <Route path="/partidos/:id" element={<MatchDetailPage />} />
                <Route path="/clasificacion" element={<StandingsPage />} />
                <Route path="/clasificacion/usuarios/:idUsuario" element={<UserPointsDetailPage />} />
                <Route path="/clasificacion-liga" element={<LaLigaStandingsPage />} />
                <Route path="/pronosticos" element={<MyPredictionsPage />} />
                <Route path="/calendario" element={<CalendarPage />} />
                <Route path="/chat" element={<ChatPage />} />
                <Route path="/estadios" element={<StadiumsPage />} />
                <Route path="/perfil" element={<ProfilePage />} />
                <Route path="/privacidad" element={<PrivacyPage />} />
                <Route path="/equipos/:idEquipo" element={<TeamMatchesPage />} />
              </Route>

              <Route path="/admin" element={<RutaAdmin />}>
                <Route index element={<Navigate to="usuarios" replace />} />
                <Route path="usuarios" element={<AdminUsersPage />} />
                <Route path="ligas" element={<AdminLigasPage />} />
                <Route path="calendario" element={<AdminCalendarioPage />} />
                <Route path="eventos-partido" element={<AdminEventosPartidoPage />} />
                <Route path="entrenadores" element={<AdminEntrenadoresPage />} />
                <Route path="equipos" element={<AdminEquiposPage />} />
                <Route path="jugadores" element={<AdminJugadoresPage />} />
                <Route path="registro-actividad" element={<AdminRegistroActividadPage />} />
                <Route path="estadios" element={<AdminEstadiosPage />} />
                <Route path="arbitros" element={<AdminArbitrosPage />} />
                <Route path="trofeos" element={<AdminTrofeosPage />} />
                <Route path="eventos-calendario" element={<AdminEventosCalendarioPage />} />
                <Route path="novedades" element={<AdminNovedadesPage />} />
                <Route path=":resource/detalle/:id" element={<AdminResourceDetailPage />} />
              </Route>

            <Route path="*" element={<NotFoundPage />} />
            </Routes>
          </BrowserRouter>
        </AuthProvider>
      </ToastProvider>
    </ErrorBoundary>
  )
}

export default App