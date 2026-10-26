# Operación del admin

El día a día de la app: qué hacer cada jornada y para qué sirve cada herramienta del panel
de administración (`/admin`, solo superadmin).

- [Cierre de jornada (cada semana)](#cierre-de-jornada)
- [Partidos aplazados](#partidos-aplazados)
- [Cuando algo no cuadra](#cuando-algo-no-cuadra)
- [Herramientas del panel](#herramientas-del-panel)

---

## Cierre de jornada

**Admin → Competición → Cierre de jornada** (`/admin/cierre-jornada`). La pantalla
enseña, para cada liga, los pasos con su estado (hecho / listo / aviso / bloqueado) y el
botón de cada uno. Se va **de arriba abajo**: el orden importa.

### Antes de empezar

- Si te ha llegado a la campana **«⏱️ Un partido no se ha actualizado»** (partido atascado: football-data no ha
  publicado el resultado), no cierres hasta que llegue, o márcalo a mano en Admin → Calendario.
- **Espera unos minutos** desde el pitido final del último partido. El scraper descarga
  solo alineaciones, goles y tarjetas de cada partido **10 minutos después** de que pase
  a "Jugado".
- La propia pantalla avisa si a algún partido le faltan datos de LaLiga (eventos o
  alineación). Si pasa, lánzalo a mano en **Sistema → Tareas** (reimportar ese partido o
  la jornada).

### Los pasos

| # | Paso | Qué hace | Notas |
|---|---|---|---|
| 1 | **Cerrar la jornada** | Puntos de pronósticos (signo / diferencia / exacto), bonus de pleno, cartas de resultado y faltas de esa jornada | Bloqueado mientras quede **algún** partido sin jugar. Es de un solo uso |
| 2 | **Calcular goleadores** | Puntos de goleadores y cartas que dependen de goles y tarjetas. **La primera vez manda a cada jugador su resumen de puntos** (correo + campana + móvil) | Se puede repetir si cambian los eventos de un partido; el resumen **no** se vuelve a mandar |
| 3 | **Repartir las cartas de la semana** | Solo en ligas con cartas: una tanda por jugador + bonus para el Top 3 de la jornada | **Después** de goleadores: el Top 3 se calcula con los puntos que haya en ese momento |
| — | **Auditar** | Recalcula los puntos en seco y los compara con lo guardado, sin cambiar nada | Úsalo si algo te huele raro |

Al terminar, la liga sale como **completa**.

### Comprobación final, como jugador

1. **Jornada → La liga → Resumen** de la jornada cerrada: tiene que aparecer.
2. **Clasificación**: que cuadre.
3. En ligas con cartas, la **jornada siguiente debe estar abierta**: sus partidos ya no
   muestran "🔒 Se podrá pronosticar cuando termine la jornada N". Ha tenido que llegar
   el aviso "🔓 Ya puedes pronosticar la jornada N".
4. Te tiene que llegar el **correo de puntos** con el desglose.

### Qué se manda a los jugadores en el cierre

| Momento | Aviso |
|---|---|
| Cerrar | Novedad de "jornada cerrada" con lo más comentado; "tus cartas" (las ya resueltas) |
| Calcular goleadores (1ª vez) | **Resumen de puntos**: total, pronósticos, bonus, goleadores, cartas, posición y si sube o baja |
| Repartir cartas | "Te han llegado cartas" |

Todo lo que va a la campana llega también al móvil a quien tenga los avisos activados,
según sus preferencias.

---

## Partidos aplazados

- **No se cierra una jornada sin todos sus partidos jugados**, ni puntos ni cartas. No se
  cierra "con 9 y recalcular después".
- Mientras tanto, la pantalla de cierre lista la jornada en "jornadas sin cerrar" para que
  no se olvide.
- Si hace falta compensar a la gente mientras espera, se reparten **códigos de canje**
  (ver abajo).
- En ligas con cartas, un aplazado **no bloquea** la apertura de la jornada siguiente: la
  cuenta de "qué jornada está en juego" ignora los partidos descolgados.
- Las cartas **no son retroactivas**: una jornada anterior al inicio de las cartas (en
  BasiLiga, anterior a la 8) no tiene ni cartas que resolver ni reparto, aunque su aplazado
  se juegue más tarde.

---

## Cuando algo no cuadra

| Situación | Herramienta |
|---|---|
| Los puntos de alguien no cuadran | **Auditar** en Cierre de jornada |
| Una carta "no hizo nada" | **Rastro de cartas**: repartida, jugada, resuelta y por qué puntuó o no |
| Se cambió la configuración de puntos | Recalcular puntos de la jornada, **liga por liga** |
| Cambiaron los eventos de un partido (gol corregido…) | Repetir **Calcular goleadores** |
| Hay que ver exactamente lo que ve un jugador | **Ver como** (solo lectura) |
| Hay que mirar datos a mano | [Chuletas → Base de datos](chuletas.md#base-de-datos-heidisql) |

Si toca corregir algo directamente con SQL (como el pronóstico de Jokin en la jornada 9),
primero el `SELECT` con el mismo `WHERE`, luego el `UPDATE`, y después **Auditar**.

---

## Herramientas del panel

Agrupadas como en el menú del admin (acordeón por grupos).

### Competición
- **Cierre de jornada**: ver arriba.
- **Calendario / Eventos de partido**: corregir partidos y eventos a mano. Pegar los
  eventos de un partido **sustituye** los anteriores, no los suma.
- **Configuración de puntos**: por liga; la fila sin liga es la global.
- **Quinielas**: las 3 quinielas de posiciones, con control manual.
- **Importar histórico**: pegar pronósticos desde Excel, **antes** de cerrar esa jornada,
  porque lo importado después de cerrar nunca recibe puntos.

### Cartas
- **Categorías / Tipos de carta**: el catálogo. `codigo_efecto` enlaza cada carta con su
  efecto en el motor.
- **Configuración de cartas**: reparto por liga y categoría, probabilidades de rareza y
  bonus del Top 3.
- **Cartas de usuarios**: dar o retirar cartas a mano.
- **Reparto inicial**: una sola vez por liga.
- **Rastro de cartas**: la vida completa de una carta.
- **Crear códigos / Historial de canjes**: códigos que dan una tirada aleatoria o una carta
  concreta. Un uso por persona, garantizado en la base de datos.

### Datos maestros
- **Equipos, Jugadores, Entrenadores, Árbitros, Estadios, Trofeos**: fichas.
- **Calidad de datos**: jugadores sin dorsal, sin foto, sin nombre de camiseta, fechas que
  se pisan.
- **Cambios de plantilla**: cola de altas, fichajes, dorsales y bajas detectados por el
  scraper. Revisar antes de aplicar; dorsal 26 o más = canterano.

### Scraper
- **Avisos del scraper**: jugadores sin emparejar, colisiones, eventos desconocidos.

### Sistema
- **Tareas y reimportación**: las tareas programadas (última ejecución y botón para
  lanzarlas) y la reimportación de LaLiga por jornada o partido. También "Completar
  identificadores" y "Comparar plantillas".
- **Comparar plantillas**: diferencias entre tus plantillas y las de football-data.org,
  pensado para los fichajes de invierno.
- **Errores y versión**: últimos errores del registro y la versión desplegada.
- **Registro de actividad**: cambios en cartas y otros modelos, con el antes y el después.
- **📣 Enviar aviso**: mensaje a los jugadores (campana + móvil), **ahora** o
  **programado** para más tarde. Los programados se pueden cancelar.
- **Ver como**: la app tal como la ve otro jugador, en solo lectura.
- **Copia para local**: descarga de la base de datos (con correos falsos y contraseña
  `password` por defecto) y de las imágenes, para cargar en local con HeidiSQL.
