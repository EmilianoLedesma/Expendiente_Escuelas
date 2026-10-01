<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;

/**
 * Corre varias validaciones (las del componente y las de sus Form objects)
 * y muestra todos los errores de una vez. Encadenar $this->validate() y
 * $form->validate() detiene en la primera que falla, así que el
 * solicitante veía los errores en dos rondas: primero unos campos y, al
 * corregirlos, otros que ya estaban mal.
 */
trait ValidaEnConjunto
{
    /** @param callable(): mixed ...$validaciones */
    protected function validarEnConjunto(callable ...$validaciones): void
    {
        $errores = new MessageBag;
        $primera = null;

        foreach ($validaciones as $validar) {
            try {
                $validar();
            } catch (ValidationException $e) {
                $primera ??= $e;
                $errores->merge($e->validator->errors());
            }
        }

        if ($primera !== null) {
            $this->setErrorBag($errores);

            throw ValidationException::withMessages($errores->toArray());
        }
    }
}
