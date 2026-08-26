import { Navigate, Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import RegisterForm from '../components/RegisterForm'
import AuthLayout from '../components/AuthLayout'

function RegisterPage() {
  const { usuario } = useAuth()

  if (usuario) return <Navigate to="/ligas/1/jornadas/1" replace />

  return (
    <AuthLayout>
      <RegisterForm />
      <p className="font-body text-sm text-borde text-center mt-6">
        ¿Ya tienes cuenta?{' '}
        <Link to="/login" className="text-acento hover:underline font-medium">Inicia sesión</Link>
      </p>
    </AuthLayout>
  )
}

export default RegisterPage