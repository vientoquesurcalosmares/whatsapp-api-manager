<?php
namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Models\WhatsappPhoneNumber;
use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;
use InvalidArgumentException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\Exceptions\WhatsappApiException;
use ScriptDevelop\WhatsappManager\Helpers\CountryCodes;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use ScriptDevelop\WhatsappManager\Models\Contact;
use ScriptDevelop\WhatsappManager\Models\Message;
use ScriptDevelop\WhatsappManager\Models\Template;
use ScriptDevelop\WhatsappManager\Enums\MessageStatus;

class TemplateMessageBuilder
{
    protected WhatsappBusinessAccount $account;
    protected WhatsappPhoneNumber $phone;
    protected ApiClient $apiClient;
    protected TemplateService $templateService;
    protected string $phoneNumber;
    protected string $templateIdentifier; // Puede ser nombre o ID
    protected ?string $language = null; // Opcional
    protected array $components = [];
    protected array $templateStructure = []; // Estructura de la plantilla

    /**
     * Constructor de la clase TemplateMessageBuilder.
     *
     * @param ApiClient $apiClient Cliente API para realizar solicitudes.
     * @param WhatsappPhoneNumber $phone Número de teléfono de WhatsApp.
     * @param TemplateService $templateService Servicio para gestionar plantillas.
     */
    public function __construct(ApiClient $apiClient, WhatsappPhoneNumber $phone, TemplateService $templateService)
    {
        $this->phone = $phone;
        $this->apiClient = $apiClient;
        $this->templateService = $templateService;
    }

    /**
     * Establece el número de teléfono del destinatario.
     *
     * @param string $phoneNumber El número de teléfono sin el código de país.
     * @param string $countryCode El código de país (por ejemplo, "57" para Colombia).
     * @return self
     * @throws InvalidArgumentException Si el número de teléfono o el código de país no son válidos.
     */
    public function to(string $countryCode, string $phoneNumber,): self
    {
        $codes = CountryCodes::list();

        if (!in_array($countryCode, $codes)) {
            throw new InvalidArgumentException("El código de país '$countryCode' no es válido.");
        }

        $cleanedPhoneNumber = preg_replace('/\D/', '', $phoneNumber);

        if (strlen($cleanedPhoneNumber) < 10) {
            throw new InvalidArgumentException("El número de teléfono '$phoneNumber' no parece ser válido.");
        }

        $this->phoneNumber = $countryCode . $cleanedPhoneNumber;

        $contact = Contact::firstOrCreate(
            [
            'phone_number' => $cleanedPhoneNumber,
            'country_code' => $countryCode
            ]
        );

        return $this;
    }

    /**
     * Establece el identificador de la plantilla (nombre o ID).
     *
     * @param string $templateIdentifier El identificador de la plantilla.
     * @return self
     */
    public function usingTemplate(string $templateIdentifier): self
    {
        $this->templateIdentifier = $templateIdentifier;
        $this->fetchTemplateStructure();
        return $this;
    }

    /**
     * Agrega un componente HEADER a la plantilla.
     *
     * @param string $type El tipo de HEADER (por ejemplo, "TEXT", "IMAGE").
     * @param mixed $content El contenido del HEADER.
     * @return self
     * @throws InvalidArgumentException Si el componente no es válido.
     */
    public function addHeader(string $type, $content): self
    {
        $this->ensureTemplateStructureLoaded();
        $this->validateComponent('HEADER', $type);
        
        $formattedParams = [];
        if (!is_array($content)) {
            $content = [$content];
        }
        
        foreach ($content as $item) {
            $formattedParams[] = [
                'type' => 'text',
                'text' => $item
            ];
        }
        
        $this->components['HEADER'] = [
            'type' => $type,
            'parameters' => $formattedParams
        ];
        
        return $this;
    }

    /**
     * Agrega un componente BODY a la plantilla.
     *
     * @param array $parameters Los parámetros dinámicos para el BODY.
     * @return self
     * @throws InvalidArgumentException Si el componente no es válido.
     */
    public function addBody(array $parameters): self
    {
        $this->ensureTemplateStructureLoaded();
        $this->validateComponent('BODY');
        
        // Formatear cada parámetro como objeto
        $formattedParams = [];
        foreach ($parameters as $param) {
            $formattedParams[] = [
                'type' => 'text', // Asumiendo que todos son texto
                'text' => $param
            ];
        }
        
        $this->components['BODY'] = [
            'type' => 'BODY',
            'parameters' => $formattedParams
        ];
        
        return $this;
    }

    /**
     * Agrega un componente FOOTER a la plantilla.
     *
     * @param string $text El texto del FOOTER.
     * @return self
     * @throws InvalidArgumentException Si el componente no es válido.
     */
    public function addFooter(string $text): self
    {
        $this->ensureTemplateStructureLoaded();
        $this->validateComponent('FOOTER');
        $this->components['FOOTER'] = [
            'text' => $text,
        ];
        return $this;
    }

