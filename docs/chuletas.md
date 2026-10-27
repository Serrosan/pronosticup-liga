# Chuletas

Lo que más se usa, listo para copiar. Si un comando necesita contexto, el enlace lleva a
la explicación completa.

- [Local](#local)
- [Servidor de producción](#servidor-de-producción)
- [Comprobaciones desde fuera (curl)](#comprobaciones-desde-fuera-curl)
- [Base de datos (HeidiSQL)](#base-de-datos-heidisql)
- [Git y despliegue](#git-y-despliegue)
- [Cuando algo falla](#cuando-algo-falla)

---

## Local

Todo desde `~/proyectos/pronosticup-liga`, en la terminal de **Ubuntu (WSL)**, no en
PowerShell.

```bash
# Arrancar / parar
./vendor/bin/sail up -d
(cd frontend && docker compose up -d)
./vendor/bin/sail down

# Tests y build (lo mínimo antes de subir nada)
./vendor/bin/sail artisan test --compact
(cd frontend && docker compose exec -T node npx vite build --outDir /tmp/build-prueba --emptyOutDir)

# Un solo test
./vendor/bin/sail artisan test --compact --filter=MotorFaltasTest

# Migraciones
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan migrate:status | tail -5

# Consola de Laravel (para mirar datos con Eloquent)
./vendor/bin/sail artisan tinker

# Tareas programadas tal como las ve Laravel
./vendor/bin/sail artisan schedule:list

# Logs de errores (uno por día)
tail -50 storage/logs/laravel-$(date +%F).log

# Correos en local: no se envían, van al log
grep -A40 "Subject:" storage/logs/laravel-$(date +%F).log | tail -60
```

### Ver un correo sin enviarlo

Genera el correo de puntos real de un usuario y una jornada y lo deja en Descargas para
abrirlo con el navegador:

```bash
./vendor/bin/sail artisan tinker --execute='
$u = App\Models\User::find(1); $liga = App\Models\Liga::find(5); $j = 9;
$s = app(App\Services\ResumenPuntosJornadaService::class); $r = $s->paraUsuario($s->contexto($liga, $j), $u);
$n = new App\Notifications\JornadaCerradaConPuntos($j, $r["total"], $r["posicion"], $liga->nombre, $r);
file_put_contents(storage_path("app/preview-correo.html"), (string) $n->toMail($u)->render());' \
&& cp storage/app/preview-correo.html /mnt/c/Users/Sergio/Downloads/
```

### ¿Qué PHP ofrece el repositorio de Ondřej ahora mismo?

Útil cuando sale una versión de seguridad de PHP. Guardado en `~/comprobar-php.sh`;
el script completo está en [Producción → Actualizar PHP](04-produccion.md#actualizar-php).

```bash
~/comprobar-php.sh        # ✅ Ya está: 8.5.x  /  ⏳ Todavía no  /  ⚠️ repositorio saturado
```

---

## Servidor de producción

Entrar por SSH como siempre. La **terminal de Coolify** puede dar "Terminal access was
rejected": por SSH llegas a lo mismo.

```bash
# El contenedor de la app cambia de nombre en cada despliegue: búscalo así
C=$(docker ps --format '{{.Names}}' | grep '^app-rvkaf529yxuu1tps40sewnsc' | head -1); echo "$C"

# Versión de PHP (la web usa php-fpm; las dos deben coincidir)
docker exec "$C" sh -c 'php -v | head -1; php-fpm8.5 -v | head -1'

# Ajustes de seguridad de PHP cargados
docker exec "$C" php -r 'echo "upload_progress=", ini_get("session.upload_progress.enabled") ?: "0", PHP_EOL;'

# ¿Hay algún .php donde no debería? (no debe salir nada)
docker exec "$C" find /var/www/html/storage/app /var/www/html/public -name "*.php" ! -path /var/www/html/public/index.php

# Logs
docker exec "$C" sh -c 'tail -50 /var/www/html/storage/logs/laravel-$(date +%F).log'
docker exec "$C" tail -50 /var/www/html/storage/app/scraper/avisos.log

# Tareas programadas y cola
docker exec "$C" php artisan schedule:list
docker exec "$C" php artisan queue:failed

# Comandos de artisan dentro del contenedor
docker exec -it -w /var/www/html "$C" php artisan liga:ver-detalle-partido 84
docker exec -it -w /var/www/html "$C" bash scripts/sincronizar_jornada.sh 9

# Espacio en disco del servidor
df -h / && docker system df
```

> El nombre que va en `C=` es el de la **primera columna** de `docker ps` (`app-…-fecha`),
> no el de la imagen (`…_app:hash`).

---

## Comprobaciones desde fuera (curl)

Desde Ubuntu (WSL). En PowerShell, `curl` es otro comando: usa `curl.exe`.

```bash
# La app está viva (debe dar 200)
curl -s -o /dev/null -w "%{http_code}\n" https://www.pronosticup.es/up

# Filtro de seguridad de nginx: un envío con archivos desde fuera de la app (debe dar 403)
curl -s -o /dev/null -w "%{http_code}\n" -F a=b https://www.pronosticup.es/api/v1/chat

# Un .php que no es index.php (debe dar 404)
curl -s -o /dev/null -w "%{http_code}\n" https://www.pronosticup.es/storage/x.php

# Cabeceras: que no se anuncie la versión de PHP (no debe salir X-Powered-By)
curl -sI https://www.pronosticup.es/ | grep -i -E "x-powered-by|server"

# Versión desplegada (fecha del build)
curl -s https://www.pronosticup.es/version.json; echo

# Sin www redirige a www (debe salir location: https://www.pronosticup.es/)
curl -sI https://pronosticup.es/ | grep -i location
```

---

## Base de datos (HeidiSQL)

Conexión por túnel SSH con `plink.exe` (ver [Producción → HeidiSQL](04-produccion.md#base-de-datos-y-heidisql)).
Estas consultas **solo leen**. Si alguna columna no cuadra: `DESCRIBE nombre_tabla;`.

**Cambia los números** (`@liga`, `@jornada`) antes de ejecutar.

```sql
SET @liga = 5, @jornada = 9;

-- Estado del cierre de cada jornada de la liga
SELECT jornada, cerrada, cerrada_en, goleadores_calculados_en, puntos_notificados_en
FROM cierres_jornada WHERE id_liga = @liga ORDER BY jornada;

-- Partidos de una jornada y su estado
SELECT p.id, el.nombre AS local, ev.nombre AS visitante, p.goles_casa, p.goles_fuera, p.estado, p.horario_estimado
FROM calendariopartidos p
JOIN equipos el ON el.id = p.id_equipo_local
JOIN equipos ev ON ev.id = p.id_equipo_visitante
WHERE p.jornada = @jornada ORDER BY p.horario_estimado;

-- Cuántos partidos ha pronosticado cada uno en la jornada
SELECT u.id, u.name, COUNT(pr.id) AS pronosticados
FROM liga_usuario lu
JOIN users u ON u.id = lu.id_usuario
LEFT JOIN pronosticos pr ON pr.id_usuario = u.id AND pr.id_liga = lu.id_liga
     AND pr.id_partido IN (SELECT id FROM calendariopartidos WHERE jornada = @jornada)
WHERE lu.id_liga = @liga GROUP BY u.id, u.name ORDER BY pronosticados;

-- Puntos de la jornada por usuario y tipo
SELECT u.name, e.tipo_evento, COUNT(*) AS veces, SUM(e.puntos) AS puntos
FROM eventos_puntos e JOIN users u ON u.id = e.id_usuario
WHERE e.id_liga = @liga AND e.jornada = @jornada
GROUP BY u.name, e.tipo_evento ORDER BY u.name, e.tipo_evento;

-- Clasificación total de la liga
SELECT u.name, SUM(e.puntos) AS total
FROM eventos_puntos e JOIN users u ON u.id = e.id_usuario
WHERE e.id_liga = @liga GROUP BY u.name ORDER BY total DESC;

-- Cartas que afectan a una jornada (jugadas, faltas, resueltas…)
SELECT c.id, u.name AS dueno, t.nombre AS carta, t.codigo_efecto, c.estado,
       c.id_partido, obj.name AS objetivo, c.puntos_generados
FROM cartas_usuario c
JOIN users u ON u.id = c.id_usuario
JOIN tipos_carta t ON t.id = c.id_tipo_carta
LEFT JOIN users obj ON obj.id = c.id_usuario_objetivo
WHERE c.id_liga = @liga AND c.jornada_efecto = @jornada ORDER BY c.estado, u.name;

-- Faltas activas ahora mismo (jugadas contra otro y sin resolver)
SELECT c.id, u.name AS atacante, obj.name AS objetivo, t.nombre, c.jornada_efecto
FROM cartas_usuario c
JOIN users u ON u.id = c.id_usuario
JOIN users obj ON obj.id = c.id_usuario_objetivo
JOIN tipos_carta t ON t.id = c.id_tipo_carta
WHERE c.id_liga = @liga AND c.estado = 'jugada' AND c.id_usuario <> c.id_usuario_objetivo;

-- Mano de cada jugador (cartas sin jugar)
SELECT u.name, COUNT(*) AS en_mano
FROM cartas_usuario c JOIN users u ON u.id = c.id_usuario
WHERE c.id_liga = @liga AND c.estado = 'en_mano' GROUP BY u.name;

-- Quién tiene "mantener la sesión" (recuérdame) activo
SELECT id, name, remember_token IS NOT NULL AS tiene_token FROM users ORDER BY id;

-- Cola y tareas: encargos esperando y fallidos
SELECT COUNT(*) AS en_cola FROM jobs;
SELECT id, failed_at, LEFT(exception, 200) AS error FROM failed_jobs ORDER BY failed_at DESC LIMIT 10;

-- Avisos del admin programados que aún no han salido
SELECT * FROM avisos_programados ORDER BY id DESC LIMIT 10;

-- Dispositivos con avisos en el móvil activados
SELECT COUNT(*) FROM push_suscripciones;
```

> Cambiar datos en producción con SQL solo en casos puntuales y sabiendo exactamente qué
> filas se tocan: primero el `SELECT` con el mismo `WHERE`, después el `UPDATE`.

---

## Git y despliegue

```bash
git status --short
git log --oneline -10
git diff --stat

# Subir (Coolify despliega solo al hacer push… casi siempre)
git add <ficheros> && git commit -q -m "Mensaje" && git push -q && echo "✅ Subido"
```

Si en Coolify el último despliegue no es el de tu commit: **Deploy a mano** en la app.
Ver [Producción → Desplegar](04-produccion.md#desplegar).

---

## Cuando algo falla

| Síntoma | Causa habitual | Qué hacer |
|---|---|---|
| `Sail is not running` | Sail parado | `./vendor/bin/sail up -d` |
| `service "node" is not running` | Contenedor de Vite parado | `cd frontend && docker compose up -d` |
| `UnableToCreateDirectory` / permisos en `storage` | Ficheros creados con otro usuario | `sudo chown -R "$USER:$USER" storage bootstrap/cache` y `sail down && sail up -d` |
| Tests: `Session store not set` | Petición de test no "stateful" | Mandar cabeceras `Origin`/`Referer` de localhost; `phpunit.xml` lleva `SANCTUM_STATEFUL_DOMAINS` |
| Tests: la cookie no llega en `getJson`/`postJson` | Laravel no manda cookies en peticiones JSON de test | Añadir `->withCredentials()` |
| Build: ``Expected `,` or `)` but found Identifier`` justo tras pegar JSX | Se perdió el `<` de la primera etiqueta al pegar | Revisar la línea indicada; suele faltar `<` delante de `a`, `div`… |
| "Se guarda pero no cambia nada" | El campo no está en `$fillable` | Añadirlo al modelo (Eloquent lo descarta en silencio) |
| Se guarda pero no se ve en la app | El campo no está en el Resource | Añadirlo al `…Resource` |
| Coolify no despliega tras el push | No llegó el aviso de GitHub | **Deploy a mano** |
| Los resultados no se actualizan (sin errores) | football-data no ha actualizado esos partidos (siguen `TIMED`) | Avisa la campana («⏱️ Un partido no se ha actualizado»). Esperar; si no llega antes del cierre, Admin → Calendario a mano |
| Build de Docker con `503 Service Unavailable` | Repositorio de PHP saturado | Reintentar más tarde o usar el espejo (ya configurado) |
| `curl: Invoke-WebRequest…` | Estás en PowerShell | Usar la terminal de Ubuntu o `curl.exe` |
| `docker exec … No such container` | Pusiste el nombre de la imagen | Usar la 1ª columna de `docker ps` o el `C=$(…)` de arriba |
| Un jugador dice que "siempre tiene que volver a entrar" | Cookie de recuérdame vieja duplicada | Se limpia sola al volver a iniciar sesión con la casilla marcada |
| Algo raro con una carta | — | Admin → **Rastro de cartas** (por qué puntuó o no) |
| No llega el aviso de fin de partido | Categoría apagada o sin avisos en ese dispositivo | Perfil → Avisos en el móvil: activar "⚽ Resultado de cada partido". Sale una sola vez por partido |
| La franja "Hay una versión nueva" vuelve tras actualizar | Caché que sirve la página vieja | Comprobar `version.json` (chuleta de arriba) y recargar a fondo; mirar la consola (F12) |
| «Los goles no cuadran» en Cierre de jornada | Goleador sin emparejar o datos de otro partido | Ver [Operación → Cuando algo no cuadra](03-operacion-admin.md#cuando-algo-no-cuadra) |
| `Command 'unzip' not found` | WSL sin `unzip` | `python3 -c "import zipfile,sys; zipfile.ZipFile(sys.argv[1]).extractall('.')" fichero.zip` |
| `Command 'npm' not found` | El frontend se compila en Docker | `docker run --rm -v "$PWD/frontend":/app -w /app node:22-alpine sh -c "npm ci && npm run build"` (mejor sobre una copia) |
| Puntos que no cuadran | — | Admin → Cierre de jornada → **Auditar** (recalcula en seco y compara) |
