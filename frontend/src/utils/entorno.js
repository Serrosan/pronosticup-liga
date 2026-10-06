// ¿Dónde está corriendo la app? Se decide por la dirección del navegador, no
// por cómo se compiló: así una compilación "de producción" abierta en tu
// ordenador sigue marcándose como local.
const DOMINIOS_DE_PRODUCCION = ['pronosticup.es', 'www.pronosticup.es']

export const ES_PRODUCCION = DOMINIOS_DE_PRODUCCION.includes(window.location.hostname)

export const ENTORNO = ES_PRODUCCION
  ? { clave: 'produccion', icono: '🔴', nombre: 'PRODUCCIÓN', detalle: 'lo que hagas aquí es de verdad' }
  : { clave: 'local', icono: '🧪', nombre: 'LOCAL', detalle: 'base de datos de pruebas' }
