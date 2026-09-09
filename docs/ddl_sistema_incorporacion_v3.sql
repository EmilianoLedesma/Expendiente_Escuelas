-- ============================================================================
-- DDL: Sistema de Incorporación de Escuelas (SEDEQ) — v3 (impulsado por catálogos)
-- Motor: PostgreSQL 15+
-- Convención: snake_case, nombres de tabla en plural (convención Laravel/Eloquent)
--
-- CAMBIOS v2 → v3 (el principio rector: los formularios, el checklist dinámico y
-- las validaciones de Básica deben ser IMPULSADOS POR LA BASE DE DATOS, no
-- hardcodeados en el código de la aplicación):
--   1. `cargos_puestos`: catálogo de cargos/puestos válidos POR NIVEL. Reemplaza
--      el texto libre `cargo_puesto` en `personal` y `perfiles_profesionales`.
--      Impulsa el selector dinámico del Anexo 1 según el nivel seleccionado.
--   2. `niveles_tipos_espacios`: tabla puente que define qué tipos de espacio
--      (biblioteca, laboratorio, etc.) aplican y son obligatorios por nivel.
--      Impulsa el formulario dinámico del sub-paso 2 (Infraestructura).
--   3. `mobiliario_conceptos`: catálogo de mobiliario/equipo esperado por sala,
--      con su ratio normativo. `mobiliario_nivel` ahora referencia este catálogo
--      en vez de texto libre, permitiendo validación automática cantidad-vs-ratio.
--
-- CAMBIOS v1 → v2 (corrección de violaciones de normalización):
--   1. Catálogo `salas` en vez de texto libre repetido en 3 tablas.
--   2. `responsables_legales` dividido en tabla base + subtipos (personas_fisicas /
--      personas_morales), igual patrón que ya se usaba en `gestores`.
--   3. `documentos` dividido en 3 tablas (plantel/escuela/escuela_nivel) con FK
--      estricta, eliminando el patrón polimórfico (documentable_type/id).
--   4. `instalaciones_espacios`, `sanitarios`, `personal` y `matriculas` divididos
--      en tabla base + extensiones atómicas, eliminando columnas dispersas tipo EAV.
--   5. Campos agregados que faltaban en v1: turno, tipo_alumnado,
--      inmueble_estudios_actuales, recibos_pago_derechos.
-- ============================================================================

-- ============================================================================
-- SECCIÓN 1: CATÁLOGOS
-- ============================================================================

