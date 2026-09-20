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

const PUNTOS_RAREZA = {
  Comun: 1,
  PocoComun: 2,
  Rara: 3,
  Legendaria: 4,
}

// min-h + line-clamp en la descripción garantizan que TODAS las cartas midan lo mismo,
// sin importar si el texto es corto o largo.
const TAMANOS = {
  normal: {
    ancho: 'w-60', padCabecera: 'py-3', textoCabecera: 'text-base',
    circulo: 'w-[120px] h-[120px]', padCirculo: 'py-5', emoji: 'text-5xl',
    insignia: 'w-10 h-10 text-sm', insigniaPos: 'top-2 right-2',
    etiqueta: 'text-[10px] px-3 py-1', mbEtiqueta: 'mb-2.5',
    descripcion: 'text-xs px-5 pb-4 h-[64px] overflow-hidden line-clamp-3',
    puntito: 'w-1.5 h-1.5', gapPuntitos: 'gap-1.5', pbPuntitos: 'pb-4',
    iconoTam: '20px', iconoPos: 'bottom-3 right-2',
  },
  mini: {
    ancho: 'w-36', padCabecera: 'py-2', textoCabecera: 'text-[11px]',
    circulo: 'w-[62px] h-[62px]', padCirculo: 'py-2', emoji: 'text-2xl',
    insignia: 'w-6 h-6 text-[9px]', insigniaPos: 'top-0 right-0.5',
    etiqueta: 'text-[7px] px-1.5 py-0.5', mbEtiqueta: 'mb-1',
    descripcion: 'text-[9px] px-3 pb-2.5 h-[32px] overflow-hidden line-clamp-2 leading-snug',
    puntito: 'w-1 h-1', gapPuntitos: 'gap-1', pbPuntitos: 'pb-2.5',
    iconoTam: '12px', iconoPos: 'bottom-1.5 right-1',
  },
}

function Puntitos({ rareza, colorBorde, tamano }) {
  const cantidad = PUNTOS_RAREZA[rareza] ?? 1
  const t = TAMANOS[tamano] ?? TAMANOS.normal

  return (
    <div className={`flex ${t.gapPuntitos}`}>
      {Array.from({ length: cantidad }, (_, i) => (
        <span key={i} className={`rounded-full ${t.puntito}`} style={{ backgroundColor: colorBorde }} />
      ))}
    </div>
  )
}

// Icono de categoría pequeño, en la esquina inferior derecha — un sello discreto,
// no una marca de agua grande. Suficientemente visible sin competir con nada más.
function IconoCategoria({ icono, tamano }) {
  if (!icono) return null
  const t = TAMANOS[tamano] ?? TAMANOS.normal

  return (
    <span
      className={`absolute ${t.iconoPos}`}
      style={{ fontSize: t.iconoTam, opacity: 0.55, lineHeight: 1 }}
    >
      {icono}
    </span>
  )
}

function Circulo({ imagenUrl, nombre, emojiSize, circuloClase }) {
  return (
    <div className={`rounded-full flex items-center justify-center overflow-hidden ${circuloClase}`} style={{ backgroundColor: '#FFFFFF' }}>
      {imagenUrl ? (
        <img src={imagenUrl} alt={nombre} className="w-full h-full object-contain p-2" />
      ) : (
        <span className={emojiSize}>🃏</span>
      )}
    </div>
  )
}

function CartaJuego({ carta, categoriaNombre, categoriaIcono, tamano = 'normal' }) {
  const colores = COLORES_RAREZA[carta.rareza] ?? COLORES_RAREZA.Comun

  if (tamano === 'pequena') {
    return (
      <div
        className="relative flex flex-col rounded-2xl border-2 overflow-hidden w-36"
        style={{ borderColor: colores.borde, backgroundColor: '#0E1B2B' }}
      >
        <div
          className="text-center font-display text-xs py-2"
          style={{ backgroundColor: colores.cabecera, color: colores.texto }}
        >
          {carta.nombre}
        </div>
        <div className="relative flex items-center justify-center py-3.5">
          <Circulo imagenUrl={carta.imagen_url} nombre={carta.nombre} emojiSize="text-2xl" circuloClase="w-16 h-16" />
          {carta.insignia_corta && (
            <span
              className="absolute top-1 right-2 rounded-full flex items-center justify-center font-marcador font-bold border-2 w-7 h-7 text-[10px]"
              style={{ backgroundColor: '#0E1B2B', borderColor: colores.borde, color: colores.borde }}
            >
              {carta.insignia_corta}
            </span>
          )}
        </div>
        <div className="flex justify-start px-3 pb-2">
          <Puntitos rareza={carta.rareza} colorBorde={colores.borde} tamano="mini" />
        </div>
        <IconoCategoria icono={categoriaIcono} tamano="mini" />
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
        <Circulo imagenUrl={carta.imagen_url} nombre={carta.nombre} emojiSize={t.emoji} circuloClase={t.circulo} />

        {carta.insignia_corta && (
          <span
            className={`absolute rounded-full flex items-center justify-center font-marcador font-bold border-2 ${t.insignia} ${t.insigniaPos}`}
            style={{ backgroundColor: '#0E1B2B', borderColor: colores.borde, color: colores.borde }}
          >
            {carta.insignia_corta}
          </span>
        )}
      </div>

      <div className={`flex justify-center ${t.mbEtiqueta}`}>
        <span
          className={`font-body font-bold tracking-widest rounded-full border ${t.etiqueta}`}
          style={{ borderColor: colores.borde, color: colores.borde }}
        >
          {ETIQUETA_CATEGORIA[categoriaNombre] ?? categoriaNombre?.toUpperCase()} · {ETIQUETA_RAREZA[carta.rareza]}
        </span>
      </div>

      <p className={`font-body text-center text-white/90 leading-relaxed ${t.descripcion}`}>
        {carta.descripcion}
      </p>

      <div className={`flex justify-start px-4 ${t.pbPuntitos}`}>
        <Puntitos rareza={carta.rareza} colorBorde={colores.borde} tamano={tamano} />
      </div>

      <IconoCategoria icono={categoriaIcono} tamano={tamano} />
    </div>
  )
}

export default CartaJuego