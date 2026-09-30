<?php

namespace App\Livewire\Hooks;

use App\Application\Captura\Normalizacion;
use App\Application\Captura\NormalizadorEntrada;
use App\Application\Captura\Normalizar;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\ComponentHook;
use Livewire\Form;
use ReflectionProperty;

/**
 * Hook global (registrado en AppServiceProvider) que corre cada vez que el
 * navegador actualiza una propiedad de cualquier componente Livewire:
 *
 * 1. Limpieza: Livewire desactiva TrimStrings/ConvertEmptyStringsToNull para
 *    sus peticiones, así que aquí se normaliza todo valor de texto (por
 *    defecto Normalizacion::Texto; una propiedad puede declarar otra con
 *    #[Normalizar]). Las contraseñas nunca se tocan.
 * 2. Validación en vivo: si el componente (o su Form object) tiene regla
 *    para ese campo, se valida solo ese campo y el mensaje aparece debajo de
 *    él al salir del campo (wire:model.blur), sin esperar al envío y sin
 *    ventanas emergentes del navegador. El envío sigue validando todo:
 *    esto adelanta el mensaje, no lo reemplaza.
 *
 * Es presentación pura: las reglas siguen viviendo en cada formulario y
 * los formatos en App\Domain\Captura\Formatos (ADR-001).
 */
class LimpiarYValidarAlCapturar extends ComponentHook
{
    /** Mismas exclusiones que el TrimStrings de Laravel. */
    private const SIN_TOCAR = ['password', 'password_confirmation', 'current_password'];

    public function update($propiedad, $rutaCompleta, $valorNuevo)
    {
        return function () use ($rutaCompleta, $valorNuevo): void {
            $campo = Str::afterLast($rutaCompleta, '.');

            if (in_array($campo, self::SIN_TOCAR, true)) {
                return;
            }

            if (is_string($valorNuevo)) {
                $limpio = NormalizadorEntrada::aplicar($this->normalizacionDe($rutaCompleta), $valorNuevo);

                if ($limpio !== $valorNuevo) {
                    data_set($this->component, $rutaCompleta, $limpio);
                }
            }

            $this->validarCampo($rutaCompleta);
        };
    }

    /** La normalización declarada con #[Normalizar] en la propiedad dueña del valor, o Texto. */
    private function normalizacionDe(string $ruta): Normalizacion
    {
        $segmentos = explode('.', $ruta);
        $propiedad = array_pop($segmentos);
        $duenio = $this->component;

        foreach ($segmentos as $segmento) {
            if (! is_object($duenio) || ! property_exists($duenio, $segmento)) {
                // Valor dentro de un arreglo: no hay propiedad que anotar.
                return Normalizacion::Texto;
            }
            $duenio = $duenio->{$segmento};
        }

        if (! is_object($duenio) || ! property_exists($duenio, $propiedad)) {
            return Normalizacion::Texto;
        }

        $atributos = (new ReflectionProperty($duenio, $propiedad))->getAttributes(Normalizar::class);

        return $atributos === [] ? Normalizacion::Texto : $atributos[0]->newInstance()->como;
    }

    private function validarCampo(string $ruta): void
    {
        if (! $this->tieneRegla($ruta)) {
            return;
        }

        try {
            $this->component->validateOnly($ruta);
        } catch (ValidationException $e) {
            // Mismo manejo que Livewire\Features\SupportValidation para una
            // ValidationException lanzada por el componente: validateOnly ya
            // combinó este error con los del resto de los campos.
            $this->component->setErrorBag($e->validator->errors());
        }
    }

    private function tieneRegla(string $ruta): bool
    {
        $raiz = Str::before($ruta, '.');
        $formulario = $this->component->all()[$raiz] ?? null;

        [$reglas, $campo] = $formulario instanceof Form
            ? [$formulario->getRules(), Str::after($ruta, '.')]
            : [$this->component->getRules(), $ruta];

        foreach (array_keys($reglas) as $clave) {
            if (Str::is((string) $clave, $campo)) {
                return true;
            }
        }

        return false;
    }
}
