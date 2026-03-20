<?php

namespace ScriptDevelop\WhatsappManager\Services\Flows;

class FlowResponse
{
    /**
     * Ordena al Flow pasar a la siguiente pantalla.
     */
    public static function nextScreen(string $screen, array $data = []): array
    {
        return [
            'version' => '3.0',
            'screen' => $screen,
            'data' => $data
        ];
    }

    /**
     * Cierra el flujo y envía un mensaje final.
     */
    public static function complete(array $data = []): array
    {
        return [
            'version' => '3.0',
            'screen' => 'SUCCESS', // SUCCESS es una palabra clave en Meta para cerrar
            'data' => $data
        ];
    }

    /**
     * Devuelve un error de validación para campos específicos.
     */
    public static function error(string $message, array $errorData = []): array
    {
        return [
            'version' => '3.0',
            'error_msg' => $message,
            'error_data' => $errorData
        ];
    }

    /**
     * Respuesta simple para el PING de Meta.
     */
    public static function pong(): array
    {
        return [
            'version' => '3.0',
            'data' => [
                'status' => 'active'
            ]
        ];
    }
}