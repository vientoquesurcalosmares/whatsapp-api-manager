
---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="01-install.md" title="Sección anterior">◄◄ Install</a>
    </td>
    <td align="center">
      <a href="00-content.md" title="Tabla of contents">▲ Table of contents</a>
    </td>
    <td align="right">
      <a href="03-messages.md" title="Sección siguiente">Messages ►►</a>
    </td>
  </tr>
</table>
</div>

<div align="center">
<sub>Documentation of Webhook de WhatsApp Manager | 
<a href="https://github.com/djdang3r/whatsapp-api-manager">Ver en GitHub</a></sub>
</div>


---

## 🚀 🧩 API Configuration

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

>⚠️ Important:
>Ensure you configure the variables in the .env file

```sh
# Basic configuration
WHATSAPP_API_URL=https://graph.facebook.com
WHATSAPP_API_VERSION=v21.0
WHATSAPP_ACCESS_TOKEN=your-access-token-here
```

---
## 1. Business Account Registration

- **Register a business account in WhatsApp Business API.**
    Registers and synchronizes WhatsApp business accounts with their associated phone numbers.
    - Makes a request to the WhatsApp API, retrieves account data, and stores it in the database. This method gets account data, WhatsApp phone numbers associated with the account, and each phone number's profile.
    - Used to retrieve data from the API and store it in the database.
  
> ⚠️**Observations:**
> - Requires a valid access token with `whatsapp_business_management` permissions.
> - The `business_id` must be the numeric ID of your WhatsApp Business Account.

  ```php
  use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

  $account = Whatsapp::account()->register([
      'api_token' => '***********************',
      'business_id' => '1243432234423'
  ]);
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


## Register Phone Number

You can register a new phone number in your system to associate it with a WhatsApp Business account. This is useful for managing multiple numbers and receiving specific notifications for each one.

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

// Register a new phone number in your local database
$newPhone = Whatsapp::phone()->registerPhoneNumber('BUSINESS_ACCOUNT_ID', [
    'id' => 'NEW_PHONE_NUMBER_ID'
]);
```

> **Note:**
> - This process only adds the number to your local system, it does not create the number on Meta. The number must already exist in your WhatsApp Business account on Meta..
>

## Delete Phone Number

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

4. Contact Linking
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


---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="01-install.md" title="Sección anterior">◄◄ Install</a>
    </td>
    <td align="center">
      <a href="00-content.md" title="Tabla of contents">▲ Table of contents</a>
    </td>
    <td align="right">
      <a href="03-messages.md" title="Sección siguiente">Messages ►►</a>
    </td>
  </tr>
</table>
</div>

<div align="center">
<sub>Documentation of Webhook de WhatsApp Manager | 
<a href="https://github.com/djdang3r/whatsapp-api-manager">Ver en GitHub</a></sub>
</div>


---



## ❤️ Support

If you find this project useful, consider supporting its development:

[![GitHub Sponsors](https://img.shields.io/badge/Sponsor%20me-GitHub-blue?style=for-the-badge&logo=github)](https://github.com/sponsors/djdang3r)
[![Mercado Pago](https://img.shields.io/badge/Donate%20via-Mercado%20Pago-blue?style=for-the-badge&logo=mercadopago)](https://mpago.li/2qe5G7E)

## 📄 License

MIT License - See [LICENSE](LICENSE) for details