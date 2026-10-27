# Anexo 2 Instalaciones: reporte de la sesion

Fecha: 2026-10-27. Rama `worktree-anexo-instalaciones` (base master `d187441`; T0-T5 hasta `3e83a6e`, mas la ola de correcciones finales, seccion 9).
Plan: `docs/superpowers/plans/2026-10-22-anexo2-instalaciones.md`. Ledger SDD: `.superpowers/sdd/2026-10-22-anexo2-instalaciones/progress.md` (local, gitignored). Reporte previo de T0: `docs/reports/2026-10-27-anexo-instalaciones-t0.md`.

## 1. Que se construyo

Commits `d187441..3e83a6e` (18 archivos, 1513 inserciones, 64 borrados segun `git diff --stat`):

| Tarea | Commit | Resultado |
|---|---|---|
| T0 verificaciones | `17d18bf` | Nota de solo lectura de codigo (consumidores de cubiculo/cancha/campo, biblioteca virtual, catalogo de materiales, diferencias de master vs plan). Ver el reporte T0. |
| T1 modelo de lectura | `5163991` | `app/Application/Infraestructura/ConsultarAnexoInstalaciones.php` y DTO `DTO/AnexoInstalaciones.php`. Sin cambio de plantilla por nivel: los tipos aplicables salen de `niveles_tipos_espacios`, las categorias de sanitarios de `CategoriasSanitariosPorNivel`. Cada tipo aplicable tiene linea (en blanco si no hay captura); lo capturado que ya no aplica se imprime en `otros`. |
| T2 PDF generico unico | `50cc9b3` | `app/Infrastructure/Pdf/AnexoInstalacionesPdf.php` y `resources/views/pdf/anexo2-instalaciones.blade.php` (dompdf, mismo patron que el Formato de Solicitud). Pie "Pagina N de M". |
| T3 ruta y politica | `cbcaa88` | `GET /tramite/paso3/{escuelaNivel}/infraestructura/anexo-instalaciones.pdf`, nombre `tramite.paso3.infraestructura.anexo-instalaciones`, controlador invocable `AnexoInstalacionesPdfController`, habilidad `EscuelaNivelPolicy::descargarAnexo` (dueno o responsable de ese nivel; el docblock de la politica ya lo documenta). |
| T4 matriz de Inicial | `a48243d` | `TiposEspaciosSeeder` mapea cubiculo, cancha_usos_multiples y campo_futbol solo a preescolar/primaria/secundaria; migracion de datos `2026_10_27_000000_retirar_espacios_no_aplicables_de_inicial` (DELETE acotado sobre `niveles_tipos_espacios`, idempotente, `down()` reinserta con `insertOrIgnore`). Nota fechada en `PENDIENTE-matriz-espacios-por-nivel.md` (sin renombrar). |
| T5 pagina de Infraestructura | `3e83a6e` | `infraestructura-nivel.blade.php`: orden de paneles del Anexo 2 por nivel, etiqueta "Equipamiento y caracteristicas" en recreativo, enlace "Descargar Anexo" bajo `@can('descargarAnexo')`. Componente PHP sin tocar. |

Orden de paneles (T5): generico = Espacios administrativos, Aulas del nivel, Cubiculos, Sanitarios, Actividades fisicas y recreativas, Instalaciones especiales. Inicial = Espacios administrativos, Aulas del nivel, Sanitarios, Areas recreativas, Instalaciones especiales. "Ya registrada" sigue primero cuando existe. "Guardar y continuar" pasa al ultimo panel.

## 2. Decisiones D1-D8 como se aplicaron

