# Anexo 2 Instalaciones: T0 verificaciones previas (solo lectura de codigo)

Fecha: 2026-10-27. Rama `worktree-anexo-instalaciones` sobre master `d187441`. Sin cambios de codigo, sin tocar la BD de desarrollo, sin seeders ni migraciones.
Plan: `.superpowers/sdd/2026-10-22-anexo2-instalaciones/task-0-brief.md` (ruling R2: T0 solo del lado del codigo).

## 1. Consumidores de cubiculo / cancha / campo de futbol / area civica (Regla 3)

Rutas relativas a `app-laravel/`.

| Clave / dato | Quien lo lee | Lo lee para Inicial? |
|---|---|---|
| `cubiculo` | `database/seeders/TiposEspaciosSeeder.php:93` (alta); `app/Application/Revision/ContenidoPaso3Revision.php:118` (solo etiqueta de categoria); blade `infraestructura-nivel.blade.php:2,11` (etiquetas) | No. Ni el motor ni las reglas lo leen. |
| `cancha_usos_multiples`, `campo_futbol` (claves) | Solo `TiposEspaciosSeeder.php:96,102`. El flag `permite_campo_futbol` en `CatalogosInfraestructura.php:41,54`, `InfraestructuraNivel.php:407`, blade `:90,216`, `TipoEspacio.php` | No por clave. Si por categoria: ver abajo. |
| `area_civica_m2`, `tiene_asta_bandera` | `RegistrarDatosInmueble.php:62-63`, `DatosInmueble.php:131-132,344-345`, `ContenidoPaso3Revision.php:71-72`, `Plantel.php` | No. El motor no los lee. |
| `reglas_validacion` `*.superficie.*` | `ValidacionCapacidadService.php:71-77` (slug a magnitud de `DatosCapacidadNivel`) | Ninguna regla referencia cubiculo, cancha, campo ni area civica. |

**Hallazgo relevante a la Regla 3 (acoplamiento indirecto):** `ConstruirDatosCapacidad.php:29,62` suma `superficie_m2` de toda la categoria `recreativo_deportivo` del plantel (`sumaEspacios`, `:119-127`) y alimenta `inicial.superficie.area_recreativa` (`ReglasValidacionSeeder.php:79`), ademas de las reglas de area de recreacion de preescolar y secundaria. Cancha y campo de futbol caen en esa suma. Consecuencias para D8:

- La suma es por plantel y no mira `niveles_tipos_espacios`. Quitar cancha/campo del mapeo Inicial no cambia filas ya capturadas ni su aporte, asi que un `cumple` no pasa a `no_evaluable` en tramites ya enviados.
- Solo afecta capturas nuevas de Inicial: ya no podran sumar cancha/campo a `area_recreativa`. Les quedan chapoteadero, arenero, zona_juegos, areas_verdes, area_recreo y otra_recreativa (todas `recreativo_deportivo`).
- Plantel compartido (ADR-009): si un plantel tiene Inicial y Primaria, la cancha capturada via Primaria sigue sumando para ambos niveles.
- `CLAVE_USOS_MULTIPLES = 'salon_usos_multiples'` (`:32,63`) no se toca; sigue aplicando a Inicial.
- `EvaluadorMobiliario`: no existe archivo con ese nombre en `app/`; ademas mobiliario esta fuera de alcance (ruling del controlador).

Veredicto: ningun consumidor lee `cubiculo`, `cancha_usos_multiples`, `campo_futbol` ni area civica por clave para Inicial. El unico acoplamiento es la suma por categoria descrita arriba.

## 2. Biblioteca virtual y `tipos_material_biblioteca`

- **Biblioteca virtual: no se captura hoy.** El grep de "virtual" en `app/`, `database/seeders` y `resources/views` solo encuentra la modalidad del plan de estudios (`RegistrarPlanEstudios.php:23`, `ConsultarTramiteSedeq.php:46`, `ContenidoPaso3Revision.php:209`, `plan-estudios.blade.php:10`). No hay campo en `instalaciones_espacios` (columnas: cantidad, superficie_m2, capacidad_promedio, ventilacion_natural, iluminacion_natural, destinado_a) ni tipo de documento del contrato de servicio virtual en `TiposDocumentosSeeder.php`. Lo mas cercano son `acervo_bibliografico_primaria/secundaria` (`:64-65`), que es la relacion del acervo, no el contrato. El docx tampoco tiene campo virtual.
- **Seed `tipos_material_biblioteca`** (`CatalogoMinimoSeeder.php:43-53`) contra la lista del plan:

| Plan | Seed (clave) |
|---|---|
| libros | `libros` |
| periodicos | `periodicos` |
| revistas | `revistas_especializadas` (nombre "Revistas especializadas") |
| diapositivas | `diapositivas` |
| videos | `videos` |
| peliculas | `peliculas` |
| discos compactos | `discos_compactos` |
| software | `software` |
| otro | `otro` |

Las 9 existen. Unica diferencia: revistas lleva "especializadas" en clave y nombre (el PDF debe imprimir "Revistas").