    /**
     * Agrega un botón a la plantilla.
     *
     * @param string $type El tipo de botón ("QUICK_REPLY" o "URL")
     * @param string $text El texto del botón
     * @param string|null $url URL (solo para tipo URL)
     * @param array $parameters Parámetros dinámicos (formato correcto para la API)
     * @return self
     * @throws InvalidArgumentException
     */
    public function addButton(
        string $type, 
        string $text, 
        ?string $url = null, 
        array $parameters = []
    ): self {
        $this->ensureTemplateStructureLoaded();
        $this->validateComponent('BUTTONS');
    
        $button = [
            'type' => strtoupper($type),
            'text' => $text,
            'url' => $url
        ];
    
        if (!isset($this->components['BUTTONS'])) {
            $this->components['BUTTONS'] = ['buttons' => []];
        }
    
        $this->components['BUTTONS']['buttons'][] = $button;
        
        return $this;
    }

    /**
     * Envía el mensaje de plantilla.
     *
     * @return array La respuesta de la API de WhatsApp.
     * @throws InvalidArgumentException Si los datos no son válidos.
     * @throws WhatsappApiException Si ocurre un error al enviar el mensaje.
     */
    public function send(): array
    {
        // Consultar la estructura de la plantilla si es necesario
        $this->fetchTemplateStructure();

        // Validar los datos
        $this->validate();

        // Establecer el idioma desde la estructura de la plantilla
        $this->language = $this->templateStructure['language'] ?? throw new InvalidArgumentException('El idioma no está definido en la estructura de la plantilla.');

        // Construir el payload
        $payload = $this->buildPayload();

        // Enviar el mensaje
        return $this->sendMessage($payload);
    }

    /**
     * Valida los datos necesarios antes de enviar el mensaje.
     *
     * @return void
     * @throws InvalidArgumentException Si falta algún dato obligatorio.
     */
    protected function validate(): void
    {
        if (empty($this->phoneNumber)) {
            throw new InvalidArgumentException('El número de teléfono es obligatorio.');
        }

        if (empty($this->templateIdentifier)) {
            throw new InvalidArgumentException('El identificador de la plantilla es obligatorio.');
        }

        if (empty($this->components)) {
            throw new InvalidArgumentException('Debe incluir al menos un componente en el mensaje.');
        }
    }

    /**
     * Obtiene la estructura de la plantilla desde la base de datos.
     *
     * @return void
     * @throws InvalidArgumentException Si la plantilla no existe en la base de datos.
     */
    // protected function fetchTemplateStructure(): void
    // {
    //     // Buscar la plantilla en la base de datos
    //     $template = Template::with('components')
    //         ->where('name', $this->templateIdentifier)
    //         ->where('whatsapp_business_id', $this->account->whatsapp_business_id)
    //         ->first();

    //     if (!$template) {
    //         throw new InvalidArgumentException("La plantilla '{$this->templateIdentifier}' no existe en la base de datos.");
    //     }

    //     // Construir la estructura de la plantilla a partir de los componentes
    //     $this->templateStructure = [
    //         'language' => $template->language,
    //         'HEADER' => $template->components->where('type', 'header')->first(),
    //         'BODY' => $template->components->where('type', 'body')->first(),
    //         'FOOTER' => $template->components->where('type', 'footer')->first(),
    //         'BUTTONS' => $template->components->where('type', 'button')->all(),
    //     ];

    //     Log::info('Estructura de la plantilla obtenida.', ['templateStructure' => $this->templateStructure]);
    // }

    protected function fetchTemplateStructure(): void
    {
        $template = Template::with('components')
            ->where('name', $this->templateIdentifier)
            ->where('whatsapp_business_id', $this->phone->businessAccount->whatsapp_business_id)
            ->first();

        Log::info('Plantilla obtenida de la base de datos.', ['template' => $template]);

        if (!$template) {
            throw new InvalidArgumentException("La plantilla '{$this->templateIdentifier}' no existe en la base de datos.");
        }

        // Normalizar tipos a mayúsculas
        $structure = [
            'language' => $template->language,
            'HEADER' => null,
            'BODY' => null,
            'FOOTER' => null,
            'BUTTONS' => [],
        ];

        foreach ($template->components as $component) {
            $type = strtoupper($component->type);
            switch ($type) {
                case 'HEADER':
                    $structure['HEADER'] = [
                        'type' => 'HEADER',
                        'formats' => isset($component->content['format']) 
                            ? (array)$component->content['format'] 
                            : ['TEXT'] // Valor por defecto si no hay formato
                    ];
                    break;
                case 'BODY':
                case 'FOOTER':
                    $structure[$type] = $component;
                    break;
                case 'BUTTON':
                case 'BUTTONS':
                    $structure['BUTTONS'][] = $component;
                    break;
            }
        }

        $this->templateStructure = $structure;

        Log::info('Estructura de la plantilla obtenida.', ['templateStructure' => $this->templateStructure]);
    }

