<?php

namespace ScriptDevelop\WhatsappManager\WhatsappApi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use ScriptDevelop\WhatsappManager\WhatsappApi\Exceptions\ApiException;

class ApiClient
{
    protected Client $client;
    protected string $baseUrl;
    protected string $version;

    public function __construct(string $baseUrl, string $version, int $timeout = 30)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->version = $version;
        
        $this->client = new Client([
            'base_uri' => $this->baseUrl . '/',
            'timeout' => $timeout,
            'headers' => [
                'Accept' => 'application/json',
            ]
        ]);
    }

    /**
     * Ejecuta una petición genérica a la API.
     */
    public function request(
        string $method,
        string $endpoint,
        array $params = [],
        array $data = [],
        array $headers = []
    ): array {
        try {
            // Construir URL final
            $url = $this->buildUrl($endpoint, $params);
            
            // Configurar opciones
            $options = ['headers' => $headers];
            
            if (!empty($data)) {
                $options['json'] = $data;
            }

            // Enviar petición
            $response = $this->client->request($method, $url, $options);
            
            // Decodificar respuesta
            return json_decode($response->getBody(), true) ?: [];

        } catch (GuzzleException $e) {
            throw $this->handleException($e);
        }
    }

    /**
     * Construye la URL reemplazando placeholders.
     */
    protected function buildUrl(string $endpoint, array $params): string
    {
        return str_replace(
            array_map(fn($k) => '{' . $k . '}', array_keys($params)),
            array_values($params),
            $this->version . '/' . $endpoint
        );
    }

    /**
     * Maneja errores de la API.
     */
    protected function handleException(GuzzleException $e): ApiException
    {
        $statusCode = 500;
        $body = [];
        $message = $e->getMessage();

        // Solo si es una excepción con respuesta (ej: 4xx/5xx)
        if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);
            $message = $body['error']['message'] ?? $message;
        }

        return new ApiException($message, $statusCode, $body);
    }
}