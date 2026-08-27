import { useTheme } from '../context/ThemeContext'

function SelectTema({ value, defaultValue, onChange, name, options, placeholder = 'Sin especificar', className = '' }) {
  const { tema } = useTheme()
  const colorFondo = tema === 'oscuro' ? '#0E1B2B' : '#FFFFFF'
  const colorTexto = tema === 'oscuro' ? '#ECE7DB' : '#111827'

  return (
    <select
      name={name}
      value={value}
      defaultValue={defaultValue}
      onChange={onChange}
      style={{ backgroundColor: colorFondo, color: colorTexto }}
      className={`font-body rounded border border-borde/40 px-3 py-1.5 focus:outline-none focus:border-acento ${className}`}
    >
      <option value="" style={{ backgroundColor: colorFondo, color: colorTexto }}>{placeholder}</option>
      {options.map((opcion) => {
        const val = typeof opcion === 'object' ? opcion.value : opcion
        const label = typeof opcion === 'object' ? opcion.label : opcion
        return (
          <option key={val} value={val} style={{ backgroundColor: colorFondo, color: colorTexto }}>
            {label}
          </option>
        )
      })}
    </select>
  )
}

export default SelectTema