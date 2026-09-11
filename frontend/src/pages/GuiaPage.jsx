import TicketHeader from '../components/TicketHeader'
import useTitulo from '../hooks/useTitulo'

function Seccion({ icono, titulo, children }) {
  return (
    <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
      <div className="px-5 py-4 bg-borde/10 border-b border-borde/20 flex items-center gap-2">
        <span className="text-xl">{icono}</span>
        <h2 className="font-display text-lg text-texto">{titulo}</h2>
      </div>
      <div className="p-5 flex flex-col gap-3">{children}</div>
    </div>
  )
}

function Paso({ numero, children }) {
  return (
    <div className="flex gap-3">
      <span className="font-marcador text-sm font-bold text-acento bg-acento/10 rounded-full w-6 h-6 flex items-center justify-center shrink-0">
        {numero}
      </span>
      <p className="font-body text-sm text-texto">{children}</p>
    </div>
  )
}

function GuiaPage() {
  useTitulo('Guía rápida')

  return (
    <div className="max-w-3xl mx-auto px-4 py-8">
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
        <TicketHeader titulo="Guía rápida" />
        <div className="px-5 py-4">
          <p className="font-body text-sm text-borde">
            Todo lo que necesitas saber para moverte por la app, en 5 pasos rápidos.
          </p>
        </div>
      </div>

      <Seccion icono="⚽" titulo="Cómo pronosticar un partido">
        <Paso numero="1">Entra en <strong>Jornada</strong>, desde el menú de arriba — te lleva directo a la jornada en curso.</Paso>
        <Paso numero="2">Elige el marcador que crees que habrá, con los botones <strong>−</strong> y <strong>+</strong> de cada equipo.</Paso>
        <Paso numero="3">Pulsa <strong>Guardar pronóstico</strong>. Puedes cambiarlo cuantas veces quieras hasta que empiece cualquier partido de esa jornada.</Paso>
        <div className="bg-borde/5 rounded-lg px-4 py-3 mt-1">
          <p className="font-body text-xs text-borde">
            <strong>Importante:</strong> en cuanto arranca el primer partido de la jornada, se bloquean todos los pronósticos de esa jornada, aunque el tuyo esté sin hacer.
          </p>
        </div>
      </Seccion>

      <Seccion icono="🥅" titulo="Elegir tus 5 goleadores">
        <Paso numero="1">Desde la pantalla de la jornada, pulsa el botón de <strong>goleadores</strong> (arriba del todo).</Paso>
        <Paso numero="2">Busca y elige hasta 5 jugadores de cualquier equipo de LaLiga — puedes filtrar por equipo si te resulta más fácil.</Paso>
        <Paso numero="3">Por cada gol real que marquen esa jornada, sumas puntos. Puedes guardar aunque no tengas los 5 completos, y seguir completándolo más tarde.</Paso>
        <div className="bg-borde/5 rounded-lg px-4 py-3 mt-1">
          <p className="font-body text-xs text-borde">
            <strong>Importante:</strong> ninguno de los 5 puede repetirse en la jornada siguiente — toca variar cada semana.
          </p>
        </div>
      </Seccion>

      <Seccion icono="🏆" titulo="Consultar la clasificación">
        <Paso numero="1">En <strong>Clasificación</strong> ves la tabla completa de tu liga, ordenada por puntos totales.</Paso>
        <Paso numero="2">Arriba puedes elegir <strong>Total</strong> o cualquier jornada ya cerrada, para ver cómo iba la tabla en ese momento exacto.</Paso>
        <Paso numero="3">Pulsa sobre cualquier persona para ver su desglose de puntos partido a partido — solo se revela una vez la jornada esté cerrada.</Paso>
      </Seccion>

      <Seccion icono="💬" titulo="El chat de la liga">
        <Paso numero="1">Desde <strong>Chat</strong>, escribe mensajes, envía fotos, notas de voz o stickers con el icono 😄.</Paso>
        <Paso numero="2">Pulsa <strong>↩</strong> en cualquier mensaje para responder citándolo, o <strong>+</strong> para reaccionar con un emoji.</Paso>
        <Paso numero="3">Usa la lupa 🔍 de arriba para buscar algo que se dijo hace tiempo.</Paso>
      </Seccion>

      <Seccion icono="🔔" titulo="Avisos y notificaciones">
        <Paso numero="1">La campana 🔔 de arriba te avisa de todo dentro de la app: jornadas cerradas, recordatorios, etc.</Paso>
        <Paso numero="2">También recibes esos avisos por email. Si prefieres no recibir correos, puedes desactivarlos desde <strong>Mi perfil</strong>.</Paso>
      </Seccion>

      <p className="font-body text-xs text-borde text-center">
        ¿Sigues con dudas? Pregunta en el chat de tu liga — seguro que alguien te ayuda.
      </p>
    </div>
  )
}

export default GuiaPage
