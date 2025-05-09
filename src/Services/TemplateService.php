<?php

namespace ScriptDevelop\WhatsappManager\Services;

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
    public function syncTemplates(WhatsappBusinessAccount $account): void
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
                $headers // Pasar los encabezados con el token
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

    /**
     * Crear o actualizar una plantilla en la base de datos.
     */
    protected function storeOrUpdateTemplate(string $businessId, array $templateData): void
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
                'category' => $this->getCategoryId($templateData['category']),
                'status' => $templateData['status'],
                'json' => $templateData,
            ]
        );

        Log::channel('whatsapp')->info('Plantilla guardada en la base de datos.', [
            'template_id' => $template->template_id,
        ]);

        // Sincronizar los componentes de la plantilla
        $this->syncTemplateComponents($template, $templateData['components'] ?? []);
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

        foreach ($components as $componentData) {
            Log::channel('whatsapp')->info('Procesando componente.', [
                'component_type' => $componentData['type'],
            ]);

            TemplateComponent::updateOrCreate(
                [
                    'template_id' => $template->template_id,
                    'type' => $componentData['type'],
                ],
                [
                    'content' => $this->getComponentContent($componentData),
                    'parameters' => $componentData['parameters'] ?? [],
                ]
            );
        }

        Log::channel('whatsapp')->info('Componentes sincronizados.', [
            'template_id' => $template->template_id,
        ]);
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
}