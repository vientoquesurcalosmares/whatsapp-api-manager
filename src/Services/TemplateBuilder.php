<?php

namespace ScriptDevelop\WhatsappManager\Services;

use InvalidArgumentException;
use ScriptDevelop\WhatsappManager\Models\Template;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use Illuminate\Support\Facades\Log;
use ScriptDevelop\WhatsappManager\Models\TemplateCategory;
use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

class TemplateBuilder
{
    protected array $templateData = [
        'components' => [],
    ];

    protected int $buttonCount = 0;
    protected ApiClient $apiClient;
    protected WhatsappBusinessAccount $account;

    public function __construct(ApiClient $apiClient, WhatsappBusinessAccount $account)
    {
        $this->apiClient = $apiClient;
        $this->account = $account;
    }


    public function setName(string $name): self
    {
        if (strlen($name) > 512) {
            throw new InvalidArgumentException('El nombre de la plantilla no puede exceder los 512 caracteres.');
        }

        $this->templateData['name'] = $name;
        return $this;
    }

    public function setLanguage(string $language): self
    {
        $this->templateData['language'] = $language;
        return $this;
    }

    public function setCategory(string $category): self
    {
        $validCategories = ['AUTHENTICATION', 'MARKETING', 'UTILITY'];
        if (!in_array($category, $validCategories)) {
            throw new InvalidArgumentException('Categoría inválida. Debe ser AUTHENTICATION, MARKETING o UTILITY.');
        }

        $this->templateData['category'] = $category;
        return $this;
    }

    public function addHeader(string $format, string $content, ?array $example = null): self
    {
        Log::channel('whatsapp')->info('Estado actual de los componentes antes de agregar HEADER.', [
            'components' => $this->templateData['components'],
        ]);
        
        if ($this->componentExists('HEADER')) {
            throw new InvalidArgumentException('Solo se permite un componente HEADER por plantilla.');
        }

        if (count($this->getComponentsByType('HEADER')) > 0) {
            throw new InvalidArgumentException('Solo se permite un componente HEADER por plantilla.');
        }

        if ($format === 'TEXT' && strlen($content) > 60) {
            throw new InvalidArgumentException('El texto del HEADER no puede exceder los 60 caracteres.');
        }

        $this->templateData['components'][] = [
            'type' => 'HEADER',
            'format' => $format,
            'text' => $content,
            'example' => $example,
        ];

        return $this;
    }

    public function addBody(string $text, ?array $example = null): self
    {
        if ($this->componentExists('HEADER')) {
            throw new InvalidArgumentException('Solo se permite un componente HEADER por plantilla.');
        }

        if (count($this->getComponentsByType('BODY')) > 0) {
            throw new InvalidArgumentException('Solo se permite un componente BODY por plantilla.');
        }

        if (strlen($text) > 1024) {
            throw new InvalidArgumentException('El texto del BODY no puede exceder los 1024 caracteres.');
        }

        $this->templateData['components'][] = [
            'type' => 'BODY',
            'text' => $text,
            'example' => $example,
        ];

        return $this;
    }

    public function addFooter(string $text): self
    {
        if ($this->componentExists('HEADER')) {
            throw new InvalidArgumentException('Solo se permite un componente HEADER por plantilla.');
        }

        if (count($this->getComponentsByType('FOOTER')) > 0) {
            throw new InvalidArgumentException('Solo se permite un componente FOOTER por plantilla.');
        }

        if (strlen($text) > 60) {
            throw new InvalidArgumentException('El texto del FOOTER no puede exceder los 60 caracteres.');
        }

        $this->templateData['components'][] = [
            'type' => 'FOOTER',
            'text' => $text,
        ];

        return $this;
    }

