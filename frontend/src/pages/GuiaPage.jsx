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

function EjemploCarta({ rareza, children }) {
  return (
    <div className="flex items-start gap-2.5 bg-borde/5 rounded-lg px-3.5 py-2.5">
      <span className="font-body text-[9px] uppercase tracking-widest text-borde bg-borde/10 rounded-full px-2 py-1 shrink-0 mt-0.5">
        {rareza}
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

      <Seccion icono="🃏" titulo="Cartas: Jugadas y Faltas">
        <p className="font-body text-sm text-texto">
          Si tu liga tiene las Cartas activadas, cada semana recibes cartas nuevas al azar, repartidas entre 2 tipos muy distintos:
          <strong> Jugadas</strong> (te benefician a ti) y <strong>Faltas</strong> (afectan a un rival que elijas). Cada carta tiene una
          rareza — Común, Poco Común, Rara o Legendaria — y cuanto más rara, más fuerte suele ser su efecto.
        </p>

        <p className="font-body text-sm text-texto mt-1">
          Las cartas te llegan en sobres cerrados: ábrelos desde <strong>Mis Cartas</strong> para revelar qué te ha tocado.
        </p>

        <p className="font-body text-xs uppercase tracking-widest text-borde mt-2">⚡ Jugadas — ejemplos reales</p>
        <div className="flex flex-col gap-2">
          <EjemploCarta rareza="Común → Legendaria">
            <strong>Chute Extra</strong>: suma puntos extra en un partido que elijas. Empieza en +1 (Común) y llega hasta +5
            (Legendaria) — la misma idea, pero más fuerte cuanto más rara te toque.
          </EjemploCarta>
          <EjemploCarta rareza="Común → Legendaria">
            <strong>Palomitas</strong>: puntos extra si tu pronóstico de signo (local/empate/visitante) coincide con el de la
            mayoría de tu liga en ese partido. También escala de +1 a +5 según la rareza.
          </EjemploCarta>
          <EjemploCarta rareza="Poco Común">
            <strong>Doblete</strong>: elige un partido de antemano — si aciertas algo en él, duplicas los puntos que ibas a sacar.
          </EjemploCarta>
          <EjemploCarta rareza="Rara">
            <strong>Pleno Garantizado</strong>: un partido a tu elección cuenta como acertado con resultado exacto, pase lo que pase.
          </EjemploCarta>
          <EjemploCarta rareza="Legendaria">
            <strong>Crack</strong>: +4 puntos extra si aciertas el resultado exacto de 2 partidos cualquiera de la jornada — sin
            tener que elegirlos antes.
          </EjemploCarta>
          <EjemploCarta rareza="Poco Común → Legendaria">
            <strong>Amuleto</strong>: te protege de fallar un partido. Cuanto más rara, menos tienes que hacer tú: en Poco Común
            eliges el partido de antemano; en Rara y Legendaria te protege automáticamente, sin elegir nada (y en Legendaria,
            hasta 2 partidos a la vez).
          </EjemploCarta>
        </div>

        <p className="font-body text-xs uppercase tracking-widest text-borde mt-3">🎯 Faltas — ejemplos reales</p>
        <div className="flex flex-col gap-2">
          <EjemploCarta rareza="Común → Legendaria">
            <strong>Todo queda en casa</strong>: obligas al rival a meter varias victorias locales entre sus pronósticos de la
            jornada siguiente — de 2 (Común) hasta 5 (Legendaria).
          </EjemploCarta>
          <EjemploCarta rareza="Común → Legendaria">
            <strong>Resultado Gafas</strong>: obligas al rival a meter 1 o varios empates entre sus pronósticos — el número sube
            con la rareza, igual que arriba.
          </EjemploCarta>
          <EjemploCarta rareza="Poco Común">
            <strong>Escudo</strong>: te proteges a ti mismo de cualquier Falta que te jueguen esta semana.
          </EjemploCarta>
          <EjemploCarta rareza="Poco Común">
            <strong>Sin Comodines</strong>: el rival no podrá jugar Amuleto ni Pleno Garantizado la jornada siguiente.
          </EjemploCarta>
          <EjemploCarta rareza="Rara → Legendaria">
            <strong>Expulsión</strong>: el rival no puede jugar cartas la jornada siguiente — en Rara, solo Faltas; en Legendaria,
            ninguna carta en absoluto.
          </EjemploCarta>
          <EjemploCarta rareza="Común">
            <strong>Amigo Invisible</strong>: le mandas un mensaje de burla anónimo — no sabrá que fuiste tú.
          </EjemploCarta>
        </div>

        <div className="bg-borde/5 rounded-lg px-4 py-3 mt-2 flex flex-col gap-1.5">
          <p className="font-body text-xs text-borde">
            <strong>Las Jugadas</strong> se juegan antes de que empiece la jornada en la que quieres que cuenten — en cuanto
            arranca el primer partido, ya no se pueden jugar más Jugadas sobre ella.
          </p>
          <p className="font-body text-xs text-borde">
            <strong>Las Faltas</strong> se juegan contra un rival de tu liga en una jornada, pero su efecto cae sobre la jornada
            <strong> siguiente</strong> a la que estás jugando ahora.
          </p>
          <p className="font-body text-xs text-borde">
            <strong>Límites:</strong> como mucho 1 Falta y 2 Jugadas por jornada, y tu mano de cartas tiene un tope — descarta
            las que no te interesen para dejar sitio a las nuevas.
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