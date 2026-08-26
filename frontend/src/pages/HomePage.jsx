import { Navigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

function HomePage() {
  const { usuario } = useAuth()

  if (usuario?.liga_activa) return <Navigate to="/dashboard" replace />

  return <Navigate to="/onboarding" replace />
}

export default HomePage