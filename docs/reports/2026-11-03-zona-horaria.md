# Zona horaria de todo el proyecto: Ciudad de México — 2026-11-03

Rama `worktree-zona-horaria`. Decisión del dueño: todo el proyecto usa hora de Ciudad de México, no solo algunas pantallas; las filas anteriores se dejan como están.

## Qué pasaba

- `config/app.php` tenía `UTC`: todo lo que escribe Laravel (`created_at`, `archivado_at`, bitácora) se guardaba en UTC y las pantallas lo mostraban en UTC (el trámite archivado de prueba decía 21:57 cuando eran las 15:57).
- La sesión de Postgres corría en `America/Mexico_City` solo por el valor por defecto del servidor local. El esquema tiene 38 columnas `DEFAULT now()` de tipo `timestamp without time zone`, así que lo que llena la base quedaba en hora de México y lo que llena Laravel en UTC: una misma tabla podía mezclar los dos relojes.

## Qué se cambió

- `config/app.php`: `timezone` = `America/Mexico_City`. Afecta a todo el proyecto: `now()`, Carbon, escrituras de Eloquent, Filament, Livewire, PDF, reglas de vigencia y cola.
- `config/database.php` (conexión `pgsql`): `timezone` = `America/Mexico_City`, para que la sesión de Postgres no dependa del servidor (CI corre con otro valor por defecto) y los `DEFAULT now()` coincidan con Laravel.
- Prueba nueva `tests/Feature/ZonaHorariaTest.php`: la app y la sesión de Postgres están en Ciudad de México y Laravel y `now()` de la base escriben el mismo reloj. Se vio fallar antes del cambio (diferencia de 21 599 s, unas 6 horas).

## Verificación

Pruebas enfocadas (la suite completa la corre CI): ZonaHoraria, Admin, Archivo, Revision (140), Application/Documentos, Captura y Validacion (353, incluyen las pruebas de vigencia con fechas fijas) y cada archivo de `tests/Feature/Tramite` en su propio proceso (11 archivos, 0 fallos). Pint y PHPStan limpios.

## Lo que se dejó sin hacer

- **Filas anteriores:** las que Laravel escribió antes del cambio están en UTC y se leerán 6 horas antes de lo real (por ejemplo el trámite de prueba archivado el 2026-11-03 a las 21:57 UTC). Decisión del dueño: no se migran; son datos de desarrollo. Las filas llenadas por `DEFAULT now()` ya estaban en hora de México y quedan correctas.
- Ciudad de México no tiene horario de verano desde 2022 (UTC−6 fijo), así que las horas guardadas sin zona no son ambiguas.
- Si algún día hay datos reales en UTC, hará falta una migración de datos que reste 6 horas a las columnas escritas por Laravel.