    /**
     * Valida un componente de la plantilla.
     *
     * @param string $componentType El tipo de componente (por ejemplo, "HEADER", "BODY").
     * @param string|null $subType El subtipo del componente (si aplica).
     * @return void
     * @throws InvalidArgumentException Si el componente no es válido.
     */
    protected function validateComponent(string $componentType, ?string $subType = null): void
    {
        $componentType = strtoupper($componentType);
        
        if (!isset($this->templateStructure[$componentType])) {
            throw new InvalidArgumentException("Componente '$componentType' no definido en la plantilla.");
        }

        // Validación especial para headers
        if ($componentType === 'HEADER' && $subType) {
            $allowedFormats = $this->templateStructure['HEADER']['formats'] ?? [];
            $allowedFormats = array_map('strtoupper', (array)$allowedFormats);
            
            if (!in_array(strtoupper($subType), $allowedFormats)) {
                throw new InvalidArgumentException("Formato '$subType' no permitido para el header. Formatos válidos: " . implode(', ', $allowedFormats));
            }
        }
    }

    /**
     * Construye el payload para enviar el mensaje.
     *
     * @return array El payload construido.
     */
    protected function buildPayload(): array
    {
        $components = [];
        
        foreach ($this->components as $componentType => $component) {
            if ($componentType === 'BUTTONS') {
                // Procesar botones como componentes individuales
                foreach ($component['buttons'] as $index => $button) {
                    $buttonComponent = [
                        'type' => 'button',
                        'sub_type' => strtolower($button['type']),
                        'index' => $index,
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $button['text']
                            ]
                        ]
                    ];
                    
                    if ($button['type'] === 'URL') {
                        $buttonComponent['parameters'][0]['type'] = 'payload';
                        $buttonComponent['parameters'][0]['payload'] = $button['url'];
                    }
                    
                    $components[] = $buttonComponent;
                }
            } else {
                // Otros componentes
                $components[] = [
                    'type' => strtolower($componentType),
                    'parameters' => $component['parameters'] ?? []
                ];
            }
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->phoneNumber,
            'type' => 'template',
            'template' => [
                'name' => $this->templateIdentifier,
                'language' => ['code' => $this->language],
                'components' => $components
            ]
        ];

        Log::info('Payload construido para el mensaje de plantilla.', ['payload' => $payload]);

        return $payload;
    }

    /**
     * Envía el mensaje a través de la API de WhatsApp.
     *
     * @param array $payload El payload del mensaje.
     * @return array La respuesta de la API de WhatsApp.
     * @throws WhatsappApiException Si ocurre un error al enviar el mensaje.
     */
    protected function sendMessage(array $payload): array
    {
        // $endpoint = Endpoints::build(Endpoints::SEND_MESSAGE);

        $endpoint = Endpoints::build(Endpoints::SEND_MESSAGE, [
            'phone_number_id' => $this->phone->api_phone_number_id,
        ]);

        Log::info('Enviando mensaje de plantilla.', [
            'endpoint' => $endpoint,
            'payload' => $payload,
            'phone_number' => $this->phoneNumber,
        ]);

        $contact = Contact::where('wa_id', $this->phoneNumber)->first();

        $message = Message::create([
            'whatsapp_phone_id' => $this->phone->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => $this->phone->display_phone_number,
            'message_to' => $contact->wa_id,
            'message_type' => 'template',
            'message_content' => NULL,
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING
        ]);


        $response = $this->apiClient->request(
            'POST',
            $endpoint,
            data: $payload,
            headers: [
                'Authorization' => 'Bearer ' . $this->phone->businessAccount->api_token,
                'Content-Type' => 'application/json',
            ]
        );

        if (!isset($response['messages'][0]['message_status']) || $response['messages'][0]['message_status'] !== 'accepted') {
            Log::error('Error al enviar el mensaje de plantilla.', [
                'endpoint' => $endpoint,
                'payload' => $payload,
                'response' => $response,
            ]);

            $errorData = $response['error'] ?? ['message' => 'Estado desconocido o mensaje no creado'];
            throw new WhatsappApiException('Error al enviar el mensaje.', $errorData);

            // throw new WhatsappApiException('Error al enviar el mensaje.', $response['error'] ?? []);
        }

        Log::info('Mensaje enviado exitosamente.', ['response' => $response]);

        return $response;
    }

    protected function ensureTemplateStructureLoaded(): void
    {
        if (empty($this->templateStructure)) {
            throw new InvalidArgumentException("Debes establecer la plantilla usando ->usingTemplate(...) antes de agregar componentes.");
        }
    }
}