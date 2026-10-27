import { BrowserRouter, Routes, Route, Navigate, Outlet } from 'react-router-dom'
import { AuthProvider, useAuth } from './context/AuthContext'
import LoginPage from './pages/LoginPage'
import NavBar from './components/NavBar'
import AdminLayout from './components/AdminLayout'
import NotificacionModal from './components/NotificacionModal'
import MarcaEntorno from './components/MarcaEntorno'
import BandaVerComo from './components/BandaVerComo'
import ErrorBoundary from './components/ErrorBoundary'
import AvisoSinConexion from './components/AvisoSinConexion'
import AvisoVersionNueva from './components/AvisoVersionNueva'
import AvisoSesionCaducada from './components/AvisoSesionCaducada'
import { ToastProvider } from './context/ToastContext'
import BotonVolverArriba from './components/BotonVolverArriba'
import RedirectJornadaActual from './pages/RedirectJornadaActual'
import { Suspense } from 'react'
import { lazyPagina, CargandoPagina } from './utils/cargaPaginas'

// Cada página se descarga la primera vez que se abre.
const RegisterPage = lazyPagina(() => import('./pages/RegisterPage'))
const ForgotPasswordPage = lazyPagina(() => import('./pages/ForgotPasswordPage'))
const ResetPasswordPage = lazyPagina(() => import('./pages/ResetPasswordPage'))
const HomePage = lazyPagina(() => import('./pages/HomePage'))
const OnboardingPage = lazyPagina(() => import('./pages/OnboardingPage'))
const JoinByLinkPage = lazyPagina(() => import('./pages/JoinByLinkPage'))
const DashboardPage = lazyPagina(() => import('./pages/DashboardPage'))
const MatchdayPage = lazyPagina(() => import('./pages/MatchdayPage'))
const MatchDetailPage = lazyPagina(() => import('./pages/MatchDetailPage'))
const StandingsPage = lazyPagina(() => import('./pages/StandingsPage'))
const UserPointsDetailPage = lazyPagina(() => import('./pages/UserPointsDetailPage'))
const MyPredictionsPage = lazyPagina(() => import('./pages/MyPredictionsPage'))
const ProfilePage = lazyPagina(() => import('./pages/ProfilePage'))
const TeamMatchesPage = lazyPagina(() => import('./pages/TeamMatchesPage'))
const LaLigaStandingsPage = lazyPagina(() => import('./pages/LaLigaStandingsPage'))
const CalendarPage = lazyPagina(() => import('./pages/CalendarPage'))
const ChatPage = lazyPagina(() => import('./pages/ChatPage'))
const AdminUsersPage = lazyPagina(() => import('./pages/AdminUsersPage'))
const AdminLigasPage = lazyPagina(() => import('./pages/AdminLigasPage'))
const AdminCalendarioPage = lazyPagina(() => import('./pages/AdminCalendarioPage'))
const AdminEventosPartidoPage = lazyPagina(() => import('./pages/AdminEventosPartidoPage'))
const AdminEquiposPage = lazyPagina(() => import('./pages/AdminEquiposPage'))
const AdminJugadoresPage = lazyPagina(() => import('./pages/AdminJugadoresPage'))
const AdminEstadiosPage = lazyPagina(() => import('./pages/AdminEstadiosPage'))
const AdminArbitrosPage = lazyPagina(() => import('./pages/AdminArbitrosPage'))
const AdminCodigosCanjePage = lazyPagina(() => import('./pages/AdminCodigosCanjePage'))
const AdminAvisosScraperPage = lazyPagina(() => import('./pages/AdminAvisosScraperPage'))
const AdminTareasPage = lazyPagina(() => import('./pages/AdminTareasPage'))
const AdminCambiosPlantillaPage = lazyPagina(() => import('./pages/AdminCambiosPlantillaPage'))
const AdminCierreJornadaPage = lazyPagina(() => import('./pages/AdminCierreJornadaPage'))
const AdminRastroCartasPage = lazyPagina(() => import('./pages/AdminRastroCartasPage'))
const AdminSistemaPage = lazyPagina(() => import('./pages/AdminSistemaPage'))
const AdminCalidadDatosPage = lazyPagina(() => import('./pages/AdminCalidadDatosPage'))
const AdminCanjesCodigoPage = lazyPagina(() => import('./pages/AdminCanjesCodigoPage'))
const AdminTrofeosPage = lazyPagina(() => import('./pages/AdminTrofeosPage'))
const AdminEventosCalendarioPage = lazyPagina(() => import('./pages/AdminEventosCalendarioPage'))
const AdminNovedadesPage = lazyPagina(() => import('./pages/AdminNovedadesPage'))
const AdminResourceDetailPage = lazyPagina(() => import('./pages/AdminResourceDetailPage'))
const AdminVerComoPage = lazyPagina(() => import('./pages/AdminVerComoPage'))
const AdminCopiaLocalPage = lazyPagina(() => import('./pages/AdminCopiaLocalPage'))
const AdminAvisosPage = lazyPagina(() => import('./pages/AdminAvisosPage'))
const AdminComparadorPlantillasPage = lazyPagina(() => import('./pages/AdminComparadorPlantillasPage'))
const AdminEntrenadoresPage = lazyPagina(() => import('./pages/AdminEntrenadoresPage'))
const NotFoundPage = lazyPagina(() => import('./pages/NotFoundPage'))
const PrivacyPage = lazyPagina(() => import('./pages/PrivacyPage'))
const AdminRegistroActividadPage = lazyPagina(() => import('./pages/AdminRegistroActividadPage'))
const StadiumsPage = lazyPagina(() => import('./pages/StadiumsPage'))
const PlayerDetailPage = lazyPagina(() => import('./pages/PlayerDetailPage'))
const AdminConfiguracionPuntosPage = lazyPagina(() => import('./pages/AdminConfiguracionPuntosPage'))
const SeleccionGoleadoresPage = lazyPagina(() => import('./pages/SeleccionGoleadoresPage'))
const QuinielaPage = lazyPagina(() => import('./pages/QuinielaPage'))
const AdminQuinielasPage = lazyPagina(() => import('./pages/AdminQuinielasPage'))
const VerifyEmailPage = lazyPagina(() => import('./pages/VerifyEmailPage'))
const AdminImportarHistoricoPage = lazyPagina(() => import('./pages/AdminImportarHistoricoPage'))
const PuntuacionesPage = lazyPagina(() => import('./pages/PuntuacionesPage'))
const GuiaPage = lazyPagina(() => import('./pages/GuiaPage'))
const EstadisticasLigaPage = lazyPagina(() => import('./pages/EstadisticasLigaPage'))
const EstadisticasCartasPage = lazyPagina(() => import('./pages/EstadisticasCartasPage'))
const LogrosPage = lazyPagina(() => import('./pages/LogrosPage'))
const AdminCategoriasCartaPage = lazyPagina(() => import('./pages/AdminCategoriasCartaPage'))
const AdminTiposCartaPage = lazyPagina(() => import('./pages/AdminTiposCartaPage'))
const AdminConfiguracionCartasPage = lazyPagina(() => import('./pages/AdminConfiguracionCartasPage'))
const AdminGestionCartasUsuariosPage = lazyPagina(() => import('./pages/AdminGestionCartasUsuariosPage'))
const MisCartasPage = lazyPagina(() => import('./pages/MisCartasPage'))
const CatalogoCartasPage = lazyPagina(() => import('./pages/CatalogoCartasPage'))
const LaJornadaPage = lazyPagina(() => import('./pages/LaJornadaPage'))

