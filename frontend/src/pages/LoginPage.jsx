import { Navigate, Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import LoginForm from '../components/LoginForm'
import AuthLayout from '../components/AuthLayout'

function LoginPage() {
  const { usuario, cargando } = useAuth()
  if (cargando) return null
  if (usuario) return <Navigate to="/" replace />

  return (
    <AuthLayout>
      <LoginForm />
      <p className="font-body text-sm text-borde text-center mt-6">
        ¿No tienes cuenta?{' '}
        <Link to="/register" className="text-acento hover:underline font-medium">Créala aquí</Link>
      </p>
    </AuthLayout>
  )
}

export default LoginPage