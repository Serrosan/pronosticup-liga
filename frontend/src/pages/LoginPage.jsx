import { Navigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import LoginForm from '../components/LoginForm'

function LoginPage() {
  const { usuario } = useAuth()

  if (usuario) return <Navigate to="/ligas/1/jornadas/1" replace />

  return (
    <div className="min-h-screen bg-fondo flex items-center justify-center">
      <LoginForm />
    </div>
  )
}

export default LoginPage