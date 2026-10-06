import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import TicketHeader from '../components/TicketHeader'
import SkeletonLista from '../components/SkeletonLista'

// Nivel del error: siempre icono + palabra, el color solo acompaña.
const NIVELES = {
  WARNING: { icono: '!', texto: 'Aviso', clase: 'text-premio' },
  ERROR: { icono: '✗', texto: 'Error', clase: 'text-red-400' },
  CRITICAL: { icono: '✗✗', texto: 'Crítico', clase: 'text-red-400' },
  ALERT: { icono: '✗✗', texto: 'Alerta', clase: 'text-red-400' },
  EMERGENCY: { icono: '✗✗', texto: 'Emergencia', clase: 'text-red-400' },
}

function fechaHora(iso) {
  if (!iso) return 'sin fecha'
  return new Date(iso).toLocaleString('es-ES', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function haceCuanto(iso) {
  if (!iso) return ''
  const minutos = Math.max(0, Math.round((Date.now() - new Date(iso)) / 60000))
  if (minutos < 1) return 'ahora mismo'
  if (minutos < 60) return `hace ${minutos} min`
  if (minutos < 1440) return `hace ${Math.round(minutos / 60)} h`
  return `hace ${Math.round(minutos / 1440)} día(s)`
}

function tamano(bytes) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`
  return `${(bytes / 1024 / 1024).toLocaleString('es-ES', { maximumFractionDigits: 1 })} MB`
}

function FilaError({ error }) {
  const [abierto, setAbierto] = useState(false)
  const nivel = NIVELES[error.nivel] ?? NIVELES.ERROR

  return (
    <div className="border-b border-borde/10 last:border-0">
      <button onClick={() => setAbierto((v) => !v)} className="w-full text-left px-4 py-3 hover:bg-borde/5">
        <div className="flex items-start justify-between gap-3">
          <p className="font-body text-sm text-texto break-words min-w-0">
            <span className="text-borde text-xs mr-1.5">{abierto ? '▾' : '▸'}</span>
            {error.mensaje || '(sin mensaje)'}
          </p>
          <span className={`font-body text-xs font-semibold whitespace-nowrap ${nivel.clase}`}>{nivel.icono} {nivel.texto}</span>
        </div>
        <p className="font-body text-[11px] text-borde mt-1 ml-4">
          {haceCuanto(error.cuando)} · {fechaHora(error.cuando)}
          {error.veces > 1 && <span className="text-premio font-semibold"> · {error.veces} veces (la primera, {fechaHora(error.primera_vez)})</span>}
        </p>
      </button>

      {abierto && (
        <div className="px-4 pb-3 ml-4">
          {error.clase && <p className="font-body text-xs text-borde">Tipo: <span className="text-texto">{error.clase}</span></p>}
          {error.archivo && (
            <p className="font-body text-xs text-borde">Dónde: <span className="text-texto font-marcador">{error.archivo}:{error.linea}</span></p>
          )}
          {error.traza.length > 0 && (
            <pre className="mt-2 font-marcador text-[11px] text-borde bg-borde/10 rounded p-2 overflow-x-auto whitespace-pre">{error.traza.join('\n')}</pre>
          )}
          {!error.clase && error.traza.length === 0 && (
            <p className="font-body text-xs text-borde">El registro no guardó más detalle de este fallo.</p>
          )}
        </div>
      )}
    </div>
  )
}

function AdminSistemaPage() {
  const [conAvisos, setConAvisos] = useState(false)

  const { data, isLoading, error, refetch, isFetching } = useQuery({
    queryKey: ['admin-sistema', conAvisos],
    queryFn: async () => (await client.get('/api/v1/admin/sistema', { params: conAvisos ? { avisos: 1 } : {} })).data.data,
    placeholderData: (anteriores) => anteriores,
  })

  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>
  if (isLoading || !data) return <div className="max-w-4xl mx-auto px-4 py-8"><SkeletonLista /></div>

  const { version, registros, errores } = data
  const pesoTotal = registros.archivos.reduce((suma, a) => suma + a.bytes, 0)

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
        <TicketHeader titulo="Versión desplegada" />
        <div className="px-4 py-4">
          {version.commit ? (
            <>
              <p className="font-body text-sm text-texto">
                Cambio <span className="font-marcador font-bold">{version.commit_corto}</span>
                {version.rama && <span className="text-borde"> · rama {version.rama}</span>}
              </p>
              <p className="font-body text-xs text-borde mt-1">
                {version.desplegado_en
                  ? `En marcha desde el ${fechaHora(version.desplegado_en)} (${haceCuanto(version.desplegado_en)}).`
                  : 'No se ha podido saber desde cuándo está en marcha.'}
              </p>
              <p className="font-body text-[11px] text-borde mt-2">
                Para saber si tu último cambio ya está aquí, compáralo con lo que dice <span className="font-marcador">git log --oneline -1</span> en tu ordenador.
              </p>
            </>
          ) : (
            <p className="font-body text-sm text-borde">
              Aquí no hay datos de despliegue (entorno: {version.entorno}). En producción saldrá el cambio que está en marcha.
            </p>
          )}
        </div>
      </div>

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        <TicketHeader titulo="Errores de la aplicación" />

        <div className="px-4 py-3 border-b border-borde/10 flex items-center justify-between gap-3 flex-wrap">
          <label className="font-body text-xs text-texto flex items-center gap-2 cursor-pointer">
            <input type="checkbox" checked={conAvisos} onChange={(e) => setConAvisos(e.target.checked)} />
            Enseñar también los avisos
          </label>
          <button onClick={() => refetch()} disabled={isFetching} className="font-body text-xs text-texto border border-borde/40 rounded px-3 py-1.5 hover:bg-borde/10 disabled:opacity-40">
            {isFetching ? 'Mirando…' : 'Volver a mirar'}
          </button>
        </div>

        {!registros.a_archivo && (
          <div className="px-4 py-3 border-b border-borde/10 bg-premio/5">
            <p className="font-body text-xs text-premio font-semibold">
              ! Los registros no se están guardando en un archivo (canal: {registros.canales.join(', ') || 'ninguno'}).
            </p>
            <p className="font-body text-[11px] text-borde mt-0.5">
              Este visor solo puede leer archivos de storage/logs. Revisa las variables LOG_CHANNEL y LOG_STACK del entorno.
            </p>
          </div>
        )}

        {errores.length === 0 ? (
          <p className="font-body text-sm text-acento font-semibold px-4 py-6">
            ✓ Ningún {conAvisos ? 'error ni aviso' : 'error'} en los registros guardados.
          </p>
        ) : (
          errores.map((e, i) => <FilaError key={i} error={e} />)
        )}

        <p className="font-body text-[11px] text-borde px-4 py-3 border-t border-borde/10">
          {registros.archivos.length === 0
            ? 'Todavía no hay ningún archivo de registro.'
            : `Leídos ${registros.archivos.length} archivo(s) de registro, ${tamano(pesoTotal)} en total. Se guarda un archivo por día durante 14 días.`}
          {' '}Los fallos iguales se juntan en una sola línea.
        </p>
      </div>
    </div>
  )
}

export default AdminSistemaPage
