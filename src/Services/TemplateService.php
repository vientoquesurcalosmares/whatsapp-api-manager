<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Models\Template;
use ScriptDevelop\WhatsappManager\Models\TemplateComponent;
use ScriptDevelop\WhatsappManager\Models\TemplateCategory;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

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

        $response = $this->apiClient->request(
            'GET',
            $endpoint
        );

        $templates = $response['data'] ?? [];

        foreach ($templates as $templateData) {
            $this->storeOrUpdateTemplate($account->whatsapp_business_id, $templateData);
        }
    }

    /**
     * Crear o actualizar una plantilla en la base de datos.
     */
    protected function storeOrUpdateTemplate(string $businessId, array $templateData): void
    {
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

        // Sincronizar los componentes de la plantilla
        $this->syncTemplateComponents($template, $templateData['components'] ?? []);
    }

    /**
     * Obtener el ID de la categoría o crearla si no existe.
     */
    protected function getCategoryId(string $categoryName): string
    {
        $category = TemplateCategory::firstOrCreate(
            ['name' => $categoryName],
            ['description' => ucfirst($categoryName)]
        );

        return $category->category_id;
    }

    /**
     * Sincronizar los componentes de una plantilla.
     */
    protected function syncTemplateComponents(Template $template, array $components): void
    {
        foreach ($components as $componentData) {
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
    }

    /**
     * Obtener el contenido del componente según su tipo.
     */
    protected function getComponentContent(array $componentData): ?array
    {
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