<?php

namespace ScriptDevelop\WhatsappManager\Services;

use InvalidArgumentException;
use ScriptDevelop\WhatsappManager\Models\Template;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use Illuminate\Support\Facades\Log;
use ScriptDevelop\WhatsappManager\Models\TemplateCategory;
use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

/**
 * Constructor de plantillas para mensajes de WhatsApp Business API
 * 
 * Permite crear y configurar plantillas con diferentes componentes,
 * validando los requisitos de la API y manejando la comunicación con el servicio.
 */
class TemplateBuilder
{
    /** @var array Datos de la plantilla en construcción */
    protected array $templateData = [
        'components' => [],
    ];

    /** @var int Contador de botones agregados */
    protected int $buttonCount = 0;

    /** @var ApiClient Cliente para comunicación con la API */
    protected ApiClient $apiClient;

    /** @var TemplateService Servicio auxiliar para plantillas */
    protected TemplateService $templateService;

    /** @var WhatsappBusinessAccount Cuenta empresarial asociada */
    protected WhatsappBusinessAccount $account;

    /** @var FlowService Servicio auxiliar para flujos */
    protected FlowService $flowService;

    /**
     * Constructor de la clase
     *
     * @param ApiClient $apiClient
     * @param WhatsappBusinessAccount $account
     * @param TemplateService $templateService
     * @param FlowService $flowService
     */
    public function __construct(ApiClient $apiClient, WhatsappBusinessAccount $account, TemplateService $templateService, FlowService $flowService)
    {
        $this->apiClient = $apiClient;
        $this->account = $account;
        $this->templateService = $templateService;
        $this->flowService = $flowService;
    }


    /**
     * Establece el nombre de la plantilla
     *
     * @param string $name
     * @return self
     * @throws InvalidArgumentException Si el nombre excede 512 caracteres
     */
    public function setName(string $name): self
    {
        if (strlen($name) > 512) {
            throw new InvalidArgumentException('El nombre de la plantilla no puede exceder los 512 caracteres.');
        }

        $this->templateData['name'] = $name;
        return $this;
    }

    /**
     * Establece el idioma de la plantilla
     *
     * @param string $language
     * @return self
     */
    public function setLanguage(string $language): self
    {
        $this->templateData['language'] = $language;
        return $this;
    }

    /**
     * Establece la categoría de la plantilla
     *
     * @param string $category
     * @return self
     * @throws InvalidArgumentException Si la categoría no es válida
     */
    public function setCategory(string $category): self
    {
        $validCategories = ['AUTHENTICATION', 'MARKETING', 'UTILITY'];
        if (!in_array($category, $validCategories)) {
            throw new InvalidArgumentException('Categoría inválida. Debe ser AUTHENTICATION, MARKETING o UTILITY.');
        }

        $this->templateData['category'] = $category;
        return $this;
    }

    /**
     * Agrega un componente HEADER a la plantilla
     *
     * @param string $format Formato del header (TEXT, IMAGE, etc)
     * @param string $content Contenido o ruta del archivo
     * @param array|null $example Ejemplos para parámetros
     * @return self
     * @throws InvalidArgumentException Para formatos o contenidos inválidos
     */
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
            // Validar parámetros en el texto del HEADER
            preg_match_all('/{{(.*?)}}/', $content, $matches);
            $placeholders = $matches[1] ?? [];
    
            if (count($placeholders) > 1) {
                throw new InvalidArgumentException('El HEADER solo puede tener un único parámetro o ninguno.');
            }
    
            // Validar el ejemplo si hay un parámetro
            if (!empty($placeholders)) {
                if ($example === null || count($example) !== 1) {
                    throw new InvalidArgumentException('El campo "example" es obligatorio y debe contener exactamente un valor para headers con un parámetro.');
                }
            }
    
