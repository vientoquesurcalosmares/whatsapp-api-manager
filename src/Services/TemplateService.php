<?php

namespace ScriptDevelop\WhatsappManager\Services;

use InvalidArgumentException;
use ScriptDevelop\WhatsappManager\Models\Template;
use ScriptDevelop\WhatsappManager\Models\TemplateComponent;
use ScriptDevelop\WhatsappManager\Models\TemplateCategory;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;
use Illuminate\Support\Facades\Log;

class TemplateService
{
    protected ApiClient $apiClient;

    public function __construct(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    /**
     * Sincronizar todas las plantillas desde la API de WhatsApp.
     */
    public function getTemplates(WhatsappBusinessAccount $account): void
    {
        $endpoint = Endpoints::build(Endpoints::GET_TEMPLATES, [
            'waba_id' => $account->whatsapp_business_id,
        ]);

        Log::channel('whatsapp')->info('Iniciando sincronización de plantillas.', [
            'endpoint' => $endpoint,
            'business_id' => $account->whatsapp_business_id,
        ]);

        $headers = [
            'Authorization' => 'Bearer ' . $account->api_token,
        ];

        Log::channel('whatsapp')->info('Iniciando sincronización de plantillas.', [
            'endpoint' => $endpoint,
            'headers' => $headers,
        ]);

        try {
            $response = $this->apiClient->request(
                'GET',
                $endpoint,
                [],
                null,
                [],
                $headers // Pasar los encabezados aquí
            );

            Log::channel('whatsapp')->info('Respuesta recibida de la API.', [
                'response' => $response,
            ]);

            $templates = $response['data'] ?? [];

            foreach ($templates as $templateData) {
                $this->storeOrUpdateTemplate($account->whatsapp_business_id, $templateData);
            }
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error al sincronizar plantillas.', [
                'error_message' => $e->getMessage(),
                'endpoint' => $endpoint,
                'business_id' => $account->whatsapp_business_id,
            ]);
            throw $e;
        }
    }

    public function getTemplateById(WhatsappBusinessAccount $account, string $templateId): Template
    {
        if (empty($templateId)) {
            throw new InvalidArgumentException('El ID de la plantilla es obligatorio.');
        }

        $endpoint = Endpoints::build(Endpoints::GET_TEMPLATE, [
            'template_id' => $templateId,
        ]);

        $headers = [
            'Authorization' => 'Bearer ' . $account->api_token,
        ];

        Log::channel('whatsapp')->info('Obteniendo plantilla desde la API.', [
            'endpoint' => $endpoint,
            'headers' => $headers,
        ]);

        try {
            $response = $this->apiClient->request(
                'GET',
                $endpoint,
                [],
                null,
                [],
                $headers
            );

            Log::channel('whatsapp')->info('Respuesta recibida de la API.', [
                'response' => $response,
            ]);

            // Almacenar o actualizar la plantilla en la base de datos
            return $this->storeOrUpdateTemplate($account->whatsapp_business_id, $response);
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error al obtener la plantilla.', [
                'error_message' => $e->getMessage(),
                'endpoint' => $endpoint,
                'template_id' => $templateId,
            ]);
            throw $e;
        }
    }

    public function getTemplateByName(WhatsappBusinessAccount $account, string $templateName): ?Template
    {
        $endpoint = Endpoints::build(Endpoints::GET_TEMPLATES, [
            'waba_id' => $account->whatsapp_business_id,
        ]);

        $query = [
            'name' => $templateName,
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $account->api_token,
        ];

        Log::channel('whatsapp')->info('Obteniendo plantilla por nombre desde la API.', [
            'endpoint' => $endpoint,
            'query' => $query,
            'headers' => $headers,
        ]);

        try {
            $response = $this->apiClient->request(
                'GET',
                $endpoint,
                [],
                null,
                $query,
                $headers
            );

            Log::channel('whatsapp')->info('Respuesta recibida de la API.', [
                'response' => $response,
            ]);

            $templateData = $response['data'][0] ?? null;

            if (!$templateData) {
                Log::channel('whatsapp')->warning('No se encontró la plantilla con el nombre especificado.', [
                    'template_name' => $templateName,
                ]);
                return null;
            }

            // Almacenar o actualizar la plantilla en la base de datos
            return $this->storeOrUpdateTemplate($account->whatsapp_business_id, $templateData);
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error al obtener la plantilla por nombre.', [
                'error_message' => $e->getMessage(),
                'endpoint' => $endpoint,
                'template_name' => $templateName,
            ]);
            throw $e;
        }
    }

    public function createUtilityTemplate(WhatsappBusinessAccount $account): TemplateBuilder
    {
        return (new TemplateBuilder($this->apiClient, $account, $this))
            ->setCategory('UTILITY'); // Categoría específica para plantillas transaccionales
    }

    public function createMarketingTemplate(WhatsappBusinessAccount $account): TemplateBuilder
    {
        return (new TemplateBuilder($this->apiClient, $account, $this))
            ->setCategory('MARKETING'); // Categoría específica para plantillas de marketing
    }

    public function createAuthenticationTemplate(WhatsappBusinessAccount $account): TemplateBuilder
    {
        return (new TemplateBuilder($this->apiClient, $account, $this))
            ->setCategory('AUTHENTICATION'); // Categoría específica para plantillas de autenticación
    }

    public function deleteTemplateById(WhatsappBusinessAccount $account, string $templateId, bool $hardDelete = false): bool
    {
        $endpoint = Endpoints::build(Endpoints::DELETE_TEMPLATE, [
            'waba_id' => $account->whatsapp_business_id,
        ]);

        $query = [
            'hsm_id' => $templateId,
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $account->api_token,
        ];

        Log::channel('whatsapp')->info('Eliminando plantilla por ID desde la API.', [
            'endpoint' => $endpoint,
            'query' => $query,
            'headers' => $headers,
        ]);

        try {
            $response = $this->apiClient->request(
                'DELETE',
                $endpoint,
                [],
                null,
                $query,
                $headers
            );

            Log::channel('whatsapp')->info('Respuesta recibida de la API.', [
                'response' => $response,
            ]);

            if ($response['success'] ?? false) {
                $template = Template::where('wa_template_id', $templateId)->first();

                if ($template) {
                    if ($hardDelete) {
                        $template->forceDelete(); // Hard delete
                    } else {
                        $template->delete(); // Soft delete
                    }
                }

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error al eliminar la plantilla por ID.', [
                'error_message' => $e->getMessage(),
                'template_id' => $templateId,
            ]);
            throw $e;
        }
    }

    public function deleteTemplateByName(WhatsappBusinessAccount $account, string $templateName, bool $hardDelete = false): bool
    {
        $endpoint = Endpoints::build(Endpoints::DELETE_TEMPLATE, [
            'waba_id' => $account->whatsapp_business_id,
        ]);

        $query = [
            'name' => $templateName,
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $account->api_token,
        ];

        Log::channel('whatsapp')->info('Eliminando plantilla por nombre desde la API.', [
            'endpoint' => $endpoint,
            'query' => $query,
            'headers' => $headers,
        ]);

        try {
            $response = $this->apiClient->request(
                'DELETE',
                $endpoint,
                [],
                null,
                $query,
                $headers
            );

            Log::channel('whatsapp')->info('Respuesta recibida de la API.', [
                'response' => $response,
            ]);

            if ($response['success'] ?? false) {
                $template = Template::where('name', $templateName)->first();

                if ($template) {
                    if ($hardDelete) {
                        $template->forceDelete(); // Hard delete
                    } else {
                        $template->delete(); // Soft delete
                    }
                }

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error al eliminar la plantilla por nombre.', [
                'error_message' => $e->getMessage(),
                'template_name' => $templateName,
            ]);
            throw $e;
        }
    }

    /**
     * Crear o actualizar una plantilla en la base de datos.
     */
    protected function storeOrUpdateTemplate(string $businessId, array $templateData): Template
    {
        Log::channel('whatsapp')->info('Procesando plantilla.', [
            'template_id' => $templateData['id'],
            'template_name' => $templateData['name'],
        ]);

        $template = Template::updateOrCreate(
            [
                'wa_template_id' => $templateData['id'],
            ],
            [
                'whatsapp_business_id' => $businessId,
                'name' => $templateData['name'],
                'language' => $templateData['language'],
                'category_id' => $this->getCategoryId($templateData['category']),
                'status' => $templateData['status'],
                'json' => json_encode($templateData),
            ]
        );

        Log::channel('whatsapp')->info('Plantilla guardada en la base de datos.', [
            'template_id' => $template->template_id,
        ]);

        // Sincronizar los componentes de la plantilla
        $this->syncTemplateComponents($template, $templateData['components'] ?? []);

        return $template;
    }

    /**
     * Obtener el ID de la categoría o crearla si no existe.
     */
    protected function getCategoryId(string $categoryName): string
    {
        Log::channel('whatsapp')->info('Obteniendo categoría.', [
            'category_name' => $categoryName,
        ]);

        $category = TemplateCategory::firstOrCreate(
            ['name' => $categoryName],
            ['description' => ucfirst($categoryName)]
        );

        Log::channel('whatsapp')->info('Categoría procesada.', [
            'category_id' => $category->category_id,
            'category_name' => $category->name,
        ]);

        return $category->category_id;
    }

    /**
     * Sincronizar los componentes de una plantilla.
     */
    protected function syncTemplateComponents(Template $template, array $components): void
    {
        Log::channel('whatsapp')->info('Sincronizando componentes de la plantilla.', [
            'template_id' => $template->template_id,
            'component_count' => count($components),
        ]);

        try {
            foreach ($components as $componentData) {
                $type = strtolower($componentData['type']);
                if ($type === 'buttons') {
                    $type = 'button';
                }

                Log::channel('whatsapp')->info('Procesando componente.', [
                    'component_type' => $type,
                ]);
    
                TemplateComponent::updateOrCreate(
                    [
                        'template_id' => $template->template_id,
                        'type' => $type,
                    ],
                    [
                        'content' => $this->getComponentContent($componentData),
                        'parameters' => $componentData['parameters'] ?? [],
                    ]
                );

                Log::channel('whatsapp')->info('Componentes sincronizados.', [
                    'template_id' => $template->template_id,
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error al sincronizar componentes.', [
                'template_id' => $template->template_id,
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Obtener el contenido del componente según su tipo.
     */
    protected function getComponentContent(array $componentData): ?array
    {
        Log::channel('whatsapp')->info('Obteniendo contenido del componente.', [
            'component_type' => $componentData['type'],
        ]);

        switch ($componentData['type']) {
            case 'HEADER':
                return [
                    'format' => $componentData['format'] ?? null,
                    'text' => $componentData['text'] ?? null,
                    'example' => $componentData['example'] ?? null,
                ];
            case 'BODY':
            case 'FOOTER':
                return [
                    'text' => $componentData['text'] ?? null,
                ];
            case 'BUTTONS':
                return [
                    'buttons' => $componentData['buttons'] ?? [],
                ];
            default:
                return null;
        }
    }

    protected function validateTemplateData(array $templateData): void
    {
        if (empty($templateData['name'])) {
            throw new InvalidArgumentException('El nombre de la plantilla es obligatorio.');
        }

        if (empty($templateData['language'])) {
            throw new InvalidArgumentException('El idioma de la plantilla es obligatorio.');
        }

        if (empty($templateData['category'])) {
            throw new InvalidArgumentException('La categoría de la plantilla es obligatoria.');
        }

        if (empty($templateData['components']) || !is_array($templateData['components'])) {
            throw new InvalidArgumentException('Los componentes de la plantilla son obligatorios.');
        }
    }

    public function createUploadSession(WhatsappBusinessAccount $account, string $filePath, string $mimeType): string
    {
        // Extraer solo el nombre del archivo
        $fileName = basename($filePath);

        // Construir la URL completa
        $baseUrl = config('whatsapp.api.base_url', 'https://graph.facebook.com');
        $version = config('whatsapp.api.version', 'v22.0');
        $url = rtrim($baseUrl, '/') . '/' . ltrim($version, '/') . '/' . $account->app_id . '/uploads';

        $headers = [
            'Authorization: Bearer ' . $account->api_token,
            'Content-Type: application/json',
        ];

        $body = [
            'file_name' => $fileName,
            'file_type' => $mimeType,
        ];

        Log::info('Creando sesión de carga.', [
            'url' => $url,
            'body' => $body,
        ]);

        // Configurar cURL
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => $headers,
        ]);

        // Ejecutar la solicitud
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Manejar errores de cURL
        if (curl_errno($curl)) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new \RuntimeException("Error en cURL: $error");
        }

        curl_close($curl);

        // Registrar la respuesta
        Log::info('Respuesta de la API al crear la sesión de carga.', [
            'response' => $response,
            'http_code' => $httpCode,
        ]);

        // Validar el código de respuesta HTTP
        if ($httpCode !== 200) {
            throw new \RuntimeException("Error al crear la sesión de carga. Código HTTP: $httpCode. Respuesta: $response");
        }

        // Decodificar la respuesta JSON
        $responseData = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Error al decodificar la respuesta JSON: ' . json_last_error_msg());
        }

        // Obtener el ID de la sesión de carga
        $uploadSessionId = $responseData['id'] ?? null;

        if (!$uploadSessionId) {
            throw new \Exception('No se pudo obtener el ID de la sesión de carga.');
        }

        Log::info('Sesión de carga creada exitosamente.', ['uploadSessionId' => $uploadSessionId]);

        return $uploadSessionId;
    }

    public function uploadMedia(WhatsappBusinessAccount $account, string $sessionId, string $filePath, string $mimeType): string
    {
        // Validar que el archivo exista
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("El archivo no existe: $filePath");
        }

        // Construir la URL completa
        $baseUrl = config('whatsapp.api.base_url', 'https://graph.facebook.com');
        $version = config('whatsapp.api.version', 'v22.0');
        $url = rtrim($baseUrl, '/') . '/' . ltrim($version, '/') . "/$sessionId";

        Log::info('URL final para la carga de medios:', ['url' => $url]);

        // Leer el contenido del archivo
        $fileContents = file_get_contents($filePath);

        // Configurar cURL
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fileContents,
            CURLOPT_HTTPHEADER => [
                'file_offset: 0',
                'Content-Type: application/octet-stream',
                'Authorization: OAuth ' . $account->api_token,
            ],
        ]);

        // Ejecutar la solicitud
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Manejar errores de cURL
        if (curl_errno($curl)) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new \RuntimeException("Error en cURL: $error");
        }

        curl_close($curl);

        // Registrar la respuesta
        Log::info('Respuesta de la API después de subir el archivo.', [
            'response' => $response,
            'http_code' => $httpCode,
        ]);

        // Validar el código de respuesta HTTP
        if ($httpCode !== 200) {
            throw new \RuntimeException("Error al subir el archivo. Código HTTP: $httpCode. Respuesta: $response");
        }

        // Decodificar la respuesta JSON
        $responseData = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Error al decodificar la respuesta JSON: ' . json_last_error_msg());
        }

        // Obtener el identificador del archivo
        $handle = $responseData['h'] ?? null;

        if (!$handle) {
            throw new \Exception('No se pudo obtener el identificador del archivo.');
        }

        Log::info('Archivo subido exitosamente.', ['handle' => $handle]);

        return $handle;
    }

    protected function validateMediaFile(string $filePath, string $mimeType): void
    {
        $fileSize = filesize($filePath);
        $mediaType = $this->getMediaTypeFromMimeType($mimeType);

        // Obtener configuraciones desde whatsapp.php
        $maxFileSize = config("whatsapp.media.max_file_size.$mediaType");
        $allowedMimeTypes = config("whatsapp.media.allowed_types.$mediaType");

        // Validar tamaño del archivo
        if ($fileSize > $maxFileSize) {
            throw new InvalidArgumentException("El archivo excede el tamaño máximo permitido de " . ($maxFileSize / 1024 / 1024) . " MB.");
        }

        // Validar tipo MIME del archivo
        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new InvalidArgumentException("El tipo de archivo no es permitido. Tipo recibido: $mimeType.");
        }

        Log::info('Archivo validado correctamente.', [
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
        ]);
    }

    protected function getMediaTypeFromMimeType(string $mimeType): string
    {
        // Mapear tipos MIME a categorías de medios
        $mediaTypes = [
            'image' => ['image/jpeg', 'image/png'],
            'audio' => ['audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/amr', 'audio/ogg'],
            'video' => ['video/mp4', 'video/3gp'],
            'document' => [
                'text/plain',
                'application/pdf',
                'application/vnd.ms-powerpoint',
                'application/msword',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'sticker' => ['image/webp'],
        ];

        foreach ($mediaTypes as $type => $mimeTypes) {
            if (in_array($mimeType, $mimeTypes)) {
                return $type;
            }
        }

        throw new InvalidArgumentException("No se pudo determinar el tipo de media para el MIME: $mimeType.");
    }
}