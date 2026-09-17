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

// Cuántos puntitos se muestran abajo de la carta, según la rareza.
const PUNTOS_RAREZA = {
  Comun: 1,
  PocoComun: 2,
  Rara: 3,
  Legendaria: 4,
}

const TAMANOS = {
  normal: {
    ancho: 'w-56', padCabecera: 'py-2.5', textoCabecera: 'text-base',
    circulo: 'w-28 h-28', padCirculo: 'py-6', emoji: 'text-4xl',
    insignia: 'w-10 h-10 text-sm', insigniaPos: 'top-0 right-2',
    iconoCategoria: 'text-base', iconoCategoriaPos: 'top-1 left-2',
    etiqueta: 'text-[10px] px-3 py-1', descripcion: 'text-xs px-4 pb-5',
    puntito: 'w-1.5 h-1.5', gapPuntitos: 'gap-1', posPuntitos: 'bottom-2 left-2',
  },
  mini: {
    ancho: 'w-32', padCabecera: 'py-1.5', textoCabecera: 'text-[11px]',
    circulo: 'w-14 h-14', padCirculo: 'py-2.5', emoji: 'text-xl',
    insignia: 'w-6 h-6 text-[9px]', insigniaPos: '-top-1 right-1',
    iconoCategoria: 'text-[10px]', iconoCategoriaPos: 'top-0.5 left-1',
    etiqueta: 'text-[7px] px-1.5 py-0.5', descripcion: 'text-[9px] px-2 pb-2.5 leading-snug',
    puntito: 'w-1 h-1', gapPuntitos: 'gap-0.5', posPuntitos: 'bottom-1 left-1',
  },
}

function Puntitos({ rareza, colorBorde, tamano }) {
  const cantidad = PUNTOS_RAREZA[rareza] ?? 1
  const t = TAMANOS[tamano] ?? TAMANOS.normal

  return (
    <div className={`absolute flex ${t.gapPuntitos} ${t.posPuntitos}`}>
      {Array.from({ length: cantidad }, (_, i) => (
        <span key={i} className={`rounded-full ${t.puntito}`} style={{ backgroundColor: colorBorde }} />
      ))}
    </div>
  )
}

function CartaJuego({ carta, categoriaNombre, categoriaIcono, tamano = 'normal' }) {
  const colores = COLORES_RAREZA[carta.rareza] ?? COLORES_RAREZA.Comun

  if (tamano === 'pequena') {
    return (
      <div
        className="relative flex flex-col rounded-2xl border-2 overflow-hidden w-32"
        style={{ borderColor: colores.borde, backgroundColor: '#0E1B2B' }}
      >
        <div
          className="text-center font-display text-xs py-1.5"
          style={{ backgroundColor: colores.cabecera, color: colores.texto }}
        >
          {carta.nombre}
        </div>
        <div className="relative flex items-center justify-center py-3">
          {categoriaIcono && <span className="absolute top-0.5 left-1 text-[10px]">{categoriaIcono}</span>}
          <div className="rounded-full flex items-center justify-center overflow-hidden w-16 h-16" style={{ backgroundColor: '#FFFFFF' }}>
            {carta.imagen_url ? (
              <img src={carta.imagen_url} alt={carta.nombre} className="w-full h-full object-contain p-2" />
            ) : (
              <span className="text-2xl">🃏</span>
            )}
          </div>
          {carta.insignia_corta && (
            <span
              className="absolute top-0 right-2 rounded-full flex items-center justify-center font-marcador font-bold border-2 w-7 h-7 text-[10px]"
              style={{ backgroundColor: '#0E1B2B', borderColor: colores.borde, color: colores.borde }}
            >
              {carta.insignia_corta}
            </span>
          )}
        </div>
        <Puntitos rareza={carta.rareza} colorBorde={colores.borde} tamano="mini" />
      </div>
    )
  }

  const t = TAMANOS[tamano] ?? TAMANOS.normal

  return (
    <div
      className={`relative flex flex-col rounded-2xl border-2 overflow-hidden ${t.ancho}`}
      style={{ borderColor: colores.borde, backgroundColor: '#0E1B2B' }}
    >
      <div
        className={`text-center font-display ${t.padCabecera} ${t.textoCabecera} truncate px-1`}
        style={{ backgroundColor: colores.cabecera, color: colores.texto }}
      >
        {carta.nombre}
      </div>

      <div className={`relative flex items-center justify-center ${t.padCirculo}`}>
        {categoriaIcono && (
          <span className={`absolute ${t.iconoCategoriaPos} ${t.iconoCategoria} opacity-80`} title={categoriaNombre}>
            {categoriaIcono}
          </span>
        )}

        <div className={`rounded-full flex items-center justify-center overflow-hidden ${t.circulo}`} style={{ backgroundColor: '#FFFFFF' }}>
          {carta.imagen_url ? (
            <img src={carta.imagen_url} alt={carta.nombre} className="w-full h-full object-contain p-2" />
          ) : (
            <span className={t.emoji}>🃏</span>
          )}
        </div>

        {carta.insignia_corta && (
          <span
            className={`absolute rounded-full flex items-center justify-center font-marcador font-bold border-2 ${t.insignia} ${t.insigniaPos}`}
            style={{ backgroundColor: '#0E1B2B', borderColor: colores.borde, color: colores.borde }}
          >
            {carta.insignia_corta}
          </span>
        )}
      </div>

      <div className="flex justify-center mb-2">
        <span
          className={`font-body font-bold tracking-widest rounded-full border ${t.etiqueta}`}
          style={{ borderColor: colores.borde, color: colores.borde }}
        >
          {ETIQUETA_CATEGORIA[categoriaNombre] ?? categoriaNombre?.toUpperCase()} · {ETIQUETA_RAREZA[carta.rareza]}
        </span>
      </div>

      <p className={`font-body text-center text-white/90 leading-snug ${t.descripcion}`}>
        {carta.descripcion}
      </p>

      <Puntitos rareza={carta.rareza} colorBorde={colores.borde} tamano={tamano} />
    </div>
  )
}

export default CartaJuego