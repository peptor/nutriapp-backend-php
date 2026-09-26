# backendphp (API Laravel)

Laravel 13, PHP 8.3+, Sanctum, MySQL (BD local de desenvolupament: `nutricion_rutines_php`).

## Base de dades
- Tota taula nova porta un prefix: `mst_` (definicions i dades de referència: plantilles, biblioteca de rutines i d'aliments), `reg_` (registres que s'acumulen amb el temps: històrics, logs, assignacions) o `sys_` (comptes i control: usuaris, pacients). Exempt: taules internes de Laravel (`cache`, `jobs`, `migrations`, `personal_access_tokens`...).
- Columnes en camelCase (`libraryRoutineId`, `scaleMin`). Els models porten `public static $snakeAttributes = false;` i el trait `HasUuidPrimaryKey` (ids UUID).
- No editar mai migracions ja existents: tot canvi és una migració nova, sempre amb `down()`. Per canviar dades de la biblioteca de rutines, migració de dades que actualitza per `name` de camp (model: `2026_09_26_130000_fix_library_routine_field_types.php`).
- Si es canvia el nom d'una taula, actualitzar el `$table` dels models i tots els `DB::table('...')` en el mateix canvi.

## Codi
- Validació d'entrada a `app/Http/Requests`. Els tipus de camp de rutina (`fieldType`) s'enumeren a tres requests (crear plantilla, actualitzar plantilla, camp de biblioteca) i a l'`enum` de les taules de camps: afegir-ne un implica tocar-ho tot.
- La validació dels valors dels registres és a `app/Support/FieldValueValidator.php` i té un mirall al frontend (`frontend/src/lib/fieldValues.ts`): si en canvies un, canvia l'altre.
- Els missatges que veu l'usuari, en català.
- Camps de rutina setmanals (`frequency = 'weekly'`): un valor per bloc de 7 dies des de l'inici de la rutina (`App\Support\FieldFrequencies`). No bloquegen el desat diari, no compten com a dia registrat a l'adherència (`RoutineProgress`) i, si TOTS els camps d'una rutina són setmanals, l'adherència es compta en setmanes.
- Els camps de la biblioteca tenen `goodDirection`, `unit`, `helpText` i rang d'escala (`scaleMin`/`scaleMax`); `templateFromLibrary` els copia tots: si s'afegeix una columna a la biblioteca, cal copiar-la allà.

## Verificació
- `php -l <fitxer>` per a la sintaxi; `php artisan migrate:status` abans de migrar. Es pot migrar la BD local; a producció només arriba amb el deploy.
- Per provar codi ràpid: `php artisan tinker --execute="..."`. Passar-li un fitxer com a argument es queda penjat esperant entrada.
- `php artisan test` fa servir SQLite en memòria (`phpunit.xml`) i només hi ha `ExampleTest`. No apuntar mai proves a la BD de desenvolupament.
- No executar `deploy-backendphp.sh` (a l'arrel) sense que ho demani l'usuari.