- D1: un solo tipo `cubiculo` de texto libre (`destinado_a`); el PDF mapea las filas a las lineas del docx ("orientacion" y "padres" en lineas fijas, el resto en linea extra) (T2).
- D2: se reutiliza `destinado_a`, etiquetado "Equipamiento y caracteristicas" para la categoria recreativo, en captura y en el bloque editable de "Lo ya capturado" (T5), y como encabezado en el PDF (T2). Sin cambio de esquema, sin ADR.
- D3: descarga solo para dueno y responsable de ese nivel (T3). SEDEQ recibe 403.
- D4: disponible como borrador con blancos; la URL del PDF sigue respondiendo al dueno y al responsable tambien tras el envio (T3, prueba con `en_revision`: 200). El enlace "Descargar Anexo" vive solo en la pagina de Infraestructura, que es editable hasta el envio (`editarPaso`); tras el envio no hay enlace en pantalla (R6, no se agrego a ResumenTramite).
- D5: se conservan los campos de captura; SI/NO solo sale de datos guardados, un renglon sin captura imprime "SI ( ) NO ( )" (T2).
- D6: no existe formulario de Inicial; una sola plantilla generica. Para Inicial se imprimen como lineas extra los renglones capturados propios (filtro/recepcion, alumnado maternal, personal, bacinicas) y no hay cubiculos. No hay blade `inicial` ni cambio de plantilla (ruling R1).
- D7: las aulas por sala NO se capturan; se mantiene el agregado `aulas_nivel` (numero y superficie). Abierto en `docs/decisions/PENDIENTE-aulas-inicial-por-aula.md`.
- D8: correcciones de la matriz de Inicial aplicadas como datos (T4). El area civica se sigue capturando en todos los niveles (Paso 3.1 sigue igual) pero NO se imprime en el PDF de Inicial (la plantilla recibe `nivelClave` y envuelve la tabla en `@unless inicial`); los datos de Inicial ya capturados se conservan.

## 3. Auditoria de datos en dev (solo SELECT)

Autorizada por el dueno el 2026-10-27 (ruling R2 enmendado); transaccion READ ONLY, sin escrituras. Resultados del ledger:

- Plantel 2 (Inicial): una fila de cubiculo, una de cancha_usos_multiples y una de campo_futbol; area civica 44 m2 con asta bandera falsa. El plantel 1 tambien tiene filas de cubiculo, cancha y campo.
- Ambos planteles tienen sanitarios `alumnado_maternal` y `personal`.
- `aulas_nivel`: una fila (nivel 3 inicial, 11 aulas, 363 m2).
- `biblioteca_materiales` vacia. Ningun tramite enviado (`escuela_niveles` con orden >= 3: ninguno).
- `inmueble_estudios_actuales`: 2 filas (50 alumnos cada una). `servicios_cercanos`: 3 y 2 filas.
- Consecuencia: T4 conserva esas filas (Regla 1) y el modelo de lectura las imprime en "otros".

## 4. Evidencia por tarea (de los reportes)

- T1: 7 pruebas escritas primero, fallaron por "Target class does not exist"; luego 7/7 verdes (69 aserciones). Directorios Infraestructura + Architecture 46/46; Pint y PHPStan (0 errores, PHPat incluido).
- T2: 12 pruebas fallaron primero (clase/vista inexistente), luego 12/12. Bloque Pdf + Application/Infraestructura + Architecture + FormatoSolicitudPdf 69/69; Pint y PHPStan 0. Revision visual: PDF de muestra lleno en la BD de pruebas (prueba descartable, ya borrada), renderizado con PyMuPDF: 3 paginas, logo, tablas, marcas X, blancos subrayados, "Pagina 3 de 3" en el pie. La pagina 1 coincide con el docx.
- T3: 9 pruebas en `AnexoInstalacionesPdfControllerTest`. Pdf + Policies + Architecture + FormatoSolicitudPdf 79/79; Pint limpio; PHPStan 0. Pruebas y codigo se escribieron en una sola pasada (fase roja no observada; la revision por mutacion mostro que las pruebas discriminan).
- T4: Database + Application/Infraestructura + Pdf 223 pasan; Livewire/Tramite 354 pasan; PHPStan 0; Pint limpio (BD `testing_ui`). Una corrida combinada previa tuvo un fatal "premature end" en ValidacionFinalTest que paso al repetir (parece fallo de memoria/proceso).
- T5: 6 pruebas nuevas. Livewire/Tramite 360, View 77, Policies 47, Feature/Tramite 101, ResponsableNivelPasosTest 5; Pint y PHPStan limpios. La corrida combinada de `tests/Feature/Livewire` muere con "Premature end of PHP process" (conocido); se corrio por subdirectorio o archivo.
- Revision independiente: T1 a T5 "clean" segun el ledger; T0 sin revision (R4).

