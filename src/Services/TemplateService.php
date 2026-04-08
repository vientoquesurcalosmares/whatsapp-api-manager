<?php

namespace ScriptDevelop\WhatsappManager\Services;

use InvalidArgumentException;
//use ScriptDevelop\WhatsappManager\Models\Template;
//use ScriptDevelop\WhatsappManager\Models\TemplateComponent;
//use ScriptDevelop\WhatsappManager\Models\TemplateCategory;
//use ScriptDevelop\WhatsappManager\Models\TemplateLanguage;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
//use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;
//use ScriptDevelop\WhatsappManager\Models\WhatsappPhoneNumber;
//use ScriptDevelop\WhatsappManager\Models\WhatsappTemplateFlow;
//use ScriptDevelop\WhatsappManager\Models\WhatsappFlow;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ScriptDevelop\WhatsappManager\Jobs\CompressTemplateMediaJob;
use ScriptDevelop\WhatsappManager\Services\TemplateMediaCompressionService;

use Illuminate\Database\Eloquent\Model;
use ScriptDevelop\WhatsappManager\Support\WhatsappModelResolver;

/**
 * Servicio para gestionar las plantillas de WhatsApp.
 * Proporciona métodos para sincronizar, crear, actualizar, eliminar y enviar plantillas.
 */
class TemplateService
{
    /**
     * Cliente para realizar solicitudes a la API de WhatsApp.
     * @var ApiClient
     */
    protected ApiClient $apiClient;

    /**
     * Servicio para gestionar flujos de WhatsApp.
     * @var FlowService
     */
    protected FlowService $flowService;

    /**
     * Constructor de la clase.
     *
     * @param ApiClient $apiClient Cliente para realizar solicitudes a la API de WhatsApp.
     */
    public function __construct(ApiClient $apiClient, FlowService $flowService)
    {
        $this->apiClient = $apiClient;
        $this->flowService = $flowService;
    }

    /**
     * Sincroniza todas las plantillas desde la API de WhatsApp.
     *
     * @param Model $account La cuenta empresarial de WhatsApp.
     * @return \Illuminate\Database\Eloquent\Collection Colección de plantillas sincronizadas.
     */
    public function getTemplates(Model $account)
    {
        $this->flowService->syncFlows($account);

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
            $templates = [];

            $nextCursor = null;

            do {
                $query = [];
                if ($nextCursor) {
                    $query['after'] = $nextCursor;
                }

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

                if (isset($response['data']) && !empty($response['data'])) {
                    $templates = array_merge($templates, $response['data']);
                    $nextCursor = $response['paging']['cursors']['after'] ?? null;
                } else {
                    $nextCursor = null;
                }
            } while ($nextCursor);

            foreach ($templates as $templateData) {
                $this->validateTemplateData($templateData);
                $template = $this->storeOrUpdateTemplate($account, $templateData);

                // Crear nueva versión si la plantilla fue actualizada
                $this->createOrUpdateVersion($template, $templateData);
            }

            if( !empty($templates) ){
                $apiTemplateIds = collect($templates)->pluck('id')->toArray();
                if( !empty($apiTemplateIds) ){
                    WhatsappModelResolver::template()->where('whatsapp_business_id', $account->whatsapp_business_id)
                        ->whereNotIn('wa_template_id', $apiTemplateIds)
                        ->where('status', '<>', 'INACTIVE')
                        ->update(['status' => 'INACTIVE']);
                }
            }

            return WhatsappModelResolver::template()->where('whatsapp_business_id', $account->whatsapp_business_id)
                ->with(['category', 'components'])
                ->get();

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
     * Crea una nueva versión de plantilla.
     */
    protected function createOrUpdateVersion(Model $template, array $templateData): Model
    {
        // Generar hash único de la estructura actual
        $structureHash = md5(json_encode($templateData['components'] ?? []));

        // Buscar si ya existe esta versión
        $existingVersion = WhatsappModelResolver::template_version()
            ->where('template_id', $template->template_id)
            ->where('version_hash', $structureHash)
            ->first();

        if ($existingVersion) {
            // Actualizar estado si cambió
            if ($existingVersion->status !== $templateData['status']) {
                $existingVersion->update(['status' => $templateData['status']]);
            }

            $this->createOrUpdateDefaultTemplateVersion($templateData['status'] ?? 'PENDING', $template, $existingVersion);

            return $existingVersion;
        }

        $templateVersion = WhatsappModelResolver::template_version()->create([
            'template_id' => $template->template_id,
            'version_hash' => $structureHash,
            'template_structure' => $templateData['components'] ?? [],
            'status' => $templateData['status'],
            'is_active' => ($templateData['status'] === 'APPROVED'),
        ]);

        $this->createOrUpdateDefaultTemplateVersion($templateData['status'] ?? 'PENDING', $template, $templateVersion);

        //Guardar el archivo del header si es que tiene
        $headerFormat = null;
        $headerUrlMultimedia = null;
        foreach ($templateData['components'] ?? [] as $component) {
            if (Str::upper($component['type']) === 'HEADER') {
                $headerFormat = Str::upper($component['format']) ?? null;
                $headerUrlMultimedia = $component['example']['header_handle'][0] ?? null;
                break;
            }
        }
        if ($headerFormat && $headerUrlMultimedia && in_array($headerFormat, ['IMAGE', 'VIDEO', 'DOCUMENT'])) {
            $this->saveTemplateVersionMedia($templateVersion, $headerUrlMultimedia, $headerFormat);
        }

        Log::channel('whatsapp')->info('New template version created', [
            'template_id' => $template->template_id,
            'version_id' => $templateVersion->version_id,
        ]);

        // Crear nueva versión
        return $templateVersion;
    }

    public function saveTemplateVersionMedia(Model $version, string $mediaUrl, string $mediaType): void
    {
        $maxTemplateMediaSize = (int) config('whatsapp.media.max_file_size.video', 16 * 1024 * 1024);

        // Ejecutar inmediatamente (sin queue) para mantener el flujo actual.
        // Futuro: reemplazar por CompressTemplateMediaJob::dispatch(...)
        /*$compressionJob = new CompressTemplateMediaJob(
            $template,
            $version,
            $mediaUrl,
            $mediaType,
            $maxTemplateMediaSize,
            3
        );
        $compressionResult = $compressionJob->handle(new TemplateMediaCompressionService());*/

        if(
            config('whatsapp.using_queue_download_multimedia', false)===true and
            config('whatsapp.package_ffmpeg_installed', false) and
            config('whatsapp.package_php_gd_installed', false)
        ){
            CompressTemplateMediaJob::dispatch(
                $version,
                $mediaUrl,
                $mediaType,
                $maxTemplateMediaSize,
                3
            )
            ->onQueue(config('whatsapp.queue_multimedia_name', 'default')); // Puedes especificar la queue que desees
        }
    }

    private function getFileExtension(?string $mimeType): string
    {
        //Prevenir que el mimetype sea parecido a esto: "audio/ogg; codecs=opus", así son las notas de voz
        if ($mimeType && str_contains($mimeType, ';')) {
            $mimeType = explode(';', $mimeType)[0];
        }

        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'audio/ogg', 'audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/amr' => 'ogg',
            'video/mp4', 'video/3gpp' => 'mp4',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
            'image/webp' => 'webp',
            default => function () use ($mimeType) {
                    Log::channel('whatsapp')->warning("Extensión desconocida para MIME type: {$mimeType}");
                    return 'bin';
                },
        };
    }

