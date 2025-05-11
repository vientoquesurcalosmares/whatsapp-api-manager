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
    protected TemplateService $templateService;
    protected WhatsappBusinessAccount $account;

    public function __construct(ApiClient $apiClient, WhatsappBusinessAccount $account, TemplateService $templateService)
    {
        $this->apiClient = $apiClient;
        $this->account = $account;
        $this->templateService = $templateService;
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
        Log::info('Estado actual de los componentes antes de agregar HEADER.', [
            'components' => $this->templateData['components'],
        ]);

        if ($this->componentExists('HEADER')) {
            throw new InvalidArgumentException('Solo se permite un componente HEADER por plantilla.');
        }

        $validFormats = ['TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT', 'LOCATION'];
        if (!in_array($format, $validFormats)) {
            throw new InvalidArgumentException('Formato inválido para el HEADER. Debe ser uno de: TEXT, IMAGE, VIDEO, DOCUMENT, LOCATION.');
        }

        if ($format === 'TEXT' && strlen($content) > 60) {
            throw new InvalidArgumentException('El texto del HEADER no puede exceder los 60 caracteres.');
        }

        if ($format === 'TEXT') {
            $this->validateParameters($content, $example, 'HEADER');
        }

        if ($format !== 'TEXT' && $example !== null) {
            throw new InvalidArgumentException('Los ejemplos solo están permitidos para headers de tipo TEXT.');
        }

        if (in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'])) {
            // Subir el archivo multimedia
            $filePath = $content; // Ruta del archivo local
            $fileSize = filesize($filePath);
            $mimeType = mime_content_type($filePath);

            $sessionId = $this->templateService->createUploadSession($this->account);
            $mediaId = $this->templateService->uploadMedia($this->account, $sessionId, $filePath, $mimeType);

            $content = $mediaId; // Reemplazar el contenido con el ID del archivo subido
        }

        if ($format === 'LOCATION' && !empty($content)) {
            throw new InvalidArgumentException('El HEADER de tipo LOCATION no debe tener contenido.');
        }

        $headerComponent = [
            'type' => 'HEADER',
            'format' => $format,
        ];

        if ($format === 'TEXT') {
            $headerComponent['text'] = $content;
            $headerComponent['example'] = [
                'header_text' => $example,
            ];
        } elseif (in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'])) {
            $headerComponent['media'] = $content; // ID del archivo subido
        } elseif ($format === 'LOCATION') {
            $headerComponent['location'] = true; // Indica que es un header de ubicación
        }

        $this->templateData['components'][] = $headerComponent;

        return $this;
    }

    public function addBody(string $text, ?array $example = null): self
    {
        if ($this->componentExists('BODY')) {
            throw new InvalidArgumentException('Solo se permite un componente BODY por plantilla.');
        }

        if (strlen($text) > 1024) {
            throw new InvalidArgumentException('El texto del BODY no puede exceder los 1024 caracteres.');
        }

        $this->validateParameters($text, $example, 'BODY');

        $formattedExample = null;
        if ($example !== null) {
            $formattedExample = [
                'body_text' => [$example]
            ];
        }

        $this->templateData['components'][] = [
            'type' => 'BODY',
            'text' => $text,
            'example' => $formattedExample,
        ];

        return $this;
    }

    public function addFooter(string $text): self
    {
        if ($this->componentExists('FOOTER')) {
            throw new InvalidArgumentException('Solo se permite un componente FOOTER por plantilla.');
        }

        if (strlen($text) > 60) {
            throw new InvalidArgumentException('El texto del FOOTER no puede exceder los 60 caracteres.');
        }

        $this->validateParameters($text, null, 'FOOTER');

        $this->templateData['components'][] = [
            'type' => 'FOOTER',
            'text' => $text,
        ];

        return $this;
    }

    public function addButton(string $type, string $text, ?string $urlOrPhone = null): self
    {
        // Validar el número máximo de botones
        if ($this->buttonCount >= 3) {
            throw new InvalidArgumentException('No se pueden agregar más de 3 botones a una plantilla.');
        }

        // Validar el texto y otros parámetros según el tipo de botón
        if ($type === 'QUICK_REPLY' && strlen($text) > 25) {
            throw new InvalidArgumentException('El texto del botón QUICK_REPLY no puede exceder los 25 caracteres.');
        }

        if ($type === 'URL' && strlen($urlOrPhone) > 2000) {
            throw new InvalidArgumentException('La URL del botón no puede exceder los 2000 caracteres.');
        }

        if ($type === 'PHONE_NUMBER' && strlen($urlOrPhone) > 20) {
            throw new InvalidArgumentException('El número de teléfono no puede exceder los 20 caracteres.');
        }

        // Crear el botón
        $button = [
            'type' => $type,
            'text' => $text,
        ];

        if ($type === 'URL' || $type === 'PHONE_NUMBER') {
            $button[strtolower($type)] = $urlOrPhone;
        }

        // Buscar el componente BUTTONS existente
        $existingButtons = $this->getComponentsByType('BUTTONS');

        if (empty($existingButtons)) {
            // Si no existe un componente BUTTONS, crearlo
            $this->templateData['components'][] = [
                'type' => 'BUTTONS',
                'buttons' => [$button],
            ];
        } else {
            // Si ya existe, agregar el botón al componente existente
            $index = array_search('BUTTONS', array_column($this->templateData['components'], 'type'));
            $this->templateData['components'][$index]['buttons'][] = $button;
        }

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

    protected function validateParameters(string $text, ?array $example, string $type): void
    {
        // Extraer los parámetros del texto ({{1}}, {{num_order}}, etc.)
        preg_match_all('/{{(.*?)}}/', $text, $matches);
        $placeholders = $matches[1] ?? [];

        if ($type === 'HEADER') {
            // Validar que el HEADER tenga exactamente un parámetro o ninguno
            if (count($placeholders) > 1) {
                throw new InvalidArgumentException('El HEADER solo puede tener un único parámetro.');
            }

            if (!empty($placeholders) && ($example === null || count($placeholders) !== count($example))) {
                throw new InvalidArgumentException('Los parámetros en el HEADER no coinciden con los ejemplos proporcionados.');
            }
        }

        if ($type === 'BODY') {
            // Validar que el BODY pueda tener múltiples parámetros o ninguno
            if (!empty($placeholders) && ($example === null || count($placeholders) !== count($example))) {
                throw new InvalidArgumentException('Los parámetros en el BODY no coinciden con los ejemplos proporcionados.');
            }
        }

        if ($type === 'FOOTER') {
            // Validar que el FOOTER no tenga parámetros
            if (!empty($placeholders)) {
                throw new InvalidArgumentException('El FOOTER no puede tener parámetros.');
            }
        }

        // Validar secuencia estricta para parámetros numéricos
        if (ctype_digit(implode('', $placeholders))) {
            $expectedSequence = range(1, count($placeholders));
            if ($placeholders !== array_map('strval', $expectedSequence)) {
                throw new InvalidArgumentException('Los parámetros numéricos deben seguir una secuencia estricta ({{1}}, {{2}}, etc.).');
            }
        }
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

            Log::info('Enviando plantilla a la API de WhatsApp.', [
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

            Log::info('Respuesta recibida de la API al crear plantilla.', [
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
            Log::error('Error al guardar la plantilla.', [
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