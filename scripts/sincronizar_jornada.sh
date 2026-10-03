cd ~/proyectos/pronosticup-liga && git checkout feature/cartas && cat > scripts/sincronizar_jornada.sh << 'FIN_SCRIPT'
#!/bin/bash
# Flujo semanal completo, de un tirón: descarga la jornada de LaLiga.com,
# la traduce, te enseña un resumen para revisar, y solo importa si confirmas.
#
# Uso (siempre desde la RAÍZ del proyecto):
#   scripts/sincronizar_jornada.sh          # la jornada que muestre /resultados por defecto
#   scripts/sincronizar_jornada.sh 7        # una jornada concreta
#
# Funciona igual en local (con Sail) y dentro del contenedor de producción
# (sin Sail: ahí usa "php artisan" directamente).
#
# Pensado para ejecutarse 1 sola vez por semana, a mano, justo cuando ya haces
# el cierre de la jornada — nunca en automático, nunca por cron.

set -e

if [ ! -f "./artisan" ]; then
  echo "⚠️  Este script se ejecuta desde la RAÍZ del proyecto, no desde scripts/."
  echo "    Prueba: scripts/sincronizar_jornada.sh $1"
  exit 1
fi

# En local (fuera de Docker, con Sail instalado) se pasa por Sail. Dentro de
# cualquier contenedor —el de producción no tiene Sail— se llama a PHP directo.
if [ -f "./vendor/bin/sail" ] && [ ! -f "/.dockerenv" ]; then
  ARTISAN="./vendor/bin/sail artisan"
else
  ARTISAN="php artisan"
fi

JORNADA="$1"
CARPETA_DESTINO="storage/app/scraper/jornada"
JSON_SALIDA="storage/app/scraper/jornada.json"
PAUSA_SEGUNDOS=3
USER_AGENT="Mozilla/5.0 (compatible; PronostiCupBot/1.0; uso personal, no comercial)"

if [ -n "$JORNADA" ]; then
  URL_RESULTADOS="https://www.laliga.com/laliga-easports/resultados/2026-27/jornada-${JORNADA}"
else
  URL_RESULTADOS="https://www.laliga.com/laliga-easports/resultados"
fi

echo "════════════════════════════════════════════════════"
echo " 1/3 — Descargando"
echo "════════════════════════════════════════════════════"

mkdir -p "$CARPETA_DESTINO"
rm -f "$CARPETA_DESTINO"/*.html

curl -sL -A "$USER_AGENT" "$URL_RESULTADOS" -o /tmp/resultados.html

grep -oE 'href="/partido/temporada-[^"]+"' /tmp/resultados.html \
  | sed -E 's/href="(.+)"/\1/' \
  | sort -u > /tmp/links_partidos.txt

TOTAL=$(wc -l < /tmp/links_partidos.txt)
echo "Encontrados $TOTAL partidos."

if [ "$TOTAL" -eq 0 ]; then
  echo "⚠️  No se encontró ningún link. Revisa /tmp/resultados.html a mano."
  exit 1
fi

CONTADOR=0
while read -r link; do
  CONTADOR=$((CONTADOR + 1))
  slug=$(basename "$link")
  echo "  [$CONTADOR/$TOTAL] $slug"
  curl -sL -A "$USER_AGENT" "https://www.laliga.com${link}" -o "$CARPETA_DESTINO/${slug}.html"
  sleep "$PAUSA_SEGUNDOS"
done < /tmp/links_partidos.txt

# Reintento automático de cualquier archivo que se haya quedado a 0 bytes
# (fallo de red puntual, ya nos pasó una vez con el Atlético-Real Madrid).
for f in "$CARPETA_DESTINO"/*.html; do
  if [ ! -s "$f" ]; then
    echo "  ↻ Reintentando (salió vacío): $(basename "$f")"
    slug=$(basename "$f" .html)
    sleep "$PAUSA_SEGUNDOS"
    curl -sL -A "$USER_AGENT" "https://www.laliga.com/partido/${slug}" -o "$f"
  fi
done

echo
echo "════════════════════════════════════════════════════"
echo " 2/3 — Traduciendo y comprobando"
echo "════════════════════════════════════════════════════"

$ARTISAN liga:preparar-jornada

echo
echo "⚠️  Revisa la lista de arriba: cada partido debería tener entre 10 y 40"
echo "    eventos más o menos. Un 0, o un número de 3 cifras, es sospechoso —"
echo "    para aquí y avisa antes de seguir."
echo
read -p "¿Todo tiene buena pinta? Importar a la base de datos [s/N]: " RESPUESTA

if [[ "$RESPUESTA" != "s" && "$RESPUESTA" != "S" ]]; then
  echo "Cancelado. El JSON queda guardado en ${JSON_SALIDA} por si quieres revisarlo a mano."
  exit 0
fi

echo
echo "════════════════════════════════════════════════════"
echo " 3/3 — Importando"
echo "════════════════════════════════════════════════════"

$ARTISAN liga:importar-partido-detalle "$JSON_SALIDA"

echo
echo "Hecho. Cualquier ⚠️ de arriba son huecos de datos (jugadores sin dar de"
echo "alta, o fechas de plantilla_temporada mal puestas) — revisar a mano, sin prisa."
FIN_SCRIPT
chmod +x scripts/sincronizar_jornada.sh; git branch --show-current; md5sum scripts/sincronizar_jornada.sh; grep -c "ARTISAN" scripts/sincronizar_jornada.sh