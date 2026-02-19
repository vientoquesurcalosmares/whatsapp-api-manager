<?php

namespace ScriptDevelop\WhatsappManager\Services;

use InvalidArgumentException;
//use ScriptDevelop\WhatsappManager\Models\Template;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use Illuminate\Support\Facades\Log;
//use ScriptDevelop\WhatsappManager\Models\TemplateCategory;
//use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

use Illuminate\Database\Eloquent\Model;
use ScriptDevelop\WhatsappManager\Support\WhatsappModelResolver;

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

    /** @var Model Cuenta empresarial asociada */
    protected Model $account;

    /** @var FlowService Servicio auxiliar para flujos */
    protected FlowService $flowService;

    protected string $parameterFormat = 'POSITIONAL';

    /**
     * Constructor de la clase
     *
     * @param ApiClient $apiClient
     * @param Model $account
     * @param TemplateService $templateService
     * @param FlowService $flowService
     */
    public function __construct(ApiClient $apiClient, Model $account, TemplateService $templateService, FlowService $flowService)
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

    public function setParameterFormat(string $format): self
    {
        $validFormats = ['POSITIONAL', 'NAMED'];
        $format = strtoupper($format);

        if (!in_array($format, $validFormats)) {
            throw new InvalidArgumentException(
                "Formato de parámetro inválido. Use: " . implode(', ', $validFormats)
            );
        }

        $this->parameterFormat = $format;
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
        Log::channel('whatsapp')->info('Estado actual de los componentes antes de agregar HEADER.', [
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

            // VALIDACIÓN: Verificar que las variables no estén al principio ni al final
            if (!empty($placeholders)) {
                $this->validateVariablePosition($content, 'HEADER');
            }

            // Validar el ejemplo si hay un parámetro
            if (!empty($placeholders)) {
                if ($example === null) {
                    throw new InvalidArgumentException('El campo "example" es obligatorio para headers con un parámetro.');
                }
            }

            $headerComponent = [
                'type' => 'HEADER',
                'format' => $format,
                'text' => $content,
            ];

            if (!empty($placeholders)) {
                // Estructura diferente para parámetros NAMED vs POSITIONAL
                if ($this->parameterFormat === 'NAMED') {
                    $namedParams = [];
                    foreach ($placeholders as $paramName) {
                        if (!isset($example[$paramName])) {
                            throw new InvalidArgumentException("Falta valor para parámetro: $paramName");
                        }
                        $namedParams[] = [
                            'param_name' => $paramName,
                            'example' => $example[$paramName],
                        ];
                    }
                    $headerComponent['example'] = [
                        'header_text_named_params' => $namedParams
                    ];
                } else {
                    // POSITIONAL: Extraer el valor y crear array de ejemplo
                    $headerValue = $this->extractHeaderValue($example);
                    $headerComponent['example'] = [
                        'header_text' => [$headerValue]
                    ];
                }
            }
        } elseif (in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'])) {
            $filePath = $content;

            if (!file_exists($filePath)) {
                throw new InvalidArgumentException("El archivo no existe: $filePath");
            }

            $fileSize = filesize($filePath);
            $mimeType = mime_content_type($filePath);

            // Validar tipo de archivo
            $this->templateService->validateMediaFile($filePath, $mimeType);

            // Crear sesión de carga
            $sessionId = $this->templateService->createUploadSession($this->account, $filePath, $mimeType);
            $mediaId = $this->templateService->uploadMedia($this->account, $sessionId, $filePath, $mimeType);

            $mediaId = explode("\n", trim($mediaId))[0];

            if (!mb_check_encoding($mediaId, 'UTF-8')) {
                Log::channel('whatsapp')->warning('Corrigiendo codificación de mediaId no UTF-8.', ['mediaId' => $mediaId]);
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
     * Extrae el valor del header de diferentes formatos de entrada
     */
    protected function extractHeaderValue($example): string
    {
        // Si es un array asociativo, tomar el primer valor
        if (is_array($example) && !array_is_list($example)) {
            return array_values($example)[0];
        }

        // Si es un array simple, tomar el primer elemento
        if (is_array($example) && !empty($example)) {
            return $example[0];
        }

        // Si es un string, devolverlo directamente
        if (is_string($example)) {
            return $example;
        }

        throw new InvalidArgumentException('Formato de ejemplo inválido para el HEADER');
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

        // VALIDACIÓN: Verificar que las variables no estén al principio ni al final
        preg_match_all('/{{(.*?)}}/', $text, $matches);
        $placeholders = $matches[1] ?? [];
        if (!empty($placeholders)) {
            $this->validateVariablePosition($text, 'BODY');
        }

        $formattedExample = null;

        if ($example !== null) {
            preg_match_all('/{{(.*?)}}/', $text, $matches);
            $placeholders = $matches[1] ?? [];

            // Estructura diferente para parámetros NAMED vs POSITIONAL
            if ($this->parameterFormat === 'NAMED') {
                $namedParams = [];
                foreach ($placeholders as $key => $paramName) {
                    if (!isset($example[$key])) {
                        throw new InvalidArgumentException("Falta valor para parámetro: $paramName");
                    }
                    $namedParams[] = [
                        'param_name' => $paramName,
                        'example' => $example[$key],
                    ];
                }
                $formattedExample = ['body_text_named_params' => $namedParams];
            } else {
                // POSITIONAL: Procesamiento especial
                $orderedExample = [];
                preg_match_all('/{{(.*?)}}/', $text, $matches);

                foreach ($matches[1] as $paramName) {
                    //Nota Cuau: Se intentaba acceder al índice 1 y no al 0, el bloque despues de estas líneas comentadas si hace bien eso de acceder al índice 0 restando 1, revisar si es necesario!
                    /*if (isset($example[$paramName])) {
                        $orderedExample[] = $example[$paramName];
                    } else*/ {
                        // Para compatibilidad con arrays indexados
                        $index = (int)$paramName - 1;
                        if (isset($example[$index])) {
                            $orderedExample[] = $example[$index];
                        } else {
                            throw new InvalidArgumentException(
                                "Falta valor para parámetro: $paramName"
                            );
                        }
                    }
                }
                $example = $orderedExample;

                // Validar consistencia en cantidad de parámetros
                if (count($example) !== count($placeholders)) {
                    throw new InvalidArgumentException(
                        'El número de ejemplos debe coincidir con los parámetros en el texto'
                    );
                }

                // Normalizar codificación
                foreach ($example as &$value) {
                    if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                        $value = mb_convert_encoding($value, 'UTF-8', 'auto');
                    }
                }

                $formattedExample = ['body_text' => [$example]];
            }
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

                // Validar parámetros en URL
                preg_match_all('/\{\{(.*?)\}\}/', $urlOrPhone, $matches);
                $placeholders = $matches[1] ?? [];

                // WhatsApp solo permite parámetros posicionales ({{1}}) en URLs de botones
                foreach ($placeholders as $placeholder) {
                    if (!is_numeric($placeholder)) {
                        throw new InvalidArgumentException(
                            "Parámetro no válido: '$placeholder'. Los botones URL solo admiten parámetros posicionales ({{1}})."
                        );
                    }
                }

                // Validar máximo un parámetro por botón
                if (count($placeholders) > 1) {
                    throw new InvalidArgumentException('Los botones URL solo pueden tener un único parámetro.');
                }

                // Validar que el parámetro sea {{1}}
                if (!empty($placeholders) && $placeholders[0] !== '1') {
                    throw new InvalidArgumentException('El parámetro del botón URL debe ser {{1}}.');
                }

                // Validar el ejemplo si hay parámetro
                if (!empty($placeholders)) {
                    if (empty($example) || count($example) !== 1) {
                        throw new InvalidArgumentException('El campo "example" es obligatorio y debe contener exactamente un valor cuando la URL incluye un parámetro.');
                    }

                    // CORRECCIÓN CRÍTICA: Convertir a array simple siempre
                    $button['example'] = [$example];
                }
                break;

            case 'QUICK_REPLY':
                // No se requiere validación adicional
                break;

            case 'FLOW':
                // Este caso se maneja en otro método (addFlowButton)
                throw new InvalidArgumentException('Usa el método addFlowButton para botones de tipo FLOW.');

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

        $flow = $this->flowService->getFlowById($flowId);
        if (!$flow) {
            throw new InvalidArgumentException("El flujo con ID ($flowId) no existe o no ha sido aprobado por META.");
        }

        if (!in_array($flow->status, ['approved', 'published'])) {
            throw new \Exception("El flujo debe estar aprobado o publicado.");
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

        if ($type === 'HEADER' && count($placeholders) > 1) {
            throw new InvalidArgumentException('El HEADER solo puede tener un único parámetro.');
        }

        if ($type === 'FOOTER' && !empty($placeholders)) {
            throw new InvalidArgumentException('El FOOTER no puede tener parámetros.');
        }

        // Validación específica por formato
        if ($this->parameterFormat === 'POSITIONAL') {
            $this->validatePositionalParameters($placeholders, $example, $type);
        } else { // NAMED
            $this->validateNamedParameters($placeholders, $example, $type);
        }

        // VALIDACIÓN: Verificar que las variables no estén consecutivas
        if (preg_match('/\{\{\d+\}\}\s*\{\{\d+\}\}/', $text)) {
            throw new InvalidArgumentException(
                "En el $type: Las variables no pueden estar consecutivas. " .
                "Debe haber texto entre los parámetros."
            );
        }
    }

    protected function validatePositionalParameters(array $placeholders, ?array $example, string $type): void
    {
        // Convertir y validar números
        $intPlaceholders = [];
        foreach ($placeholders as $p) {
            if (!is_numeric($p)) {
                throw new InvalidArgumentException(
                    "Parámetro no numérico: '$p'. POSITIONAL requiere {{1}}, {{2}}"
                );
            }
            $intPlaceholders[] = (int)$p;
        }

        // Verificar que los números sean consecutivos sin saltos
        if (!empty($intPlaceholders)) {
            $min = min($intPlaceholders);
            $max = max($intPlaceholders);

            if ($min !== 1) {
                throw new InvalidArgumentException(
                    'El primer parámetro POSITIONAL debe ser {{1}}'
                );
            }

            $expectedRange = range(1, $max);
            $actualValues = array_unique($intPlaceholders);

            if (count($actualValues) !== count($expectedRange) ||
                array_diff($expectedRange, $actualValues))
            {
                throw new InvalidArgumentException(
                    'Secuencia POSITIONAL inválida. Debe usar números consecutivos desde {{1}} hasta {{'.$max.'}}'
                );
            }
        }

        // Validar coincidencia con ejemplos
        if ($example && count($example) !== count($intPlaceholders)) {
            throw new InvalidArgumentException(
                'Número de ejemplos no coincide con parámetros en el texto'
            );
        }
    }

    protected function validateNamedParameters(array $placeholders, ?array $example, string $type): void
    {
        foreach ($placeholders as $placeholder) {
            if (!preg_match('/^[a-z_][a-z0-9_]*$/', $placeholder)) {
                throw new InvalidArgumentException(
                    "Nombre de parámetro inválido: '$placeholder'. Solo caracteres alfanuméricos y guiones bajos"
                );
            }
        }

        if ($example) {
            $exampleKeys = array_keys($example);
            $placeholderKeys = array_keys($placeholders);
            $missingParams = array_diff($placeholderKeys, $exampleKeys);

            if (!empty($missingParams)) {
                throw new InvalidArgumentException(
                    'Faltan ejemplos para parámetros: ' . implode(', ', $missingParams)
                );
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
     * @return Model Modelo de plantilla creado
     * @throws \Exception En caso de error durante el proceso
     */
    public function save(): Model
    {
        try {
            $this->validateTemplate();

            // Validar que todos los datos estén en UTF-8
            array_walk_recursive($this->templateData, function (&$value) {
                if (is_string($value)) {
                    if (!mb_check_encoding($value, 'UTF-8')) {
                        Log::channel('whatsapp')->warning('Corrigiendo codificación de un valor no UTF-8.', ['value' => $value]);
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

            $this->templateData['parameter_format'] = $this->parameterFormat;

            // Registrar los datos antes de codificar en JSON
            Log::channel('whatsapp')->info('Datos de la plantilla antes de codificar en JSON.', [
                'template_data' => $this->templateData,
            ]);

            // Codificar los datos en JSON
            $jsonData = json_encode($this->templateData, JSON_UNESCAPED_UNICODE);
            if ($jsonData === false) {
                $error = json_last_error_msg();
                Log::channel('whatsapp')->error('Error al codificar JSON.', ['error' => $error, 'data' => $this->templateData]);
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

            Log::channel('whatsapp')->info('Respuesta recibida de la API al crear plantilla.', [
                'response' => $response,
            ]);

            // Crear el registro de la plantilla en la base de datos
            $template = WhatsappModelResolver::template()->create([
                'whatsapp_business_id' => $this->account->whatsapp_business_id,
                'wa_template_id' => $response['id'] ?? null,
                'name' => $this->templateData['name'],
                'language' => $this->templateData['language'],
                'category_id' => $this->getCategoryId($this->templateData['category']),
                'status' => 'PENDING',
                'json' => json_encode($this->templateData, JSON_UNESCAPED_UNICODE),
            ]);

            try {
                $endpoint = Endpoints::build(Endpoints::GET_TEMPLATE, [
                    'template_id' => $response['id'],
                ]);

                $headers = [
                    'Authorization' => 'Bearer ' . $this->account->api_token,
                ];

                $fullTemplateResponse = $this->apiClient->request(
                    'GET',
                    $endpoint,
                    [],
                    null,
                    [],
                    $headers
                );

                // Actualizar el registro con los datos completos que incluyen URLs
                $template->update([
                    'status' => $fullTemplateResponse['status'] ?? 'PENDING',
                    'json' => json_encode($fullTemplateResponse, JSON_UNESCAPED_UNICODE),
                ]);

                // Crear versión inicial
                $this->createInitialVersion($template, $fullTemplateResponse);

            } catch (\Exception $e) {
                Log::channel('whatsapp')->error('Error al obtener detalles completos de la plantilla', [
                    'template_id' => $response['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }


            // Reiniciar el estado del builder
            $this->templateData = ['components' => []];
            $this->buttonCount = 0;

            return $template;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Manejo específico para errores de Guzzle
            $response = $e->getResponse();
            $responseBody = json_decode($response->getBody(), true);

            $errorCode = $responseBody['error']['code'] ?? null;
            $errorSubcode = $responseBody['error']['error_subcode'] ?? null;

            $errorUserMsg = $responseBody['error']['error_user_msg'] 
                 ?? $responseBody['error']['message'] 
                 ?? 'Error desconocido de la API';

            if ($errorCode === 100 && $errorSubcode === 2388023) {
                throw new \Exception(
                    'No puedes crear una plantilla con el mismo nombre e idioma inmediatamente después de eliminar otra. ' .
                    'Espera 4 semanas o usa un nombre diferente.'
                );
            }

            Log::channel('whatsapp')->error('Error de API al guardar la plantilla.', [
                'error' => $responseBody,
                'user_message' => $errorUserMsg,
                'status' => $response->getStatusCode()
            ]);
            throw new \Exception('Error al crear plantilla en WhatsApp: ' . $errorUserMsg);
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error general al guardar la plantilla.', [
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Crea la versión inicial de una plantilla.
     */
    protected function createInitialVersion(Model $template, array $apiResponse): Model
    {
        return WhatsappModelResolver::template_version()->create([
            'template_id' => $template->template_id,
            'version_hash' => md5(json_encode($apiResponse['components'])),
            'template_structure' => $apiResponse['components'],
            'status' => $apiResponse['status'] ?? 'PENDING',
            'is_active' => ($apiResponse['status'] === 'APPROVED'),
        ]);
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

        $category = WhatsappModelResolver::template_category()->firstOrCreate(
            ['name' => $categoryName],
            ['description' => ucfirst($categoryName)]
        );

        return $category->category_id;
    }

    /**
     * NUEVO MÉTODO: Valida que las variables no estén al principio ni al final del texto
     *
     * @param string $text Texto a validar
     * @param string $componentName Nombre del componente para mensajes de error
     * @return void
     * @throws InvalidArgumentException Si las variables están al principio o al final
     */
    protected function validateVariablePosition(string $text, string $componentName): void
    {
        $trimmedText = trim($text);
        
        // Verificar si el texto comienza con una variable
        if (preg_match('/^\s*\{\{\d+\}\}/', $trimmedText)) {
            throw new InvalidArgumentException(
                "En el $componentName: Las variables no pueden estar al principio del texto. " .
                "WhatsApp Business API no permite parámetros al inicio del contenido."
            );
        }
        
        // Verificar si el texto termina con una variable
        if (preg_match('/\{\{\d+\}\}\s*$/', $trimmedText)) {
            throw new InvalidArgumentException(
                "En el $componentName: Las variables no pueden estar al final del texto. " .
                "WhatsApp Business API no permite parámetros al final del contenido."
            );
        }
        
        // Validación adicional: verificar que haya texto antes y después de cada variable
        $pattern = '/\{\{\d+\}\}/';
        $parts = preg_split($pattern, $trimmedText);
        
        foreach ($parts as $index => $part) {
            $part = trim($part);
            
            // Si es el primer segmento y está vacío, significa que la variable está al principio
            if ($index === 0 && empty($part)) {
                throw new InvalidArgumentException(
                    "En el $componentName: Las variables no pueden estar al principio del texto. " .
                    "Agrega texto antes del primer parámetro."
                );
            }
            
            // Si es el último segmento y está vacío, significa que la variable está al final
            if ($index === count($parts) - 1 && empty($part)) {
                throw new InvalidArgumentException(
                    "En el $componentName: Las variables no pueden estar al final del texto. " .
                    "Agrega texto después del último parámetro."
                );
            }
        }
    }
}