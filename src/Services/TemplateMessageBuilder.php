<?php
namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;
use InvalidArgumentException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ScriptDevelop\WhatsappManager\Exceptions\WhatsappApiException;
use ScriptDevelop\WhatsappManager\Helpers\CountryCodes;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use ScriptDevelop\WhatsappManager\Models\Template;

class TemplateMessageBuilder
{
    protected WhatsappBusinessAccount $account;
    protected string $phoneNumber;
    protected string $templateIdentifier; // Puede ser nombre o ID
    protected ?string $language = null; // Opcional
    protected array $components = [];
    protected array $templateStructure = []; // Estructura de la plantilla

    /**
     * Constructor de la clase.
     *
     * @param WhatsappBusinessAccount $account La cuenta empresarial de WhatsApp.
     */
    public function __construct(WhatsappBusinessAccount $account)
    {
        $this->account = $account;
    }

    /**
     * Establece el número de teléfono del destinatario.
     *
     * @param string $phoneNumber El número de teléfono sin el código de país.
     * @param string $countryCode El código de país (por ejemplo, "57" para Colombia).
     * @return self
     * @throws InvalidArgumentException Si el número de teléfono o el código de país no son válidos.
     */
    public function to(string $phoneNumber, string $countryCode): self
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
        $this->validateComponent('HEADER', $type);
        $this->components['HEADER'] = [
            'type' => $type,
            'parameters' => is_array($content) ? $content : [$content],
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
        $this->validateComponent('BODY');
        $this->components['BODY'] = [
            'parameters' => $parameters,
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
        $this->validateComponent('FOOTER');
        $this->components['FOOTER'] = [
            'text' => $text,
        ];
        return $this;
    }

    /**
     * Agrega un botón a la plantilla.
     *
     * @param string $type El tipo de botón (por ejemplo, "QUICK_REPLY", "URL").
     * @param string $text El texto del botón.
     * @param string|null $url La URL asociada al botón (si aplica).
     * @param array $parameters Los parámetros dinámicos para el botón (si aplica).
     * @return self
     * @throws InvalidArgumentException Si el componente no es válido.
     */
    public function addButton(string $type, string $text, string $url = null, array $parameters = []): self
    {
        $this->validateComponent('BUTTONS');
        $button = ['type' => $type, 'text' => $text];
        if ($type === 'URL') {
            $button['url'] = $url;
            $button['parameters'] = $parameters;
        }
        $this->components['BUTTONS'][] = $button;
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
        // Validar los datos
        $this->validate();

        // Consultar la estructura de la plantilla si es necesario
        $this->fetchTemplateStructure();

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
            ->where('whatsapp_business_id', $this->account->whatsapp_business_id)
            ->first();

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
        if (!isset($this->templateStructure[$componentType])) {
            throw new InvalidArgumentException("El componente '$componentType' no está definido en la plantilla '{$this->templateIdentifier}'.");
        }

        if ($subType && isset($this->templateStructure[$componentType]['type']) && !in_array($subType, $this->templateStructure[$componentType]['type'])) {
            throw new InvalidArgumentException("El tipo '$subType' no es válido para el componente '$componentType' en la plantilla '{$this->templateIdentifier}'.");
        }
    }

    /**
     * Construye el payload para enviar el mensaje.
     *
     * @return array El payload construido.
     */
    protected function buildPayload(): array
    {
        $payload = [
            'to' => $this->phoneNumber,
            'template' => [
                'name' => $this->templateIdentifier,
                'language' => ['code' => $this->language],
                'components' => array_values($this->components),
            ],
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
        $endpoint = Endpoints::build(Endpoints::SEND_MESSAGE);

        Log::info('Enviando mensaje de plantilla.', [
            'endpoint' => $endpoint,
            'payload' => $payload,
        ]);

        $response = $this->account->apiClient->request(
            'POST',
            $endpoint,
            data: $payload,
            headers: [
                'Authorization' => 'Bearer ' . $this->account->api_token,
                'Content-Type' => 'application/json',
            ]
        );

        if (!$response['success']) {
            Log::error('Error al enviar el mensaje de plantilla.', [
                'endpoint' => $endpoint,
                'payload' => $payload,
                'response' => $response,
            ]);

            throw new WhatsappApiException('Error al enviar el mensaje.', $response['error'] ?? []);
        }

        Log::info('Mensaje enviado exitosamente.', ['response' => $response]);

        return $response;
    }
}