## 5. Rulings del controlador

- R1: D6 gana sobre el texto de T1/T2; una sola plantilla `pdf/anexo2-instalaciones.blade.php`, sin blade `inicial`. Costo si se equivoco: una plantilla extra despues.
- R2: la auditoria en dev no se corre sin visto bueno del dueno; enmendado el 2026-10-27 con aprobacion de SELECT solamente.
- R3: mobiliario fuera de este plan (no hay anexo en Downloads); el dueno indicara uno despues.
- R4: revision de T0 omitida (solo una nota en espanol, sin codigo; el controlador re-ejecuto las consultas). Costo: un error factual, se corregiria en T7.
- R5: T6 omitida: T0 encontro los 9 tipos de material sin hueco de catalogo; la palabra "zona de juegos mecanicos" para Inicial es cosmetica y no esta en un formulario fuente. Costo: un cambio de etiqueta despues.

## 6. Menores diferidos (copia del ledger)

- T1: `orderBy('categoria')` redundante en `ConsultarAnexoInstalaciones.php:107` (las lineas salen alfabeticas por nombre, T2 debia saberlo); clave `cubiculo` en SECCIONES vs salida `cubiculos` (:41,135); la prueba de sanitario "otros" podria afirmar capturado/cantidadRetretes (:455); los materiales se indexan globalmente desde `edicionCapturada` (correcto con una biblioteca por plantel); las pruebas de Inicial simulaban la matriz corregida borrando mapeos y T4 debia mantenerlas con sentido.
- T2: M1 sin `page-break-inside: avoid` en filas, la tabla de sanitarios puede partirse; M2 texto largo sin cortes puede desbordar (word-wrap/table-layout); M3 sin pruebas de pie "Pagina N de M", Predio/Construido, escape (`<script>` en calle) ni ausencia de "obligatorio"; M4 comentario sobre `page_text` tras el render; M5 espacios administrativos en orden alfabetico, no el del docx (orden de T1); M6 mezcla de `{{ }}` y `{!! !!}` en la linea 235 del blade. La prueba del pie vale la pena en la ola final de correcciones.
- T3: `routes/web.php` tiene 2 hunks (linea `use` y bloque de ruta); sin prueba de un responsable revocado ni de un responsable sobre tramite enviado (D4); el docblock de la clase `EscuelaNivelPolicy` no menciona `descargarAnexo`; fase roja no observada. SEDEQ recibe 403 por D3: preguntar al dueno si debe poder descargar.
- T4: `RetirarEspaciosInicialTest` arma el estado legado llamando a `down()` (las aserciones tras `up()` son independientes, aceptable). La BD de dev conserva los mapeos viejos: la migracion necesita visto bueno del dueno. El cambio de fixture en `RegistrarInfraestructuraNivelTest` (campo_futbol fuera de la rama Inicial) se juzgo legitimo.
- T5: doble linea en blanco antes del comentario `$indice++` en `infraestructura-nivel.blade.php`; sin prueba del enlace en tramite enviado de solo lectura (D4) ni de que el boton de guardar este ausente en solo lectura (ambos sin cambio en el diff); "Guardar y continuar" paso al ultimo panel (aserciones ajustadas, legitimo).

## 7. Lo que se dejo sin hacer, a proposito

