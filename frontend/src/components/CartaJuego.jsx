const COLORES_RAREZA = {
  Comun: { cabecera: '#C8FF4D', texto: '#0E1B2B', borde: '#C8FF4D' },
  PocoComun: { cabecera: '#4DA6FF', texto: '#0E1B2B', borde: '#4DA6FF' },
  Rara: { cabecera: '#B44DFF', texto: '#FFFFFF', borde: '#B44DFF' },
  Legendaria: { cabecera: '#FFB238', texto: '#0E1B2B', borde: '#FFB238' },
}

const ETIQUETA_RAREZA = {
  Comun: 'COMÚN',
  PocoComun: 'POCO COMÚN',
  Rara: 'RARA',
  Legendaria: 'LEGENDARIA',
}

const ETIQUETA_CATEGORIA = {
  Jugadas: 'JUGADA',
  Faltas: 'FALTA',
  Trampa: 'TRAMPA',
}

function CartaJuego({ carta, categoriaNombre, tamano = 'normal' }) {
  const colores = COLORES_RAREZA[carta.rareza] ?? COLORES_RAREZA.Comun
  const esPequena = tamano === 'pequena'

  return (
    <div
      className={`relative flex flex-col rounded-2xl border-2 overflow-hidden ${esPequena ? 'w-32' : 'w-56'}`}
      style={{ borderColor: colores.borde, backgroundColor: '#0E1B2B' }}
    >
      <div
        className={`text-center font-display ${esPequena ? 'text-xs py-1.5' : 'text-base py-2.5'}`}
        style={{ backgroundColor: colores.cabecera, color: colores.texto }}
      >
        {carta.nombre}
      </div>

      <div className={`relative flex items-center justify-center ${esPequena ? 'py-3' : 'py-6'}`}>
        <div
          className={`rounded-full flex items-center justify-center overflow-hidden ${esPequena ? 'w-16 h-16' : 'w-28 h-28'}`}
          style={{ backgroundColor: '#FFFFFF' }}
        >
          {carta.imagen_url ? (
            <img src={carta.imagen_url} alt={carta.nombre} className="w-full h-full object-contain p-2" />
          ) : (
            <span className={esPequena ? 'text-2xl' : 'text-4xl'}>🃏</span>
          )}
        </div>

        {carta.insignia_corta && (
          <span
            className={`absolute top-0 right-2 rounded-full flex items-center justify-center font-marcador font-bold border-2 ${esPequena ? 'w-7 h-7 text-[10px]' : 'w-10 h-10 text-sm'}`}
            style={{ backgroundColor: '#0E1B2B', borderColor: colores.borde, color: colores.borde }}
          >
            {carta.insignia_corta}
          </span>
        )}
      </div>

      {!esPequena && (
        <div className="flex justify-center mb-3">
          <span
            className="font-body text-[10px] font-bold tracking-widest rounded-full px-3 py-1 border"
            style={{ borderColor: colores.borde, color: colores.borde }}
          >
            {ETIQUETA_CATEGORIA[categoriaNombre] ?? categoriaNombre?.toUpperCase()} · {ETIQUETA_RAREZA[carta.rareza]}
          </span>
        </div>
      )}

      {!esPequena && (
        <p className="font-body text-xs text-center text-white/90 px-4 pb-5 leading-snug">
          {carta.descripcion}
        </p>
      )}

      <span
        className="absolute bottom-3 left-3 w-2 h-2 rounded-full"
        style={{ backgroundColor: colores.borde }}
      />
    </div>
  )
}

export default CartaJuego