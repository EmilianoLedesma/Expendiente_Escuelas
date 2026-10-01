<?php

/*
|--------------------------------------------------------------------------
| Mensajes de validación en español
|--------------------------------------------------------------------------
|
| Se muestran junto a cada campo (x-ui.field) y en el resumen de errores
| (x-ui.error-summary), así que cada mensaje debe entenderse solo, fuera
| del formulario: por eso nombran el campo con :attribute.
|
| Las reglas condicionales (required_if, required_with, ...) usan el mismo
| texto que "required": el formulario solo muestra el campo cuando la
| condición aplica, y nombrar la propiedad de la que depende (p. ej.
| "cuando bifurcacion es nuevo") no le dice nada al solicitante.
|
*/

$obligatorio = 'El campo :attribute es obligatorio.';

return [

    'accepted' => 'Debes aceptar :attribute.',
    'accepted_if' => 'Debes aceptar :attribute.',
    'active_url' => 'El campo :attribute debe ser una URL válida.',
    'after' => 'La fecha de :attribute debe ser posterior a :date.',
    'after_or_equal' => 'La fecha de :attribute debe ser igual o posterior a :date.',
    'alpha' => 'El campo :attribute solo puede contener letras.',
    'alpha_dash' => 'El campo :attribute solo puede contener letras, números, guiones y guiones bajos.',
    'alpha_num' => 'El campo :attribute solo puede contener letras y números.',
    'any_of' => 'El valor de :attribute no es válido.',
    'array' => 'El campo :attribute no es válido.',
    'array_keys' => 'El campo :attribute contiene datos no permitidos.',
    'ascii' => 'El campo :attribute solo puede contener letras, números y símbolos sin acentos.',
    'before' => 'La fecha de :attribute debe ser anterior a :date.',
    'before_or_equal' => 'La fecha de :attribute debe ser igual o anterior a :date.',
    'between' => [
        'array' => 'El campo :attribute debe tener entre :min y :max elementos.',
        'file' => 'El archivo de :attribute debe pesar entre :min y :max kilobytes.',
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => 'El campo :attribute debe ser sí o no.',
    'can' => 'El campo :attribute contiene un valor no autorizado.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'contains' => 'Al campo :attribute le falta un valor obligatorio.',
    'current_password' => 'La contraseña es incorrecta.',
    'date' => 'Ingresa una fecha válida en :attribute.',
    'date_equals' => 'La fecha de :attribute debe ser :date.',
    'date_format' => 'Ingresa una fecha válida en :attribute.',
    'decimal' => 'El campo :attribute admite como máximo :decimal decimales.',
    'declined' => 'El campo :attribute debe rechazarse.',
    'declined_if' => 'El campo :attribute debe rechazarse.',
    'different' => 'Los campos :attribute y :other deben ser distintos.',
    'digits' => 'El campo :attribute debe tener :digits dígitos.',
    'digits_between' => 'El campo :attribute debe tener entre :min y :max dígitos.',
    'dimensions' => 'La imagen de :attribute no tiene dimensiones válidas.',
    'distinct' => 'El campo :attribute tiene un valor repetido.',
    'doesnt_contain' => 'El campo :attribute contiene un valor no permitido.',
    'doesnt_end_with' => 'El campo :attribute no debe terminar con: :values.',
    'doesnt_start_with' => 'El campo :attribute no debe empezar con: :values.',
    'email' => 'Ingresa un correo electrónico válido, por ejemplo: nombre@dominio.com.',
    'encoding' => 'El campo :attribute tiene caracteres no válidos.',
    'ends_with' => 'El campo :attribute debe terminar con: :values.',
    'enum' => 'Selecciona una opción válida en :attribute.',
    'exists' => 'Selecciona una opción válida en :attribute.',
    'extensions' => 'El archivo de :attribute debe tener una de estas extensiones: :values.',
    'file' => 'Selecciona un archivo en :attribute.',
    'filled' => $obligatorio,
    'gt' => [
        'array' => 'El campo :attribute debe tener más de :value elementos.',
        'file' => 'El archivo de :attribute debe pesar más de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser mayor que :value.',
        'string' => 'El campo :attribute debe tener más de :value caracteres.',
    ],
    'gte' => [
        'array' => 'El campo :attribute debe tener :value elementos o más.',
        'file' => 'El archivo de :attribute debe pesar :value kilobytes o más.',
        'numeric' => 'El campo :attribute debe ser mayor o igual que :value.',
        'string' => 'El campo :attribute debe tener :value caracteres o más.',
    ],
    'hex_color' => 'El campo :attribute debe ser un color hexadecimal válido.',
    'image' => 'El archivo de :attribute debe ser una imagen.',
    'in' => 'Selecciona una opción válida en :attribute.',
    'in_array' => 'El valor de :attribute no es válido.',
    'in_array_keys' => 'El campo :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero, sin decimales.',
    'ip' => 'El campo :attribute debe ser una dirección IP válida.',
    'ipv4' => 'El campo :attribute debe ser una dirección IPv4 válida.',
    'ipv6' => 'El campo :attribute debe ser una dirección IPv6 válida.',
    'json' => 'El campo :attribute no es válido.',
    'list' => 'El campo :attribute no es válido.',
    'lowercase' => 'El campo :attribute debe escribirse en minúsculas.',
    'lt' => [
        'array' => 'El campo :attribute debe tener menos de :value elementos.',
        'file' => 'El archivo de :attribute debe pesar menos de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser menor que :value.',
        'string' => 'El campo :attribute debe tener menos de :value caracteres.',
    ],
    'lte' => [
        'array' => 'El campo :attribute debe tener :value elementos o menos.',
        'file' => 'El archivo de :attribute debe pesar :value kilobytes o menos.',
        'numeric' => 'El campo :attribute debe ser menor o igual que :value.',
        'string' => 'El campo :attribute debe tener :value caracteres o menos.',
    ],
    'mac_address' => 'El campo :attribute debe ser una dirección MAC válida.',
    'max' => [
        'array' => 'El campo :attribute admite como máximo :max elementos.',
        'file' => 'El archivo de :attribute no debe pesar más de :max kilobytes.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute admite como máximo :max caracteres.',
    ],
    'max_digits' => 'El campo :attribute admite como máximo :max dígitos.',
    'mimes' => 'El archivo de :attribute debe ser de tipo: :values.',
    'mimetypes' => 'El archivo de :attribute debe ser de tipo: :values.',
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'file' => 'El archivo de :attribute debe pesar al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'min_digits' => 'El campo :attribute debe tener al menos :min dígitos.',
    'missing' => 'El campo :attribute no debe enviarse.',
    'missing_if' => 'El campo :attribute no debe enviarse.',
    'missing_unless' => 'El campo :attribute no debe enviarse.',
    'missing_with' => 'El campo :attribute no debe enviarse.',
    'missing_with_all' => 'El campo :attribute no debe enviarse.',
    'multiple_of' => 'El campo :attribute debe ser múltiplo de :value.',
    'not_in' => 'Selecciona una opción válida en :attribute.',
    'not_regex' => 'El formato de :attribute no es válido.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'password' => [
        'letters' => 'La contraseña debe incluir al menos una letra.',
        'mixed' => 'La contraseña debe incluir al menos una mayúscula y una minúscula.',
        'numbers' => 'La contraseña debe incluir al menos un número.',
        'symbols' => 'La contraseña debe incluir al menos un símbolo.',
        'uncompromised' => 'Esta contraseña apareció en una filtración de datos. Elige otra.',
    ],
    'present' => 'El campo :attribute debe estar presente.',
    'present_if' => 'El campo :attribute debe estar presente.',
    'present_unless' => 'El campo :attribute debe estar presente.',
    'present_with' => 'El campo :attribute debe estar presente.',
    'present_with_all' => 'El campo :attribute debe estar presente.',
    'prohibited' => 'El campo :attribute no está permitido.',
    'prohibited_if' => 'El campo :attribute no está permitido.',
    'prohibited_if_accepted' => 'El campo :attribute no está permitido.',
    'prohibited_if_declined' => 'El campo :attribute no está permitido.',
    'prohibited_unless' => 'El campo :attribute no está permitido.',
    'prohibits' => 'El campo :attribute no puede capturarse junto con :other.',
    'regex' => 'El formato de :attribute no es válido.',
    'required' => $obligatorio,
    'required_array_keys' => 'Al campo :attribute le faltan datos: :values.',
    'required_if' => $obligatorio,
    'required_if_accepted' => $obligatorio,
    'required_if_declined' => $obligatorio,
    'required_unless' => $obligatorio,
    'required_with' => $obligatorio,
    'required_with_all' => $obligatorio,
    'required_without' => $obligatorio,
    'required_without_all' => $obligatorio,
    'same' => 'Los campos :attribute y :other deben coincidir.',
    'size' => [
        'array' => 'El campo :attribute debe tener :size elementos.',
        'file' => 'El archivo de :attribute debe pesar :size kilobytes.',
        'numeric' => 'El campo :attribute debe ser :size.',
        'string' => 'El campo :attribute debe tener :size caracteres.',
    ],
    'starts_with' => 'El campo :attribute debe empezar con: :values.',
    'string' => 'El campo :attribute debe ser texto.',
    'timezone' => 'El campo :attribute debe ser una zona horaria válida.',
    'unique' => 'Ya existe un registro con ese :attribute.',
    'uploaded' => 'No se pudo subir el archivo de :attribute. Inténtalo de nuevo.',
    'uppercase' => 'El campo :attribute debe escribirse en mayúsculas.',
    'url' => 'El campo :attribute debe ser una URL válida.',
    'ulid' => 'El campo :attribute no es válido.',
    'uuid' => 'El campo :attribute no es válido.',

    /*
    |--------------------------------------------------------------------------
    | Mensajes específicos
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'email' => [
            'unique' => 'Ya existe una cuenta con ese correo electrónico.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nombres visibles de los campos
    |--------------------------------------------------------------------------
    |
    | Clave = ruta de la propiedad Livewire (la misma que la clave de error y
    | el id del campo); valor = cómo la llama la etiqueta del formulario.
    |
    | Los Form objects (personaFisicaForm, gestorForm, ...) validan con la
    | clave SIN prefijo ("nombre", no "gestorForm.nombre"), así que cada Form
    | declara sus nombres en validationAttributes(): dos forms tienen un
    | "nombre" que significa cosas distintas. Las claves genéricas de abajo
    | ("nombre", "curp", "rfc", ...) son solo el respaldo para un Form que
    | aún no declare los suyos (p. ej. los de la rama del motor documental,
    | ADR-007), para que nunca salga el nombre de la propiedad en crudo.
    |
    */

    'attributes' => [
        // Autenticación y perfil
        'name' => 'nombre completo',
        'email' => 'correo electrónico',
        'password' => 'contraseña',
        'password_confirmation' => 'confirmación de contraseña',
        'current_password' => 'contraseña actual',
        'form.email' => 'correo electrónico',
        'form.password' => 'contraseña',

        // Paso 1 — plantel
        'bifurcacion' => 'tipo de plantel',
        'plantelId' => 'plantel registrado',
        'calle' => 'calle',
        'numeroExt' => 'número exterior',
        'numeroInt' => 'número interior',
        'colonia' => 'colonia',
        'localidad' => 'localidad',
        'municipio' => 'municipio',
        'codigoPostal' => 'código postal',
        'telefono' => 'teléfono',
        'correoElectronico' => 'correo electrónico',

        // Paso 2.1 — responsable legal
        'tipoPersona' => 'tipo de persona',
        'domicilioNotificaciones' => 'domicilio para notificaciones',
        'personaAutorizadaRecoger' => 'persona autorizada para recoger notificaciones',
        'nombrePropuesto1' => 'propuesta de nombre 1',
        'nombrePropuesto2' => 'propuesta de nombre 2',
        'nombrePropuesto3' => 'propuesta de nombre 3',
        'nivelesSeleccionados' => 'niveles educativos',
        'nivelesSeleccionados.*' => 'nivel educativo',

        // Paso 2.2 — documentos
        'archivos.*' => 'archivo PDF',

        // Respaldo para campos de Form objects sin validationAttributes()
        'nombre' => 'nombre',
        'curp' => 'CURP',
        'rfc' => 'RFC',
        'fechaEmision' => 'fecha de emisión',

        // Paso 2.4 — documentos del nivel
        'turno' => 'turno',
        'tipoAlumnado' => 'tipo de alumnado',

        // Paso 3.1 — datos del inmueble
        'metrosTotales' => 'superficie del predio',
        'metrosConstruidos' => 'superficie construida',
        'areaCivicaM2' => 'área cívica',
        'tieneAstaBandera' => 'asta bandera',
        'colindanciaNorte' => 'colindancia norte',
        'colindanciaSur' => 'colindancia sur',
        'colindanciaEste' => 'colindancia este',
        'colindanciaOeste' => 'colindancia oeste',
        'latitud' => 'latitud',
        'longitud' => 'longitud',
        'serviciosCercanos.*.nombre' => 'nombre del servicio',
        'serviciosCercanos.*.tipo' => 'tipo de servicio',
        'serviciosCercanos.*.esPublico' => 'institución pública',
        'serviciosCercanos.*.distanciaValor' => 'distancia',
        'serviciosCercanos.*.distanciaUnidad' => 'unidad de distancia',
        'estudiosActuales.*.nivelEducativoId' => 'nivel educativo',
        'estudiosActuales.*.otroNivelTexto' => 'otro nivel',
        'estudiosActuales.*.numeroAlumnos' => 'número de alumnos',

        // Paso 3.2 — infraestructura
        'numeroAulas' => 'número de aulas',
        'superficieAulasM2' => 'superficie total de aulas',
        'espacios.*.cantidad' => 'cantidad',
        'espacios.*.superficieM2' => 'superficie',
        'espacios.*.capacidadPromedio' => 'capacidad promedio',
        'espacios.*.destinadoA' => 'destinado a',
        'espacios.*.campoFutbolTipoSuperficie' => 'tipo de superficie del campo',
        'espacios.*.campoFutbolFormato' => 'formato del campo',
        'sanitarios.*.cantidadRetretes' => 'retretes',
        'sanitarios.*.cantidadMingitorios' => 'mingitorios',
        'sanitarios.*.cantidadLavabos' => 'lavabos',
        'sanitarios.*.superficieM2' => 'superficie',
        'sanitarios.*.cantidadBacinicas' => 'bacinicas',
        'materialesBiblioteca.*.numeroTitulos' => 'número de títulos',
        'materialesBiblioteca.*.numeroVolumenes' => 'número de volúmenes',

        // Paso 3.3 — mobiliario
        'cantidades' => 'cantidades de mobiliario',
        'cantidades.*' => 'cantidad',
    ],

];
