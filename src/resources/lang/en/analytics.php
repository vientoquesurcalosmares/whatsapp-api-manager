<?php

return [
    // Command Description and Options
    'description' => 'Retrieves WhatsApp Business template analytics from the Meta API for one, several, or all accounts according to the provided options',

    'option_force' => 'Force retrieval of 90 days even if data exists',
    'option_template' => 'Get analytics for specific templates (can be used multiple times)',
    'option_days' => 'Specific number of days to retrieve (maximum 90)',
    'option_account' => 'Process specific accounts (can be used multiple times)',
    'option_show_errors' => 'Show error logs during execution',
    'option_show_info' => 'Show information logs during execution',
    'option_show_warning' => 'Show warning logs during execution',

    // Process Messages
    'starting' => 'Starting retrieval of WhatsApp Business template analytics...',
    'no_accounts_found' => 'No WhatsApp Business accounts found to process',
    'processing_accounts' => 'Processing <fg=blue>:count</> WhatsApp Business account(s)',
    'analyzing_period' => 'Retrieving analytics for the last <fg=blue>:days</> days (from <fg=blue>:start</> to <fg=blue>:end</>)',
    'processing_account' => 'Processing account: <fg=blue>:id | :name</>',
    'account_processed' => 'Account processed: <fg=blue>:processed</> processed, <fg=blue>:saved</> saved, <fg=blue>:skipped</> skipped (because their values are 0), <fg=blue>:errors</> errors',
    'account_error' => 'Error processing account: <fg=blue>:error</>',
    'pause_between_accounts' => 'Pausing <fg=blue>3</> seconds between accounts...',

    // Summary
    'process_completed' => 'Process completed:',
    'accounts_processed' => 'Accounts processed: <fg=blue>:processed/:total</>',
    'records_processed' => 'Records processed: <fg=blue>:count</>',
    'records_saved' => 'Records saved: <fg=blue>:count</>',
    'records_skipped' => 'Records skipped (because their values are 0): <fg=blue>:count</>',
    'total_errors' => 'Total errors: <fg={color}>:count</>',

    // Errors
    'general_error' => 'General error: :message',
    'no_accounts_with_ids' => 'No valid accounts found with IDs: <fg=blue>:ids</>',

    // Account Processing
    'specific_accounts' => '<fg=blue>:count</> specific account(s): :ids',
    'found_valid_accounts' => 'Found <fg=blue>:count</> valid account(s) with configured token',
    'found_accounts_with_token' => 'Found <fg=blue>:count</> accounts with configured token',
    'api_client_setup_failed' => 'Could not configure API client',
    'no_templates_found' => 'No templates found for this account',
    'processing_templates' => 'Processing <fg=blue>:count</> templates in chunks of <fg=blue>10</>',
    'processing_chunk' => 'Chunk <fg=blue>:current</>/<fg=blue>:total</>',
    'api_token_not_configured' => 'API token not configured in the account',

    // Template Filtering
    'filtering_templates' => 'Filtering by <fg=blue>:count</> specific template(s): <fg=blue>:ids</>',

    // Days Determination
    'days_specified' => 'Days specified manually: <fg={color}>:input</> (maximum allowed: <fg=blue>90</>)',
    'forced_mode' => 'Forced mode: retrieving <fg=blue>90</> days',
    'empty_table' => '<fg=yellow>Empty table:</> retrieving <fg=blue>90</> initial days',
    'update_mode' => 'Table with data: retrieving <fg=blue>7</> days for update',

    // Chunk Processing
    'no_data_for_chunk' => 'Could not obtain data for this chunk',
    'error_saving_template' => 'Error saving template <fg=blue>:id</>: :message',
    'chunk_processing_error' => 'Error processing chunk: :message',

    // API Communication
    'api_response_code' => 'API responded with code: <fg=blue>:code</>',
    'api_connection_error' => 'Connection error with API: :message',
    'api_error_response' => 'Error response: :body',
];
