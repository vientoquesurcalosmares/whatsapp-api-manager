<?php

namespace ScriptDevelop\WhatsappManager\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use ScriptDevelop\WhatsappManager\Support\WhatsappModelResolver;

class WhatsappBusinessGetGeneralTemplateAnalyticsCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:get-general-template-analytics
                            {--force : Forzar obtención de 90 días incluso si hay datos}
                            {--template=* : Obtener analytics para templates específicos (puede usarse múltiples veces)}
                            {--days= : Número específico de días a obtener (máximo 90)}
                            {--account=* : Procesar cuentas específicas (puede usarse múltiples veces)}
                            {--show-errors : Mostrar logs de error durante la ejecución}
                            {--show-info : Mostrar logs de información durante la ejecución}
                            {--show-warning : Mostrar logs de advertencia durante la ejecución}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Obtiene analytics de templates de WhatsApp Business desde la API de Meta para una, varias o todas las cuentas según las opciones proporcionadas';

    /**
     * Account de WhatsApp Business actual
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $account;

    /**
     * Cliente HTTP
     *
     * @var GuzzleClient
     */
    protected $client;

    protected $currency;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Iniciando obtención de analytics de templates de WhatsApp Business...');

        try {
            $this->currency = config('whatsapp.api.currency', 'USD'); // Valor por defecto

            // 1. Obtener cuentas a procesar
            $accounts = $this->getAccountsToProcess();

            if ($accounts->isEmpty()) {
                $this->logError('❌ No se encontraron cuentas de WhatsApp Business para procesar');
                return Command::FAILURE;
            }

            $this->logInfo("🏢 Procesando <fg=blue>" . $accounts->count() . "</> cuenta(s) de WhatsApp Business");

            // 2. Determinar período de análisis
            $days = $this->determineDaysToFetch();
            $endDate = Carbon::now('UTC');
            $startDate = $endDate->copy()->subDays($days - 1);
            $this->logInfo("📅 Obteniendo analytics de los últimos <fg=blue>{$days}</> días (desde <fg=blue>{$startDate->format('Y-m-d')}</> hasta <fg=blue>{$endDate->format('Y-m-d')}</>)");

            // 3. Procesar por cada cuenta
            $totalProcessed    = 0;
            $totalSaved        = 0;
            $totalSkipped      = 0;
            $totalErrors       = 0;
            $accountsProcessed = 0;

            foreach ($accounts as $account) {
                $this->info("🏢 Procesando cuenta: <fg=blue>{$account->whatsapp_business_id} | {$account->name}</>");

                $result = $this->processAccount($account, $startDate, $endDate);

                if ($result['success']) {
                    $totalProcessed += $result['processed'];
                    $totalSaved += $result['saved'];
                    $totalSkipped += $result['skipped'];
                    $totalErrors += $result['errors'];
                    $accountsProcessed++;
                    $this->logInfo("   ✅ Cuenta procesada: <fg=blue>{$result['processed']}</> procesados, <fg=blue>{$result['saved']}</> guardados, <fg=blue>{$result['skipped']}</> omitidos (porque sus valores son 0), <fg=blue>{$result['errors']}</> errores");
                } else {
                    $this->logError("   ❌ Error procesando cuenta: <fg=blue>{$result['error']}</>");
                }

                // Pausa entre cuentas para evitar rate limiting
                if ($account !== $accounts->last()) {
                    $this->logInfo("⏱️ Pausa de <fg=blue>3</> segundos entre cuentas...");
                    sleep(3);
                }
            }

            // 4. Resumen final
            $this->logInfo("✅ Proceso completado:");
            $this->logInfo("   🏢 Cuentas procesadas: <fg=blue>{$accountsProcessed}/{$accounts->count()}</>");
            $this->logInfo("   📊 Registros procesados: <fg=blue>{$totalProcessed}</>");
            $this->logInfo("   💾 Registros guardados: <fg=blue>{$totalSaved}</>");
            $this->logInfo("   ⏭️ Registros omitidos (porque sus valores son 0): <fg=blue>{$totalSkipped}</>");
            $this->logInfo("   ❌ Errores totales: <fg=blue>{$totalErrors}</>");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->logError("💥 Error general: " . $e->getMessage());
            if ($this->option('show-errors')) {
                Log::error('WhatsApp Analytics Cron Error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return Command::FAILURE;
        }
    }

    /**
     * Obtener cuentas a procesar
     */
    protected function getAccountsToProcess()
    {
        // Si se especifican cuentas específicas
        $specificAccounts = $this->option('account');
        if (!empty($specificAccounts)) {
            $accounts = WhatsappModelResolver::business_account()
                ->whereIn('whatsapp_business_id', $specificAccounts)
                ->whereNotNull('api_token')
                ->where('api_token', '!=', '')
                ->get();

            if ($accounts->isEmpty()) {
                $this->logError("❌ No se encontraron cuentas válidas con los IDs: <fg=blue>" . implode(', ', $specificAccounts) . "</>");
                return collect();
            }

            $this->logInfo("🎯 <fg=blue>" . count($specificAccounts) . "</> cuenta(s) específica(s): " . implode(', ', $specificAccounts));
            $this->logInfo("🔍 Encontradas <fg=blue>" . $accounts->count() . "</> cuenta(s) válida(s) con token configurado");
            return $accounts;
        }

        // Obtener todas las cuentas activas con token configurado
        $accounts = WhatsappModelResolver::business_account()
            ->whereNotNull('api_token')
            ->where('api_token', '!=', '')
            ->get();

        $this->logInfo("🔍 Encontradas <fg=blue>" . $accounts->count() . "</> cuentas con token configurado");
        return $accounts;
    }

    /**
     * Procesar una cuenta específica
     */
    protected function processAccount($account, Carbon $startDate, Carbon $endDate): array
    {
        try {
            // Configurar la cuenta actual
            $this->account = $account;

            // Configurar cliente HTTP
            if (!$this->setupApiClientForAccount()) {
                return [
                    'success' => false,
                    'error' => 'No se pudo configurar el cliente API',
                    'processed' => 0,
                    'saved' => 0,
                    'skipped' => 0,
                    'errors' => 0
                ];
            }

            // Obtener templates de esta cuenta
            $templates = $this->getTemplatesChunksForAccount($account);

            if ($templates->isEmpty()) {
                $this->logInfo("   ⚠️ No se encontraron templates para esta cuenta");
                return [
                    'success' => true,
                    'processed' => 0,
                    'saved' => 0,
                    'skipped' => 0,
                    'errors' => 0
                ];
            }

            $this->logInfo("   📋 Procesando <fg=blue>" . $templates->flatten()->count() . "</> templates en chunks de <fg=blue>10</>");

            // Procesar cada chunk de templates
            $processed = 0;
            $saved = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($templates as $chunkIndex => $templateChunk) {
                $this->logInfo("   🔄 Chunk <fg=blue>" . ($chunkIndex + 1) . "</>/<fg=blue>" . $templates->count() . "</>");

                $result = $this->processTemplateChunk($templateChunk, $startDate, $endDate);
                $processed += $result['processed'];
                $saved += $result['saved'];
                $skipped += $result['skipped'];
                $errors += $result['errors'];

                // Pausa entre chunks para evitar rate limiting
                if ($chunkIndex < $templates->count() - 1) {
                    sleep(2);
                }
            }

            return [
                'success' => true,
                'processed' => $processed,
                'saved' => $saved,
                'skipped' => $skipped,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            if ($this->option('show-errors')) {
                Log::error('WhatsApp Analytics Account Processing Error', [
                    'account_id' => $account->whatsapp_business_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processed' => 0,
                'saved' => 0,
                'skipped' => 0,
                'errors' => 0
            ];
        }
    }

    /**
     * Configurar cliente API para la cuenta actual
     */
    protected function setupApiClientForAccount(): bool
    {
        if (!$this->account->api_token) {
            $this->logWarn("   ⚠️ Token de API no configurado en la cuenta");
            return false;
        }

        $this->client = new GuzzleClient([
            'timeout' => config('whatsapp.api.timeout', 30),
            'verify' => false // ⚠️ Solo para desarrollo
        ]);

        return true;
    }

    /**
     * Obtener templates en chunks de 10 para una cuenta específica
     */
    protected function getTemplatesChunksForAccount($account)
    {
        $query = WhatsappModelResolver::template()
            ->select('wa_template_id', 'name')
            //Asegurar que solo se obtienen templates aprobados
            ->where('status', '=', 'APPROVED')
            ->where('whatsapp_business_id', $account->whatsapp_business_id);

        // Si se especifican templates específicos
        $specificTemplates = $this->option('template');
        if (!empty($specificTemplates)) {
            $query->whereIn('wa_template_id', $specificTemplates);
            $this->logInfo("   🎯 Filtrando por <fg=blue>" . count($specificTemplates) . "</> template(s) específico(s): <fg=blue>" . implode(', ', $specificTemplates) . "</>");
        }

        return $query->pluck('wa_template_id')->chunk(10);
    }

    /**
     * Determinar cuántos días obtener
     */
    protected function determineDaysToFetch(): int
    {
        // Si se especifica días manualmente
        if ($this->option('days')) {
            $inputDays = (int)$this->option('days');
            $days = $inputDays > 90 ? 90 : $inputDays;
            $color = 'blue';
            if ($inputDays > 90) {
                $color = 'red';
            }
            $this->logInfo("🎯 Días especificados manualmente: <fg={$color}>{$inputDays}</> (máximo permitido: <fg=blue>90</>)");
            return $days;
        }

        // Si se fuerza obtención completa
        if ($this->option('force')) {
            $this->logInfo("🔒 Modo forzado: obteniendo <fg=blue>90</> días");
            return 90;
        }

        // Verificar si la tabla está vacía
        $hasData = WhatsappModelResolver::general_template_analytics()->exists();

        if (!$hasData) {
            $this->logInfo("📝 <fg=yellow>Tabla vacía:</> obteniendo <fg=blue>90</> días iniciales");
            return 90;
        } else {
            $this->logInfo("🔄 Tabla con datos: obteniendo <fg=blue>7</> días para actualización");
            return 7;
        }
    }

    /**
     * Obtener templates en chunks de 10 (método legacy)
     */
    protected function getTemplatesChunks()
    {
        $query = WhatsappModelResolver::template()->select('wa_template_id', 'name');

        // Si se especifican templates específicos
        $specificTemplates = $this->option('template');
        if (!empty($specificTemplates)) {
            $query->whereIn('wa_template_id', $specificTemplates);
        }

        // Si hay una cuenta configurada, filtrar por ella
        if ($this->account) {
            $query->where('whatsapp_business_id', $this->account->whatsapp_business_id);
        }

        return $query->pluck('wa_template_id')->chunk(10);
    }

    /**
     * Procesar un chunk de templates
     */
    protected function processTemplateChunk($templateIds, Carbon $startDate, Carbon $endDate): array
    {
        $processed = 0;
        $saved = 0;
        $skipped = 0;
        $errors = 0;

        try {
            // Llamar a la API
            $analyticsData = $this->fetchAnalyticsFromApi($templateIds->toArray(), $startDate, $endDate);

            if (!$analyticsData) {
                $this->logWarn("⚠️ No se pudieron obtener datos para este chunk");
                return [
                    'processed' => 0,
                    'saved' => 0,
                    'skipped' => 0,
                    'errors'    => count($templateIds)
                ];
            }

            // Procesar respuesta de la API
            foreach ($analyticsData['data'] as $dataGroup) {
                foreach ($dataGroup['data_points'] as $dataPoint) {
                    try {
                        $processed++;

                        // Verificar si se guardará realmente el registro
                        $sent = $dataPoint['sent'] ?? 0;
                        $delivered = $dataPoint['delivered'] ?? 0;
                        $read = $dataPoint['read'] ?? 0;
                        $totalMetrics = $sent + $delivered + $read;

                        if ($totalMetrics <= 0) {
                            $skipped++;
                            continue;
                        }

                        $this->saveAnalyticsData($dataPoint, $dataGroup);
                        $saved++;
                    } catch (\Exception $e) {
                        $errors++;
                        $this->logWarn("❌ Error guardando template <fg=blue>{$dataPoint['template_id']}</>: " . $e->getMessage());
                    }
                }
            }

        } catch (\Exception $e) {
            $this->logError("💥 Error procesando chunk: " . $e->getMessage());
            $errors = count($templateIds);
        }

        return [
            'processed' => $processed,
            'saved' => $saved,
            'skipped' => $skipped,
            'errors'    => $errors
        ];
    }

    /**
     * Obtener analytics desde la API de WhatsApp
     */
    protected function fetchAnalyticsFromApi(array $templateIds, Carbon $startDate, Carbon $endDate): ?array
    {
        $baseUri = config('whatsapp.api.base_url') . '/' . config('whatsapp.api.version') . '/' . $this->account->phone_number_id;

        try {
            $response = $this->client->get($baseUri . '/template_analytics', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->account->api_token,
                ],
                'query' => [
                    'start' => (string)$startDate->timestamp,
                    'end' => (string)$endDate->timestamp,
                    'granularity' => 'DAILY',
                    'metric_types' => [
                        'COST',
                        'CLICKED',
                        'SENT',
                        'DELIVERED',
                        'READ',
                    ],
                    'template_ids' => $templateIds,
                    'limit' => 2000, // Máximo para obtener todos los días
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                return json_decode($response->getBody()->getContents(), true);
            }

            $this->logWarn("⚠️ API respondió con código: <fg=blue>{$statusCode}</>");
            return null;

        } catch (RequestException $e) {
            $this->logError("🔌 Error de conexión con la API: " . $e->getMessage());

            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
                $this->logError("📄 Respuesta de error: " . $errorBody);
            }

            return null;
        }
    }

    /**
     * Guardar datos de analytics en la base de datos
     */
    protected function saveAnalyticsData(array $dataPoint, array $dataGroup): void
    {
        // Verificar que la suma de métricas principales sea mayor a cero
        $sent = $dataPoint['sent'] ?? 0;
        $delivered = $dataPoint['delivered'] ?? 0;
        $read = $dataPoint['read'] ?? 0;

        $totalMetrics = $sent + $delivered + $read;

        if ($totalMetrics <= 0) {
            // No hay datos relevantes, omitir el guardado
            return;
        }

        DB::transaction(function () use ($dataPoint, $dataGroup, $sent, $delivered, $read) {
            // Timestamps en UTC (de la API)
            $startTimestamp = $dataPoint['start'];
            $endTimestamp   = $dataPoint['end'];

            // Crear fechas desde timestamps manteniendo UTC (sin conversión de zona horaria)
            // Esto evita que las fechas cambien al día anterior cuando se convierten a timezone local
            $startDate = Carbon::createFromTimestamp($startTimestamp, 'UTC');
            $endDate   = Carbon::createFromTimestamp($endTimestamp, 'UTC');

            // Guardar registro principal
            $analytics = WhatsappModelResolver::general_template_analytics()->updateOrCreate([
                'wa_template_id'  => $dataPoint['template_id'],
                'start_timestamp' => $startTimestamp, // UTC
                'end_timestamp'   => $endTimestamp,   // UTC
            ], [
                'granularity'     => $dataGroup['granularity'],
                'product_type'    => $dataGroup['product_type'],
                'start_date'      => $startDate->format('Y-m-d'),
                'end_date'        => $endDate->format('Y-m-d'),
                'sent'            => $sent,
                'delivered'       => $delivered,
                'read'            => $read,
                'json_data'       => $dataPoint,
            ]);

            // Asegurar que tenemos el ID del registro
            $analytics->refresh();

            // Guardar datos de clicks
            if (isset($dataPoint['clicked']) && is_array($dataPoint['clicked'])) {
                foreach ($dataPoint['clicked'] as $clickData) {
                    //Conversando con Wilfredo vemos que no es necesario guardar si count es 0
                    if (isset($clickData['type']) && isset($clickData['count']) && $clickData['count'] > 0) {
                        WhatsappModelResolver::general_template_analytics_clicked()->updateOrCreate(
                            [
                                'general_template_analytics_id' => $analytics->id,
                                'type' => $clickData['type'],
                                'button_content' => $clickData['button_content'],
                            ],
                            [
                                'count' => $clickData['count'],
                            ]
                        );
                    }
                }
            }

            // Guardar datos de costos
            if (isset($dataPoint['cost']) && is_array($dataPoint['cost'])) {
                foreach ($dataPoint['cost'] as $costData) {
                    //Conversando con Wilfredo vemos que no es necesario guardar si value es 0
                    if (isset($costData['type']) && isset($costData['value']) && $costData['value'] > 0) {
                        $costModel = WhatsappModelResolver::general_template_analytics_cost()->firstOrNew([
                            'general_template_analytics_id' => $analytics->id,
                            'type' => $costData['type'],
                        ]);
                        $costModel->value = $costData['value'];
                        if (!$costModel->exists) {
                            $costModel->currency = $this->currency;
                        }
                        $costModel->save();
                    }
                }
            }
        });
    }

    /**
     * Mostrar mensaje de error solo si está habilitado
     */
    protected function logError(string $message): void
    {
        if ($this->option('show-errors')) {
            $this->error($message);
        }
    }

    /**
     * Mostrar mensaje de información solo si está habilitado
     */
    protected function logInfo(string $message): void
    {
        if ($this->option('show-info')) {
            $this->info($message);
        }
    }

    /**
     * Mostrar mensaje de advertencia solo si está habilitado
     */
    protected function logWarn(string $message): void
    {
        if ($this->option('show-warning')) {
            $this->warn($message);
        }
    }
}