function RutaProtegida() {
  const { usuario, cargando } = useAuth()
  if (cargando) return <p className="font-body text-texto p-4">Cargando...</p>
  if (!usuario) return <Navigate to="/login" replace />
  return (
    <div className="min-h-screen bg-lienzo">
      <NavBar />
      <NotificacionModal />
      <Suspense fallback={<CargandoPagina />}>
        <Outlet />
      </Suspense>
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
        <Suspense fallback={<CargandoPagina />}>
          <Outlet />
        </Suspense>
      </AdminLayout>
    </div>
  )
}

function App() {
  return (
    <ErrorBoundary>
      <MarcaEntorno />
      <BandaVerComo />
      <ToastProvider>
        <AuthProvider>
          <AvisoSinConexion />
          <AvisoVersionNueva />
          <BrowserRouter>
            <AvisoSesionCaducada />
            <BotonVolverArriba />
            <Suspense fallback={<CargandoPagina />}>
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
                <Route path="/jornadas" element={<RedirectJornadaActual />} />
                <Route path="/jornadas/:jornada" element={<MatchdayPage />} />
                <Route path="/partidos/:id" element={<MatchDetailPage />} />
                <Route path="/clasificacion" element={<StandingsPage />} />
                <Route path="/clasificacion/usuarios/:idUsuario" element={<UserPointsDetailPage />} />
                <Route path="/clasificacion-liga" element={<LaLigaStandingsPage />} />
                <Route path="/pronosticos" element={<MyPredictionsPage />} />
                <Route path="/calendario" element={<CalendarPage />} />
                <Route path="/puntuaciones" element={<PuntuacionesPage />} />
                <Route path="/mis-cartas" element={<MisCartasPage />} />
                <Route path="/estadisticas-liga" element={<EstadisticasLigaPage />} />
                <Route path="/estadisticas-cartas" element={<EstadisticasCartasPage />} />
                <Route path="/verificar-email/:id/:hash" element={<VerifyEmailPage />} />
                <Route path="/jugadores/:idJugador" element={<PlayerDetailPage />} />
                <Route path="/jornadas/:jornada/goleadores" element={<SeleccionGoleadoresPage />} />
                <Route path="/jornadas/:jornada/liga" element={<LaJornadaPage />} />
                <Route path="/chat" element={<ChatPage />} />
                <Route path="/logros" element={<LogrosPage />} />
                <Route path="/catalogo-cartas" element={<CatalogoCartasPage />} />
                <Route path="/quinielas/:tipo" element={<QuinielaPage />} />
                <Route path="/estadios" element={<StadiumsPage />} />
                <Route path="/perfil" element={<ProfilePage />} />
                <Route path="/guia" element={<GuiaPage />} />
                <Route path="/privacidad" element={<PrivacyPage />} />
                <Route path="/equipos/:idEquipo" element={<TeamMatchesPage />} />
              </Route>

              <Route path="/admin" element={<RutaAdmin />}>
                <Route index element={<Navigate to="usuarios" replace />} />
                <Route path="usuarios" element={<AdminUsersPage />} />
                <Route path="ligas" element={<AdminLigasPage />} />
                <Route path="configuracion-puntos" element={<AdminConfiguracionPuntosPage />} />
                <Route path="calendario" element={<AdminCalendarioPage />} />
                <Route path="eventos-partido" element={<AdminEventosPartidoPage />} />
                <Route path="entrenadores" element={<AdminEntrenadoresPage />} />
                <Route path="equipos" element={<AdminEquiposPage />} />
                <Route path="jugadores" element={<AdminJugadoresPage />} />
                <Route path="registro-actividad" element={<AdminRegistroActividadPage />} />
                <Route path="estadios" element={<AdminEstadiosPage />} />
                <Route path="quinielas" element={<AdminQuinielasPage />} />
                <Route path="arbitros" element={<AdminArbitrosPage />} />
                <Route path="trofeos" element={<AdminTrofeosPage />} />
                <Route path="eventos-calendario" element={<AdminEventosCalendarioPage />} />
                <Route path="importar-historico" element={<AdminImportarHistoricoPage />} />
                <Route path="/admin/configuracion-cartas" element={<AdminConfiguracionCartasPage />} />
                <Route path="/admin/gestion-cartas-usuarios" element={<AdminGestionCartasUsuariosPage />} />
                <Route path="novedades" element={<AdminNovedadesPage />} />
                <Route path=":resource/detalle/:id" element={<AdminResourceDetailPage />} />
                <Route path="/admin/categorias-carta" element={<AdminCategoriasCartaPage />} />
                <Route path="/admin/tipos-carta" element={<AdminTiposCartaPage />} />
                <Route path="/admin/codigos-canje" element={<AdminCodigosCanjePage />} />
                <Route path="/admin/canjes-codigo" element={<AdminCanjesCodigoPage />} />
                <Route path="/admin/avisos-scraper" element={<AdminAvisosScraperPage />} />
                <Route path="/admin/tareas" element={<AdminTareasPage />} />
                <Route path="/admin/cambios-plantilla" element={<AdminCambiosPlantillaPage />} />
                <Route path="/admin/cierre-jornada" element={<AdminCierreJornadaPage />} />
                <Route path="/admin/rastro-cartas" element={<AdminRastroCartasPage />} />
                <Route path="/admin/sistema" element={<AdminSistemaPage />} />
                <Route path="/admin/calidad-datos" element={<AdminCalidadDatosPage />} />
                <Route path="/admin/ver-como" element={<AdminVerComoPage />} />
                <Route path="/admin/copia-local" element={<AdminCopiaLocalPage />} />
                <Route path="/admin/avisos" element={<AdminAvisosPage />} />
                <Route path="/admin/comparador-plantillas" element={<AdminComparadorPlantillasPage />} />
              </Route>
            <Route path="*" element={<NotFoundPage />} />
            </Routes>
            </Suspense>
          </BrowserRouter>
        </AuthProvider>
      </ToastProvider>
    </ErrorBoundary>
  )
}

export default App