- T6 omitida (R5).
- Mobiliario fuera de alcance: no hay anexo de mobiliario en Downloads (R3).
- La migracion `2026_10_27_000000_retirar_espacios_no_aplicables_de_inicial` NO se ha corrido en la base de desarrollo; requiere visto bueno del dueno. Hasta entonces dev conserva el mapeo viejo de Inicial.
- El SEDEQ no puede descargar el Anexo (D3); decision del dueno si se quiere.
- No se vio en navegador (no se pidio permiso para abrirlo).
- El PDF solo se contrasto con el docx en la pagina 1; las demas paginas no se compararon una por una.
- Aulas por sala de Inicial (D7): pendiente en `PENDIENTE-aulas-inicial-por-aula.md` (alcance WS-6).
- `docs/progress.md` no se toca aqui; lo escribe el controlador al fusionar.

## 8. Propuesta de recorrido manual (previo al merge)

1. Con el dueno de un tramite con Primaria, abrir Paso 3.2: los paneles siguen el orden generico, el de actividades fisicas pide "Equipamiento y caracteristicas", y aparece "Descargar Anexo".
2. Pulsar "Descargar Anexo": abre un PDF de 3-4 paginas con blancos donde falta captura y pie "Pagina N de M"; comparar la pagina 1 con el docx.
3. Con un responsable del mismo nivel: el enlace aparece y descarga; con otro solicitante o con SEDEQ: 403.
4. En un nivel Inicial nuevo (BD de pruebas con la migracion aplicada): no hay Cubiculos ni cancha/campo en "Por capturar"; los renglones propios de Inicial salen como extras en el PDF.
5. En una BD de copia con filas legadas de cubiculo/cancha/campo en Inicial: siguen visibles en "Lo ya capturado" y salen en "Otras instalaciones capturadas" del PDF.
6. Solo con visto bueno del dueno: correr la migracion en dev y repetir los SELECT de la auditoria para confirmar que las filas capturadas no cambian.

## 9. Ola de correcciones finales (revision final, R6 y R7)

- Area civica oculta en el PDF de Inicial (R7); sigue capturandose y se imprime en los demas niveles.
- Capas (ADR-001): el controlador corre `ConsultarAnexoInstalaciones` y pasa el DTO a `AnexoInstalacionesPdf::generar(EscuelaNivel, AnexoInstalaciones)`. La clase de Infrastructure ya no invoca el caso de uso; solo conserva el import del DTO (que vive en Application) para tiparlo.
- "Otras instalaciones capturadas" imprime tambien superficie y formato de un campo de futbol legado.
- CSS del PDF: `tr { page-break-inside: avoid; }` y `td { word-wrap: break-word; }`. La tabla de sanitarios puede seguir partiendose entre paginas (dompdf no la mantiene junta de forma simple); no se uso `table-layout: fixed` porque las columnas no son fijas.
- Docblock de `EscuelaNivelPolicy` con `descargarAnexo`; `orderBy('categoria')` redundante eliminado; doble linea en blanco quitada de `infraestructura-nivel.blade.php`; comentario sobre por que el script de pie corre tras el render.
- `docs/guia-recorrido-app.md`: el enlace solo aparece en la pagina de Infraestructura mientras es editable; la URL sigue respondiendo tras el envio (R6).
- Pruebas nuevas: area civica oculta en Inicial y presente en generico, campo de futbol legado en "Otros", Predio/Construido, escape de `<script>` en calle y `destinado_a`, ausencia de "obligatorio", pie "Pagina N de M" verificado descomprimiendo los streams del PDF generado, responsable revocado recibe 403. Se elimino el helper `inicialSinCubiculo()` (el seeder ya no mapea cubiculo a Inicial).
- Diferidos que quedan: M5 (administrativos alfabeticos, no en el orden del docx), M6 (estilo mixto de escape), remapeo de clave `cubiculo`/`cubiculos`, prueba del enlace en tramite enviado (no requerido: no hay enlace tras el envio).