## 3. `InmuebleEstudioActual` y datos de domicilio

- `app/Models/InmuebleEstudioActual.php`: tabla `inmueble_estudios_actuales`; fillable `plantel_id`, `nivel_educativo_id` (NULL = "otro"), `otro_nivel_texto`, `numero_alumnos`; sin `updated_at`. Coincide con el plan.
- `planteles` (`database/migrations/2026_01_01_*_create_planteles_table.php:11-20`): calle, numero_ext, numero_int, colonia, localidad, municipio, codigo_postal, `telefono`, `correo_electronico`. No hay fax (grep "fax" en app y migraciones: vacio). Coincide: linea en blanco en el PDF.
- `servicios_cercanos` (`..._000014_create_servicios_cercanos_table.php:10-19`): nombre, tipo (`salud`/`emergencia`), es_publico, distancia_valor, distancia_unidad (`m`/`km`). Se reescribe en `RegistrarDatosInmueble.php:78-81`. Coincide con el plan.
- `aulas_nivel` (`..._000028_...`): `escuela_nivel_id`, `numero_aulas`, `superficie_m2`; sin altura (confirma D7, agregado).

## 4. Pagina Infraestructura y matriz en master vs seccion 2 del plan

Archivos: `app/Livewire/Tramite/Paso3/InfraestructuraNivel.php`, `resources/views/livewire/tramite/paso3/infraestructura-nivel.blade.php`, `database/seeders/TiposEspaciosSeeder.php`.

Mapeo actual (`TiposEspaciosSeeder.php:24-38,56`): lo que no esta en `APLICABILIDAD_EXPLICITA` va a los 4 niveles. Inicial hoy tiene: direccion, oficinas_administrativas, control_escolar, filtro_recepcion, **cubiculo**, **cancha_usos_multiples**, chapoteadero, arenero, zona_juegos, areas_verdes, area_recreo, **campo_futbol**, otra_recreativa, salon_usos_multiples, cocina, comedor, otra_especial. Sanitarios Inicial: `alumnado_maternal` y `personal` (`CategoriasSanitariosPorNivel.php:19`). Los conflictos 1, 3 y 5 del plan siguen vigentes tal como los describe su tabla.

Diferencias master vs plan (el plan es del 2026-10-22; master avanzo con ADR-019/020):

1. **"Ya registrada" es editable para el dueno (ADR-019, WS-7b-2).** El plan la trata como solo lectura (ADR-005). En master el dueno la edita (`InfraestructuraNivel.php:84-113,204-235`, `guardarEdicion` a `EditarInfraestructuraCompartida`; blade `:36-175`, con "Quitar este registro al guardar"); el responsable por nivel la ve de solo lectura (blade `:40-42`). Con `bloqueado` (otro tramite enviado) solo se completan campos vacios (blade `:49-51`; `render` `:489-494`). Impacto: el PDF lee lo capturado del plantel, no solo lo que escribio otro nivel.
2. **Stepper por categoria.** La pantalla es un `x-ui.stepper` con un paso por categoria mas "Ya registrada", "Sanitarios" y "Aulas del nivel" (blade `:10-24`). Quitar `cubiculo` de Inicial elimina ese paso para Inicial sin tocar codigo (se calcula de `$tipos->groupBy('categoria')`).
3. **`destinado_a` se etiqueta "Destinado a (opcional)" para todo tipo** (blade `:72,198`); bodega es select limpieza/general/otro (blade `:73-79,199-205`; regla `InfraestructuraNivel.php:138`). D2 (etiqueta "Equipamiento y caracteristicas" para recreativo) exige una etiqueta condicionada por categoria en ambos bloques (captura y edicion); hoy no existe.
4. **Regla 1 y edicion.** El dueno puede quitar cubiculo/cancha de Inicial en el bloque ya registrado; la matriz solo los oculta de "Por capturar". `espaciosCapturados()` (`:339-344`) no filtra por nivel, asi que las filas capturadas siguen visibles aunque el tipo ya no aplique. Compatible con la Regla 1, sin cambios.
5. **Devolucion por secciones (ADR-020) y orden de pasos (ADR-018).** El plan no los menciona. El PDF no depende de ellos, pero "tramite enviado = solo lectura" debe leerse junto con ADR-019 (edicion hasta envio).
6. **Material de biblioteca** tambien se captura en el bloque de edicion (blade `:110-126`, `edicionMateriales`), no solo en "por capturar".
7. **Cubiculo (Regla 5):** sigue siendo un solo tipo "Cubiculo de atencion" (seeder `:93`). Sin cambio respecto al plan.
8. **Sin diferencia** en: conflicto 4 (aulas agregadas, `InfraestructuraNivel.php:132-133`), instalaciones especiales (`APLICABILIDAD_EXPLICITA` coincide con la tabla), bodega, sanitarios (superset) y area civica en Paso 3.1 sin filtro por nivel (`DatosInmueble.php:131`).

