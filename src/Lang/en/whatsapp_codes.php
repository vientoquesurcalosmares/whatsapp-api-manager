<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API Error Codes
    |--------------------------------------------------------------------------
    |
    | Source: https://developers.facebook.com/documentation/business-messaging/whatsapp/support/error-codes
    | Each entry contains:
    |   'title'   => Short error title
    |   'detail'  => Description of the error and likely cause
    |   'solution'=> Suggested resolution
    |
    */

    // -------------------------------------------------------------------------
    // Authorization Errors
    // -------------------------------------------------------------------------

    0 => [
        'title'    => 'Auth Exception',
        'detail'   => 'Could not authenticate the app user. Usually caused by an expired or invalidated access token, or the user changed a setting preventing all apps from accessing their data.',
        'solution' => 'Get a new access token.',
    ],

    3 => [
        'title'    => 'API Method',
        'detail'   => 'Capability or permissions issue.',
        'solution' => 'Use the Access Token Debugger to verify your app has the required permissions. See Authentication and Authorization Errors.',
    ],

    10 => [
        'title'    => 'Permission Denied',
        'detail'   => 'Permission was not granted or was removed.',
        'solution' => 'Use the Access Token Debugger to verify permissions. For WhatsApp Flows with an endpoint, make sure the phone number used to set up the business public key is authorized.',
    ],

    190 => [
        'title'    => 'Access Token Expired',
        'detail'   => 'The access token has expired.',
        'solution' => 'Get a new access token.',
    ],

    // Codes 200–299
    '200_299' => [
        'title'    => 'API Permission',
        'detail'   => 'Permission was not granted or was removed.',
        'solution' => 'Use the Access Token Debugger to verify your app has the required permissions.',
    ],

    // -------------------------------------------------------------------------
    // Integrity Errors
    // -------------------------------------------------------------------------

    368 => [
        'title'    => 'Temporarily Blocked for Policy Violations',
        'detail'   => 'The WhatsApp Business Account associated with the app was restricted or disabled due to a platform policy violation.',
        'solution' => 'See the Policy Enforcement document for information about policy violations and how to resolve them.',
    ],

    130497 => [
        'title'    => 'Business Account Cannot Send Messages to Users in This Country',
        'detail'   => 'The WhatsApp Business Account cannot send messages to users in certain countries.',
        'solution' => 'See WhatsApp Business Policy for information about which countries allow messaging by business category.',
    ],

    131031 => [
        'title'    => 'Account Locked',
        'detail'   => 'The WhatsApp Business Account was restricted or disabled due to a platform policy violation, or the data in the request could not be verified against data configured on the account (e.g., the two-step verification PIN is incorrect).',
        'solution' => 'See Policy Enforcement. You can also use the Health Status API for additional information about why the account was blocked.',
    ],

    // -------------------------------------------------------------------------
    // Throttling / Rate Limit Errors
    // -------------------------------------------------------------------------

    4 => [
        'title'    => 'Too Many API Calls',
        'detail'   => 'The app has reached the API call rate limit.',
        'solution' => 'Load the app in the app dashboard and check the App Rate Limit section. If the limit is reached, retry later or reduce the frequency of API calls.',
    ],

    80007 => [
        'title'    => 'Rate Limit Issues',
        'detail'   => 'The WhatsApp Business Account has reached its rate limit.',
        'solution' => 'See WhatsApp Business Account Rate Limits. Retry later or reduce the frequency of API calls.',
    ],

    130429 => [
        'title'    => 'Rate Limit Hit',
        'detail'   => 'Cloud API message throughput has been reached.',
        'solution' => 'Retry later or reduce the frequency with which the app sends messages. See Throughput.',
    ],

    131048 => [
        'title'    => 'Spam Rate Limit Reached',
        'detail'   => 'Message failed to send because there are restrictions on how many messages can be sent from this phone number. This may be because too many previous messages were blocked or flagged as spam.',
        'solution' => 'Check quality status in WhatsApp Manager. See Template Limits and Template Quality.',
    ],

    131056 => [
        'title'    => 'Business Account/Customer Pair Rate Limit Reached',
        'detail'   => 'Too many messages sent from the sender phone number to the same recipient phone number in a short period.',
        'solution' => 'Wait and retry the operation to send messages to the same phone number. You can still send messages to different phone numbers without waiting.',
    ],

    131064 => [
        'title'    => 'Message Limit Reached Due to Template Classification Violations',
        'detail'   => 'Message failed to send because this account has reached its message limit due to template classification violations. Applies to both template and direct send messages.',
        'solution' => 'Review your template classifications and ensure they are correctly categorized. This restriction is automatically lifted after the enforcement period. See Template Quality.',
    ],

    133016 => [
        'title'    => 'Registration/Deregistration Rate Limit Exceeded',
        'detail'   => 'Registration or deregistration failed because too many attempts were made for this phone number in a short period.',
        'solution' => 'The business phone number is being blocked because it reached the registration/deregistration attempt limit. Retry when the number is unblocked. See "Limitations" in the Registration document.',
    ],

    // -------------------------------------------------------------------------
    // Other / Messaging Errors
    // -------------------------------------------------------------------------

    1 => [
        'title'    => 'API Unknown',
        'detail'   => 'Invalid request or possible server error.',
        'solution' => 'Check the WhatsApp Business Platform Status page. If the server is not down, review the endpoint reference and verify the request is properly formatted.',
    ],

    2 => [
        'title'    => 'API Service',
        'detail'   => 'Temporary issue due to downtime or server overload.',
        'solution' => 'Check the WhatsApp Business Platform Status page before retrying.',
    ],

    33 => [
        'title'    => 'Parameter Value Is Not Valid',
        'detail'   => 'The business phone number has been deleted.',
        'solution' => 'Verify the business phone number is correct.',
    ],

    100 => [
        'title'    => 'Invalid Parameter',
        'detail'   => 'One or more unsupported or misspelled parameters were included in the request.',
        'solution' => 'Check the endpoint reference for supported parameters. Ensure there is no mismatch between the phone number ID being registered and the previously stored phone number ID.',
    ],

    130472 => [
        'title'    => 'User Number Part of an Experiment',
        'detail'   => 'Message was not sent as part of an experiment.',
        'solution' => 'See Message Experiment.',
    ],

    131000 => [
        'title'    => 'Something Went Wrong',
        'detail'   => 'Message failed to send due to an unknown error.',
        'solution' => 'Try again. If the error persists, open a direct support ticket.',
    ],

    131005 => [
        'title'    => 'Access Denied',
        'detail'   => 'Permission was not granted or was removed.',
        'solution' => 'Use the Access Token Debugger to verify permissions.',
    ],

    131008 => [
        'title'    => 'Required Parameter Is Missing',
        'detail'   => 'A required parameter is missing from the request.',
        'solution' => 'Check the endpoint reference to determine which parameters are required.',
    ],

    131009 => [
        'title'    => 'Parameter Value Is Not Valid',
        'detail'   => 'One or more parameter values are invalid.',
        'solution' => 'Check the endpoint reference for supported values for each parameter.',
    ],

    131016 => [
        'title'    => 'Service Unavailable',
        'detail'   => 'A service is temporarily unavailable.',
        'solution' => 'Check the WhatsApp Business Platform Status page before retrying.',
    ],

    131021 => [
        'title'    => 'Recipient Cannot Be Sender',
        'detail'   => 'The sender and recipient phone numbers are the same.',
        'solution' => 'Send a message to a phone number that is not the sender.',
    ],

    131026 => [
        'title'    => 'Message Undeliverable',
        'detail'   => 'Message cannot be delivered. Possible reasons: recipient phone number is not a WhatsApp number; recipient has not accepted new Terms of Service; recipient is using an old version of WhatsApp.',
        'solution' => 'Use a different communication method and ask the WhatsApp user to confirm they can message your WhatsApp business phone number, and that they have accepted the Terms of Service.',
    ],

    131037 => [
        'title'    => 'WhatsApp Number Needs Approved Display Name Before Sending',
        'detail'   => 'The business phone number used to send the request does not have an approved display name.',
        'solution' => 'Change the display name of the business phone number.',
    ],

    131042 => [
        'title'    => 'Business Eligibility - Payment Issue',
        'detail'   => 'There was an error related to your payment method. Common issues: no payment account attached, credit line exceeded, credit line not configured, WABA deleted or suspended, timezone or currency not configured, or MessagingFor request pending/rejected.',
        'solution' => 'See Billing Information for a WhatsApp Business Account and verify billing is set up correctly.',
    ],

    131045 => [
        'title'    => 'Incorrect Certificate',
        'detail'   => 'Message failed to send due to a phone number registration error.',
        'solution' => 'Register the phone number before retrying.',
    ],

    131047 => [
        'title'    => 'Re-engagement Message',
        'detail'   => 'More than 24 hours have passed since the last time the recipient sent a message to the sender.',
        'solution' => 'Send the recipient a template message instead.',
    ],

    131049 => [
        'title'    => 'Meta Chose Not to Deliver the Message',
        'detail'   => 'This message was not delivered in order to maintain a healthy ecosystem.',
        'solution' => 'If you suspect this is due to a limit, wait at least 24 hours before resending the template message. See Per-User Marketing Template Message Limits.',
    ],

    131050 => [
        'title'    => 'User Has Stopped Marketing Messages',
        'detail'   => 'This recipient has chosen to stop receiving marketing messages from your business on WhatsApp.',
        'solution' => 'Do not retry sending messages to this user. Subscribe to the user_preferences webhook for opt-out notifications.',
    ],

    131051 => [
        'title'    => 'Unsupported Message Type',
        'detail'   => 'Message type is not supported.',
        'solution' => 'See Messages for supported message types before retrying with a supported type.',
    ],

    131052 => [
        'title'    => 'Media Download Error',
        'detail'   => 'Unable to download the media sent by the user.',
        'solution' => 'Check error.error_data.details in the message webhooks. Ask the WhatsApp user to send the media file through a channel other than WhatsApp.',
    ],

    131053 => [
        'title'    => 'Media Upload Error',
        'detail'   => 'Unable to upload the media used in the message. Possible reasons include an unsupported media type.',
        'solution' => 'Inspect media files causing errors to confirm they are supported types. See Supported Media Types.',
    ],

    131057 => [
        'title'    => 'Account in Maintenance Mode',
        'detail'   => 'The business account is in maintenance mode, possibly due to a performance update.',
        'solution' => 'Wait and retry. If the issue persists, contact support.',
    ],

    131063 => [
        'title'    => 'Marketing Templates Disabled for Cloud API',
        'detail'   => 'Your template is categorized as marketing, but marketing templates are currently disabled for the Cloud API setup.',
        'solution' => 'Use the marketing messages endpoint or re-enable marketing templates by setting disable_marketing_messages_on_cloud_api to false.',
    ],

    // -------------------------------------------------------------------------
    // Template Errors
    // -------------------------------------------------------------------------

    132000 => [
        'title'    => 'Template Parameter Count Mismatch',
        'detail'   => 'The number of variable parameter values in the request does not match the number defined in the template.',
        'solution' => 'See Templates to ensure the request includes values for all required parameters.',
    ],

    132001 => [
        'title'    => 'Template Does Not Exist',
        'detail'   => 'The template does not exist in the specified language or has not been approved.',
        'solution' => 'Ensure the template is approved and that the name and language are correct.',
    ],

    132005 => [
        'title'    => 'Template Hydrated Text Too Long',
        'detail'   => 'The translated text is too long.',
        'solution' => 'Check in WhatsApp Manager that the template has been translated. See Template Quality.',
    ],

    132007 => [
        'title'    => 'Template Format Character Policy Violated',
        'detail'   => 'Template content violates a WhatsApp policy.',
        'solution' => 'See Template Review for possible reasons for the violation.',
    ],

    132012 => [
        'title'    => 'Template Parameter Format Mismatch',
        'detail'   => 'Variable parameter values have an incorrect format.',
        'solution' => 'Ensure variable parameter values use the format specified in the template. See Templates.',
    ],

    132015 => [
        'title'    => 'Template Is Paused',
        'detail'   => 'Template is paused due to low quality and cannot be sent.',
        'solution' => 'Edit the template to improve quality and retry once it is approved.',
    ],

    132016 => [
        'title'    => 'Template Is Disabled',
        'detail'   => 'Template was paused too many times due to low quality and is now permanently disabled.',
        'solution' => 'Create a new template with different content.',
    ],

    132018 => [
        'title'    => 'Template Validation Error',
        'detail'   => 'There is an issue with the parameters in your template.',
        'solution' => 'Review the errors, update parameters as needed, and resend with a correctly configured template.',
    ],

    132068 => [
        'title'    => 'Flow Is Blocked',
        'detail'   => 'The flow is in a blocked state.',
        'solution' => 'Fix the flow.',
    ],

    132069 => [
        'title'    => 'Flow Is Throttled',
        'detail'   => 'The flow is throttled; 10 messages using this flow have already been sent in the last hour.',
        'solution' => 'Fix the flow.',
    ],

    // -------------------------------------------------------------------------
    // Registration Errors
    // -------------------------------------------------------------------------

    133000 => [
        'title'    => 'Incomplete Deregistration',
        'detail'   => 'A previous deregistration attempt failed.',
        'solution' => 'Deregister the number again before registering.',
    ],

    133004 => [
        'title'    => 'Server Temporarily Unavailable',
        'detail'   => 'The server is temporarily unavailable.',
        'solution' => 'Check the WhatsApp Business Platform Status page and the details value before retrying.',
    ],

    133005 => [
        'title'    => 'Two-Step Verification PIN Mismatch',
        'detail'   => 'The two-step verification PIN is incorrect.',
        'solution' => 'Verify the PIN is correct. To reset it, disable two-step verification and set a new PIN. See Two-Step Verification.',
    ],

    133006 => [
        'title'    => 'Phone Number Needs to Be Verified',
        'detail'   => 'The phone number needs to be verified before it can be registered.',
        'solution' => 'Verify and register the phone number.',
    ],

    133008 => [
        'title'    => 'Too Many Two-Step Verification PIN Guesses',
        'detail'   => 'Too many incorrect two-step verification PIN attempts for this phone number.',
        'solution' => 'Retry after the time specified in the details response value.',
    ],

    133009 => [
        'title'    => 'Two-Step Verification PIN Guessed Too Fast',
        'detail'   => 'The two-step verification PIN was entered too quickly.',
        'solution' => 'See the details response value before retrying.',
    ],

    133010 => [
        'title'    => 'Phone Number Not Registered',
        'detail'   => 'Phone number is not registered on the WhatsApp Business Platform.',
        'solution' => 'Register the phone number before retrying.',
    ],

    133015 => [
        'title'    => 'Please Wait Before Attempting to Register This Phone Number',
        'detail'   => 'The phone number you are trying to register was recently deleted and the action has not yet completed.',
        'solution' => 'Wait 5 minutes before resending the request.',
    ],

    // -------------------------------------------------------------------------
    // Payment Errors
    // -------------------------------------------------------------------------

    134011 => [
        'title'    => 'WhatsApp Payments Terms of Service Not Accepted',
        'detail'   => 'Message could not be sent because acceptance of WhatsApp Payments Terms of Service is pending for this WhatsApp Business Account.',
        'solution' => 'Accept the WhatsApp Payments Terms of Service via the link in the error message before retrying.',
    ],

    // -------------------------------------------------------------------------
    // Generic / Catch-All Errors
    // -------------------------------------------------------------------------

    135000 => [
        'title'    => 'Generic Usage Error',
        'detail'   => 'Message failed to send due to an unknown error with the request parameters.',
        'solution' => 'Check the endpoint reference for the correct syntax. Contact support if this error persists.',
    ],

    // -------------------------------------------------------------------------
    // Template Creation Errors
    // -------------------------------------------------------------------------

    2388019 => [
        'title'    => 'Message Template Count Limit Exceeded',
        'detail'   => 'You have exceeded the maximum number of message templates for this WhatsApp Business Account.',
        'solution' => 'Each WhatsApp Business Account can have a maximum of 250 message templates. See Template Limits.',
    ],

    2388040 => [
        'title'    => 'Character Limit Exceeded',
        'detail'   => 'A field in the template exceeded the maximum allowed character limit.',
        'solution' => 'Check the error message for specific information about the affected field and its character limits.',
    ],

    2388047 => [
        'title'    => 'Incorrect Message Header Format',
        'detail'   => 'The message header contains an invalid format.',
        'solution' => 'Check the error message for specific information about valid formats.',
    ],

    2388072 => [
        'title'    => 'Incorrect Message Body Format',
        'detail'   => 'The message body contains an invalid format.',
        'solution' => 'Check the error message for specific information about valid formats.',
    ],

    2388073 => [
        'title'    => 'Incorrect Message Footer Format',
        'detail'   => 'The message footer contains an invalid format.',
        'solution' => 'Check the error message for specific information about valid formats.',
    ],

    2388293 => [
        'title'    => 'Parameter Word Ratio Exceeds Limit',
        'detail'   => 'This template has too many variables relative to its length. Reduce the number of variables or increase the message length.',
        'solution' => 'Check the error message for specific information about valid formats.',
    ],

    2388299 => [
        'title'    => 'Beginning or Ending Parameters Not Allowed',
        'detail'   => 'Variables cannot be at the beginning or end of the template.',
        'solution' => 'Check the error message for specific information about valid formats.',
    ],

    // -------------------------------------------------------------------------
    // Phone Migration Errors
    // -------------------------------------------------------------------------

    2388012 => [
        'title'    => 'This Phone Number Already Exists in Your Phone Number List',
        'detail'   => 'The phone number you are trying to migrate already exists in your WhatsApp account.',
        'solution' => 'Try again with a phone number that is not already in your WhatsApp account.',
    ],

    '2388091_2388093' => [
        'title'    => 'This Phone Number Isn\'t Eligible to Receive/Verify a Registration Code Since It Is Not Being Migrated',
        'detail'   => 'Phone ownership verification APIs are not available for this use case.',
        'solution' => 'Register and verify the number.',
    ],

    '2388103_webhooks' => [
        'title'    => 'Cannot Migrate Phone Number',
        'detail'   => 'Webhooks have not been set up for the destination WhatsApp Business account.',
        'solution' => 'Subscribe your app to webhooks on the destination WhatsApp Business account.',
    ],

    '2388103_register_directly' => [
        'title'    => 'Please Add This Phone Number in Your WhatsApp Account',
        'detail'   => 'This phone number is eligible to be added directly, and does not need to use phone migration APIs.',
        'solution' => 'Register and verify the number.',
    ],

    '2388103_display_name' => [
        'title'    => 'Registered Name Should Be Present and Approved',
        'detail'   => 'The business phone number must have an approved display name (name_status is APPROVED) and cannot have any associated pending display name change requests.',
        'solution' => 'Get your business phone number\'s display name approved.',
    ],

    '2388103_source_account' => [
        'title'    => 'The WhatsApp Account That This Phone Number Is Registered With Is Not Set Up Correctly',
        'detail'   => 'The source WhatsApp Business Account must be approved, and its "messaging on behalf of" must be approved.',
        'solution' => 'The WhatsApp Business Account may be using the now deprecated On-Behalf-Of ownership model. Contact support.',
    ],

    '2388103_payment_account' => [
        'title'    => 'Your WhatsApp Account Does Not Have a Payment Account',
        'detail'   => 'Your WhatsApp account must have an active credit line in order to send messages after migration.',
        'solution' => 'Set up a credit line and share it with the business customer.',
    ],

    '2388103_migration_error' => [
        'title'    => 'There Was an Error Migrating This Phone Number',
        'detail'   => 'Something went wrong when trying to migrate your phone number.',
        'solution' => 'Try again after some time. If that does not work, contact support.',
    ],

    '2388103_different_business' => [
        'title'    => 'This Phone Number Belongs to a Different Business Manager Account',
        'detail'   => 'The source and destination WABAs must represent the same business.',
        'solution' => 'Migrate the phone number into a WhatsApp Business Account that is messaging for the same business as the source WhatsApp Business Account.',
    ],

    '2388103_destination_approval' => [
        'title'    => 'Your WhatsApp Account Must Be Approved',
        'detail'   => 'The destination WhatsApp Business Account must be approved before you can migrate phone numbers.',
        'solution' => 'Ensure business verification is completed, and the WhatsApp Business Account review status is approved.',
    ],

    '2388103_messaging_for' => [
        'title'    => 'Your WhatsApp Account\'s "Messaging For" Request Must Be Approved',
        'detail'   => 'The destination WhatsApp Business Account "Messaging For" request must be approved by the client.',
        'solution' => 'Ask your client to accept your "Messaging For" request in the Meta Business Suite.',
    ],

    2494100 => [
        'title'    => 'Account in Maintenance Mode',
        'detail'   => 'The business phone number is in maintenance mode.',
        'solution' => 'Retry in a few minutes.',
    ],

    // -------------------------------------------------------------------------
    // Template Analytics Errors
    // -------------------------------------------------------------------------

    200005 => [
        'title'    => 'Template Analytics Unavailable',
        'detail'   => 'Template analytics are not yet available for this WhatsApp Business Account.',
        'solution' => 'You cannot enable template analytics for this WhatsApp Business Account at this time.',
    ],

    200006 => [
        'title'    => 'Cannot Disable Template Analytics',
        'detail'   => 'Invalid operation. Template analytics cannot be disabled once enabled.',
        'solution' => 'Template analytics cannot be disabled once activated on a WhatsApp Business Account.',
    ],

    200007 => [
        'title'    => 'Template Analytics Not Enabled',
        'detail'   => 'Template analytics are not enabled for this WhatsApp Business Account.',
        'solution' => 'To enable template analytics, see Confirm Template Analytics.',
    ],

    // -------------------------------------------------------------------------
    // WhatsApp Business Account Errors
    // -------------------------------------------------------------------------

    2593079 => [
        'title'    => 'WABA Already Flagged for Migration',
        'detail'   => 'This WABA has already been flagged for migration to a different solution ID.',
        'solution' => 'The OBO account ownership model is deprecated. Contact the support team.',
    ],

    2593085 => [
        'title'    => 'WhatsApp Business Account Not Eligible for OBO Mobility',
        'detail'   => 'The WABA is not eligible for OBO ownership transfer. Possible reasons: WABA is already owned by the business customer (uses the WABA sharing model), or the business customer has not yet accepted the OBO request in Meta Business Suite.',
        'solution' => 'Note that the OBO account ownership model is deprecated. Contact the support team.',
    ],

    // -------------------------------------------------------------------------
    // Sync Errors
    // -------------------------------------------------------------------------

    2593107 => [
        'title'    => 'Sync Request Rate Limit Exceeded',
        'detail'   => 'You have exceeded the maximum number of sync API calls for this phone number.',
        'solution' => 'You can only call this endpoint once to sync contacts and once to sync message history. See Business App User Registration. Delete the business customer and re-register them.',
    ],

    2593108 => [
        'title'    => 'Sync Request Made Outside Allowed Time Window',
        'detail'   => 'The sync request can only be made within 24 hours of registration.',
        'solution' => 'You can only initiate contact and message history sync for a registered WhatsApp Business App user within 24 hours of registration. Delete the user and re-register them.',
    ],

    // -------------------------------------------------------------------------
    // Marketing API Errors
    // -------------------------------------------------------------------------

    131055 => [
        'title'    => 'Method Not Allowed',
        'detail'   => 'Only marketing template messages are supported.',
        'solution' => 'Resend with a marketing message template.',
    ],

    134100 => [
        'title'    => 'Only Marketing Messages Supported',
        'detail'   => 'You are only able to send marketing messages on this API.',
        'solution' => 'Use a MARKETING category template. Available from Graph API v23.0.',
    ],

    134101 => [
        'title'    => 'Template Is Still Syncing',
        'detail'   => 'When you send a message from a template, the template syncing process can take up to 10 minutes to complete.',
        'solution' => 'Wait a few minutes and then try sending your message again. Available from Graph API v23.0.',
    ],

    134102 => [
        'title'    => 'Template Unavailable for Use',
        'detail'   => 'Ad sync for the template could not be completed, or you may not be eligible for the WhatsApp marketing messages API.',
        'solution' => 'Check your eligibility status. If marketing_messages_lite_api_status is ONBOARDED and the issue persists, contact support. Available from Graph API v23.0.',
    ],

    1752041 => [
        'title'    => 'Duplicate Request',
        'detail'   => 'Duplicate request thrown when a client has already been invited to onboard by any partner.',
        'solution' => 'Onboarding requests are limited to one per business customer. If you receive this error, all eligible WABAs for that client will be registered automatically without further action.',
    ],

];