    public function addButton(string $type, string $text, ?string $urlOrPhone = null): self
    {
        $existingButtons = array_filter($this->templateData['components'], fn($c) => $c['type'] === 'BUTTONS');
        $buttonCount = count($existingButtons);

        if ($this->buttonCount >= 10) {
            throw new InvalidArgumentException('No se pueden agregar más de 10 botones a una plantilla.');
        }

        if ($type === 'QUICK_REPLY' && strlen($text) > 25) {
            throw new InvalidArgumentException('El texto del botón QUICK_REPLY no puede exceder los 25 caracteres.');
        }

        if ($type === 'URL' && strlen($urlOrPhone) > 2000) {
            throw new InvalidArgumentException('La URL del botón no puede exceder los 2000 caracteres.');
        }

        if ($type === 'PHONE_NUMBER' && strlen($urlOrPhone) > 20) {
            throw new InvalidArgumentException('El número de teléfono no puede exceder los 20 caracteres.');
        }

        if ($buttonCount >= 3) {
            throw new InvalidArgumentException('No se pueden agregar más de 3 botones a una plantilla.');
        }

        $button = [
            'type' => $type,
            'text' => $text,
        ];

        if ($type === 'URL' || $type === 'PHONE_NUMBER') {
            $button[strtolower($type)] = $urlOrPhone;
        }

        $this->templateData['components'][] = [
            'type' => 'BUTTONS',
            'buttons' => [$button],
        ];

        $this->buttonCount++;
        return $this;
    }

    protected function componentExists(string $type): bool
    {
        foreach ($this->templateData['components'] as $component) {
            if ($component['type'] === $type) {
                return true;
            }
        }
        return false;
    }

    public function build(): array
    {
        if (empty($this->templateData['name'])) {
            throw new InvalidArgumentException('El nombre de la plantilla es obligatorio.');
        }

        if (empty($this->templateData['language'])) {
            throw new InvalidArgumentException('El idioma de la plantilla es obligatorio.');
        }

        if (empty($this->templateData['category'])) {
            throw new InvalidArgumentException('La categoría de la plantilla es obligatoria.');
        }

        if (empty($this->getComponentsByType('BODY'))) {
            throw new InvalidArgumentException('El componente BODY es obligatorio.');
        }

        return $this->templateData;
    }

    public function save(): Template
    {
        try {
            $this->validateTemplate();

            $endpoint = Endpoints::build(Endpoints::CREATE_TEMPLATE, [
                'waba_id' => $this->account->whatsapp_business_id,
            ]);

            $headers = [
                'Authorization' => 'Bearer ' . $this->account->api_token,
                'Content-Type' => 'application/json',
            ];

            Log::channel('whatsapp')->info('Enviando plantilla a la API de WhatsApp.', [
                'endpoint' => $endpoint,
                'template_data' => $this->templateData,
            ]);

            $response = $this->apiClient->request(
                'POST',
                $endpoint,
                [],
                $this->templateData,
                [],
                $headers
            );

            Log::channel('whatsapp')->info('Respuesta recibida de la API al crear plantilla.', [
                'response' => $response,
            ]);

            $template = Template::create([
                'whatsapp_business_id' => $this->account->whatsapp_business_id,
                'wa_template_id' => $response['id'] ?? null,
                'name' => $this->templateData['name'],
                'language' => $this->templateData['language'],
                'category_id' => $this->getCategoryId($this->templateData['category']),
                'status' => 'PENDING',
                'json' => json_encode($this->templateData),
            ]);
    
            // Reiniciar el estado del builder
            $this->templateData = ['components' => []];
            $this->buttonCount = 0;
    
            return $template;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error al guardar la plantilla.', [
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function validateTemplate(): void
    {
        if (empty($this->templateData['name'])) {
            throw new InvalidArgumentException('El nombre de la plantilla es obligatorio.');
        }

        if (empty($this->templateData['language'])) {
            throw new InvalidArgumentException('El idioma de la plantilla es obligatorio.');
        }

        if (empty($this->templateData['category'])) {
            throw new InvalidArgumentException('La categoría de la plantilla es obligatoria.');
        }

        if (empty($this->getComponentsByType('BODY'))) {
            throw new InvalidArgumentException('El componente BODY es obligatorio.');
        }
    }

    protected function getComponentsByType(string $type): array
    {
        return array_filter($this->templateData['components'], fn($component) => $component['type'] === $type);
    }

    protected function getCategoryId(string $categoryName): string
    {
        if (empty($categoryName)) {
            throw new InvalidArgumentException('El nombre de la categoría es obligatorio.');
        }

        $category = TemplateCategory::firstOrCreate(
            ['name' => $categoryName],
            ['description' => ucfirst($categoryName)]
        );

        return $category->category_id;
    }
}