            $headerComponent = [
                'type' => 'HEADER',
                'format' => $format,
                'text' => $content,
                'example' => !empty($placeholders) ? ['header_text' => $example] : null,
            ];
        } elseif (in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'])) {
            $filePath = $content;
            $fileSize = filesize($filePath);
            $mimeType = mime_content_type($filePath);
    
            // Asumiendo que createUploadSession requiere $account, $filePath, $mimeType como argumentos
            $sessionId = $this->templateService->createUploadSession($this->account, $filePath, $mimeType);
            $mediaId = $this->templateService->uploadMedia($this->account, $sessionId, $filePath, $mimeType);
    
            if (!mb_check_encoding($mediaId, 'UTF-8')) {
                Log::warning('Corrigiendo codificación de mediaId no UTF-8.', ['mediaId' => $mediaId]);
                $mediaId = mb_convert_encoding($mediaId, 'UTF-8', 'auto');
            }
    
            $headerComponent = [
                'type' => 'HEADER',
                'format' => $format,
                'example' => ['header_handle' => [$mediaId]],
            ];
        } elseif ($format === 'LOCATION') {
            if (!empty($content)) {
                throw new InvalidArgumentException('El HEADER de tipo LOCATION no debe tener contenido.');
            }
    
            $headerComponent = [
                'type' => 'HEADER',
                'format' => $format,
                'location' => true,
            ];
        }

        $this->templateData['components'][] = $headerComponent;

        return $this;
    }

    /**
     * Agrega un componente BODY a la plantilla
     *
     * @param string $text Texto principal con parámetros opcionales
     * @param array|null $example Ejemplos para parámetros dinámicos
     * @return self
     * @throws InvalidArgumentException Para textos o parámetros inválidos
     */
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
            foreach ($example as &$value) {
                if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                    Log::warning('Corrigiendo codificación de un ejemplo no UTF-8.', ['value' => $value]);
                    $value = mb_convert_encoding($value, 'UTF-8', 'auto');
                }
            }

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


    /**
     * Agrega un componente FOOTER a la plantilla
     *
     * @param string $text Texto del footer
     * @return self
     * @throws InvalidArgumentException Si excede longitud máxima o tiene parámetros
     */
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


    /**
     * Agrega un botón a la plantilla
     *
     * @param string $type Tipo de botón (PHONE_NUMBER, URL, QUICK_REPLY)
     * @param string $text Texto visible del botón
     * @param string|null $urlOrPhone URL o número de teléfono según el tipo
     * @param array|null $example Ejemplos para parámetros en URL
     * @return self
     * @throws InvalidArgumentException Para tipos inválidos o límites excedidos
     */
    public function addButton(string $type, string $text, ?string $urlOrPhone = null, ?array $example = null): self
    {
        // Validar el número máximo de botones
        if ($this->buttonCount >= 10) {
            throw new InvalidArgumentException('No se pueden agregar más de 10 botones a una plantilla.');
        }

        // Validar el texto del botón
        if (strlen($text) > 25) {
            throw new InvalidArgumentException('El texto del botón no puede exceder los 25 caracteres.');
        }

        // Crear el botón base
        $button = [
            'type' => $type,
            'text' => $text,
        ];

        // Validar y agregar propiedades específicas según el tipo de botón
        switch ($type) {
            case 'PHONE_NUMBER':
                if (!preg_match('/^\+?[1-9]\d{1,14}$/', $urlOrPhone)) {
                    throw new InvalidArgumentException('El número de teléfono no es válido. Debe estar en formato internacional, como +1234567890.');
                }
                $button['phone_number'] = $urlOrPhone;
                break;

            case 'URL':
                if (strlen($urlOrPhone) > 2000) {
                    throw new InvalidArgumentException('La URL no puede exceder los 2000 caracteres.');
                }
                $button['url'] = $urlOrPhone;

                // Validar y agregar el campo `example` si la URL contiene un parámetro
                if (strpos($urlOrPhone, '{{1}}') !== false) {
                    if (empty($example) || count($example) !== 1) {
                        throw new InvalidArgumentException('El campo "example" es obligatorio y debe contener exactamente un valor cuando la URL incluye un parámetro.');
                    }
                    $button['example'] = $example;
                }
                break;

            case 'QUICK_REPLY':
                // No se requiere validación adicional
                break;

            default:
                throw new InvalidArgumentException('Tipo de botón no válido. Debe ser uno de: PHONE_NUMBER, URL, QUICK_REPLY.');
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

    /**
     * Agrega un botón de tipo FLOW a la plantilla.
     *
     * @param string $text Texto visible del botón (CTA)
     * @param string $flowId ID del flujo en WhatsApp Manager
     * @param string|null $flowToken Token opcional para autenticación/contexto
     * @return self
     * @throws InvalidArgumentException Si se violan restricciones de la API
     */
    public function addFlowButton(string $text, string $flowId, ?string $flowToken = null): self
    {
        if ($this->buttonCount >= 10) {
            throw new InvalidArgumentException('No se pueden agregar más de 10 botones a una plantilla.');
        }

        if (strlen($text) > 25) {
            throw new InvalidArgumentException('El texto del botón no puede exceder los 25 caracteres.');
        }

        if (empty($flowId)) {
            throw new InvalidArgumentException('El ID del flujo (flow_id) es obligatorio.');
        }

        if (!$this->flowService->getFlowById($flowId)) {
            throw new InvalidArgumentException("El flujo con ID $flowId no existe.");
        }

        $button = [
            'type' => 'FLOW',
            'text' => $text,
            'flow_id' => $flowId,
            'flow_action' => 'navigate',
        ];

        if (!empty($flowToken)) {
            $button['flow_token'] = $flowToken;
        }

        // Agregar el botón al componente BUTTONS
        $existingButtons = $this->getComponentsByType('BUTTONS');

        if (empty($existingButtons)) {
            $this->templateData['components'][] = [
                'type' => 'BUTTONS',
                'buttons' => [$button],
            ];
        } else {
            $index = array_search('BUTTONS', array_column($this->templateData['components'], 'type'));
            $this->templateData['components'][$index]['buttons'][] = $button;
        }

        $this->buttonCount++;

        return $this;
    }

    /**
     * Verifica si un tipo de componente ya existe
     *
     * @param string $type Tipo de componente a verificar
     * @return bool
     */
    protected function componentExists(string $type): bool
    {
        foreach ($this->templateData['components'] as $component) {
            if ($component['type'] === $type) {
                return true;
            }
        }
        return false;
    }

    /**
     * Valida parámetros dinámicos en el texto contra ejemplos proporcionados
     *
     * @param string $text Texto con placeholders
     * @param array|null $example Valores de ejemplo
     * @param string $type Tipo de componente (HEADER, BODY, FOOTER)
     * @return void
     * @throws InvalidArgumentException Por parámetros inválidos
     */
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

    /**
     * Construye y valida la estructura final de la plantilla
     *
     * @return array
     * @throws InvalidArgumentException Si faltan datos requeridos
     */
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

    /**
     * Guarda la plantilla en la API y la base de datos
     *
     * @return Template Modelo de plantilla creado
     * @throws \Exception En caso de error durante el proceso
     */
    public function save(): Template
    {
        try {
            $this->validateTemplate();

            // Validar que todos los datos estén en UTF-8
            array_walk_recursive($this->templateData, function (&$value) {
                if (is_string($value)) {
                    if (!mb_check_encoding($value, 'UTF-8')) {
                        Log::warning('Corrigiendo codificación de un valor no UTF-8.', ['value' => $value]);
                        $value = mb_convert_encoding($value, 'UTF-8', 'auto');
                    }
                    // Eliminar caracteres invisibles o no imprimibles
                    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
                }
            });

            $endpoint = Endpoints::build(Endpoints::CREATE_TEMPLATE, [
                'waba_id' => $this->account->whatsapp_business_id,
            ]);

            $headers = [
                'Authorization' => 'Bearer ' . $this->account->api_token,
                'Content-Type' => 'application/json',
            ];

            // Registrar los datos antes de codificar en JSON
            Log::info('Datos de la plantilla antes de codificar en JSON.', [
                'template_data' => $this->templateData,
            ]);

            // Codificar los datos en JSON
            $jsonData = json_encode($this->templateData, JSON_UNESCAPED_UNICODE);
            if ($jsonData === false) {
                $error = json_last_error_msg();
                Log::error('Error al codificar JSON.', ['error' => $error, 'data' => $this->templateData]);
                throw new \Exception('Error al codificar JSON: ' . $error);
            }

            // Enviar la solicitud a la API
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

            // Crear el registro de la plantilla en la base de datos
            $template = Template::create([
                'whatsapp_business_id' => $this->account->whatsapp_business_id,
                'wa_template_id' => $response['id'] ?? null,
                'name' => $this->templateData['name'],
                'language' => $this->templateData['language'],
                'category_id' => $this->getCategoryId($this->templateData['category']),
                'status' => 'PENDING',
                'json' => json_encode($this->templateData, JSON_UNESCAPED_UNICODE),
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

    /**
     * Valida los datos requeridos para la plantilla
     *
     * @return void
     * @throws InvalidArgumentException Si faltan campos obligatorios
     */
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

    /**
     * Obtiene componentes por tipo
     *
     * @param string $type Tipo de componente a filtrar
     * @return array Componentes coincidentes
     */
    protected function getComponentsByType(string $type): array
    {
        return array_filter($this->templateData['components'], fn($component) => $component['type'] === $type);
    }

    /**
     * Obtiene o crea el ID de categoría desde la base de datos
     *
     * @param string $categoryName Nombre de la categoría
     * @return string ID de la categoría
     * @throws InvalidArgumentException Si el nombre está vacío
     */
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