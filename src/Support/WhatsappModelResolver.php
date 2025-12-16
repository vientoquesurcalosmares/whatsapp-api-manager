<?php

namespace ScriptDevelop\WhatsappManager\Support;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class WhatsappModelResolver
{
    /**
     * Devuelve una instancia de la clase del modelo resuelto desde el archivo de configuración
     */
    public static function make(string $key, array $attributes = []): \Illuminate\Database\Eloquent\Model
    {
        $class = config("whatsapp.models.$key");

        if (!class_exists($class)) {
            throw new InvalidArgumentException("La clase para el modelo [$key] no existe: [$class]");
        }

        return new $class($attributes);
    }

    /**
     * Forward de métodos Eloquent estáticos, como ModelResolver::message()->firstOrCreate()
     */
    public static function __callStatic(string $method, array $arguments)
    {
        $class = config("whatsapp.models.$method");

        if (!class_exists($class)) {
            throw new InvalidArgumentException(whatsapp_trans('messages.model_not_found_for_method', ['method' => $method]));
        }

        return $class::query();
    }

    /**
     * Alternativa explícita: ModelResolver::resolve('contact')->firstOrCreate()
     */
    public static function resolve(string $key)
    {
        $class = config("whatsapp.models.$key");

        if (!class_exists($class)) {
            throw new InvalidArgumentException(whatsapp_trans('messages.model_not_found_for_key', ['key' => $key]));
        }

        return $class::query();
    }

    /**
     * Permite sustituir el modelo dinámicamente (conveniente para testing).
     */
    public static function fake(string $key, string $class): void
    {
        config()->set("whatsapp.models.$key", $class);
    }
}
