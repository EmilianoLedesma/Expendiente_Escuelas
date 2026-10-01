<?php

namespace App\Application\Captura;

/** Cómo se limpia un valor de texto capturado antes de validarlo y guardarlo. */
enum Normalizacion
{
    /** Una línea: recorta, colapsa espacios y quita caracteres invisibles. Es el default. */
    case Texto;

    /** Varias líneas (observaciones): como Texto, pero conserva los párrafos. */
    case TextoLargo;

    /** CURP, RFC: mayúsculas, sin espacios, guiones ni puntos. */
    case Identificador;

    /** Correo electrónico: minúsculas y sin espacios. */
    case Correo;

    /** Teléfono: sin separadores ni lada internacional +52. */
    case Telefono;

    /** Códigos numéricos (código postal): sin espacios ni guiones. */
    case Digitos;

    /** Contraseñas y valores que deben llegar tal cual. */
    case Ninguna;
}
