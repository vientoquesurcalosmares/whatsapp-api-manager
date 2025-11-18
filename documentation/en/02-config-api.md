
---

<div align="center">
  <table>
    <tr>
      <td align="left">
        <a href="01-install.md" title="Previous section">◄◄ Installation</a>
      </td>
      <td align="center">
        <a href="00-content.md" title="Table of contents">▲ Table of contents</a>
      </td>
      <td align="right">
        <a href="03-messages.md" title="Next section: Message Management">Message Management ►►</a>
      </td>
    </tr>
  </table>
</div>

<div align="center">
  <sub>WhatsApp Manager Webhook Documentation | 
    <a href="https://github.com/djdang3r/whatsapp-api-manager">View on GitHub</a>
  </sub>
</div>

---

## 🚀 🧩 API Configuration

### Table of Contents

🚀 API Configuration

🔑 Meta Credentials

1. Business Account Registration

2. Get Phone Number Details

3. Register phone number

4. Delete phone number

5. Block, unblock and list users

6. Webhook Subscription Management

  - Manual Subscription

  - Subscription with Custom Fields

7. Country Code Configuration


### 🔑 Meta Credentials
To integrate your application with WhatsApp Business API, you need to configure Meta credentials in your environment:

### Essential Requirements

1. Access Token: Access token with permissions:
    - whatsapp_business_management
    - whatsapp_business_messaging
    - Obtained from Meta Developer Panel

2. Business Account ID: Unique ID of your business account:
    - Located at: Business Settings > Accounts > WhatsApp Accounts

3. Phone Number ID: Identifier for your WhatsApp business number:
    - Location: WhatsApp Tools > API and webhooks > Settings

> ⚠️ Important:
> Make sure to configure the variables in the .env file

```sh
# Basic configuration
WHATSAPP_API_URL=https://graph.facebook.com
WHATSAPP_API_VERSION=v21.0
WHATSAPP_ACCESS_TOKEN=your-access-token-here
```

---

## 1. Business Account Registration.

- **Register a business account in WhatsApp Business API.**
  Registers and synchronizes WhatsApp business accounts with their associated phone numbers.
  - Makes a request to the WhatsApp API, retrieves account data, and stores it in the database. This method gets account data, WhatsApp phone numbers associated with the account, and each phone number's profile.
  - Used to retrieve data from the API and store it in the database.

> ⚠️**Observations:**
> - Requires a valid access token with `whatsapp_business_management` permissions.
> - The `business_id` must be the numeric ID of your WhatsApp Business Account.

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

// When registering an account, configured webhooks are automatically subscribed
$account = Whatsapp::account()->register([
    'api_token' => '***********************',
    'business_id' => '1243432234423'
]);

// During registration it also:
// - Automatically registers all associated phone numbers
// - Subscribes configured webhooks by default
// - Configures business profiles
```

## 2. Get Phone Number Details
**Get detailed information about a registered phone number.**

- Makes a request to the WhatsApp API to get WhatsApp number details and stores them in the database. If the number already exists, it updates the information.

  Get and manage phone numbers associated with a WhatsApp Business account.

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

// Get all numbers associated with a business account (by Business ID)
$phones = Whatsapp::phone()
    ->forAccount('4621942164157') // Business ID
    ->getPhoneNumbers('4621942164157');

$phoneDetails = Whatsapp::phone()->getPhoneNumberDetails('564565346546');
```

> **Notes:**
> - Always use the **Phone Number ID** to perform operations on phone numbers.
> - The **Business ID** is used only to identify the business account.

## Register phone number

You can register a new phone number in your system to associate it with a WhatsApp Business account. This is useful for managing multiple numbers and receiving specific notifications for each one.

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

// Register a new phone number in your local database
$newPhone = Whatsapp::phone()->registerPhoneNumber('BUSINESS_ACCOUNT_ID', [
    'id' => 'NEW_PHONE_NUMBER_ID'
]);
```

- **Note:** This process only adds the number to your local system, it does not create the number on Meta. The number must already exist in your WhatsApp Business account on Meta.

---

## Delete phone number

You can delete a phone number from your system if you no longer want to manage it or receive associated notifications. This helps keep your database clean and up-to-date.

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

// Delete the phone number from your local system
Whatsapp::phone()->deletePhoneNumber('PHONE_NUMBER_ID');
```

