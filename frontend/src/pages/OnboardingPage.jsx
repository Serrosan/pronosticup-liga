import CreateLeagueForm from '../components/CreateLeagueForm'
import JoinLeagueForm from '../components/JoinLeagueForm'

function OnboardingPage() {
  return (
    <div className="max-w-3xl mx-auto px-4 py-10">
      <h1 className="font-display text-2xl text-texto mb-2">¡Ya casi estás!</h1>
      <p className="font-body text-borde mb-8">Crea tu propia liga o únete a una con un código.</p>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div className="bg-fondo border border-borde/30 rounded-lg p-5">
          <h2 className="font-display text-lg text-texto mb-3">Crear una liga nueva</h2>
          <CreateLeagueForm />
        </div>
        <div className="bg-fondo border border-borde/30 rounded-lg p-5">
          <h2 className="font-display text-lg text-texto mb-3">Unirme con un código</h2>
          <JoinLeagueForm />
        </div>
      </div>
    </div>
  )
}

export default OnboardingPage