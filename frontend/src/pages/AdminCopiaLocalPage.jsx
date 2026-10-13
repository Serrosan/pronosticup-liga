import { useState } from 'react'
import client from '../api/client'

// Descarga un .sql de toda la base de datos para cargarlo en local con HeidiSQL.
// Por defecto sale sin datos de acceso reales (correos falsos y contraseña
// "password"), así en tu ordenador no queda nada sensible de nadie.

function Opcion({ marcada, onCambiar, titulo, detalle }) {
  return (
    <label className="flex items-start gap-3 px-4 py-3 cursor-pointer hover:bg-borde/5">
      <input type="checkbox" checked={marcada} onChange={(e) => onCambiar(e.target.checked)} className="mt-1 w-4 h-4 accent-[var(--color-acento)] shrink-0" />
      <span>
        <span className="font-body text-sm text-texto block">{titulo}</span>
        <span className="font-body text-xs text-borde block">{detalle}</span>
      </span>
    </label>
  )
}

function AdminCopiaLocalPage() {
  const [correosFalsos, setCorreosFalsos] = useState(true)
  const [passwordDePrueba, setPasswordDePrueba] = useState(true)
  const [estado, setEstado] = useState(null) // null | 'bajando' | {ok, texto}

  async function descargar() {
    setEstado('bajando')
    try {
      const respuesta = await client.post(
        '/api/v1/admin/copia-local',
        { correos_falsos: correosFalsos, password_de_prueba: passwordDePrueba },
        { responseType: 'blob' }
      )
      const nombre =
        respuesta.headers['content-disposition']?.match(/filename="?([^"]+)"?/)?.[1] ??
        `pronosticup-${new Date().toISOString().slice(0, 10)}.sql`
      const url = URL.createObjectURL(respuesta.data)
      const enlace = document.createElement('a')
      enlace.href = url
      enlace.download = nombre
      document.body.appendChild(enlace)
      enlace.click()
      enlace.remove()
      URL.revokeObjectURL(url)
      setEstado({ ok: true, texto: `Descargado: ${nombre} (${(respuesta.data.size / 1024 / 1024).toFixed(1)} MB)` })
    } catch (error) {
      let mensaje = 'No se pudo generar la copia.'
      try {
        mensaje = JSON.parse(await error.response?.data?.text())?.message ?? mensaje
      } catch {
        // la respuesta no era JSON: se queda el mensaje genérico
      }
      setEstado({ ok: false, texto: mensaje })
    }
  }

  const sinProteccion = !correosFalsos || !passwordDePrueba

  return (
    <div className="max-w-2xl">
      <h1 className="font-display text-2xl text-texto mb-1">Copia para local</h1>
      <p className="font-body text-sm text-borde mb-4">
        Descarga un <span className="font-mono text-texto">.sql</span> con toda la base de datos para cargarlo en tu ordenador con HeidiSQL.
        Solo lee: aquí no se cambia nada.
      </p>

      <div className="bg-fondo border border-borde/30 rounded-lg divide-y divide-borde/10 mb-4">
        <Opcion
          marcada={correosFalsos}
          onCambiar={setCorreosFalsos}
          titulo="Correos falsos"
          detalle="usuario12@pronosticup.local en vez del real. Los superadmins conservan el suyo para poder entrar."
        />
        <Opcion
          marcada={passwordDePrueba}
          onCambiar={setPasswordDePrueba}
          titulo="Contraseña «password» para todos"
          detalle="Así el archivo no lleva las contraseñas reales (ni cifradas) y en local entras como cualquiera."
        />
      </div>

      {sinProteccion && (
        <p className="font-body text-xs text-premio bg-premio/10 border border-premio/30 rounded-lg px-3 py-2 mb-4">
          ⚠️ Con alguna opción desmarcada, el archivo lleva datos reales de tus jugadores. Guárdalo bien y bórralo cuando acabes.
        </p>
      )}

      <button
        type="button"
        onClick={descargar}
        disabled={estado === 'bajando'}
        className="font-body text-sm font-semibold bg-acento text-fondo rounded px-5 py-2 hover:brightness-110 disabled:opacity-50"
      >
        {estado === 'bajando' ? 'Generando…' : '⬇ Descargar .sql'}
      </button>

      {estado && estado !== 'bajando' && (
        <p className={`font-body text-sm mt-3 ${estado.ok ? 'text-acento' : 'text-red-500'}`}>
          {estado.ok ? '✓ ' : '✗ '}
          {estado.texto}
        </p>
      )}

      <div className="mt-6 bg-borde/5 border border-borde/10 rounded-lg px-4 py-3">
        <p className="font-body text-[10px] uppercase tracking-widest text-borde mb-2">Después, en tu ordenador</p>
        <ol className="font-body text-sm text-texto list-decimal pl-5 space-y-1">
          <li>HeidiSQL, conexión <strong>LOCAL</strong> → Archivo → Ejecutar archivo SQL → el que acabas de bajar.</li>
          <li>
            En Ubuntu: <span className="font-mono text-xs bg-borde/10 rounded px-1.5 py-0.5">./vendor/bin/sail artisan migrate</span>
          </li>
          <li>Entra con tu correo y la contraseña <span className="font-mono">password</span> (si la marcaste).</li>
        </ol>
        <p className="font-body text-xs text-borde mt-2">
          Lleva todo menos la tabla de migraciones; sesiones, tokens y colas van vacías. Las imágenes subidas (avatares, logos) no van en el .sql.
        </p>
      </div>
    </div>
  )
}

export default AdminCopiaLocalPage