- **Important:**  
  - Deleting a number only removes it from your local system, **it does not delete it from your Meta account**.
  - Phone Number IDs are different from Business Account IDs.
  - For webhooks to work correctly, ensure your endpoints are accessible via valid HTTPS.

---

**Summary:**
- Use these methods to synchronize and clean the phone numbers you manage locally.
- Changes here do not affect number configuration on the Meta platform, only in your application.
- Keep your webhook endpoints updated to receive notifications from active numbers.

## Block, unblock, and list WhatsApp users
With these functions you can block, unblock, and list the numbers of customers or users as you wish.

**Key Features**
- User blocking: Prevents specific numbers from sending messages to your WhatsApp Business
- User unblocking: Restores communication capability for previously blocked numbers
- Blocked list: Get paginated information of all blocked numbers
- Automatic synchronization: Keeps your database synchronized with the actual status on WhatsApp
- Contact management: Automatically links blocks with your existing contacts

```php
// Block users (with automatic formatting)
$response = Whatsapp::block()->blockUsers(
    $phone->phone_number_id,
    ['3135694227', '57 3012345678']
);

// Unblock users (with automatic retry)
$response = Whatsapp::block()->unblockUsers(
    $phone->phone_number_id,
    ['573137181908']
);

// List blocked users with pagination
$blocked = Whatsapp::block()->listBlockedUsers(
    $phone->phone_number_id,
    50,
    $cursor // Use real cursor from previous response
);
```

**Important Notes**

**1. Number Format**
Numbers are automatically normalized to international format

Conversion examples:
3135694227 → 573135694227 (for Colombia)
57 3012345678 → 573012345678
+1 (555) 123-4567 → 15551234567

**2. Error Handling**
- Pre-validation: Redundant operations are not performed
- Automatic retry: For unblock operations requiring alternative methods
- Conditional persistence: Database is only updated if the API responds successfully

**3. Pagination**
Use response cursors to navigate between pages:

```php
// First page
$page1 = Whatsapp::block()->listBlockedUsers($phoneId, 50);

// Second page
$page2 = Whatsapp::block()->listBlockedUsers(
    $phoneId,
    50,
    $page1['paging']['cursors']['after']
);
```

**4. Contact Linking**
- Contact records are automatically created if they don't exist
- Blocks are associated with the Contact model
- Marketing status updated when blocking:
  - accepts_marketing = false
  - marketing_opt_out_at = now()

**Additional Methods**

Check block status

```php
$contact = Contact::find('contact_123');
$isBlocked = $contact->isBlockedOn($phone->phone_number_id);
```

Block/Unblock from Contact model

```php
$contact->blockOn($phone->phone_number_id);
$contact->unblockOn($phone->phone_number_id);
```

# WhatsApp Webhook Subscription Management

## 🛠 Configuration

---

## 1. Manual Subscription with Default Configuration
You can override the subscription configuration using environment variables to adapt fields and parameters according to your needs. The following example shows how to manually subscribe to WhatsApp webhooks using the default configured values in your application:

```php
use ScriptDevelop\WhatsappManager\Services\WhatsappService;

$whatsappService = app(WhatsappService::class);

// Subscribe the application to webhooks using default fields
$response = $whatsappService
  ->forAccount('your_business_account_id')
  ->subscribeApp('whatsapp_business_id');

// Verify subscription result
if (isset($response['success'])) {
  echo "Subscription successful";
} else {
  echo "Subscription error: " . ($response['error']['message'] ?? 'Unknown');
}
```

This operation allows your business account to receive automatic notifications of relevant events, such as incoming messages, status updates, and number quality changes, according to the fields defined in the configuration.

---

## 2. Subscription with Custom Fields During Registration
- You can pass as a parameter the webhooks you want to subscribe your account to.

```php
use ScriptDevelop\WhatsappManager\Services\AccountRegistrationService;

$registrationService = app(AccountRegistrationService::class);

$accountData = [
    'api_token' => 'your_api_token',
    'business_id' => 'your_whatsapp_business_id',
];

// Define specific fields to subscribe during registration
$customFields = [
    'messages',                    // Only incoming messages
    'message_deliveries',          // Only deliveries
    'message_template_status_update', // Only template status
];

$account = $registrationService->register($accountData, $customFields);
```

- If not passed as parameters, the ones in the configuration file will be used by default
- In your config/whatsapp-manager.php file, configure default subscribed fields:

