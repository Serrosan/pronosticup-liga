import { Link } from 'react-router-dom'

function PrivacyPage() {
  return (
    <div className="max-w-2xl mx-auto px-4 py-10">
      <Link to="/dashboard" className="font-body text-sm text-acento hover:underline mb-6 inline-block">← Volver</Link>

      <h1 className="font-display text-2xl text-texto mb-6">Privacidad y condiciones</h1>

      <div className="flex flex-col gap-6 font-body text-sm text-texto">
        <section>
          <h2 className="font-display text-base text-texto mb-2">Qué es PronostiCup Liga</h2>
          <p className="text-borde">
            Una app privada, sin ánimo de lucro, para pronosticar los partidos de LaLiga entre un grupo cerrado de amigos.
            No hay publicidad, no se venden datos, no hay dinero de por medio.
          </p>
        </section>

        <section>
          <h2 className="font-display text-base text-texto mb-2">Qué datos guardamos</h2>
          <ul className="text-borde list-disc pl-5 flex flex-col gap-1">
            <li>Tu nombre, email y avatar (si lo subes)</li>
            <li>Tus pronósticos y puntos, para poder llevar la clasificación</li>
            <li>Los mensajes, imágenes y notas de voz que envíes en el chat de tu liga</li>
          </ul>
        </section>

        <section>
          <h2 className="font-display text-base text-texto mb-2">Con quién se comparte</h2>
          <p className="text-borde">
            Con nadie fuera de tu propia liga. Tus compañeros de liga ven tu nombre, avatar, pronósticos y puntos —
            es parte del juego. No se comparte con terceros ni con fines comerciales.
          </p>
        </section>

        <section>
          <h2 className="font-display text-base text-texto mb-2">Cómo borrar tus datos</h2>
          <p className="text-borde">
            Puedes desactivar tu cuenta en cualquier momento desde tu perfil. Si quieres el borrado completo
            y definitivo de tus datos, contacta con el administrador de la app.
          </p>
        </section>

        <section>
          <h2 className="font-display text-base text-texto mb-2">Cookies</h2>
          <p className="text-borde">
            Solo usamos una cookie de sesión, estrictamente necesaria para mantenerte conectado. No usamos
            cookies de rastreo ni de publicidad.
          </p>
        </section>
      </div>
    </div>
  )
}

export default PrivacyPage