CREATE TABLE niveles_educativos (
    id              SMALLSERIAL PRIMARY KEY,
    clave           VARCHAR(30) NOT NULL UNIQUE,
    nombre          VARCHAR(100) NOT NULL,
    orden           SMALLINT NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT now(),
    updated_at      TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE tipos_documentos (
    id                  SERIAL PRIMARY KEY,
    clave               VARCHAR(60) NOT NULL UNIQUE,
    nombre              VARCHAR(150) NOT NULL,
    aplica_persona      VARCHAR(20) NOT NULL DEFAULT 'ambas'
                            CHECK (aplica_persona IN ('fisica', 'moral', 'ambas')),
    nivel_educativo_id  SMALLINT REFERENCES niveles_educativos(id),
    requiere_pdf        BOOLEAN NOT NULL DEFAULT true,
    vigencia_max_dias   INTEGER,
    ambito              VARCHAR(20) NOT NULL
                            CHECK (ambito IN ('plantel', 'escuela', 'escuela_nivel')),
    created_at          TIMESTAMP NOT NULL DEFAULT now(),
    updated_at          TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE asignaturas (
    id      SERIAL PRIMARY KEY,
    nombre  VARCHAR(100) NOT NULL UNIQUE
);

-- Catálogo de cargos/puestos válidos POR NIVEL — impulsa el selector "Cargo/Puesto"
-- del Anexo 1 en el formulario (qué opciones mostrar según el nivel seleccionado)
-- y es la referencia que usan perfiles_profesionales y personal, en vez de texto libre.
CREATE TABLE cargos_puestos (
    id                  SERIAL PRIMARY KEY,
    nivel_educativo_id  SMALLINT NOT NULL REFERENCES niveles_educativos(id),
    nombre              VARCHAR(100) NOT NULL,   -- "Director Técnico", "Docente Titular de Grupo", "Responsable de Sala", "Asistente Educativo"
    requiere_asignatura BOOLEAN NOT NULL DEFAULT false,  -- true solo para Secundaria (docente por asignatura)
    requiere_sala       BOOLEAN NOT NULL DEFAULT false,  -- true solo para Inicial (responsable/asistente por sala)
    UNIQUE (nivel_educativo_id, nombre)
);

-- Catálogo normativo de salas de Educación Inicial (edad y nombre son datos fijos,
-- no deben repetirse como texto libre en otras tablas)
CREATE TABLE salas (
    id              SMALLSERIAL PRIMARY KEY,
    clave           VARCHAR(20) NOT NULL UNIQUE,   -- lactantes_a, lactantes_b, lactantes_c, maternal_a, maternal_b
    nombre          VARCHAR(50) NOT NULL,           -- "Lactantes A"
    edad_min_meses  SMALLINT NOT NULL,
    edad_max_meses  SMALLINT NOT NULL,
    orden           SMALLINT NOT NULL
);

-- Catálogo de grados por nivel (1°, 2°, 3°... aplica a Preescolar/Primaria/Secundaria)
CREATE TABLE grados (
    id                  SERIAL PRIMARY KEY,
    nivel_educativo_id  SMALLINT NOT NULL REFERENCES niveles_educativos(id),
    nombre              VARCHAR(20) NOT NULL,   -- "1°", "2°", etc.
    orden               SMALLINT NOT NULL,
    UNIQUE (nivel_educativo_id, orden)
);

-- Perfiles profesionales aceptados por cargo/asignatura (catálogo de validación
-- derivado de los Profesiogramas; el solicitante nunca lo llena directamente)
CREATE TABLE perfiles_profesionales (
    id                      SERIAL PRIMARY KEY,
    cargo_puesto_id         INTEGER NOT NULL REFERENCES cargos_puestos(id),
    asignatura_id           INTEGER REFERENCES asignaturas(id),  -- NULL salvo Secundaria
    carrera_aceptada        VARCHAR(200) NOT NULL,
    documento_acreditacion  VARCHAR(20) NOT NULL
                                CHECK (documento_acreditacion IN ('titulo_cedula', 'certificado')),
    created_at              TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE tipos_espacios (
    id                  SERIAL PRIMARY KEY,
    clave               VARCHAR(60) NOT NULL UNIQUE,
    nombre              VARCHAR(150) NOT NULL,
    categoria           VARCHAR(30) NOT NULL
                            CHECK (categoria IN ('administrativo', 'cubiculo', 'recreativo_deportivo', 'especial')),
    permite_campo_futbol    BOOLEAN NOT NULL DEFAULT false,
    permite_material_biblioteca BOOLEAN NOT NULL DEFAULT false
);

-- Impulsa dinámicamente el sub-paso 2 (Infraestructura) del formulario: qué tipos
-- de espacio se muestran/exigen para cada nivel educativo (ej. "biblioteca" solo
-- aplica a Primaria/Secundaria; "laboratorio polifuncional" solo a Secundaria/Media
-- Superior). Sin esta tabla, esa lógica quedaría hardcodeada en el código de la app.
CREATE TABLE niveles_tipos_espacios (
    nivel_educativo_id  SMALLINT NOT NULL REFERENCES niveles_educativos(id),
    tipo_espacio_id     INTEGER NOT NULL REFERENCES tipos_espacios(id),
    obligatorio         BOOLEAN NOT NULL DEFAULT false,
    PRIMARY KEY (nivel_educativo_id, tipo_espacio_id)
);

CREATE TABLE tipos_material_biblioteca (
    id      SMALLSERIAL PRIMARY KEY,
    clave   VARCHAR(30) NOT NULL UNIQUE,  -- libros, periodicos, revistas_especializadas, diapositivas, videos, peliculas, discos_compactos, software, otro
    nombre  VARCHAR(100) NOT NULL
);

CREATE TABLE reglas_validacion (
    id                  SERIAL PRIMARY KEY,
    clave               VARCHAR(100) NOT NULL UNIQUE,
    nivel_educativo_id  SMALLINT NOT NULL REFERENCES niveles_educativos(id),
    tipo_regla          VARCHAR(30) NOT NULL
                            CHECK (tipo_regla IN ('superficie', 'personal', 'mobiliario', 'infraestructura')),
    tipo_calculo        VARCHAR(30) NOT NULL
                            CHECK (tipo_calculo IN (
                                'ratio_por_alumno', 'ratio_por_grado', 'minimo_fijo',
                                'adicional_fijo', 'factor', 'personal_obligatorio',
                                'personal_umbral', 'personal_proporcional', 'personal_por_espacio'
                            )),
    ambito              VARCHAR(20) NOT NULL
                            CHECK (ambito IN ('aula', 'sala', 'plantel', 'escuela', 'predio')),
    redondeo            VARCHAR(10) NOT NULL
                            CHECK (redondeo IN ('arriba', 'abajo', 'na')),
    concepto            TEXT NOT NULL,
    cargo_puesto_id     INTEGER REFERENCES cargos_puestos(id),
    condicion_min       NUMERIC(10,2),
    condicion_max       NUMERIC(10,2),
    valor_numerico      NUMERIC(10,2) NOT NULL,
    unidad              VARCHAR(30) NOT NULL,
    fuente              TEXT NOT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT now()
);

-- Catálogo de mobiliario/equipo obligatorio por sala — impulsa tanto el formulario
-- del sub-paso 3 (qué artículos capturar según la sala/nivel) como el Motor de
-- Validación (ratio esperado vs. cantidad declarada en mobiliario_nivel).
CREATE TABLE mobiliario_conceptos (
    id              SERIAL PRIMARY KEY,
    sala_id         SMALLINT REFERENCES salas(id),  -- NULL si el concepto no depende de una sala específica
    nombre          VARCHAR(150) NOT NULL,           -- "Cuna con barandal", "Mesa infantil"
    tipo_ratio      VARCHAR(20) NOT NULL
                        CHECK (tipo_ratio IN ('fijo_por_sala', 'por_alumno_ratio')),
    valor_ratio     NUMERIC(6,2) NOT NULL,           -- 1 (fijo) o el divisor del ratio (ej. 2 = "1 por cada 2 niños")
    fuente          VARCHAR(200)
);

CREATE TABLE estados_expediente (
    id      SMALLSERIAL PRIMARY KEY,
    clave   VARCHAR(30) NOT NULL UNIQUE,
    nombre  VARCHAR(100) NOT NULL,
    orden   SMALLINT NOT NULL
);

-- ============================================================================
-- SECCIÓN 2: PLANTEL
-- ============================================================================

CREATE TABLE planteles (
    id                      BIGSERIAL PRIMARY KEY,
    calle                   VARCHAR(150) NOT NULL,
    numero_ext              VARCHAR(20),
    numero_int              VARCHAR(20),
    colonia                 VARCHAR(150) NOT NULL,
    localidad               VARCHAR(150),
    municipio               VARCHAR(150) NOT NULL,
    codigo_postal           VARCHAR(10) NOT NULL,
    telefono                VARCHAR(20),
    correo_electronico      VARCHAR(150),
    metros_totales          NUMERIC(10,2),
    metros_construidos      NUMERIC(10,2),
    colindancia_norte       VARCHAR(150),
    colindancia_sur         VARCHAR(150),
    colindancia_este        VARCHAR(150),
    colindancia_oeste       VARCHAR(150),
    latitud                 NUMERIC(10,7),
    longitud                NUMERIC(10,7),
    area_civica_m2          NUMERIC(10,2),
    tiene_asta_bandera      BOOLEAN,
    created_at              TIMESTAMP NOT NULL DEFAULT now(),
    updated_at              TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE servicios_cercanos (
    id                  BIGSERIAL PRIMARY KEY,
    plantel_id          BIGINT NOT NULL REFERENCES planteles(id) ON DELETE CASCADE,
    nombre              VARCHAR(200) NOT NULL,
    tipo                VARCHAR(20) NOT NULL CHECK (tipo IN ('salud', 'emergencia')),
    es_publico          BOOLEAN,
    distancia_valor     NUMERIC(6,2),
    distancia_unidad    VARCHAR(5) CHECK (distancia_unidad IN ('m', 'km')),
    created_at          TIMESTAMP NOT NULL DEFAULT now()
);

-- "Tipo de estudios que imparte el local actualmente" (lista repetible del Anexo 2)
CREATE TABLE inmueble_estudios_actuales (
    id                  BIGSERIAL PRIMARY KEY,
    plantel_id          BIGINT NOT NULL REFERENCES planteles(id) ON DELETE CASCADE,
    nivel_educativo_id  SMALLINT REFERENCES niveles_educativos(id),  -- NULL si es "otro"
    otro_nivel_texto    VARCHAR(150),
    numero_alumnos      SMALLINT NOT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT now()
);

-- Espacios del plantel: SOLO datos comunes a cualquier tipo de espacio.
-- Datos específicos de un subtipo (campo de fútbol, biblioteca) viven en tablas de extensión.
CREATE TABLE instalaciones_espacios (
    id                  BIGSERIAL PRIMARY KEY,
    plantel_id          BIGINT NOT NULL REFERENCES planteles(id) ON DELETE CASCADE,
    tipo_espacio_id     INTEGER NOT NULL REFERENCES tipos_espacios(id),
    cantidad            SMALLINT,
    superficie_m2       NUMERIC(10,2),
    capacidad_promedio  SMALLINT,
    ventilacion_natural BOOLEAN,
    iluminacion_natural BOOLEAN,
    destinado_a         VARCHAR(200),
    created_at          TIMESTAMP NOT NULL DEFAULT now(),
    updated_at          TIMESTAMP NOT NULL DEFAULT now()
);

-- Extensión: solo existe una fila si instalaciones_espacios.tipo_espacio = campo_futbol
CREATE TABLE campos_futbol (
    instalacion_espacio_id  BIGINT PRIMARY KEY REFERENCES instalaciones_espacios(id) ON DELETE CASCADE,
    tipo_superficie         VARCHAR(50),
    formato                 VARCHAR(20)  -- 11, 7, 5, baby_fut
);

-- Extensión: material de biblioteca (relación N:M real entre espacio-biblioteca y tipo de material)
CREATE TABLE biblioteca_materiales (
    instalacion_espacio_id     BIGINT NOT NULL REFERENCES instalaciones_espacios(id) ON DELETE CASCADE,
    tipo_material_id           SMALLINT NOT NULL REFERENCES tipos_material_biblioteca(id),
    numero_titulos              INTEGER,
    numero_volumenes             INTEGER,
    PRIMARY KEY (instalacion_espacio_id, tipo_material_id)
);

-- Sanitarios: solo datos comunes a cualquier categoría.
CREATE TABLE sanitarios (
    id                      BIGSERIAL PRIMARY KEY,
    plantel_id              BIGINT NOT NULL REFERENCES planteles(id) ON DELETE CASCADE,
    categoria               VARCHAR(30) NOT NULL
                                CHECK (categoria IN (
                                    'alumnado_masculino', 'alumnado_femenino',
                                    'personal_masculino', 'personal_femenino',
                                    'alumnado_maternal', 'personal'
                                )),
    cantidad_retretes       SMALLINT,
    cantidad_mingitorios    SMALLINT,
    cantidad_lavabos        SMALLINT,
    superficie_m2           NUMERIC(8,2),
    ventilacion_natural     BOOLEAN,
    iluminacion_natural     BOOLEAN,
    created_at              TIMESTAMP NOT NULL DEFAULT now()
);

-- Extensión: solo existe fila si categoria = alumnado_maternal (Educación Inicial)
CREATE TABLE sanitarios_bacinicas (
    sanitario_id        BIGINT PRIMARY KEY REFERENCES sanitarios(id) ON DELETE CASCADE,
    cantidad_bacinicas  SMALLINT NOT NULL
);

-- Añadida 2026-09-08 (docs/decisions/ADR-002, pendiente de escribirse tras
-- esta implementación): identidad de negocio del solicitante, separada de
-- `users` (autenticación) y de `responsables_legales` (papeleo legal por
-- trámite). Ver docs/superpowers/specs/2026-09-08-modelo-identidad-solicitante-design.md.
CREATE TABLE solicitantes (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT NOT NULL UNIQUE REFERENCES users(id),
    created_at  TIMESTAMP NOT NULL DEFAULT now(),
    updated_at  TIMESTAMP NOT NULL DEFAULT now()
);

-- ============================================================================
-- SECCIÓN 3: ESCUELA
-- ============================================================================

-- solicitante_id añadido 2026-09-08 (ver ADR pendiente, spec
-- docs/superpowers/specs/2026-09-08-modelo-identidad-solicitante-design.md).
-- ON DELETE RESTRICT explícito: una escuela es un expediente real, un
-- solicitante con escuelas no debe poder eliminarse en cascada.
-- ALTER TABLE escuelas ADD COLUMN solicitante_id BIGINT NOT NULL REFERENCES solicitantes(id) ON DELETE RESTRICT;

CREATE TABLE escuelas (
    id              BIGSERIAL PRIMARY KEY,
    plantel_id      BIGINT NOT NULL REFERENCES planteles(id),
    solicitante_id  BIGINT NOT NULL REFERENCES solicitantes(id) ON DELETE RESTRICT,
    nombre_aprobado VARCHAR(200),
    created_at      TIMESTAMP NOT NULL DEFAULT now(),
    updated_at      TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE ternas_nombres (
    id                      BIGSERIAL PRIMARY KEY,
    escuela_id              BIGINT NOT NULL REFERENCES escuelas(id) ON DELETE CASCADE,
    numero_propuesta        SMALLINT NOT NULL CHECK (numero_propuesta BETWEEN 1 AND 3),
    nombre_propuesto        VARCHAR(200) NOT NULL,
    valido_marca_comercial  BOOLEAN,
    valido_registro_sedeq   BOOLEAN,
    created_at              TIMESTAMP NOT NULL DEFAULT now(),
    UNIQUE (escuela_id, numero_propuesta)
);

-- Tabla base del responsable: SOLO campos comunes a física y moral.
CREATE TABLE responsables_legales (
    id                          BIGSERIAL PRIMARY KEY,
    escuela_id                  BIGINT NOT NULL UNIQUE REFERENCES escuelas(id) ON DELETE CASCADE,
    tipo_persona                VARCHAR(20) NOT NULL
                                    CHECK (tipo_persona IN ('fisica', 'fisica_con_gestor', 'moral')),
    domicilio_notificaciones    VARCHAR(250),
    persona_autorizada_recoger  VARCHAR(200),
    created_at                  TIMESTAMP NOT NULL DEFAULT now(),
    updated_at                  TIMESTAMP NOT NULL DEFAULT now()
);

-- Subtipo: persona física (existe solo si tipo_persona IN ('fisica','fisica_con_gestor'))
CREATE TABLE personas_fisicas (
    responsable_legal_id    BIGINT PRIMARY KEY REFERENCES responsables_legales(id) ON DELETE CASCADE,
    nombre                  VARCHAR(200) NOT NULL,
    fecha_nacimiento        DATE,
    rfc                     VARCHAR(13),
    curp                    VARCHAR(18)
);

-- Subtipo: persona moral (existe solo si tipo_persona = 'moral')
CREATE TABLE personas_morales (
    responsable_legal_id            BIGINT PRIMARY KEY REFERENCES responsables_legales(id) ON DELETE CASCADE,
    razon_social                    VARCHAR(200) NOT NULL,
    numero_escritura_constitutiva   VARCHAR(50),
    fecha_escritura_constitutiva    DATE,
    notario_nombre                  VARCHAR(150),
    notario_numero                  VARCHAR(20),
    notario_ciudad                  VARCHAR(100),
    folio_registro_publico          VARCHAR(50),
    fecha_inscripcion_rpp           DATE,
    nombre_representante_legal      VARCHAR(200) NOT NULL
);

-- Gestor/tercero con poder (existe solo si tipo_persona = 'fisica_con_gestor')
CREATE TABLE gestores (
    responsable_legal_id    BIGINT PRIMARY KEY REFERENCES responsables_legales(id) ON DELETE CASCADE,
    nombre                  VARCHAR(200) NOT NULL,
    numero_poder            VARCHAR(50),
    notario_nombre          VARCHAR(150),
    notario_numero          VARCHAR(20),
    fecha_poder             DATE,
    created_at              TIMESTAMP NOT NULL DEFAULT now()
);

-- ============================================================================
-- SECCIÓN 4: ESCUELA + NIVEL (expediente)
-- ============================================================================

CREATE TABLE escuela_niveles (
    id                          BIGSERIAL PRIMARY KEY,
    escuela_id                  BIGINT NOT NULL REFERENCES escuelas(id) ON DELETE CASCADE,
    nivel_educativo_id          SMALLINT NOT NULL REFERENCES niveles_educativos(id),
    estado_id                   SMALLINT NOT NULL REFERENCES estados_expediente(id),

    folio_expediente            VARCHAR(20) UNIQUE,
    tipo_tramite                VARCHAR(20) NOT NULL
                                    CHECK (tipo_tramite IN ('alta_nueva', 'reincorporacion')),
    modalidad                   VARCHAR(20)
                                    CHECK (modalidad IN ('escolarizada', 'no_escolarizada', 'mixta', 'virtual')),
    plan_estudios_referencia    VARCHAR(200),
    turno                       VARCHAR(20) CHECK (turno IN ('matutino', 'vespertino', 'mixto')),
    tipo_alumnado                VARCHAR(20) CHECK (tipo_alumnado IN ('mixto', 'femenino', 'masculino')),
    plataforma_educativa_tipo   VARCHAR(20) CHECK (plataforma_educativa_tipo IN ('propia', 'rentada')),

    fecha_inicio_tramite        DATE NOT NULL DEFAULT CURRENT_DATE,
    fecha_resolucion            DATE,

    created_at                  TIMESTAMP NOT NULL DEFAULT now(),
    updated_at                  TIMESTAMP NOT NULL DEFAULT now(),

    UNIQUE (escuela_id, nivel_educativo_id)
);

CREATE TABLE aulas_nivel (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_nivel_id    BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    numero_aulas        SMALLINT NOT NULL,
    superficie_m2       NUMERIC(10,2),
    created_at          TIMESTAMP NOT NULL DEFAULT now()
);

-- Mobiliario: concepto_id ahora es FK al catálogo (impulsa validación automática
-- contra el ratio esperado en mobiliario_conceptos), en vez de texto libre.
CREATE TABLE mobiliario_nivel (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_nivel_id    BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    concepto_id         INTEGER NOT NULL REFERENCES mobiliario_conceptos(id),
    cantidad_declarada  SMALLINT NOT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT now(),
    UNIQUE (escuela_nivel_id, concepto_id)
);

-- Personal: tabla base SOLO con campos comunes a cualquier cargo (corrige columnas
-- dispersas asignatura_id/sala_referencia de v1)
-- cargo_puesto_id/nombre/nacionalidad/sexo/estudios/cedula_o_documento son
-- NULLable desde 2026-09-07 (docs/reports/2026-09-07-wizard-progreso.md):
-- el wizard resumible guarda filas de personal a medio llenar como borrador.
-- Completitud real la define App\Domain\Personal\RegistroPersonalCompleto,
-- no esta tabla — el Motor de Validación debe usarla antes de contar filas.
CREATE TABLE personal (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_nivel_id    BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    cargo_puesto_id     INTEGER REFERENCES cargos_puestos(id),
    nombre              VARCHAR(200),
    nacionalidad        VARCHAR(100),
    sexo                CHAR(1) CHECK (sexo IN ('M', 'F')),
    estudios            VARCHAR(200),
    cedula_o_documento  VARCHAR(100),
    perfil_validado     BOOLEAN,
    created_at          TIMESTAMP NOT NULL DEFAULT now(),
    updated_at          TIMESTAMP NOT NULL DEFAULT now()
);

-- Extensión: solo existe fila si el personal es docente de una asignatura (Secundaria)
CREATE TABLE personal_asignaturas (
    personal_id     BIGINT PRIMARY KEY REFERENCES personal(id) ON DELETE CASCADE,
    asignatura_id   INTEGER NOT NULL REFERENCES asignaturas(id)
);

-- Extensión: solo existe fila si el personal está asignado a una sala (Educación Inicial)
CREATE TABLE personal_salas (
    personal_id BIGINT PRIMARY KEY REFERENCES personal(id) ON DELETE CASCADE,
    sala_id     SMALLINT NOT NULL REFERENCES salas(id)
);

-- Matrícula dividida en 4 tablas atómicas (una por estructura real de nivel),
-- en vez de una tabla genérica categoria+etiqueta (corrige violación v1).
CREATE TABLE matricula_salas (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_nivel_id    BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    sala_id             SMALLINT NOT NULL REFERENCES salas(id),
    cantidad_alumnos    SMALLINT NOT NULL DEFAULT 0,
    UNIQUE (escuela_nivel_id, sala_id)
);

CREATE TABLE matricula_grados (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_nivel_id    BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    grado_id            INTEGER NOT NULL REFERENCES grados(id),
    grupo               VARCHAR(5) NOT NULL DEFAULT 'A',  -- etiqueta libre (A, B, C... K); no requiere catálogo normativo
    cantidad_alumnos    SMALLINT NOT NULL DEFAULT 0,
    UNIQUE (escuela_nivel_id, grado_id, grupo)
);

CREATE TABLE matricula_semestres (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_nivel_id    BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    numero_semestre     SMALLINT NOT NULL CHECK (numero_semestre BETWEEN 1 AND 6),
    modalidad           VARCHAR(20) NOT NULL
                            CHECK (modalidad IN ('escolarizado', 'no_escolarizado', 'mixto')),
    cantidad_alumnos    SMALLINT NOT NULL DEFAULT 0,
    UNIQUE (escuela_nivel_id, numero_semestre, modalidad)
);

CREATE TABLE matricula_cuatrimestres (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_nivel_id    BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    numero_cuatrimestre SMALLINT NOT NULL CHECK (numero_cuatrimestre BETWEEN 1 AND 6),
    cantidad_alumnos    SMALLINT NOT NULL DEFAULT 0,
    UNIQUE (escuela_nivel_id, numero_cuatrimestre)
);

-- ============================================================================
-- SECCIÓN 5: DOCUMENTOS (3 tablas con FK estricta, sin polimorfismo)
-- ============================================================================

CREATE TABLE documentos_plantel (
    id                  BIGSERIAL PRIMARY KEY,
    plantel_id          BIGINT NOT NULL REFERENCES planteles(id) ON DELETE CASCADE,
    tipo_documento_id   INTEGER NOT NULL REFERENCES tipos_documentos(id),
    archivo_path        VARCHAR(500),
    fecha_emision       DATE,
    fecha_vigencia      DATE,
    estado_validacion   VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                            CHECK (estado_validacion IN ('pendiente', 'validado', 'rechazado')),
    observaciones       TEXT,
    created_at          TIMESTAMP NOT NULL DEFAULT now(),
    updated_at          TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE documentos_escuela (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_id          BIGINT NOT NULL REFERENCES escuelas(id) ON DELETE CASCADE,
    tipo_documento_id   INTEGER NOT NULL REFERENCES tipos_documentos(id),
    archivo_path        VARCHAR(500),
    fecha_emision       DATE,
    fecha_vigencia      DATE,
    estado_validacion   VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                            CHECK (estado_validacion IN ('pendiente', 'validado', 'rechazado')),
    observaciones       TEXT,
    created_at          TIMESTAMP NOT NULL DEFAULT now(),
    updated_at          TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE documentos_escuela_nivel (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_nivel_id    BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    tipo_documento_id   INTEGER NOT NULL REFERENCES tipos_documentos(id),
    archivo_path        VARCHAR(500),
    fecha_emision       DATE,
    fecha_vigencia      DATE,
    estado_validacion   VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                            CHECK (estado_validacion IN ('pendiente', 'validado', 'rechazado')),
    observaciones       TEXT,
    created_at          TIMESTAMP NOT NULL DEFAULT now(),
    updated_at          TIMESTAMP NOT NULL DEFAULT now()
);

-- Extensión de Constancia de Seguridad Estructural (documento de PLANTEL).
-- Incluye datos del perito/DRO — fusionada con "Carta responsiva del DRO" (ver compendio, Paso 2).
CREATE TABLE constancias_seguridad_estructural (
    documento_plantel_id        BIGINT PRIMARY KEY REFERENCES documentos_plantel(id) ON DELETE CASCADE,
    perito_nombre                VARCHAR(200),
    perito_cedula_profesional    VARCHAR(50),
    perito_registro_dro          VARCHAR(50),
    perito_registro_autoridad    VARCHAR(150),
    perito_registro_vigencia     DATE
    -- Regla de validación cruzada (año registro perito = año emisión constancia) se valida en aplicación.
);

-- Extensión de Acreditación de Ocupación Legal (documento de PLANTEL). 4 variantes excluyentes.
CREATE TABLE acreditaciones_ocupacion_legal (
    documento_plantel_id     BIGINT PRIMARY KEY REFERENCES documentos_plantel(id) ON DELETE CASCADE,
    tipo                      VARCHAR(20) NOT NULL
                                CHECK (tipo IN ('escritura_publica', 'arrendamiento', 'comodato', 'otro')),
    numero_escritura          VARCHAR(50),
    notario_nombre            VARCHAR(150),
    notario_numero            VARCHAR(20),
    notario_localidad         VARCHAR(100),
    folio_rpp                 VARCHAR(50),
    fecha_inscripcion_rpp     DATE,
    arrendador_comodante      VARCHAR(200),
    arrendatario_comodatario  VARCHAR(200),
    fecha_contrato            DATE,
    vigencia_contrato         DATE,
    uso_autorizado            VARCHAR(200),
    ratificado_notario        BOOLEAN,
    otro_especifique          VARCHAR(200),
    observaciones             TEXT
);

-- Extensión de Recibo de Pago de Derechos (documento de ESCUELA_NIVEL) — faltaba en v1.
CREATE TABLE recibos_pago_derechos (
    documento_escuela_nivel_id  BIGINT PRIMARY KEY REFERENCES documentos_escuela_nivel(id) ON DELETE CASCADE,
    folio                        VARCHAR(50),
    monto                        NUMERIC(10,2),
    fecha_pago                   DATE,
    portal_referencia            VARCHAR(200)  -- ej. portal-tributario.queretaro.gob.mx
);

-- ============================================================================
-- SECCIÓN 6: BITÁCORA
-- ============================================================================

CREATE TABLE historial_estados_expediente (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_nivel_id    BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    estado_id           SMALLINT NOT NULL REFERENCES estados_expediente(id),
    comentario          TEXT,
    -- usuario_sedeq (VARCHAR libre) reemplazada 2026-09-08 por un FK real:
    usuario_sedeq_id    BIGINT NOT NULL REFERENCES users(id),
    fecha               TIMESTAMP NOT NULL DEFAULT now()
);

-- ============================================================================
-- SECCIÓN 7: PROGRESO DEL WIZARD (añadida 2026-09-07, ver ADR-001 y
-- docs/reports/2026-09-07-wizard-progreso.md — autorizado explícitamente,
-- no es parte del port original de v1)
-- ============================================================================

-- Catálogo de los seis sub-pasos del Paso 3 (PRD §5) — reconfigurable sin
-- cambio de código, mismo principio que niveles_educativos/estados_expediente.
CREATE TABLE pasos_captura (
    id      SMALLSERIAL PRIMARY KEY,
    clave   VARCHAR(40) NOT NULL UNIQUE,
    nombre  VARCHAR(100) NOT NULL,
    orden   SMALLINT NOT NULL
);

-- Una fila por (expediente, sub-paso). Nada en el código crea o avanza estas
-- filas todavía — eso pertenece al wizard, fuera de alcance de esta tabla.
CREATE TABLE escuela_nivel_pasos (
    id                BIGSERIAL PRIMARY KEY,
    escuela_nivel_id  BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    paso_captura_id   SMALLINT NOT NULL REFERENCES pasos_captura(id),
    estado            VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                          CHECK (estado IN ('pendiente','en_progreso','completado')),
    completado_at     TIMESTAMP,
    created_at        TIMESTAMP NOT NULL DEFAULT now(),
    updated_at        TIMESTAMP NOT NULL DEFAULT now(),
    UNIQUE (escuela_nivel_id, paso_captura_id)
);

-- ============================================================================
-- SEEDS MÍNIMOS DE CATÁLOGO
-- ============================================================================

INSERT INTO niveles_educativos (clave, nombre, orden) VALUES
    ('inicial', 'Educación Inicial', 1),
    ('preescolar', 'Preescolar', 2),
    ('primaria', 'Primaria', 3),
    ('secundaria', 'Secundaria', 4),
    ('media_superior', 'Media Superior', 5),
    ('superior', 'Superior', 6),
    ('posgrado', 'Posgrado', 7);

INSERT INTO estados_expediente (clave, nombre, orden) VALUES
    ('preregistro', 'Preregistro', 1),
    ('en_captura', 'En captura', 2),
    ('en_revision', 'En revisión', 3),
    ('con_observaciones', 'Con observaciones', 4),
    ('aprobado', 'Aprobado', 5),
    ('rechazado', 'Rechazado', 6);

INSERT INTO salas (clave, nombre, edad_min_meses, edad_max_meses, orden) VALUES
    ('lactantes_a', 'Lactantes A', 1, 6, 1),      -- 45 días ≈ 1 mes
    ('lactantes_b', 'Lactantes B', 7, 12, 2),
    ('lactantes_c', 'Lactantes C', 13, 18, 3),
    ('maternal_a', 'Maternal A', 19, 24, 4),
    ('maternal_b', 'Maternal B', 25, 35, 5);

INSERT INTO pasos_captura (clave, nombre, orden) VALUES
    ('inmueble', 'Datos del inmueble', 1),
    ('infraestructura', 'Infraestructura del nivel', 2),
    ('mobiliario', 'Mobiliario', 3),
    ('plan_estudios', 'Plan de estudios y modalidad', 4),
    ('plantilla_docente', 'Plantilla docente', 5),
    ('matricula', 'Matrícula', 6);

INSERT INTO tipos_material_biblioteca (clave, nombre) VALUES
    ('libros', 'Libros'),
    ('periodicos', 'Periódicos'),
    ('revistas_especializadas', 'Revistas especializadas'),
    ('diapositivas', 'Diapositivas'),
    ('videos', 'Videos'),
    ('peliculas', 'Películas'),
    ('discos_compactos', 'Discos compactos'),
    ('software', 'Software'),
    ('otro', 'Otro');

