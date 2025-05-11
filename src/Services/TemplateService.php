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

    public function createUploadSession(WhatsappBusinessAccount $account): string
    {
        $endpoint = Endpoints::build(Endpoints::CREATE_UPLOAD_SESSION, [
            'app_id' => $account->app_id,
        ]);

        $headers = [
            'Authorization' => 'Bearer ' . $account->api_token,
        ];

        Log::info('Creando sesión de carga.', [
            'endpoint' => $endpoint,
            'app_id' => $account->app_id,
        ]);

        $response = $this->apiClient->request(
            'POST',
            $endpoint,
            [],
            null,
            [],
            $headers
        );

        Log::info('Sesión de carga creada.', [
            'response' => $response,
        ]);

        return $response['id'] ?? throw new \Exception('No se pudo obtener el ID de la sesión de carga.');
    }

    public function uploadMedia(WhatsappBusinessAccount $account, string $sessionId, string $filePath, string $mimeType): string
    {
        $endpoint = Endpoints::build(Endpoints::SESSION_UPLOAD_MEDIA, [
            'session_id' => $sessionId,
        ]);

        // Validar que el archivo exista
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("El archivo no existe: $filePath");
        }

        $fileContents = file_get_contents($filePath);

        // Validar y convertir a UTF-8 si es necesario
        if (!mb_check_encoding($fileContents, 'UTF-8')) {
            $fileContents = mb_convert_encoding($fileContents, 'UTF-8', 'auto');
        }

        $headers = [
            'Authorization' => "OAuth {$account->api_token}",
            'file_offset' => '0',
            'Content-Type' => $mimeType,
        ];

        Log::info('Subiendo archivo.', [
            'endpoint' => $endpoint,
            'file_path' => $filePath,
        ]);

        $response = $this->apiClient->request(
            'POST',
            $endpoint,
            [],
            $fileContents,
            [],
            $headers
        );

        Log::info('Archivo subido.', [
            'response' => $response,
        ]);

        return $response['h'] ?? throw new \Exception('No se pudo obtener el identificador del archivo.');
    }
}