```php
'webhook' => [
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'processor' => \ScriptDevelop\WhatsappManager\Services\WebhookProcessors\BaseWebhookProcessor::class,
    
    // Default subscribed fields
    'subscribed_fields' => [
        'messages',                         // Incoming messages
        'message_deliveries',              // Delivery confirmations
        'message_reads',                   // Read confirmations
        'message_template_status_update',  // Template status
        'phone_number_quality_update',     // Number quality
        'account_update',                  // Account updates
        'account_review_update',           // Account reviews
        'business_capability_update',      // Business capabilities
        'flows',                           // WhatsApp flows
    ],
],
```

---
# Country Code Configuration

The package includes a flexible system for managing country codes that is used during phone number registration to correctly extract the country code and local number.

## Basic Configuration

In your config/whatsapp-manager.php file, you can add custom country codes:

```php
'custom_country_codes' => [
    // Add custom country codes here
    // Format: 'numeric_code' => 'alpha_2_code'
    '57' => 'CO',  // Colombia
    '1'  => 'US',  // United States
    '52' => 'MX',  // Mexico
    '34' => 'ES',  // Spain
    '54' => 'AR',  // Argentina
    '55' => 'BR',  // Brazil
    '56' => 'CL',  // Chile
    '51' => 'PE',  // Peru
    '58' => 'VE',  // Venezuela
    '593' => 'EC', // Ecuador
    '507' => 'PA', // Panama
    '506' => 'CR', // Costa Rica
    '502' => 'GT', // Guatemala
    '503' => 'SV', // El Salvador
    '504' => 'HN', // Honduras
    '505' => 'NI', // Nicaragua
    '507' => 'PA', // Panama
    '598' => 'UY', // Uruguay
    '595' => 'PY', // Paraguay
    '591' => 'BO', // Bolivia
    '53' => 'CU',  // Cuba
    '1809' => 'DO', // Dominican Republic
    '1829' => 'DO', // Dominican Republic
    '1849' => 'DO', // Dominican Republic
],
```

---

```php
// First set the account with forAccount()
Whatsapp::account()->forAccount('1243432234423');


$account = Whatsapp::account()->register([
    'api_token' => 'EAAKt6D2DgZCMBPhMmgtjmnhvUa8O7rZA5zxWxU8UXso07zgugZAJwScJOd3KwHAOZAcnSdSi8wjZCPvVd33vk0ikI8kZBbxvjBN4nP7j5BF1dJiqHCQH9ER1kRFZClpiAOcGasebw8S08yDvwCarUSZCr6YJxojUgZDZD',
    'business_id' => '747336830188'
]);

// First set the account
// Set account first (IMPORTANT)
Whatsapp::account()->forAccount('747336830188');

// Subscribe application (uses default configuration fields)
$response = Whatsapp::account()->subscribeApp();

// Subscribe with specific fields
$response = Whatsapp::account()->subscribeApp([
    'messages',
    'message_deliveries', 
    'message_reads',
    'message_template_status_update'
]);

// Get subscribed applications
$subscribedApps = Whatsapp::account()->subscribedApps();

// Cancel subscription
$response = Whatsapp::account()->unsubscribeApp();
```

---

<div align="center">
  <table>
    <tr>
      <td align="left">
        <a href="01-install.md" title="Previous section: Installation">◄◄ Installation</a>
      </td>
      <td align="center">
        <a href="00-content.md" title="Table of contents">▲ Table of contents</a>
      </td>
      <td align="right">
        <a href="03-messages.md" title="Next section: Message Management">Message Management ►►</a>
      </td>
    </tr>
  </table>
</div>

<div align="center">
  <sub>WhatsApp Manager Webhook Documentation | 
    <a href="https://github.com/djdang3r/whatsapp-api-manager">View on GitHub</a>
  </sub>
</div>

---

## ❤️ Support

If you find this project useful, consider supporting its development:

[![GitHub Sponsors](https://img.shields.io/badge/Sponsor%20me-GitHub-blue?style=for-the-badge&logo=github)](https://github.com/sponsors/djdang3r)
[![Mercado Pago](https://img.shields.io/badge/Donate%20via-Mercado%20Pago-blue?style=for-the-badge&logo=mercadopago)](https://mpago.li/2qe5G7E)

## 📄 License

MIT License - See [LICENSE](LICENSE) for details
