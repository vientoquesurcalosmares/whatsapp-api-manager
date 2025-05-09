<?php

namespace ScriptDevelop\WhatsappManager\WhatsappApi;

use Illuminate\Support\Facades\Log;
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
        mixed $data = null, // Cambiado a mixed para soportar flujos
        array $query = [],
        array $headers = []
    ): array {
        try {
            // Construir URL final
            $url = $this->buildUrl($endpoint, $params, $query);
            
            // Configurar opciones
            $options = ['headers' => $headers];

            Log::channel('whatsapp')->info('Enviando solicitud a la API de WhatsApp.', [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
            ]);
            
            // Manejar datos según el tipo
            if (isset($data['multipart'])) {
                // Si los datos son de tipo multipart
                $options['multipart'] = $data['multipart'];
            } elseif (is_resource($data)) {
                // Si los datos son un flujo (archivo)
                $options['body'] = $data;
            } elseif (!empty($data)) {
                // Si los datos son un array (JSON)
                $options['json'] = $data;
            }

            // Enviar petición
            $response = $this->client->request($method, $url, $options);
            
            // Decodificar respuesta
            return json_decode($response->getBody(), true) ?: [];

        } catch (GuzzleException $e) {
            Log::channel('whatsapp')->error('API Error', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            // throw ApiException::fromGuzzleException($e);
            throw $this->handleException($e);
        }
    }

    /**
     * Construye la URL reemplazando placeholders.
     */
    protected function buildUrl(string $endpoint, array $params, array $query = []): string
    {
        $url = str_replace(
            array_map(fn($k) => '{' . $k . '}', array_keys($params)),
            array_values($params),
            $this->version . '/' . $endpoint
        );

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        Log::channel('whatsapp')->info('URL construida:', ['url' => $url]);

        return $url;
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

            Log::error('Error en la respuesta de la API.', [
                'status_code' => $statusCode,
                'response_body' => $body,
                'headers' => $response->getHeaders(),
            ]);

            Log::channel('whatsapp')->error('Error en la respuesta de la API.', [
                'status_code' => $statusCode,
                'response_body' => $body,
                'headers' => $response->getHeaders(),
            ]);
        }

        return new ApiException($message, $statusCode, $body);
    }
}