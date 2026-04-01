<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use ScriptDevelop\WhatsappManager\Support\WhatsappModelResolver;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para gestionar nombres de usuario de empresa en WhatsApp.
 *
 * Los nombres de usuario de empresa permiten que los usuarios encuentren
 * al negocio en WhatsApp mediante búsqueda exacta. Este servicio expone
 * los endpoints disponibles a partir de 2026:
 *
 *   POST   /{phone_id}/username             — Adoptar o cambiar nombre de usuario
 *   GET    /{phone_id}/username             — Obtener nombre de usuario actual
 *   DELETE /{phone_id}/username             — Eliminar nombre de usuario
 *   GET    /{phone_id}/username_suggestions — Listar nombres de usuario reservados
 *
 * Restricciones de formato para nombres de usuario de empresa:
 *   - Solo letras inglesas (a-z), números (0-9), puntos (.) y guiones bajos (_)
 *   - Entre 3 y 35 caracteres de longitud
 *   - Debe contener al menos una letra
 *   - No puede empezar ni terminar con punto, ni tener dos puntos consecutivos
 *   - No puede empezar con "www"
 *   - No puede terminar con dominio (.com, .org, etc.)
 */
class UsernameService
{
    public function __construct(
        protected ApiClient $apiClient
    ) {}

    /**
     * Adopta o cambia el nombre de usuario de empresa para un número de teléfono.
     *
     * @param string $phoneNumberId ID del número de teléfono registrado en el paquete
     * @param string $username      Nombre de usuario deseado
     * @return array Respuesta de la API con 'status': approved | reserved
     */
    public function setUsername(string $phoneNumberId, string $username): array
    {
        $phone = WhatsappModelResolver::phone_number()->find($phoneNumberId);
        if (!$phone) {
            throw new \RuntimeException("Número telefónico no encontrado: {$phoneNumberId}");
        }

        $endpoint = Endpoints::build(
            Endpoints::BUSINESS_USERNAME,
            ['phone_number_id' => $phone->api_phone_number_id]
        );

        Log::channel('whatsapp')->info('Estableciendo nombre de usuario de empresa.', [
            'phone_number_id' => $phoneNumberId,
            'username'        => $username,
        ]);

        return $this->apiClient->request(
            'POST',
            $endpoint,
            [],
            ['username' => $username],
            [],
            $this->getAuthHeaders($phone->businessAccount)
        );
    }

    /**
     * Obtiene el nombre de usuario actual y su estado para un número de teléfono.
     *
     * @param string $phoneNumberId ID del número de teléfono registrado en el paquete
     * @return array Respuesta con 'username' (puede estar ausente) y 'status'
     */
    public function getUsername(string $phoneNumberId): array
    {
        $phone = WhatsappModelResolver::phone_number()->find($phoneNumberId);
        if (!$phone) {
            throw new \RuntimeException("Número telefónico no encontrado: {$phoneNumberId}");
        }

        $endpoint = Endpoints::build(
            Endpoints::BUSINESS_USERNAME,
            ['phone_number_id' => $phone->api_phone_number_id]
        );

        return $this->apiClient->request(
            'GET',
            $endpoint,
            [],
            [],
            [],
            $this->getAuthHeaders($phone->businessAccount)
        );
    }

    /**
     * Elimina el nombre de usuario de empresa asociado al número de teléfono.
     *
     * @param string $phoneNumberId ID del número de teléfono registrado en el paquete
     * @return array Respuesta con 'success': true|false
     */
    public function deleteUsername(string $phoneNumberId): array
    {
        $phone = WhatsappModelResolver::phone_number()->find($phoneNumberId);
        if (!$phone) {
            throw new \RuntimeException("Número telefónico no encontrado: {$phoneNumberId}");
        }

        $endpoint = Endpoints::build(
            Endpoints::BUSINESS_USERNAME,
            ['phone_number_id' => $phone->api_phone_number_id]
        );

        Log::channel('whatsapp')->info('Eliminando nombre de usuario de empresa.', [
            'phone_number_id' => $phoneNumberId,
        ]);

        return $this->apiClient->request(
            'DELETE',
            $endpoint,
            [],
            [],
            [],
            $this->getAuthHeaders($phone->businessAccount)
        );
    }

    /**
     * Obtiene la lista de nombres de usuario reservados por WhatsApp para el número.
     * Estos nombres tienen mayor probabilidad de aprobación al solicitarlos.
     *
     * @param string $phoneNumberId ID del número de teléfono registrado en el paquete
     * @return array Respuesta con 'data[0].username_suggestions': array de strings
     */
    public function getUsernameSuggestions(string $phoneNumberId): array
    {
        $phone = WhatsappModelResolver::phone_number()->find($phoneNumberId);
        if (!$phone) {
            throw new \RuntimeException("Número telefónico no encontrado: {$phoneNumberId}");
        }

        $endpoint = Endpoints::build(
            Endpoints::BUSINESS_USERNAME_SUGGESTIONS,
            ['phone_number_id' => $phone->api_phone_number_id]
        );

        return $this->apiClient->request(
            'GET',
            $endpoint,
            [],
            [],
            [],
            $this->getAuthHeaders($phone->businessAccount)
        );
    }

    protected function getAuthHeaders($businessAccount): array
    {
        return [
            'Authorization' => 'Bearer ' . $businessAccount->api_token,
        ];
    }
}
