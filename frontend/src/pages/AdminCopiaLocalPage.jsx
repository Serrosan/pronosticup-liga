import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'

// Copia para local: la base de datos (.sql) y, aparte, las imágenes subidas
// (.zip). Solo lee. Por defecto el .sql sale sin datos de acceso reales.

async function bajar(url, cuerpo, nombrePorDefecto) {
  const respuesta = await client.post(url, cuerpo, { responseType: 'blob' })
  const nombre = respuesta.headers['content-disposition']?.match(/filename="?([^"]+)"?/)?.[1] ?? nombrePorDefecto
  const enlace = document.createElement('a')
  enlace.href = URL.createObjectURL(respuesta.data)
  enlace.download = nombre
  document.body.appendChild(enlace)
  enlace.click()
  enlace.remove()
  URL.revokeObjectURL(enlace.href)
  return `${nombre} (${tamano(respuesta.data.size)})`
}

async function mensajeDeError(error, porDefecto) {
  try {
    return JSON.parse(await error.response?.data?.text())?.message ?? porDefecto
  } catch {
    return porDefecto
  }
}

function tamano(bytes) {
  if (bytes >= 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(1)} MB`
  return `${Math.max(1, Math.round(bytes / 1024))} KB`
}

const hoy = () => new Date().toISOString().slice(0, 10)

function Casilla({ marcada, onCambiar, titulo, detalle, derecha }) {
  return (
    <label className="flex items-start gap-3 px-4 py-3 cursor-pointer hover:bg-borde/5">
      <input type="checkbox" checked={marcada} onChange={(e) => onCambiar(e.target.checked)} className="mt-1 w-4 h-4 accent-[var(--color-acento)] shrink-0" />
      <span className="flex-1 min-w-0">
        <span className="font-body text-sm text-texto block">{titulo}</span>
        {detalle && <span className="font-body text-xs text-borde block">{detalle}</span>}
      </span>
      {derecha && <span className="font-body text-xs text-borde whitespace-nowrap shrink-0">{derecha}</span>}
    </label>
  )
}

function Resultado({ estado }) {
  if (!estado || estado === 'bajando') return null
  return (
    <p className={`font-body text-sm mt-3 ${estado.ok ? 'text-acento' : 'text-red-500'}`}>
      {estado.ok ? '✓ Descargado: ' : '✗ '}
      {estado.texto}
    </p>
  )
}

function Boton({ onClick, ocupado, children, deshabilitado = false }) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={ocupado || deshabilitado}
      className="font-body text-sm font-semibold bg-acento text-fondo rounded px-5 py-2 hover:brightness-110 disabled:opacity-50"
    >
      {ocupado ? 'Generando…' : children}
    </button>
  )
}

function Paso({ numero, titulo, children }) {
  return (
    <section className="mb-8">
      <h2 className="font-display text-lg text-texto mb-3">
        <span className="text-acento">{numero}.</span> {titulo}
      </h2>
      {children}
    </section>
  )
}

function BaseDeDatos() {
  const [correosFalsos, setCorreosFalsos] = useState(true)
  const [passwordDePrueba, setPasswordDePrueba] = useState(true)
  const [estado, setEstado] = useState(null)

  async function descargar() {
    setEstado('bajando')
    try {
      const texto = await bajar('/api/v1/admin/copia-local', { correos_falsos: correosFalsos, password_de_prueba: passwordDePrueba }, `pronosticup-${hoy()}.sql`)
      setEstado({ ok: true, texto })
    } catch (error) {
      setEstado({ ok: false, texto: await mensajeDeError(error, 'No se pudo generar la copia.') })
    }
  }

  return (
    <Paso numero="1" titulo="Base de datos (.sql)">
      <div className="bg-fondo border border-borde/30 rounded-lg divide-y divide-borde/10 mb-4">
        <Casilla
          marcada={correosFalsos}
          onCambiar={setCorreosFalsos}
          titulo="Correos falsos"
          detalle="usuario12@pronosticup.local en vez del real. Los superadmins conservan el suyo para poder entrar."
        />
        <Casilla
          marcada={passwordDePrueba}
          onCambiar={setPasswordDePrueba}
          titulo="Contraseña «password» para todos"
          detalle="Así el archivo no lleva las contraseñas reales (ni cifradas) y en local entras como cualquiera."
        />
      </div>

      {(!correosFalsos || !passwordDePrueba) && (
        <p className="font-body text-xs text-premio bg-premio/10 border border-premio/30 rounded-lg px-3 py-2 mb-4">
          ⚠️ Con alguna opción desmarcada, el archivo lleva datos reales de tus jugadores. Guárdalo bien y bórralo cuando acabes.
        </p>
      )}

      <Boton onClick={descargar} ocupado={estado === 'bajando'}>⬇ Descargar .sql</Boton>
      <Resultado estado={estado} />

      <p className="font-body text-xs text-borde mt-3">
        En tu ordenador: HeidiSQL, conexión <strong className="text-texto">LOCAL</strong> → Archivo → Ejecutar archivo SQL. Cárgala con local y producción
        al día (el mismo código subido). Lleva todo menos la tabla de migraciones; sesiones, tokens y colas van vacías.
      </p>
    </Paso>
  )
}

function Imagenes() {
  const { data: carpetas, isLoading, error } = useQuery({
    queryKey: ['admin-copia-imagenes'],
    queryFn: async () => (await client.get('/api/v1/admin/copia-local/imagenes')).data.data,
  })
  const [elegidas, setElegidas] = useState(null)
  const [estado, setEstado] = useState(null)

  // Al cargar, todas marcadas.
  useEffect(() => {
    if (carpetas && elegidas === null) setElegidas(carpetas.map((c) => c.carpeta))
  }, [carpetas, elegidas])

  const marcadas = elegidas ?? []
  const pesoElegido = (carpetas ?? []).filter((c) => marcadas.includes(c.carpeta)).reduce((suma, c) => suma + c.bytes, 0)

  function alternar(carpeta, marcada) {
    setElegidas((antes) => (marcada ? [...(antes ?? []), carpeta] : (antes ?? []).filter((c) => c !== carpeta)))
  }

  async function descargar() {
    setEstado('bajando')
    try {
      const texto = await bajar('/api/v1/admin/copia-local/imagenes', { carpetas: marcadas }, `pronosticup-imagenes-${hoy()}.zip`)
      setEstado({ ok: true, texto })
    } catch (error) {
      setEstado({ ok: false, texto: await mensajeDeError(error, 'No se pudo generar el .zip.') })
    }
  }

  return (
    <Paso numero="2" titulo="Imágenes y archivos subidos (.zip)">
      {isLoading && <p className="font-body text-sm text-borde">Mirando qué hay…</p>}
      {error && <p className="font-body text-sm text-red-500">{error.response?.data?.message ?? 'No se pudo cargar.'}</p>}

      {carpetas && carpetas.length === 0 && <p className="font-body text-sm text-borde">No hay ningún archivo subido.</p>}

      {carpetas && carpetas.length > 0 && (
        <>
          <div className="bg-fondo border border-borde/30 rounded-lg divide-y divide-borde/10 mb-4">
            {carpetas.map((c) => (
              <Casilla
                key={c.carpeta}
                marcada={marcadas.includes(c.carpeta)}
                onCambiar={(marcada) => alternar(c.carpeta, marcada)}
                titulo={c.carpeta}
                derecha={`${c.archivos} archivo${c.archivos === 1 ? '' : 's'} · ${tamano(c.bytes)}`}
              />
            ))}
          </div>

          <Boton onClick={descargar} ocupado={estado === 'bajando'} deshabilitado={marcadas.length === 0}>
            ⬇ Descargar .zip{marcadas.length > 0 ? ` (${tamano(pesoElegido)})` : ''}
          </Boton>
          <Resultado estado={estado} />

          <p className="font-body text-xs text-borde mt-3">
            Si hay carpetas del chat (fotos, audios), son de tus jugadores: llévatelas solo si te hacen falta para probar algo.
          </p>
          <div className="mt-3 bg-borde/5 border border-borde/10 rounded-lg px-4 py-3">
            <p className="font-body text-[10px] uppercase tracking-widest text-borde mb-1.5">En tu ordenador (Ubuntu)</p>
            <pre className="font-mono text-[11px] text-texto whitespace-pre-wrap break-all">
{`cd ~/proyectos/pronosticup-liga
python3 -m zipfile -e "$(ls -t /mnt/c/Users/*/Downloads/pronosticup-imagenes-*.zip | head -1)" storage/app/public/
./vendor/bin/sail artisan storage:link`}
            </pre>
          </div>
        </>
      )}
    </Paso>
  )
}

function AdminCopiaLocalPage() {
  return (
    <div className="max-w-2xl">
      <h1 className="font-display text-2xl text-texto mb-1">Copia para local</h1>
      <p className="font-body text-sm text-borde mb-6">
        Lo necesario para tener en tu ordenador la app tal como está aquí. Solo lee: no se cambia nada. Cada descarga queda apuntada en el log.
      </p>
      <BaseDeDatos />
      <Imagenes />
    </div>
  )
}

export default AdminCopiaLocalPage
