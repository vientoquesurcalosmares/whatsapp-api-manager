<?php

namespace ScriptDevelop\WhatsappManager\WhatsappApi;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use ScriptDevelop\WhatsappManager\WhatsappApi\Exceptions\ApiException;

/**
 * ApiClient class to interact with the WhatsApp Business API.
 * Provides methods to make HTTP requests to the API.
 *
 * Clase ApiClient para interactuar con la API de WhatsApp Business.
 * Proporciona métodos para realizar solicitudes HTTP a la API.
 */
/**
 * @package ScriptDevelop\WhatsappManager\WhatsappApi
 */
class ApiClient
{
    /**
     * @var Client Guzzle HTTP client.
     */
    protected Client $client;
    /**
     * @var string API base URL / URL base de la API.
     */
    protected string $baseUrl;
    /**
     * @var string API version / Versión de la API.
     */
    protected string $version;

    /**
     * ApiClient class constructor.
     * Constructor de la clase ApiClient.
     *
     * @param string $baseUrl API base URL / URL base de la API.
     * @param string $version API version / Versión de la API.
     * @param int $timeout Request timeout in seconds / Tiempo de espera para las solicitudes (en segundos).
     */
    public function __construct(string $baseUrl, string $version, int $timeout = 30)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->version = $version;

        $this->client = new Client([
            'base_uri' => $this->baseUrl . '/',
            'timeout' => $timeout,
        ]);
    }

    /**
     * Executes a generic API request.
     * Ejecuta una petición genérica a la API.
     *
     * @param string $method HTTP method (GET, POST, etc.) / Método HTTP (GET, POST, etc.).
     * @param string $endpoint API endpoint / Endpoint de la API.
     * @param array $params URL parameters / Parámetros de la URL.
     * @param mixed $data Data to send (can be array, JSON, or stream) / Datos a enviar (puede ser un array, JSON o un flujo).
     * @param array $query Additional query parameters / Parámetros de consulta adicionales.
     * @param array $headers Additional HTTP headers / Encabezados HTTP adicionales.
     * @return array API response decoded as array / Respuesta de la API decodificada como array.
     * @throws ApiException If an error occurs during the request / Si ocurre un error durante la solicitud.
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \RuntimeException
     */
    public function request(
        string $method,
        string $endpoint,
        array $params = [],
        mixed $data = null, // Cambiado a mixed para soportar flujos
        array $query = [],
        array $headers = [],
        $is_multimedia = false
    ): mixed {
        try {
            // Construir URL final
            $url = $this->buildUrl($endpoint, $params, $query, $is_multimedia);

            // Configurar opciones
            $options = [
                'headers' => array_merge([
                    'Accept' => 'application/json',
                ], $headers),
            ];

            // Log::channel('whatsapp')->info('Enviando solicitud a la API de WhatsApp.', [
            //     'method' => $method,
            //     'url' => $url,
            //     'headers' => $options['headers'],
            // ]);

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

            // Verificar el código de estado HTTP
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {

                // Respuesta exitosa
                $logArray = [
                    'URL' => $url,
                    'status_code' => $statusCode,
                ];

                if( $is_multimedia==false ){
                    $logArray['response_body'] = $response->getBody()->getContents();
                }

                Log::channel('whatsapp')->info(whatsapp_trans('messages.api_successful_response'), $logArray);

                if( $is_multimedia==true ){
                    return $response->getBody()->getContents();
                }

                // When it's a multimedia file, return raw content
                return json_decode($response->getBody(), true) ?: [];
            }

            // Handle unsuccessful status codes
            Log::channel('whatsapp')->warning(whatsapp_trans('messages.error_api_response'), [
                'status_code' => $statusCode,
                'response_body' => $response->getBody()->getContents(),
            ]);

            throw new ApiException(whatsapp_trans('messages.api_unsuccessful_response'), $statusCode);

        } catch (GuzzleException $e) {
            Log::channel('whatsapp')->error('API Error', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            // throw ApiException::fromGuzzleException($e);
            throw $this->handleException($e);
        }
    }

    public function requestMultimedia(
        string $method,
        string $endpoint,
        array $params = [],
        mixed $data = null, // Cambiado a mixed para soportar flujos
        array $query = [],
        array $headers = []
    ) {
        return $this->request(
            $method,
            $endpoint,
            $params,
            $data, // Cambiado a mixed para soportar flujos
            $query,
            $headers,
            true //Mandar siempre en true para que se retorne $response->getBody()->getContents()
        );
    }

    /**
     * Builds the final URL for the request.
     * Construye la URL final para la solicitud.
     *
     * @param string $endpoint API endpoint / Endpoint de la API.
     * @param array $params URL parameters / Parámetros de la URL.
     * @param array $query Additional query parameters / Parámetros de consulta adicionales.
     * @return string Built URL / URL construida.
     */
    protected function buildUrl(string $endpoint, array $params, array $query = [], $is_multimedia=false): string
    {
        if( $is_multimedia==true ){
            $url = $endpoint;
        }
        else{
            $url = str_replace(
                array_map(fn($k) => '{' . $k . '}', array_keys($params)),
                array_values($params),
                $this->version . '/' . $endpoint
            );
        }

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        Log::channel('whatsapp')->info(whatsapp_trans('messages.api_url_built'), ['url' => $url]);

        return $url;
    }

    /**
     * Handles Guzzle exceptions and converts them to ApiException.
     * Maneja excepciones de Guzzle y las convierte en ApiException.
     *
     * @param GuzzleException $e Guzzle exception / Excepción de Guzzle.
     * @return ApiException Custom exception / Excepción personalizada.
     */
    protected function handleException(GuzzleException $e): ApiException
    {
        $statusCode = 500;
        $body = [];
        $message = $e->getMessage();

        // Only if it's an exception with response (e.g., 4xx/5xx)
        if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);
            $message = $body['error']['message'] ?? $message;

            Log::channel('whatsapp')->error(whatsapp_trans('messages.api_error_in_response'), [
                'status_code' => $statusCode,
                'response_body' => $body,
                'headers' => $response->getHeaders(),
            ]);

            Log::channel('whatsapp')->error(whatsapp_trans('messages.api_error_in_response'), [
                'status_code' => $statusCode,
                'response_body' => $body,
                'headers' => $response->getHeaders(),
            ]);
        }

        return new ApiException($message, $statusCode, $body);
    }
}