Notas para las tareas siguientes: el seeder usa `insertOrIgnore` (`:44,67`), asi que quitar mapeos de Inicial requiere un `DELETE FROM niveles_tipos_espacios` explicito (Regla 2) con prueba de que las `instalaciones_espacios` capturadas sobreviven. Ademas hay que marcar como resuelto lo "inferido" de cancha/campo en `docs/decisions/PENDIENTE-matriz-espacios-por-nivel.md`.

## Auditoria de datos en dev: pendiente de visto bueno del dueno

No ejecutada. Solo `SELECT`. Se correria contra `sedeq_incorporacion` unicamente con el visto bueno del dueno (CLAUDE.md: la BD de dev no se toca sin preguntar). Alternativa: una copia o `sedeq_incorporacion_testing_ui`.

```sql
-- A. Filas fuera de la matriz corregida de Inicial (cubiculo, cancha, campo de futbol) por plantel.
SELECT p.id AS plantel_id, te.clave, count(*) AS filas
FROM instalaciones_espacios ie
JOIN tipos_espacios te ON te.id = ie.tipo_espacio_id
JOIN planteles p ON p.id = ie.plantel_id
WHERE te.clave IN ('cubiculo','cancha_usos_multiples','campo_futbol')
  AND EXISTS (
    SELECT 1 FROM escuelas e
    JOIN escuela_niveles en ON en.escuela_id = e.id
    JOIN niveles_educativos ne ON ne.id = en.nivel_educativo_id AND ne.clave = 'inicial'
    WHERE e.plantel_id = p.id)
GROUP BY p.id, te.clave ORDER BY p.id, te.clave;

-- B. Area civica / asta bandera capturadas en planteles con Inicial.
SELECT p.id, p.area_civica_m2, p.tiene_asta_bandera
FROM planteles p
WHERE (p.area_civica_m2 IS NOT NULL OR p.tiene_asta_bandera IS NOT NULL)
  AND EXISTS (
    SELECT 1 FROM escuelas e
    JOIN escuela_niveles en ON en.escuela_id = e.id
    JOIN niveles_educativos ne ON ne.id = en.nivel_educativo_id AND ne.clave = 'inicial'
    WHERE e.plantel_id = p.id);

-- C. Filas de instalaciones por plantel, categoria y tipo.
SELECT ie.plantel_id, te.categoria, te.clave, count(*) AS filas
FROM instalaciones_espacios ie JOIN tipos_espacios te ON te.id = ie.tipo_espacio_id
GROUP BY 1,2,3 ORDER BY 1,2,3;

-- D. Sanitarios por plantel y categoria.
SELECT plantel_id, categoria, count(*) FROM sanitarios GROUP BY 1,2 ORDER BY 1,2;

-- E. Aulas por escuela_nivel y nivel.
SELECT an.escuela_nivel_id, ne.clave AS nivel, an.numero_aulas, an.superficie_m2
FROM aulas_nivel an
JOIN escuela_niveles en ON en.id = an.escuela_nivel_id
JOIN niveles_educativos ne ON ne.id = en.nivel_educativo_id ORDER BY 1;

-- F. Material de biblioteca por plantel y tipo.
SELECT ie.plantel_id, tm.clave, bm.numero_titulos, bm.numero_volumenes
FROM biblioteca_materiales bm
JOIN instalaciones_espacios ie ON ie.id = bm.instalacion_espacio_id
JOIN tipos_material_biblioteca tm ON tm.id = bm.tipo_material_id ORDER BY 1,2;

-- G. Servicios cercanos por plantel (el PDF imprime hasta 3; todos si hay mas).
SELECT plantel_id, tipo, count(*) FROM servicios_cercanos GROUP BY 1,2 ORDER BY 1,2;

-- H. Estudios actuales por plantel.
SELECT plantel_id, nivel_educativo_id, otro_nivel_texto, numero_alumnos
FROM inmueble_estudios_actuales ORDER BY 1;

-- I. Tramites enviados (en_revision en adelante): solo lectura (WS-7a); deben imprimirse tal cual.
SELECT en.id AS escuela_nivel_id, e.plantel_id, ne.clave AS nivel, ee.clave AS estado
FROM escuela_niveles en
JOIN estados_expediente ee ON ee.id = en.estado_id
JOIN escuelas e ON e.id = en.escuela_id
JOIN niveles_educativos ne ON ne.id = en.nivel_educativo_id
WHERE ee.orden >= 3 ORDER BY 2,1;
```

Los nombres de columna de `biblioteca_materiales` y `sanitarios.plantel_id` se verificaron contra las migraciones. Los de `escuela_niveles` (`escuela_id`, `nivel_educativo_id`) se asumen por las relaciones del modelo; confirmar antes de ejecutar.

## Decisiones D1-D8

Ya resueltas por el dueno el 2026-10-22 (ver brief). T0 no abre preguntas nuevas; solo deja la advertencia de la Regla 3 (suma por categoria `recreativo_deportivo`, seccion 1) para aplicar D8 sabiendo que Inicial pierde cancha/campo solo en capturas nuevas.