    /**
     * Crea o actualiza la versión predeterminada de una plantilla Aprobada.
     *
     * @param string $status
     * @param Model $template
     * @param Model $version
     * @return void
     */
    protected function createOrUpdateDefaultTemplateVersion(string $status, Model $template, Model $version): void
    {
        if( $status === 'APPROVED' && $template && $version ){
            $templateVersionDefaultModel = config('whatsapp.models.template_version_default');
            $templateVersionDefaultModel::upsertDefault($template->template_id, $version->version_id);
        }
    }

    /**
     * Obtiene una plantilla por su ID desde la API de WhatsApp.
     *
     * @param Model $account La cuenta empresarial de WhatsApp.
     * @param string $templateId El ID de la plantilla.
     * @return Model La plantilla obtenida.
     * @throws InvalidArgumentException Si el ID de la plantilla no es válido.
     */
    public function getTemplateById(Model $account, string $templateId): Model
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

            $this->validateTemplateData($response);

            // Almacenar o actualizar la plantilla en la base de datos
            $template = $this->storeOrUpdateTemplate($account, $response);

            // Crear nueva versión si la plantilla fue actualizada
            $this->createOrUpdateVersion($template, $response);

            return $template;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error al obtener la plantilla.', [
                'error_message' => $e->getMessage(),
                'endpoint' => $endpoint,
                'template_id' => $templateId,
            ]);
            throw $e;
        }
    }

    /**
     * Obtiene solo una plantilla que coincida parcialmente por su nombre desde la API de WhatsApp.
     * Nota: Si deseas obtener una coincidencia exacta por nombre y lenguaje, se recomienda usar getTemplateByNameLanguage.
     *
     * @param Model $account La cuenta empresarial de WhatsApp.
     * @param string $templateName El nombre de la plantilla.
     * @return Model|null La plantilla obtenida o null si no existe.
     */
    public function getTemplateByName(Model $account, string $templateName): ?Model
    {
        if (!$account) {
            throw new InvalidArgumentException('La cuenta de WhatsApp es obligatoria.');
        }

        if (empty($templateName)) {
            throw new InvalidArgumentException('El nombre de la plantilla es obligatorio.');
        }

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

            $this->validateTemplateData($templateData);

            // Almacenar o actualizar la plantilla en la base de datos
            $template = $this->storeOrUpdateTemplate($account, $templateData);

            // Crear nueva versión si la plantilla fue actualizada
            $this->createOrUpdateVersion($template, $templateData);

            return $template;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error al obtener la plantilla por nombre.', [
                'error_message' => $e->getMessage(),
                'endpoint' => $endpoint,
                'template_name' => $templateName,
            ]);
            throw $e;
        }
    }

    /**
     * Obtiene TODAS las plantillas que coincidan parcialmente por el nombre desde la API de WhatsApp.
     * Nota: Si deseas obtener una coincidencia exacta por nombre y lenguaje, se recomienda usar getTemplateByNameLanguage.
     *
     * @param Model $account La cuenta empresarial de WhatsApp.
     * @param string $templateName El nombre de la plantilla.
     * @return array|null Las plantillas obtenidas o null si no existen.
     */
    public function getTemplatesByName(Model $account, string $templateName): ?array
    {
        if (!$account) {
            throw new InvalidArgumentException('La cuenta de WhatsApp es obligatoria.');
        }

        if (empty($templateName)) {
            throw new InvalidArgumentException('El nombre de la plantilla es obligatorio.');
        }

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

            $templatesData = $response['data'] ?? [];

            if (empty($templatesData)) {
                Log::channel('whatsapp')->warning('No se encontraron plantillas con el nombre especificado.', [
                    'template_name' => $templateName,
                ]);
                return null;
            }

            $templates = [];

            foreach( $templatesData as $templateData ){
                $this->validateTemplateData($templateData);

                // Almacenar o actualizar la plantilla en la base de datos
                $template = $this->storeOrUpdateTemplate($account, $templateData);

                // Crear nueva versión si la plantilla fue actualizada
                $this->createOrUpdateVersion($template, $templateData);
                $templates[] = $template;
            }

            return $templates;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error al obtener la plantilla por nombre.', [
                'error_message' => $e->getMessage(),
                'endpoint' => $endpoint,
                'template_name' => $templateName,
            ]);
            throw $e;
        }
    }

    /**
     * Obtiene una plantilla por su nombre y lenguaje exacto desde la API de WhatsApp.
     *
     * @param Model $account La cuenta empresarial de WhatsApp.
     * @param string $templateName El nombre de la plantilla.
     * @param string $language El ID del idioma de la plantilla.
     * @return Model|null La plantilla obtenida o null si no existe.
     * @throws InvalidArgumentException Si los parámetros requeridos están vacíos.
     */
    public function getTemplateByNameLanguage(Model $account, string $templateName, string $language): ?Model
    {
        if (!$account) {
            throw new InvalidArgumentException('La cuenta de WhatsApp es obligatoria.');
        }

        if (empty($templateName)) {
            throw new InvalidArgumentException('El nombre de la plantilla es obligatorio.');
        }

        if (empty($language)) {
            throw new InvalidArgumentException('El lenguaje de la plantilla es obligatorio.');
        }

        $endpoint = Endpoints::build(Endpoints::GET_TEMPLATES, [
            'waba_id' => $account->whatsapp_business_id,
        ]);

        $query = [
            'name' => $templateName,
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $account->api_token,
        ];

        Log::channel('whatsapp')->info('Obteniendo plantilla por nombre y lenguaje exacto desde la API.', [
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

            // Filtrar la respuesta para encontrar la plantilla con el nombre y lenguaje exactos
            $templateData = collect($response['data'] ?? [])->first(function ($item) use ($templateName, $language) {
                return $item['name'] === $templateName && $item['language'] === $language;
            });

            Log::channel('whatsapp')->info('Respuesta recibida de la API.', [
                'response' => $response,
                'templateData' => $templateData,
            ]);

            if (!$templateData) {
                Log::channel('whatsapp')->warning('No se encontró la plantilla con el nombre especificado.', [
                    'template_name' => $templateName,
                ]);
                return null;
            }

            $this->validateTemplateData($templateData);

            // Almacenar o actualizar la plantilla en la base de datos
            $template = $this->storeOrUpdateTemplate($account, $templateData);

            // Crear nueva versión si la plantilla fue actualizada
            $this->createOrUpdateVersion($template, $templateData);

            return $template;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error al obtener la plantilla por nombre.', [
                'error_message' => $e->getMessage(),
                'endpoint' => $endpoint,
                'template_name' => $templateName,
                'language' => $language,
            ]);
            throw $e;
        }
    }

    /**
     * Crea una plantilla de utilidad.
     *
     * @param Model $account La cuenta empresarial de WhatsApp.
     * @return TemplateBuilder El constructor de plantillas.
     */
    public function createUtilityTemplate(Model $account): TemplateBuilder
    {
        return (new TemplateBuilder($this->apiClient, $account, $this, $this->flowService))
            ->setCategory('UTILITY'); // Categoría específica para plantillas transaccionales
    }

    /**
     * Crea una plantilla de marketing.
     *
     * @param Model $account La cuenta empresarial de WhatsApp.
     * @return TemplateBuilder El constructor de plantillas.
     */
    public function createMarketingTemplate(Model $account): TemplateBuilder
    {
        return (new TemplateBuilder($this->apiClient, $account, $this, $this->flowService))
            ->setCategory('MARKETING'); // Categoría específica para plantillas de marketing
    }

    /**
     * Crea una plantilla de autenticación.
     *
     * @param Model $account La cuenta empresarial de WhatsApp.
     * @return TemplateBuilder El constructor de plantillas.
     */
    public function createAuthenticationTemplate(Model $account): TemplateBuilder
    {
        return (new TemplateBuilder($this->apiClient, $account, $this, $this->flowService))
            ->setCategory('AUTHENTICATION'); // Categoría específica para plantillas de autenticación
    }

    /**
     * Elimina una plantilla por su ID.
     *
     * @param Model $account La cuenta empresarial de WhatsApp.
     * @param string $templateId El ID de la plantilla.
     * @param bool $hardDelete Indica si se debe realizar un borrado permanente.
     * @return bool True si la plantilla fue eliminada, false en caso contrario.
     */
    public function deleteTemplateById(Model $account, string $templateId, bool $hardDelete = false): bool
    {
        $template = WhatsappModelResolver::template()->where('wa_template_id', $templateId)->first();

        if (!$template) {
            Log::channel('whatsapp')->error('La plantilla no existe!', [
                'wa_template_id' => $templateId
            ]);

            return false;
        }

        $endpoint = Endpoints::build(Endpoints::DELETE_TEMPLATE, [
            'waba_id' => $account->whatsapp_business_id,
        ]);

        $query = [
            'hsm_id' => $templateId,
            'name' => $template->name
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
                if ($template) {
                    $template->flows()->detach();
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

    /**
     * Elimina una plantilla por su nombre.
     *
     * @param Model $account La cuenta empresarial de WhatsApp.
     * @param string $templateName El nombre de la plantilla.
     * @param bool $hardDelete Indica si se debe realizar un borrado permanente.
     * @return bool True si la plantilla fue eliminada, false en caso contrario.
     */
    public function deleteTemplateByName(Model $account, string $templateName, bool $hardDelete = false): bool
    {
        $template = WhatsappModelResolver::template()->where('name', $templateName)->first();

        if (!$template) {
            Log::channel('whatsapp')->error('La plantilla no existe!', [
                'name' => $templateName
            ]);

            return false;
        }

        $endpoint = Endpoints::build(Endpoints::DELETE_TEMPLATE, [
            'waba_id' => $account->whatsapp_business_id,
        ]);

        $query = [
            'name' => $templateName
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
                if ($template) {
                    $template->flows()->detach();
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
     * Crea un mensaje de plantilla para enviar.
     *
     * @param Model $phone Numero de telefono de WhatsApp.
     * @return TemplateMessageBuilder El constructor del mensaje de plantilla.
     */
    public function sendTemplateMessage(Model $phone): TemplateMessageBuilder
    {
        return new TemplateMessageBuilder($this->apiClient, $phone, $this);
    }

    /**
     * Crea o actualiza una plantilla en la base de datos.
     *
     * @param string $businessId El ID de la cuenta empresarial.
     * @param array $templateData Los datos de la plantilla.
     * @return Model La plantilla creada o actualizada.
     */
    protected function storeOrUpdateTemplate(Model $account, array $templateData): Model
    {
        Log::channel('whatsapp')->info('Procesando plantilla.', [
            'template_id' => $templateData['id'],
            'template_name' => $templateData['name'],
        ]);

        $template = WhatsappModelResolver::template()->updateOrCreate(
            [
                'wa_template_id' => $templateData['id'],
            ],
            [
                'whatsapp_business_id' => $account->whatsapp_business_id,
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
        $this->syncTemplateComponents($account, $template, $templateData['components'] ?? []);

        return $template;
    }

    /**
     * Sincroniza la relación entre plantilla y flujo
     */
    protected function syncTemplateFlowRelation(Model $account, string $templateId, string $apiFlowId, string $buttonLabel): ?Model
    {
        try {
            // Validar que el flujo existe en la base de datos
            $flow = $this->flowService->getFlowById($apiFlowId);
            if (!$flow) {
                Log::channel('whatsapp')->error('Flujo no encontrado en la base de datos', [
                    'flow_id' => $apiFlowId,
                    'template_id' => $templateId
                ]);
                return null;
            }

            // Usar el ULID local del flujo
            WhatsappModelResolver::template_flow()->updateOrCreate(
                [
                    'template_id' => $templateId,
                    'flow_id' => $flow->flow_id, // ULID local
                ],
                [
                    'flow_button_label' => $buttonLabel,
                ]
            );

            return $flow;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error en syncTemplateFlowRelation', [
                'error' => $e->getMessage(),
                'account_id' => $account->whatsapp_business_id,
                'template_id' => $templateId,
                'flow_id' => $apiFlowId
            ]);
            return null;
        }
    }

    protected function getLanguageId(string $templateData): string
    {
        $language = WhatsappModelResolver::template_language()->find($templateData);

        return $language->category_id;
    }

    /**
     * Obtiene el ID de la categoría o la crea si no existe.
     *
     * @param string $categoryName El nombre de la categoría.
     * @return string El ID de la categoría.
     */
    protected function getCategoryId(string $categoryName): string
    {
        $category = WhatsappModelResolver::template_category()->firstOrCreate(
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
     * Sincroniza los componentes de una plantilla.
     *
     * @param Model $template La plantilla a sincronizar.
     * @param array $components Los componentes de la plantilla.
     * @return void
     */
    protected function syncTemplateComponents(Model $account, Model $template, array $components): void
    {
        Log::channel('whatsapp')->info('Sincronizando componentes de la plantilla.', [
            'template_id' => $template->template_id,
            'component_count' => count($components),
        ]);

        try {
            $currentLocalFlowIds = []; // Para relaciones actuales

            foreach ($components as $componentData) {
                $type = strtolower($componentData['type']);
                if ($type === 'buttons') {
                    $type = 'button';
                }

                // 1. Sincronizar componente principal
                $component = WhatsappModelResolver::template_component()->updateOrCreate(
                    [
                        'template_id' => $template->template_id,
                        'type' => $type,
                    ],
                    [
                        'content' => $this->getComponentContent($componentData),
                        'parameters' => $componentData['parameters'] ?? [],
                    ]
                );

                // 2. Sincronizar relaciones con flujos (si es componente de botones)
                if ($type === 'button' && isset($componentData['buttons'])) {
                    foreach ($componentData['buttons'] as $button) {
                        if ($button['type'] === 'FLOW' && isset($button['flow_id'])) {
                            $flow = $this->syncTemplateFlowRelation(
                                $account,
                                $template->template_id,
                                $button['flow_id'],
                                $button['text'] ?? 'Iniciar flujo'
                            );

                            // Almacenar ULID local en lugar de ID de API
                            if ($flow) {
                                $currentLocalFlowIds[] = $flow->flow_id;
                            }
                        }
                    }
                }
            }

            // 3. Eliminar relaciones obsoletas
            WhatsappModelResolver::template_flow()->where('template_id', $template->template_id)
                ->whereNotIn('flow_id', $currentLocalFlowIds)
                ->delete();

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error al sincronizar componentes.', [
                'template_id' => $template->template_id,
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Obtiene el contenido de un componente según su tipo.
     *
     * @param array $componentData Los datos del componente.
     * @return array|null El contenido del componente o null si no aplica.
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

    /**
     * Valida los datos de una plantilla.
     *
     * @param array $templateData Los datos de la plantilla.
     * @return void
     * @throws InvalidArgumentException Si los datos no son válidos.
     */
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

        $validCategories = ['AUTHENTICATION', 'MARKETING', 'UTILITY'];

        if (!in_array($templateData['category'], $validCategories)) {
            throw new InvalidArgumentException('Categoría inválida');
        }

        if (isset($templateData['parameter_format'])) {
            $validFormats = ['POSITIONAL', 'NAMED'];
            if (!in_array($templateData['parameter_format'], $validFormats)) {
                throw new InvalidArgumentException('Formato de parámetro inválido');
            }
        }
    }

    /**
     * Crea una sesión de carga para un archivo.
     *
     * @param Model $account La cuenta empresarial de WhatsApp.
     * @param string $filePath La ruta del archivo.
     * @param string $mimeType El tipo MIME del archivo.
     * @return string El ID de la sesión de carga.
     * @throws \RuntimeException Si ocurre un error durante la creación de la sesión.
     */
    public function createUploadSession(Model $account, string $filePath, string $mimeType): string
    {
        // Extraer solo el nombre del archivo
        $fileName = basename($filePath);

        // Obtener el App ID (Usar el de la cuenta, o el global si es nulo por registro embebido)
        $appId = !empty($account->app_id) ? $account->app_id : config('whatsapp.meta_auth.client_id');

        if (empty($appId)) {
            throw new \RuntimeException("No se encontró un App ID válido para crear la sesión de carga. Verifica la configuración 'whatsapp.meta_auth.client_id'.");
        }

        // Construir la URL completa
        $baseUrl = config('whatsapp.api.base_url', 'https://graph.facebook.com');
        $version = config('whatsapp.api.version', 'v22.0');
        $url = rtrim($baseUrl, '/') . '/' . ltrim($version, '/') . '/' . $appId . '/uploads';

        $headers = [
            'Authorization: Bearer ' . $account->api_token,
            'Content-Type: application/json',
        ];

        $body = [
            'file_name'   => $fileName,
            'file_type'   => $mimeType,
            'file_length' => filesize($filePath),
        ];

        Log::channel('whatsapp')->info('Creando sesión de carga.', [
            'url' => $url,
            'body' => $body,
        ]);

        // Configurar cURL
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => $headers,
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
        Log::channel('whatsapp')->info('Respuesta de la API al crear la sesión de carga.', [
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

        Log::channel('whatsapp')->info('Sesión de carga creada exitosamente.', ['uploadSessionId' => $uploadSessionId]);

        return $uploadSessionId;
    }

    /**
     * Sube un archivo a la sesión de carga.
     *
     * @param Model $account La cuenta empresarial de WhatsApp.
     * @param string $sessionId El ID de la sesión de carga.
     * @param string $filePath La ruta del archivo.
     * @param string $mimeType El tipo MIME del archivo.
     * @return string El identificador del archivo subido.
     * @throws InvalidArgumentException Si el archivo no existe.
     * @throws \RuntimeException Si ocurre un error durante la carga.
     */
    public function uploadMedia(Model $account, string $sessionId, string $filePath, string $mimeType, int $maxRetries = 3): string
    {
        // Validar que el archivo exista
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("El archivo no existe: $filePath");
        }

        $fileSize = filesize($filePath);

        // Limpiar sessionId para remover prefijo duplicado 'upload:'
        $cleanSessionId = str_starts_with($sessionId, 'upload:') ? substr($sessionId, 7) : $sessionId;
        $baseUrl = config('whatsapp.api.base_url', 'https://graph.facebook.com');
        $version = config('whatsapp.api.version', 'v22.0');
        $url = rtrim($baseUrl, '/') . '/' . ltrim($version, '/') . "/upload:$cleanSessionId";

        Log::channel('whatsapp')->info('Iniciando carga de archivo.', [
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'url' => $url,
        ]);

        // La API de Meta no soporta chunks independientes: cada petición debe enviar
        // todos los bytes restantes desde file_offset. El 'resumable' solo aplica
        // para retomar tras un fallo de red, consultando el offset alcanzado.
        $fileOffset = 0;
        $retryCount = 0;

        while ($retryCount < $maxRetries) {
            $fileHandle = fopen($filePath, 'rb');

            if ($fileHandle === false) {
                throw new \RuntimeException("No se pudo abrir el archivo para lectura: $filePath");
            }

            try {
                fseek($fileHandle, $fileOffset);
                $bytesRemaining = $fileSize - $fileOffset;

                $curl = curl_init();

                // CURLOPT_PUT habilita el streaming via CURLOPT_INFILE.
                // CURLOPT_CUSTOMREQUEST sobreescribe el método a POST conservando el streaming.
                curl_setopt_array($curl, [
                    CURLOPT_URL            => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                    CURLOPT_PUT            => true,
                    CURLOPT_CUSTOMREQUEST  => 'POST',
                    CURLOPT_INFILE         => $fileHandle,
                    CURLOPT_INFILESIZE     => $bytesRemaining,
                    CURLOPT_TIMEOUT        => 60,
                    CURLOPT_HTTPHEADER     => [
                        'Authorization: OAuth ' . $account->api_token,
                        'file_offset: ' . $fileOffset,
                        'Content-Type: ' . $mimeType,
                        'Content-Length: ' . $bytesRemaining,
                        'Expect:',
                    ],
                ]);

                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $curlError = curl_error($curl);
                $curlErrno = curl_errno($curl);

                curl_close($curl);
                fclose($fileHandle);

                if ($curlErrno) {
                    throw new \RuntimeException("Error en cURL: $curlError");
                }

                if ($httpCode === 200 || $httpCode === 201) {
                    $responseData = json_decode($response, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \RuntimeException('JSON inválido: ' . json_last_error_msg());
                    }

                    $handle = $responseData['h'] ?? $this->getUploadedFileHandle($account, $sessionId);

                    if (!$handle) {
                        throw new \RuntimeException('No se pudo obtener el identificador del archivo.');
                    }

                    Log::channel('whatsapp')->info('Archivo subido exitosamente.', [
                        'handle' => $handle,
                        'file_size' => $fileSize,
                        'mime_type' => $mimeType,
                        'session_id' => $sessionId,
                    ]);

                    return $handle;
                }

                // HTTP 206: carga parcial — verificar offset alcanzado y retomar desde ahí
                if ($httpCode === 206) {
                    $partialOffset = $this->getUploadOffset($account, $sessionId);

                    if ($partialOffset !== null && $partialOffset > $fileOffset) {
                        Log::channel('whatsapp')->info('Carga parcial detectada, retomando desde offset.', [
                            'previous_offset' => $fileOffset,
                            'new_offset'      => $partialOffset,
                        ]);

                        $fileOffset = $partialOffset;
                        $retryCount = 0;
                        continue;
                    }
                }

                throw new \RuntimeException("HTTP Error $httpCode. Respuesta: $response");

            } catch (\Exception $e) {
                if (is_resource($fileHandle)) {
                    fclose($fileHandle);
                }

                $retryCount++;

                Log::channel('whatsapp')->warning("Error en intento $retryCount/$maxRetries.", [
                    'error'       => $e->getMessage(),
                    'file_offset' => $fileOffset,
                ]);

                if ($retryCount >= $maxRetries) {
                    Log::channel('whatsapp')->error('Error crítico en uploadMedia.', [
                        'error_message' => $e->getMessage(),
                        'file_path'     => $filePath,
                        'session_id'    => $sessionId,
                    ]);

                    throw new \RuntimeException("Falló la carga después de $maxRetries intentos. " . $e->getMessage());
                }

                usleep(1000000 * (2 ** ($retryCount - 1)));
            }
        }

        throw new \RuntimeException("Falló la carga después de $maxRetries intentos.");
    }


    // public function uploadMedia(Model $account, string $sessionId, string $filePath, string $mimeType): string
    // {
    //     // Validar que el archivo exista
    //     if (!file_exists($filePath)) {
    //         throw new InvalidArgumentException("El archivo no existe: $filePath");
    //     }

    //     // Construir la URL completa
    //     $baseUrl = config('whatsapp.api.base_url', 'https://graph.facebook.com');
    //     $version = config('whatsapp.api.version', 'v22.0');
    //     $url = rtrim($baseUrl, '/') . '/' . ltrim($version, '/') . "/$sessionId";

    //     Log::channel('whatsapp')->info('=== INICIO UPLOAD MEDIA ===', [
    //         'url' => $url,
    //         'session_id' => $sessionId,
    //         'file_path' => $filePath,
    //         'file_size' => filesize($filePath),
    //         'mime_type' => $mimeType
    //     ]);

    //     // Leer el contenido del archivo
    //     $fileContents = file_get_contents($filePath);
    //     $fileSize = filesize($filePath);

    //     // Configurar cURL
    //     $curl = curl_init();

    //     $headers = [
    //         'Authorization: Bearer ' . $account->api_token,
    //         'file_offset: 0',
    //         'Content-Type: ' . $mimeType,
    //         'Content-Length: ' . $fileSize,
    //     ];

    //     curl_setopt_array($curl, [
    //         CURLOPT_URL => $url,
    //         CURLOPT_RETURNTRANSFER => true,
    //         CURLOPT_ENCODING => '',
    //         CURLOPT_MAXREDIRS => 1,
    //         CURLOPT_TIMEOUT => 60,
    //         CURLOPT_FOLLOWLOCATION => false,
    //         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //         CURLOPT_CUSTOMREQUEST => 'POST',
    //         CURLOPT_POSTFIELDS => $fileContents,
    //         CURLOPT_HTTPHEADER => $headers,
    //         CURLOPT_SSL_VERIFYPEER => true,
    //         CURLOPT_SSL_VERIFYHOST => 2,
    //     ]);

    //     // Ejecutar la solicitud
    //     $response = curl_exec($curl);
    //     $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    //     $curlError = curl_error($curl);
    //     $curlErrno = curl_errno($curl);

    //     curl_close($curl);

    //     // LOG DETALLADO
    //     Log::channel('whatsapp')->info('=== RESPONSE DEBUG ===', [
    //         'http_code' => $httpCode,
    //         'curl_error' => $curlError,
    //         'curl_errno' => $curlErrno,
    //         'response_size' => strlen($response),
    //     ]);

    //     // Validar respuesta
    //     if ($httpCode !== 200) {
    //         throw new \RuntimeException("HTTP Error: $httpCode. Curl: $curlError. Response: $response");
    //     }

    //     if ($curlErrno) {
    //         throw new \RuntimeException("cURL Error ($curlErrno): $curlError");
    //     }

    //     // ANALIZAR LA RESPUESTA
    //     $this->analyzeUploadResponse($response, $sessionId, $filePath);

    //     // Decodificar JSON
    //     $responseData = json_decode($response, true);

    //     if (json_last_error() !== JSON_ERROR_NONE) {
    //         Log::channel('whatsapp')->error('JSON decode error:', [
    //             'error' => json_last_error_msg(),
    //             'raw_body' => $response
    //         ]);
    //         throw new \RuntimeException('Invalid JSON response: ' . json_last_error_msg());
    //     }

    //     $handle = $responseData['h'] ?? null;

    //     if (!$handle) {
    //         throw new \Exception('No handle in response. Response: ' . $response);
    //     }

    //     // Validar que sea un único handle
    //     if (is_string($handle) && substr_count($handle, "\n") > 0) {
    //         $handle = $this->handleMultipleHandles($handle, $sessionId, $filePath);
    //     }

    //     Log::channel('whatsapp')->info('=== UPLOAD COMPLETED ===', [
    //         'handle' => $handle,
    //         'handle_length' => strlen($handle)
    //     ]);

    //     return $handle;
    // }

    // /**
    //  * Analiza la respuesta del upload en detalle
    //  */
    // protected function analyzeUploadResponse(string $body, string $sessionId, string $filePath): void
    // {
    //     $lineCount = substr_count($body, "\n") + 1;
    //     $isJson = json_decode($body, true) !== null;

    //     Log::channel('whatsapp')->info('=== RESPONSE ANALYSIS ===', [
    //         'line_count' => $lineCount,
    //         'is_valid_json' => $isJson,
    //         'first_200_chars' => substr($body, 0, 200),
    //     ]);

    //     if ($lineCount > 1) {
    //         Log::channel('whatsapp')->warning('Multiple lines detected in response', [
    //             'lines' => explode("\n", $body),
    //         ]);
    //     }
    // }

    // /**
    //  * Maneja el caso de múltiples handles
    //  */
    // protected function handleMultipleHandles(string $handle, string $sessionId, string $filePath): string
    // {
    //     $handles = explode("\n", $handle);
    //     $validHandles = array_filter(array_map('trim', $handles), function($h) {
    //         return !empty($h) && preg_match('/^\d+:/', $h);
    //     });

    //     Log::channel('whatsapp')->error('MULTIPLE HANDLES DETECTED - USING FIRST AS WORKAROUND', [
    //         'total_handles' => count($handles),
    //         'valid_handles' => count($validHandles),
    //         'all_handles' => $handles
    //     ]);

    //     if (!empty($validHandles)) {
    //         $firstHandle = $validHandles[0];
    //         Log::channel('whatsapp')->warning('WORKAROUND: Using first valid handle', [
    //             'selected_handle' => $firstHandle
    //         ]);
    //         return $firstHandle;
    //     }

    //     throw new \RuntimeException("No valid handles found in multiple handles response");
    // }

    /**
     * Valida un archivo multimedia antes de subirlo.
     *
     * @param string $filePath La ruta del archivo.
     * @param string $mimeType El tipo MIME del archivo.
     * @return void
     * @throws InvalidArgumentException Si el archivo no es válido.
     */
    public function validateMediaFile(string $filePath, string $mimeType): void
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

        Log::channel('whatsapp')->info('Archivo validado correctamente.', [
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
        ]);
    }

    /**
     * Obtiene el tipo de medio a partir del tipo MIME.
     *
     * @param string $mimeType El tipo MIME del archivo.
     * @return string El tipo de medio (por ejemplo, "image", "audio").
     * @throws InvalidArgumentException Si no se puede determinar el tipo de medio.
     */
    protected function getMediaTypeFromMimeType(string $mimeType): string
    {
        // Mapear tipos MIME a categorías de medios
        $mediaTypes = config('whatsapp.media.allowed_types', []);

        foreach ($mediaTypes as $type => $mimeTypes) {
            if (in_array($mimeType, $mimeTypes)) {
                return $type;
            }
        }

        throw new InvalidArgumentException("No se pudo determinar el tipo de media para el MIME: $mimeType.");
    }

    /**
     * Consulta el offset alcanzado en una sesión de carga reanudable.
     *
     * @param Model $account La cuenta empresarial de WhatsApp.
     * @param string $sessionId El ID de la sesión de carga.
     * @return int|null El offset en bytes o null si no se pudo obtener.
     */
    protected function getUploadOffset(Model $account, string $sessionId): ?int
    {
        $cleanSessionId = str_starts_with($sessionId, 'upload:') ? substr($sessionId, 7) : $sessionId;
        $baseUrl = config('whatsapp.api.base_url', 'https://graph.facebook.com');
        $version = config('whatsapp.api.version', 'v22.0');
        $url = rtrim($baseUrl, '/') . '/' . ltrim($version, '/') . "/upload:$cleanSessionId";

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPGET        => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: OAuth ' . $account->api_token,
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($curl);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlErrno) {
            Log::channel('whatsapp')->warning('getUploadOffset: error cURL.', [
                'error'      => $curlError,
                'session_id' => $sessionId,
            ]);
            return null;
        }

        if ($httpCode !== 200) {
            Log::channel('whatsapp')->warning('getUploadOffset: respuesta inesperada.', [
                'http_code'  => $httpCode,
                'response'   => $response,
                'session_id' => $sessionId,
            ]);
            return null;
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['file_offset'])) {
            Log::channel('whatsapp')->warning('getUploadOffset: no se encontró file_offset en la respuesta.', [
                'response'   => $response,
                'session_id' => $sessionId,
            ]);
            return null;
        }

        return (int) $data['file_offset'];
    }

    /**
     * Obtiene el handle del archivo tras una carga completada.
     *
     * @param Model $account La cuenta empresarial de WhatsApp.
     * @param string $sessionId El ID de la sesión de carga.
     * @return string|null El handle del archivo o null si no se pudo obtener.
     */
    protected function getUploadedFileHandle(Model $account, string $sessionId): ?string
    {
        $cleanSessionId = str_starts_with($sessionId, 'upload:') ? substr($sessionId, 7) : $sessionId;
        $baseUrl = config('whatsapp.api.base_url', 'https://graph.facebook.com');
        $version = config('whatsapp.api.version', 'v22.0');
        $url = rtrim($baseUrl, '/') . '/' . ltrim($version, '/') . "/upload:$cleanSessionId";

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPGET        => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: OAuth ' . $account->api_token,
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($curl);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlErrno) {
            Log::channel('whatsapp')->warning('getUploadedFileHandle: error cURL.', [
                'error'      => $curlError,
                'session_id' => $sessionId,
            ]);
            return null;
        }

        if ($httpCode !== 200) {
            Log::channel('whatsapp')->warning('getUploadedFileHandle: respuesta inesperada.', [
                'http_code'  => $httpCode,
                'response'   => $response,
                'session_id' => $sessionId,
            ]);
            return null;
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($data['h'])) {
            Log::channel('whatsapp')->warning('getUploadedFileHandle: no se encontró handle en la respuesta.', [
                'response'   => $response,
                'session_id' => $sessionId,
            ]);
            return null;
        }

        return (string) $data['h'];
    }
}
