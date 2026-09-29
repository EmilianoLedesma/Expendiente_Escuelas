# WS-5a — Catálogo de documentos y aplicabilidad (2026-09-29)

Primer sub-plan de WS-5 de la remediación de auditoría (D2, D3a). Rama `worktree-ws5a-catalogo-documentos`, fusionada a `master` con `--no-ff` (`7f1d3c0`). Plan: `docs/superpowers/plans/2026-09-28-ws5a-catalogo-documentos.md` (gitignored). WS-5b (Paso 2.4, documentos por nivel, reubicación del Formato de Solicitud) y WS-5c (reconstrucción del PDF) **no** se tocaron.

## Qué se entregó

1. **Migración `2026_01_02_000002`**: el CHECK de `tipos_documentos.aplica_persona` acepta `fisica_con_gestor` (`responsables_legales.tipo_persona` ya lo permitía; el catálogo no). `up()` además corrige `acta_nacimiento` a `ambas` en bases ya sembradas, porque el seeder usa `insertOrIgnore` y nunca actualiza filas existentes.
2. **`TiposDocumentosSeeder`**: 7 → 13 filas. Nuevas: `acta_constitutiva` (moral), `poder_gestor` (fisica_con_gestor), `visto_bueno_proteccion_civil`, `plano_inmueble`, `certificado_numero_oficial`, `recibo_pago_derechos_plantel` (todas `ambas`, ámbito plantel). `acta_nacimiento` pasó de `fisica` a `ambas` (Requisitos §4). Las filas nuevas se agregan al final: `clavesAplicables()` ordena por `id`, y ese es el orden del checklist.
3. **`DocumentosCompletos::clavesAplicables()`** ahora lee el catálogo (antes, arreglo fijo de 6). Conteos resultantes: fisica 10, moral 12, fisica_con_gestor 11. Con catálogo vacío lanza `RuntimeException` diagnosticable (ver hallazgo I1).
4. **`Paso2Documentos`**: las 6 claves nuevas se suben por el `guardarDocumentoSimple()` existente (lista blanca ∩ `clavesAplicables()`); la vista solo ganó títulos y enrutamiento. El plan proponía 6 métodos `guardarXxx()` nuevos; se descartó por duplicación pura (decisión del controlador, revisada sin objeciones).

## Evidencia

- Suite en `master` tras el merge: **546/546** (1462 aserciones), Pint limpio, PHPStan 0 errores. Base previa: 534 (WS-4).
- Cada tarea: implementador → revisor de tarea. Revisión final de rama con Opus (`e71997d..b6b4c02`): "listo para fusionar con correcciones"; una ola de correcciones; re-revisión acotada (Sonnet) confirmó todo y halló un problema nuevo (bytes Latin-1 en comentarios), corregido en `4423ce4` y verificado por el controlador (UTF-8 válido y sin CRLF en los 17 archivos de la rama). La ola de correcciones (`30d35e6`, `4423ce4`) no fue re-revisada por Opus.
- Dev (`sedeq_incorporacion`), ejecutado por instrucción explícita del dueño: `php artisan migrate --force` (1 migración pendiente, la de esta rama) y `db:seed --class=TiposDocumentosSeeder`. Verificado con SELECT: 13 filas, `acta_nacimiento` = `ambas`, ids 15-20 para las nuevas (los ids 8-14 se consumieron por conflictos de `insertOrIgnore`; solo importa el orden relativo).

## Hallazgos y decisiones

- **La ruptura en cascada fue correcta, no un defecto.** Al crecer el conjunto aplicable, 26 pruebas se rompieron (el plan anticipaba ~8), porque `DocumentosCompletos::paraEscuela()` gobierna el paso 2→3 en todo el proyecto y muchas fixtures registraban solo los 6 documentos antiguos. Se creó la Tarea 5 (fixtures catalog-driven, ningún cambio de código de producción) en lugar de parchear en silencio. Causa raíz compartida: `tests/Concerns/CompletaPaso2.php`.
- **Pruebas vacuas (dos casos)**: tras ampliar el catálogo, las pruebas de "dictamen vencido" en `Paso2ResponsableTest` y `ResumenTramiteTest` seguían pasando aunque el dictamen no estuviera vencido (faltaban 5 documentos y eso ya provocaba la redirección). Ambas se corrigieron con "todos menos dictamen" y se demostró que fallan cuando el dictamen es vigente. El revisor de Opus revisó el resto de fixtures con `subDays(N)`: todas suben el catálogo completo.
- **I1 (Importante) — catálogo vacío fallaba abierto.** `clavesAplicables()` devolvía `[]`, `paraEscuela()` daba `true` y una base migrada pero sin sembrar dejaba pasar a toda escuela al Paso 3. Antes de la rama lanzaba `RuntimeException` (guard añadido tras un incidente real en dev). Restaurado; se eliminó por inalcanzable la rama antigua de fila-faltante en `clavesPendientes()` (ambas listas salen de la misma tabla).
- **I2 (Importante) — `acta_nacimiento` no cambiaba en bases existentes** por `insertOrIgnore`; se resolvió con el `UPDATE` en la migración (idempotente, no-op en base nueva), con prueba que reejecuta `up()`.
- **Error del controlador en el brief de Tarea 1**: se indicó "commitear el DDL en el mismo commit" sin comprobar que `docs/ddl_sistema_incorporacion_v3.sql` está sin versionar por decisión D7; el implementador lo agregó con `git add -f`. Corregido de inmediato (`314e6e9`, `git rm --cached`). Lección: verificar `git ls-files` antes de pedir "commit" de un archivo del DDL.
- Errores de conteo en los briefs del plan (Tarea 3: `moral`=12 y `fisica_con_gestor`=11, no 10) y la predicción de "8 pruebas rotas": ambos detectados por los implementadores y verificados de forma independiente.
- Proceso: el clasificador del modo automático falló de forma transitoria varias veces (incluso en lecturas); el commit de la Tarea 5 lo hizo el dueño con `!`.

## Efectos de despliegue a tener presentes

- Escuelas que ya terminaron el Paso 2 con los 6 documentos antiguos **volverán del Paso 3 a Documentos** hasta subir los nuevos. Es lo aprobado en D3a, no un defecto.
- `down()` de la migración falla si existe alguna fila `fisica_con_gestor` (Postgres revalida el CHECK anterior); la falla es ruidosa y segura, pero bloquea revertir más allá de esta migración.

## Pendiente / no hecho

- **M1 (para WS-5b)**: la vista no tiene rama `@else`; una fila nueva del catálogo sin su rama en el blade contaría como pendiente pero no mostraría control de carga, y el paso no podría completarse. Añadir una prueba "toda clave aplicable renderiza `doc-{clave}` para los 3 tipos de persona" antes de que WS-5b agregue filas.
- La lista de claves en la vista duplica la lista blanca de PHP (limpieza futura: constante compartida).
- `recibo_pago_derechos_plantel` es un marcador que WS-5b debe resolver o eliminar cuando diseñe el recibo por nivel.
- Plantel compartido en dev (`PENDIENTE-plantel-solicitante-cardinalidad.md`): con WS-5a, 4 documentos de plantel más son visibles/sobrescribibles por ambos dueños; misma clase de problema, sin ampliar el mecanismo (nota añadida al PENDIENTE).
- Sin push de `master`.
