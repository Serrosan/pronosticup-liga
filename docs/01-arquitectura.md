# Arquitectura

Cómo está montada la app y dónde vive cada cosa.

- [Visión general](#visión-general)
- [Backend](#backend)
- [Frontend](#frontend)
- [Datos externos](#datos-externos)
- [Tareas programadas y cola](#tareas-programadas-y-cola)
- [Avisos: campana, móvil y correo](#avisos-campana-móvil-y-correo)
- [Sesión y autenticación](#sesión-y-autenticación)
- [Tablas principales](#tablas-principales)

---

## Visión general

```
Navegador / app instalada (PWA)
   │  HTTPS
   ▼
nginx ──► ficheros estáticos del frontend (public/, compilado por Vite)
   │
   └──► index.php ──► Laravel (API /api/v1/…) ──► MySQL
                         │
                         ├─ scheduler cada minuto (supervisord)
                         ├─ football-data.org (resultados, horarios)
                         ├─ LaLiga.com (alineaciones, eventos)
                         ├─ Brevo (correo)
                         └─ Web Push (avisos al móvil)
```

En producción todo va en **un contenedor**: nginx + PHP-FPM + un bucle que lanza el
scheduler cada 60 s, los tres bajo `supervisord`. Ver [Producción](04-produccion.md).

## Backend

Laravel 13, PHP 8.5. API REST bajo `/api/v1`; el admin bajo `/api/v1/admin`, con el
middleware `admin`, es decir, solo superadmin.

| Carpeta | Qué hay |
|---|---|
| `app/Http/Controllers/Api/V1` | Controladores de la app de jugador |
| `app/Http/Controllers/Api/V1/Admin` | Controladores del panel de admin. Muchos usan el trait `CrudAdminBasico` |
| `app/Services` | La lógica de verdad (ver abajo) |
| `app/Services/EfectosCartas` | Una clase por efecto de carta, agrupadas por forma |
| `app/Notifications` | Avisos: campana (`database`), correo (`mail`) y, por un listener, móvil |
| `app/Console/Commands` | Comandos `liga:*`, `avisos:*`, `push:*` |
| `app/Jobs` | Encargos de cola (scraper por partido, sincronización) |
| `routes/api.php` | Rutas. Las de admin van en el grupo `prefix('admin')`: dentro **no** se repite `/admin` |
| `routes/console.php` | Tareas programadas |

### Servicios clave

| Servicio | Para qué |
|---|---|
| `MotorEfectosCartas` | Resuelve las cartas Jugadas (formas 1–6) |
| `MotorFaltas` | Faltas: requisitos, bloqueos, Escudo, resolución |
| `SorteoCartasService` | Sorteo de cartas por rareza; registra en `storage/logs/cartas.log` |
| `AperturaJornadas` | Si una jornada está abierta para pronosticar (ligas con cartas) |
| `CierreJornadaService` | Estado de la lista de cierre (solo mira, no actúa) |
| `AuditoriaPuntosService` | Recalcula puntos en seco y compara |
| `RastroCartasService` | Historia completa de una carta |
| `ResumenJornadaService` | Pestaña "Resumen" de la jornada y titular de la Novedad |
| `ResumenPuntosJornadaService` | Desglose de puntos de un jugador en una jornada (correo de puntos) |
| `LaJornadaService` | "La jornada en tu liga": pronósticos, goleadores y cartas de todos |
| `AyudaGoleadoresService` | Rachas, pichichi y por partido al elegir goleadores |
| `PushService`, `AvisosPush` | Envío de avisos al móvil y preferencias por categoría |
| `AvisosDelAdminService` | Avisos del admin, inmediatos y programados |
| `ImportadorPartidoDetalle`, `ParserComentariosPartido` | Scraper de LaLiga y parser manual de eventos |
| `CambiosPlantillaService`, `ComparadorPlantillasService`, `CalidadDatosService` | Datos de jugadores |
| `FootballDataService` | Cliente de football-data.org |
| `TareasAdmin` | Catálogo de tareas programadas y su registro |
| `CopiaParaLocalService`, `CopiaImagenesService` | Copia de producción para local (anonimizada) |
| `VisorErroresService`, `VigilanciaAdminService` | Errores y avisos al admin |
| `ImagenService` | Compresión de imágenes subidas (pngquant) |

### Puntos: de dónde salen

Todo punto es una fila de **`eventos_puntos`** (usuario, liga, jornada, partido opcional,
`tipo_evento`, puntos). Tipos:

| Tipo | Se crea en |
|---|---|
| `AciertoExacto`, `AciertoDiferencia`, `Acierto1x2`, `Fallo`, `BonusPleno` | Cerrar |
| `CartaBonoJornada`, `CartaDoblePartido` | Cerrar (formas 3 y 6) |
| `GolesGoleadorElegido`, `CartaEventoPartido` | Calcular goleadores |

Las cartas de forma 1 no crean fila propia: ajustan los puntos del partido y lo explican
en `nota_carta`. Los puntos que aportó cada carta están en `cartas_usuario.puntos_generados`.

## Frontend

React 19 + Vite 8 + TanStack Query 5 + Tailwind 4 + React Router 7, en `frontend/`.

- **Rutas** en `src/App.jsx`. Cada página se descarga al abrirla (`lazyPagina` en
  `src/utils/cargaPaginas.jsx`). Si falla la descarga de un trozo porque se ha desplegado
  una versión nueva, la app se recarga una vez sola.
- **Cliente HTTP** `src/api/client.js`: axios con cookies (`withCredentials`) y token CSRF.
  Si recibe un 401 dispara el aviso de "sesión caducada"; añade la cabecera `X-Ver-Como`
  cuando el admin está en modo "ver como".
- **Sesión**: `src/context/AuthContext.jsx`.
- **Componentes compartidos** que conviene reutilizar antes de inventar: `TicketHeader`,
  `MatchCard`, `FilaPronostico`, `SelectorJornada`, `SelectTema`, `CartaJuego`,
  `AdminResourceTable`, `CampoPassword`.
- **Fuentes** servidas desde la propia app (`@fontsource`: Anton, Work Sans, JetBrains
  Mono), no desde Google.
- **PWA**: instalable (`site.webmanifest`, `public/sw.js`). El service worker solo se
  registra en producción y se usa para los avisos push, no como caché.
- **Accesibilidad**: el color **nunca** es la única señal. Siempre texto, icono, borde o
  forma.

## Datos externos

| Fuente | Qué trae | Cómo |
|---|---|---|
| football-data.org | Calendario, resultados, horarios, árbitros, identificadores de jugadores | `SincronizarPartidosJob` cada 2 min; horarios próximos a las 08:00 |
| LaLiga.com | Alineaciones, estadísticas, eventos (goles, tarjetas, cambios) | Scraper: lee el JSON `__NEXT_DATA__` de la página del partido. Automático 10 min después de que el partido pase a "Jugado"; a mano en Sistema → Tareas o con `scripts/sincronizar_jornada.sh N` |

Los avisos del scraper se guardan en `storage/app/scraper/avisos.log`, que es un volumen
persistente, y se ven en Admin → Avisos del scraper.

## Tareas programadas y cola

`routes/console.php`. Las del catálogo `TareasAdmin::PROGRAMADAS` aparecen en Sistema →
Tareas con su última ejecución.

| Tarea | Cuándo |
|---|---|
| Sincronizar partidos (football-data) | Cada 2 min |
| Avisar pendientes (`liga:avisar-pendientes`: recordatorios de cierre y "jornada abierta") | Cada 30 min |
| Actualizar horarios próximos | 08:00 |
| `avisos:enviar-programados` (avisos del admin) | Cada minuto |
| `queue:work --stop-when-empty --max-time=50` | Cada minuto |

No hay un proceso de cola permanente: la cola (`QUEUE_CONNECTION=database`) se vacía cada
minuto con esa tarea. En ella van el scraper de cada partido y lo que se lanza a mano desde
Sistema → Tareas.

## Avisos: campana, móvil y correo

- **Campana**: cada notificación con canal `database`. Las `importante: true` salen en
  rojo **y** marcadas, no solo por el color.
- **Móvil**: el listener `EnviarAvisoAlMovil` escucha `NotificationSent` del canal
  `database` y reenvía **cada aviso de la campana** al móvil (`PushService`), según las
  preferencias del usuario (`users.preferencias_push`). Categorías: cierre, faltas,
  jornada, cartas, chat, reacciones (desactivada por defecto), avisos y admin (solo
  superadmin). Necesita Android, o iPhone/iPad con iOS/iPadOS **16.4 o superior** y la app
  instalada en la pantalla de inicio.
- **Correo** (Brevo): puntos de la jornada (si `recibir_email_puntos`), verificación,
  contraseña. El correo de puntos sale **una vez**, al calcular goleadores
  (`cierres_jornada.puntos_notificados_en`).

## Sesión y autenticación

- **Sanctum en modo SPA**: cookies de sesión (driver `database`) y token CSRF. Los dominios
  de la app van en `SANCTUM_STATEFUL_DOMAINS`.
- **Sesión normal**: caduca tras **2 horas sin actividad** (`SESSION_LIFETIME=120`).
- **"Mantener la sesión iniciada"** (casilla del login, marcada por defecto): cookie de
  recuérdame de **30 días** que se **renueva en cada visita** (`RenovarRecordarSesion`).
  Ese middleware solo renueva cookies válidas y borra una copia antigua duplicada si
  aparece.
- Login limitado a 5 intentos por minuto.
- "Ver como" (`VerComoUsuario`): el superadmin manda `X-Ver-Como`; solo peticiones de
  lectura.

## Tablas principales

| Tabla | Qué guarda |
|---|---|
| `users`, `ligas`, `liga_usuario` (con `rol` Admin/Miembro) | Usuarios, ligas y pertenencia |
| `temporadas`, `equipos`, `jugadores`, `plantilla_temporada` | Datos maestros |
| `calendariopartidos` | Partidos (ojo: sin guion bajo) |
| `eventos_partido`, `alineaciones_jugador`, `estadisticas_partido` | Lo que pasó en cada partido (scraper) |
| `pronosticos`, `goleadores_jornada`, `quinielas_posiciones` | Lo que juega cada usuario |
| `eventos_puntos` | **Todos** los puntos |
| `cierres_jornada` | Estado del cierre por liga y jornada |
| `configuracion_puntos` | Reglas de puntos por liga |
| `categorias_carta`, `tipos_carta`, `cartas_usuario` | Catálogo de cartas y la mano/historial de cada uno |
| `configuracion_cartas_liga`, `rarezas_probabilidad_liga`, `bonus_top3_probabilidad_liga` | Reparto de cartas por liga |
| `codigos_canje`, `canjes_codigo` | Códigos de canje y quién los usó |
| `notifications`, `push_suscripciones`, `avisos_programados` | Avisos |
| `novedades` | Novedades de la portada (por liga) |
| `ejecuciones_tarea` | Registro de tareas para Sistema → Tareas |
| `jobs`, `failed_jobs`, `sessions` | De Laravel |
