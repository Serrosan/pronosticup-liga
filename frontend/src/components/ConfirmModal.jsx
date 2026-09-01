import useCerrarConEscape from '../hooks/useCerrarConEscape'

function ConfirmModal({ abierto, titulo, mensaje, onConfirmar, onCancelar, peligroso = true }) {
  useCerrarConEscape(abierto, onCancelar)

  if (!abierto) return null

  return (
    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4" onClick={onCancelar}>
      <div className="bg-fondo border-2 border-borde/30 rounded-lg p-6 max-w-sm w-full text-center" onClick={(e) => e.stopPropagation()}>
        <p className="text-3xl mb-3">{peligroso ? '⚠️' : '❓'}</p>
        <h2 className="font-display text-lg text-texto mb-2">{titulo}</h2>
        <p className="font-body text-sm text-borde mb-5">{mensaje}</p>
        <div className="flex gap-3 justify-center">
          <button onClick={onCancelar} className="font-body text-sm text-borde hover:text-texto px-4 py-2">
            Cancelar
          </button>
          <button
            onClick={onConfirmar}
            className={`font-body text-sm font-semibold rounded px-5 py-2 ${
              peligroso ? 'bg-red-500 text-white hover:brightness-110' : 'bg-acento text-fondo hover:brightness-110'
            }`}
          >
            Confirmar
          </button>
        </div>
      </div>
    </div>
  )
}

export default ConfirmModal