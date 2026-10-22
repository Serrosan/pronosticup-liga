# Desarrollo

Entorno local, tests y cómo se aplican y suben los cambios.

- [Entorno local](#entorno-local)
- [Tests](#tests)
- [Cómo se aplica un cambio](#cómo-se-aplica-un-cambio)
- [Integración continua (GitHub Actions)](#integración-continua-github-actions)
- [Actualizar dependencias](#actualizar-dependencias)
- [Trampas conocidas](#trampas-conocidas)

---

## Entorno local

| | |
|---|---|
| Sistema | Windows + WSL 2 con Ubuntu 24.04 |
| Docker | Docker Engine **dentro de Ubuntu** (no Docker Desktop) |
| Proyecto | `~/proyectos/pronosticup-liga` |
| Backend | **Laravel Sail** (`./vendor/bin/sail`): PHP 8.5 + MySQL |
| Frontend | Contenedor propio en `frontend/` (`docker compose`), servicio `node` |
| Descargas de Windows | `/mnt/c/Users/Sergio/Downloads` |

```bash
cd ~/proyectos/pronosticup-liga
./vendor/bin/sail up -d
(cd frontend && docker compose up -d)
```

- Frontend: http://localhost:5173 · API: http://localhost
- Los correos no se envían: van a `storage/logs/laravel-AAAA-MM-DD.log`.
- Para entrar como un jugador real con una copia de producción: la copia para local deja la
  contraseña `password` a todos. Si no, "olvidé mi contraseña" y sacar el enlace del log.

### Actualizar el PHP de Sail

Sail construye su propia imagen. Para coger un PHP nuevo:

```bash
./vendor/bin/sail build --no-cache && ./vendor/bin/sail up -d && ./vendor/bin/sail php -v | head -1
```

## Tests

```bash
./vendor/bin/sail artisan test --compact
./vendor/bin/sail artisan test --compact --filter=NombreDelTest
```

- Corren sobre **SQLite**, con `RefreshDatabase`.
- Factories disponibles: Temporada, Liga, User, Equipo, CalendarioPartido, Jugador,
  Arbitro, Entrenador, Estadio, Trofeo, Competicion, EventoCalendario.
- **Patrón de los tests del cierre** (`MotorPuntosTest`): crear temporada, liga y admin
  (`liga_activa_id` + `attach` con rol `Admin`), partido "Jugado" con goles, pronósticos,
  y `postJson('/api/v1/jornadas/1/cerrar')`.
- Rutas de admin en tests: `/api/v1/admin/...` con un usuario con `es_superadmin` (ponerlo
  con `forceFill`).
- Peticiones que necesitan sesión o cookies: cabeceras `Origin`/`Referer` de localhost y,
  para mandar cookies en `getJson`/`postJson`, `->withCredentials()`.
- Avisos: `Notification::fake()`. Para los envíos diferidos con `defer()` (avisos al
  móvil): `app(DeferredCallbackCollection::class)->invoke()`.

## Cómo se aplica un cambio

El flujo que usamos:

1. **Volcado de solo lectura**: un bloque que junta en un `.txt` el código que hace falta
   ver.
2. **Entrega en un zip** con su **md5**. Lleva ficheros nuevos y un `parche.py` que cambia
   los existentes:
   - **todo o nada**: si algo no cuadra, no cambia ningún fichero;
   - **idempotente**: lanzarlo dos veces no rompe nada.
3. **Bloque de aplicación**: comprueba el md5, descomprime con `python3 -m zipfile -e`,
   aplica, pasa los tests y el build.
4. **Probar en local**, luego `git add` de los ficheros concretos, commit y push.
5. **Comprobar el despliegue** en Coolify, con Deploy a mano si no salta.

Reglas que salen caras de olvidar:
- **Tests + build antes de subir**, siempre.
- Nunca tocar código que funciona solo por estética.
- No mezclar cambios grandes con el cierre de una jornada.

## Integración continua (GitHub Actions)

| Workflow | Qué hace | Cuándo |
|---|---|---|
| `.github/workflows/tests.yml` | Tests del backend con PHP 8.5 + build del frontend (`ubuntu-24.04`) | Cada push |
| `.github/workflows/dependencias.yml` | `composer audit` + `npm audit` | Lunes 05:00 UTC |

`phpunit.xml` lleva `SANCTUM_STATEFUL_DOMAINS` para que los tests funcionen igual en CI que
en local.

## Actualizar dependencias

Cada pocas semanas, o cuando la auditoría avise:

```bash
# Qué hay
./vendor/bin/sail composer audit
./vendor/bin/sail composer outdated --direct
(cd frontend && docker compose exec -T node npm audit; docker compose exec -T node npm outdated)
```

- **Arreglos y menores** (`!` en composer, columna *Wanted* en npm): se actualizan sin
  miedo.
  - PHP: `sail composer update paquete1 paquete2 --with-all-dependencies`
  - Frontend: `npm update`
- **Saltos grandes** (`~` en composer, *Latest* distinto de *Wanted* en npm), por ejemplo
  PHPUnit 12 → 13: aparte, con calma y leyendo qué cambia.
- Después: tests + build, y subir `composer.lock`, `frontend/package.json` y
  `frontend/package-lock.json`. Coolify instala exactamente esas versiones.
- Imagen de Node local: `cd frontend && docker compose pull && docker compose up -d`.
- Ubuntu de WSL: `sudo apt-get update && sudo apt-get upgrade`. Si actualiza Docker,
  los contenedores se paran: vuelve a arrancarlos.

El aviso de git "dubious ownership" que sale con `sail composer` es inofensivo.

## Trampas conocidas

- **`$fillable`**: un campo que no está ahí se descarta **en silencio**. Primer sospechoso
  de "se guarda pero no cambia nada".
- **Resource**: si un campo se guarda pero no llega al frontend, falta en el `…Resource`.
- **`updated_at`** no cambia si ningún valor cambió de verdad. Para "última vez
  comprobado", usar un campo propio (como `sincronizado_en`).
- **Carbon 3**: `diffInYears()` puede dar negativo; usar `diffInYears($fecha, true)`.
- **Nombres de tabla**: `calendariopartidos`, sin guion bajo. Ante la duda,
  `Schema::getColumnListing('tabla')`.
- **Rutas de admin**: dentro del grupo `prefix('admin')` las rutas no repiten `/admin`. Un
  `grep "/admin/algo"` en `api.php` no las encuentra.
- **Pegar JSX**: a veces se pierde el `<` de la primera etiqueta tras `return (`, y el
  build da ``Expected `,` or `)` but found Identifier``.
- **html2canvas** (compartir clasificación como imagen): no soporta degradados ni hex de 8
  dígitos; solo `rgba()` o hex de 6.
- **Cambios en `jugar()` de Mis cartas**: cualquier camino nuevo hay que revisarlo contra
  **todas** las comprobaciones de la rama "con partido" (jornada empezada, Expulsión, Sin
  comodines, límites…).
- **Antes de borrar algo "sin usar"**: buscar también en `app/Console`, y comprobar si es
  infraestructura para algo futuro.
