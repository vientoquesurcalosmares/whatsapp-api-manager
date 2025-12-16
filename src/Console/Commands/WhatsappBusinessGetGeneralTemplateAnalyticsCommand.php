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
                            {--force : Force retrieval of 90 days even if data exists}
                            {--template=* : Get analytics for specific templates (can be used multiple times)}
                            {--days= : Specific number of days to retrieve (maximum 90)}
                            {--account=* : Process specific accounts (can be used multiple times)}
                            {--show-errors : Show error logs during execution}
                            {--show-info : Show information logs during execution}
                            {--show-warning : Show warning logs during execution}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retrieves WhatsApp Business template analytics from the Meta API';

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
        $this->info('🚀 ' . whatsapp_trans('analytics.starting'));

        try {
            $this->currency = config('whatsapp.api.currency', 'USD');

            // 1. Get accounts to process
            $accounts = $this->getAccountsToProcess();

            if ($accounts->isEmpty()) {
                $this->logError('❌ ' . whatsapp_trans('analytics.no_accounts_found'));
                return Command::FAILURE;
            }

            $this->logInfo('🏢 ' . whatsapp_trans('analytics.processing_accounts', ['count' => $accounts->count()]));

            // 2. Determine analysis period
            $days = $this->determineDaysToFetch();
            $endDate = Carbon::now('UTC');
            $startDate = $endDate->copy()->subDays($days - 1);
            $this->logInfo('📅 ' . whatsapp_trans('analytics.analyzing_period', [
                'days' => $days,
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ]));

            // 3. Process each account
            $totalProcessed    = 0;
            $totalSaved        = 0;
            $totalSkipped      = 0;
            $totalErrors       = 0;
            $accountsProcessed = 0;

            foreach ($accounts as $account) {
                $this->info('🏢 ' . whatsapp_trans('analytics.processing_account', [
                    'id' => $account->whatsapp_business_id,
                    'name' => $account->name
                ]));

                $result = $this->processAccount($account, $startDate, $endDate);

                if ($result['success']) {
                    $totalProcessed += $result['processed'];
                    $totalSaved += $result['saved'];
                    $totalSkipped += $result['skipped'];
                    $totalErrors += $result['errors'];
                    $accountsProcessed++;
                    $this->logInfo('   ✅ ' . whatsapp_trans('analytics.account_processed', [
                        'processed' => $result['processed'],
                        'saved' => $result['saved'],
                        'skipped' => $result['skipped'],
                        'errors' => $result['errors']
                    ]));
                } else {
                    $this->logError('   ❌ ' . whatsapp_trans('analytics.account_error', ['error' => $result['error']]));
                }

                // Pause between accounts to avoid rate limiting
                if ($account !== $accounts->last()) {
                    $this->logInfo('⏱️ ' . whatsapp_trans('analytics.pause_between_accounts'));
                    sleep(3);
                }
            }

            // 4. Final summary
            $this->logInfo('✅ ' . whatsapp_trans('analytics.process_completed'));
            $this->logInfo('   🏢 ' . whatsapp_trans('analytics.accounts_processed', [
                'processed' => $accountsProcessed,
                'total' => $accounts->count()
            ]));
            $this->logInfo('   📊 ' . whatsapp_trans('analytics.records_processed', ['count' => $totalProcessed]));
            $this->logInfo('   💾 ' . whatsapp_trans('analytics.records_saved', ['count' => $totalSaved]));
            $this->logInfo('   ⏭️ ' . whatsapp_trans('analytics.records_skipped', ['count' => $totalSkipped]));
            $color = 'blue';
            if( $totalErrors > 0 ) {
                $color = 'red';
            }
            $this->logInfo('   ❌ ' . whatsapp_trans('analytics.total_errors', ['color' => $color, 'count' => $totalErrors]));

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->logError('💥 ' . whatsapp_trans('analytics.general_error', ['message' => $e->getMessage()]));
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
     * Get accounts to process
     */
    protected function getAccountsToProcess()
    {
        // If specific accounts are specified
        $specificAccounts = $this->option('account');
        if (!empty($specificAccounts)) {
            $accounts = WhatsappModelResolver::business_account()
                ->whereIn('whatsapp_business_id', $specificAccounts)
                ->whereNotNull('api_token')
                ->where('api_token', '!=', '')
                ->get();

            if ($accounts->isEmpty()) {
                $this->logError('❌ ' . whatsapp_trans('analytics.no_accounts_with_ids', [
                    'ids' => implode(', ', $specificAccounts)
                ]));
                return collect();
            }

            $this->logInfo('🎯 ' . whatsapp_trans('analytics.specific_accounts', [
                'count' => count($specificAccounts),
                'ids' => implode(', ', $specificAccounts)
            ]));
            $this->logInfo('🔍 ' . whatsapp_trans('analytics.found_valid_accounts', ['count' => $accounts->count()]));
            return $accounts;
        }

        // Get all active accounts with configured token
        $accounts = WhatsappModelResolver::business_account()
            ->whereNotNull('api_token')
            ->where('api_token', '!=', '')
            ->get();

        $this->logInfo('🔍 ' . whatsapp_trans('analytics.found_accounts_with_token', ['count' => $accounts->count()]));
        return $accounts;
    }

    /**
     * Process a specific account
     */
    protected function processAccount($account, Carbon $startDate, Carbon $endDate): array
    {
        try {
            // Configure current account
            $this->account = $account;

            // Configure HTTP client
            if (!$this->setupApiClientForAccount()) {
                return [
                    'success' => false,
                    'error' => whatsapp_trans('analytics.api_client_setup_failed'),
                    'processed' => 0,
                    'saved' => 0,
                    'skipped' => 0,
                    'errors' => 0
                ];
            }

            // Get templates for this account
            $templates = $this->getTemplatesChunksForAccount($account);

            if ($templates->isEmpty()) {
                $this->logInfo('   ⚠️ ' . whatsapp_trans('analytics.no_templates_found'));
                return [
                    'success' => true,
                    'processed' => 0,
                    'saved' => 0,
                    'skipped' => 0,
                    'errors' => 0
                ];
            }

            $this->logInfo('   📋 ' . whatsapp_trans('analytics.processing_templates', [
                'count' => $templates->flatten()->count()
            ]));

            // Process each template chunk
            $processed = 0;
            $saved = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($templates as $chunkIndex => $templateChunk) {
                $this->logInfo('   🔄 ' . whatsapp_trans('analytics.processing_chunk', [
                    'current' => ($chunkIndex + 1),
                    'total' => $templates->count()
                ]));

                $result = $this->processTemplateChunk($templateChunk, $startDate, $endDate);
                $processed += $result['processed'];
                $saved += $result['saved'];
                $skipped += $result['skipped'];
                $errors += $result['errors'];

                // Pause between chunks to avoid rate limiting
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
     * Configure API client for current account
     */
    protected function setupApiClientForAccount(): bool
    {
        if (!$this->account->api_token) {
            $this->logWarn('   ⚠️ ' . whatsapp_trans('analytics.api_token_not_configured'));
            return false;
        }

        $this->client = new GuzzleClient([
            'timeout' => config('whatsapp.api.timeout', 30),
            'verify' => false
        ]);

        return true;
    }

    /**
     * Get templates in chunks of 10 for a specific account
     */
    protected function getTemplatesChunksForAccount($account)
    {
        $query = WhatsappModelResolver::template()
            ->select('wa_template_id', 'name')
            // Ensure only approved templates are retrieved
            ->where('status', '=', 'APPROVED')
            ->where('whatsapp_business_id', $account->whatsapp_business_id);

        // If specific templates are specified
        $specificTemplates = $this->option('template');
        if (!empty($specificTemplates)) {
            $query->whereIn('wa_template_id', $specificTemplates);
            $this->logInfo('   🎯 ' . whatsapp_trans('analytics.filtering_templates', [
                'count' => count($specificTemplates),
                'ids' => implode(', ', $specificTemplates)
            ]));
        }

        return $query->pluck('wa_template_id')->chunk(10);
    }

    /**
     * Determine how many days to fetch
     */
    protected function determineDaysToFetch(): int
    {
        // If days are specified manually
        if ($this->option('days')) {
            $inputDays = (int)$this->option('days');
            $days = $inputDays > 90 ? 90 : $inputDays;
            $color = 'blue';
            if ($inputDays > 90) {
                $color = 'red';
            }
            $this->logInfo('🎯 ' . whatsapp_trans('analytics.days_specified', [
                'color' => $color,
                'input' => $inputDays
            ]));
            return $days;
        }

        // If forced full retrieval
        if ($this->option('force')) {
            $this->logInfo('🔒 ' . whatsapp_trans('analytics.forced_mode'));
            return 90;
        }

        // Check if table is empty
        $hasData = WhatsappModelResolver::general_template_analytics()->exists();

        if (!$hasData) {
            $this->logInfo('📝 ' . whatsapp_trans('analytics.empty_table'));
            return 90;
        } else {
            $this->logInfo('🔄 ' . whatsapp_trans('analytics.update_mode'));
            return 7;
        }
    }

    /**
     * Get templates in chunks of 10 (legacy method)
     */
    protected function getTemplatesChunks()
    {
        $query = WhatsappModelResolver::template()->select('wa_template_id', 'name');

        // If specific templates are specified
        $specificTemplates = $this->option('template');
        if (!empty($specificTemplates)) {
            $query->whereIn('wa_template_id', $specificTemplates);
        }

        // If an account is configured, filter by it
        if ($this->account) {
            $query->where('whatsapp_business_id', $this->account->whatsapp_business_id);
        }

        return $query->pluck('wa_template_id')->chunk(10);
    }

    /**
     * Process a template chunk
     */
    protected function processTemplateChunk($templateIds, Carbon $startDate, Carbon $endDate): array
    {
        $processed = 0;
        $saved = 0;
        $skipped = 0;
        $errors = 0;

        try {
            // Call the API
            $analyticsData = $this->fetchAnalyticsFromApi($templateIds->toArray(), $startDate, $endDate);

            if (!$analyticsData) {
                $this->logWarn('⚠️ ' . whatsapp_trans('analytics.no_data_for_chunk'));
                return [
                    'processed' => 0,
                    'saved' => 0,
                    'skipped' => 0,
                    'errors'    => count($templateIds)
                ];
            }

            // Process API response
            foreach ($analyticsData['data'] as $dataGroup) {
                foreach ($dataGroup['data_points'] as $dataPoint) {
                    try {
                        $processed++;

                        // Check if the record will actually be saved
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
                        $this->logWarn('❌ ' . whatsapp_trans('analytics.error_saving_template', [
                            'id' => $dataPoint['template_id'],
                            'message' => $e->getMessage()
                        ]));
                    }
                }
            }

        } catch (\Exception $e) {
            $this->logError('💥 ' . whatsapp_trans('analytics.chunk_processing_error', ['message' => $e->getMessage()]));
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
     * Fetch analytics from WhatsApp API
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
                    'limit' => 2000,
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                return json_decode($response->getBody()->getContents(), true);
            }

            $this->logWarn('⚠️ ' . whatsapp_trans('analytics.api_response_code', ['code' => $statusCode]));
            return null;

        } catch (RequestException $e) {
            $this->logError('🔌 ' . whatsapp_trans('analytics.api_connection_error', ['message' => $e->getMessage()]));

            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
                $this->logError('📄 ' . whatsapp_trans('analytics.api_error_response', ['body' => $errorBody]));
            }

            return null;
        }
    }

    /**
     * Save analytics data to database
     */
    protected function saveAnalyticsData(array $dataPoint, array $dataGroup): void
    {
        // Verify that the sum of main metrics is greater than zero
        $sent = $dataPoint['sent'] ?? 0;
        $delivered = $dataPoint['delivered'] ?? 0;
        $read = $dataPoint['read'] ?? 0;

        $totalMetrics = $sent + $delivered + $read;

        if ($totalMetrics <= 0) {
            // No relevant data, skip saving
            return;
        }

        DB::transaction(function () use ($dataPoint, $dataGroup, $sent, $delivered, $read) {
            // Timestamps in UTC (from API)
            $startTimestamp = $dataPoint['start'];
            $endTimestamp   = $dataPoint['end'];

            // Create dates from timestamps maintaining UTC (without timezone conversion)
            // This prevents dates from changing to the previous day when converted to local timezone
            $startDate = Carbon::createFromTimestamp($startTimestamp, 'UTC');
            $endDate   = Carbon::createFromTimestamp($endTimestamp, 'UTC');

            // Save main record
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

            // Ensure we have the record ID
            $analytics->refresh();

            // Save click data
            if (isset($dataPoint['clicked']) && is_array($dataPoint['clicked'])) {
                foreach ($dataPoint['clicked'] as $clickData) {
                    // No need to save if count is 0
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

            // Save cost data
            if (isset($dataPoint['cost']) && is_array($dataPoint['cost'])) {
                foreach ($dataPoint['cost'] as $costData) {
                    // No need to save if value is 0
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