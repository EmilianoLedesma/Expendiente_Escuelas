# Informe — Entidad y municipio del Certificado de Número Oficial desde el catálogo geográfico

Fecha: 2026-10-20. Rama: `worktree-numero-oficial-geografia` (sin commit, sin push, sin merge). Continúa `docs/reports/2026-10-20-numero-oficial.md` y reutiliza el catálogo de `docs/decisions/ADR-017-catalogo-geografico-nacional.md`.

## Evaluación: ¿qué campos pueden ser listas desplegables?

| Campo | ¿Lista desplegable? | Motivo |
|---|---|---|
| Entidad federativa | **Sí** | Catálogo nacional local de 32 entidades (ADR-017), el mismo de la notaría en la escritura. |
| Municipio | **Sí** | Catálogo local de 2,478 municipios, dependiente de la entidad. |
| Colonia | No (texto) | No se encontró un servicio oficial de INEGI para colonias; los asentamientos y códigos postales los administra Correos de México (SEPOMEX). Las fuentes de terceros que aparecieron no se pudieron verificar y tienen términos propios. |
| Código postal | No (texto) | Igual: catálogo de Correos de México, no de INEGI; ya se valida el formato de 5 dígitos. |
| Calle, números | No | Datos libres del documento. |

INEGI sí publica un Servicio Web del Catálogo Único de Claves Geoestadísticas (`gaia.inegi.org.mx/wscatgeo`, JSON, entidades, municipios y localidades). **No se usa en línea**: ADR-017 decidió importar el catálogo a tablas propias en lugar de consultar una API en cada solicitud, y el origen actual de la importación es `api_info_mexico` (su estructura coincide con INEGI pero no declara fuente; sigue pendiente contrastar conteos con INEGI). Cambiar el origen de la importación al servicio oficial de INEGI queda como mejora independiente, sin tocar este formulario.

## Qué cambió

- `NumeroOficialForm`: el texto «Municipio» se sustituye por `entidadFederativaId` y `municipioId` (listas dependientes; cambiar la entidad limpia el municipio; ambos obligatorios). Colonia y código postal siguen siendo texto.
- `RegistrarDocumento` es la autoridad: la entidad debe existir, el municipio debe existir y pertenecerle; no hay municipio sin entidad ni entidad sin municipio. Con ambas referencias, el **nombre del municipio se deriva del catálogo en el servidor** (lo que mande el llamador se ignora), así el motor de validación sigue comparando el mismo texto. Un llamador anterior sin referencias conserva el comportamiento con texto.
- Migración `2026_10_20_000400_add_geografia_to_certificados_numero_oficial`: `entidad_federativa_id` y `municipio_id` (FK a las tablas del catálogo, nullable, `ON DELETE SET NULL`). **No ejecutada en la BD de desarrollo.** Los certificados ya capturados conservan su municipio en texto y quedan con NULL en las referencias.
- Vista: dos listas (mismo patrón que la notaría) con aviso si el catálogo está vacío.

## Pruebas

Línea base 1795/1795; después **1810/1810** (6442 aserciones), Pint limpio, PHPStan 0 (tras corregir dos observaciones). Nuevas: `RegistrarNumeroOficialGeografiaTest` (10: referencias, derivación del nombre, texto del llamador ignorado, 5 ubicaciones incoherentes, llamador anterior, migración reversible, reemplazo inválido) y 5 pruebas Livewire en `Paso2NumeroOficialTest`. Se actualizaron las pruebas que escribían el municipio como texto.

## Riesgos

El catálogo debe estar importado (`php artisan geografia:importar`) para que el certificado pueda capturarse; sin él las listas salen vacías y no se puede guardar (el aviso lo indica). `planteles.municipio` sigue siendo texto (ADR-008, trabajo pendiente aparte). La comparación del motor entre plantel y certificado sigue siendo textual y normalizada.
