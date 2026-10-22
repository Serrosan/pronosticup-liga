# Reglas del juego

Las reglas tal como están **programadas**, con dónde vive cada una en el código. Si
alguna regla cambia, actualiza también este documento y [Decisiones](decisiones.md).

- [Puntos de los pronósticos](#puntos-de-los-pronósticos)
- [Goleadores](#goleadores)
- [Quinielas](#quinielas)
- [Cuándo se puede pronosticar](#cuándo-se-puede-pronosticar)
- [Cartas](#cartas)
- [Faltas](#faltas)
- [Clasificación](#clasificación)

---

## Puntos de los pronósticos

Configurables **por liga** (`configuracion_puntos`; la fila con `id_liga = null` es la
global). Valores por defecto:

| Resultado | Evento | Puntos |
|---|---|---|
| Exacto | `AciertoExacto` | 5 |
| Signo + diferencia de goles | `AciertoDiferencia` | 2 |
| Solo el signo (1X2) | `Acierto1x2` | 1 |
| Fallo | `Fallo` | 0 |

**Empates "vecinos"**: en un empate, acertar empate a una diferencia de 1 gol (decir 1-1 y
que salga 0-0 o 2-2) cuenta como `AciertoDiferencia`. Así los empates también tienen tres
escalones. El 0-0 solo tiene como vecino el 1-1.

**Bonus de pleno**, según el porcentaje de signos acertados en la jornada: 70 %, 80 %,
90 % o 100 % (`bonus_pleno_7` … `bonus_pleno_10`). Un **Amuleto** puede hacer que un fallo
cuente como acierto para este cálculo.

Código: `JornadaController::calcularPuntosPronosticos()` y `calcularBonusPleno()`.
Recalcular tras cambiar reglas es **liga por liga**.

## Goleadores

- Hasta **5 jugadores por jornada**. Se puede guardar a medias.
- No se puede repetir ninguno de la jornada anterior.
- **+1 punto por gol** (`puntos_gol_goleador`). Los goles en propia puerta no cuentan para
  el goleador, aunque sí para el marcador.
- Se calculan en el paso **Calcular goleadores** del cierre (`recalcularEventos()`), con
  los eventos que trae el scraper. Antes de ese paso la app no enseña puntos de
  goleadores, para no mostrar una cifra provisional.

## Quinielas

Tres quinielas de posiciones finales (`quinielas_posiciones`), con control manual desde el
admin.

## Cuándo se puede pronosticar

- Una jornada se **bloquea** cuando empieza su grueso de partidos: la mediana de horarios
  ± 5 días, para que un partido adelantado o aplazado no la bloquee entera. Regla única en
  `CalendarioPartido::jornadaBloqueada()`: no reimplementarla en otro sitio.
- En **ligas con cartas** (`ConExtras`), la jornada J **solo se abre cuando termina la
  anterior**: J ≤ jornada en juego (`AperturaJornadas`, `proximaJornadaPorJugar`, que
  ignora los aplazados descolgados). Así nadie rellena la jornada antes de ver las faltas
  que le han jugado. Al abrirse llega el aviso "🔓 Ya puedes pronosticar la jornada J".

## Cartas

Solo en ligas `ConExtras`. En BasiLiga empezaron en la **jornada 8**: **no son
retroactivas**.

### Lo básico

- Dos categorías: **Jugadas** (te ayudan) y **Faltas** (contra un rival, o el Escudo para
  ti mismo).
- Cuatro rarezas: Común, Poco común, Rara y Legendaria. Probabilidades por defecto
  65 / 25 / 8 / 2 (`ProbabilidadesPorDefecto`).
- **Tope de mano** por liga (10 en BasiLiga). Nunca se descarta nada automáticamente: si te
  pasas, no puedes **jugar** hasta descartar, aunque sí recibir.
- **Reparto semanal** tras cerrar y calcular goleadores: una Jugada y una Falta por jugador
  más el bonus para el Top 3 de la jornada.
- Límites por jornada: **2 Jugadas** y **1 Falta**, Escudo incluido.

### Jugadas: el motor por "formas"

`MotorEfectosCartas` + `app/Services/EfectosCartas/`. Cada forma es una manera de afectar a
los puntos; añadir una carta de una forma que ya existe es una línea en su registro.

| Forma | Qué hace | Ejemplos | Se resuelve en |
|---|---|---|---|
| 1 | Ajusta los puntos de un partido elegido | Chute Extra, Ojo de Halcón, Doblete, Pleno Garantizado | Cerrar |
| 2 | Compara con la mayoría de la liga | Palomitas | Cerrar |
| 3 | Bono sobre varios partidos sin elegir | Crack | Cerrar |
| 4 | Protege el bonus de pleno | Amuleto | Cerrar |
| 5 | Depende de eventos (tarjetas, goles por franja) | Amigo del Árbitro, Madrugador, Filo del Descanso, Tiempo Añadido | Calcular goleadores |
| 6 | Dos partidos elegidos de antemano | (preparada, sin carta todavía) | Cerrar |

El historial nunca se falsea: con Pleno Garantizado el evento conserva el tipo real y la
carta va en `nota_carta`.

## Faltas

`MotorFaltas`. Se juegan contra un rival y afectan a la **jornada siguiente a la que está
en juego**. Mientras se juega la 9, una Falta cuenta para la 10; cuando la 9 termina,
pasa a contar para la 11.

| Falta | Efecto |
|---|---|
| **Todo queda en casa / Visita obligada** | El rival debe incluir N victorias locales / visitantes (2–5 según rareza) |
| **Resultado Gafas** | El rival debe incluir N empates (1 / 2 / 3 / 4 según rareza) |
| **Fallo clamoroso** | El rival no puede elegir como goleador a ninguno del **Top 3 de goleadores reales de la temporada** (empatados en el 3.º incluidos) |
| **Expulsión** | Bloquea jugar cartas: Legendaria → Jugadas y Faltas; Rara → solo Faltas |
| **Sin comodines** | Bloquea solo Amuletos y Pleno Garantizado |
| **Silbato** | Mensaje de burla, sin efecto en puntos. El mensaje es obligatorio y privado |
| **Amigo invisible** | Como Silbato, pero anónimo ("Alguien…") |
| **Escudo** | Te lo juegas a ti: bloquea la primera Falta que te llegue. **Se pierden las dos cartas** |

Reglas comunes:

- **Prevalidación**: con Casa, Visita y Gafas no se exige que cada pronóstico cumpla. Solo
  se bloquea el guardado en el momento en que ya sería **imposible** llegar al mínimo con
  los partidos que quedan. Se permiten cambios que no empeoran la situación.
- **Varias Faltas del mismo tipo contra la misma persona no se suman**: manda la más
  exigente, y el atacante no recibe información de lo que han jugado otros.
- No se puede repetir rival dos semanas seguidas.
- Máximo de Faltas recibidas por jornada: la mitad de la liga, redondeando hacia abajo.
- El jugador ve sus Faltas activas en **Mis cartas → Amenazas** y una franja con su
  progreso ("llevas 1/2 de victoria local") al pronosticar.

## Clasificación

- Suma de `eventos_puntos` de la liga. Los puntos de cartas ya van dentro del total.
- Vista por jornada: la jornada sola. La flecha de tendencia compara con la clasificación
  acumulada de antes.
- El **detalle de un jugador** desglosa pronósticos, bonus, cartas y goleadores, y siempre
  suma el total. El correo de puntos usa las mismas cuentas
  (`ResumenPuntosJornadaService`).
