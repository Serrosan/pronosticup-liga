import { Link } from 'react-router-dom'
import ForgotPasswordForm from '../components/ForgotPasswordForm'
import AuthLayout from '../components/AuthLayout'

function ForgotPasswordPage() {
  return (
    <AuthLayout>
      <ForgotPasswordForm />
      <Link to="/login" className="font-body text-sm text-acento hover:underline text-center block mt-6">
        Volver a iniciar sesión
      </Link>
    </AuthLayout>
  )
}

export default ForgotPasswordPage