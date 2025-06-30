
---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="03-messages.md" title="Previous section">◄◄ Message Management</a>
    </td>
    <td align="center">
      <a href="00-content.md" title="Table of contents">▲ Table of contents</a>
    </td>
    <td align="right">
      <a href="05-events.md" title="Next section">Events ►►</a>
    </td>
  </tr>
</table>
</div>

<div align="center">
<sub>WhatsApp Manager Webhook Documentation | 
<a href="https://github.com/djdang3r/whatsapp-api-manager">View on GitHub</a></sub>
</div>

---

## 📋 Template Management

### Introduction
The template module provides comprehensive tools to create, manage, and send messages based on WhatsApp-approved templates. Templates are essential for automated communications like notifications, promotions, and transactional messages, allowing you to maintain consistency in your communication while complying with WhatsApp's policies.

**Key features:**
- Template creation for different categories (utility, marketing)
- Version and component management (headers, bodies, footers, buttons)
- Synchronization with WhatsApp API
- Bulk sending of template-based messages
- Advanced editing with real-time validation

### 📚 Table of Contents
1. Template Administration
    - Get all templates
    - Get by name
    - Get by ID
    - Delete templates
    - Soft delete
    - Hard delete

2. Edit Templates
    - Component management
    - Validations
    - Error handling

3. Create Templates
    - Utility templates
    - Marketing templates
    - With images
    - With buttons
    - Variations

4. Send Messages with Templates

## Template Administration

- **Get all templates for a WhatsApp business account**
    Retrieves all templates for a WhatsApp business account and stores them in the database.
    Makes a request to the WhatsApp API to get all templates associated with the account.

    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

    // Get a WhatsApp Business Account instance
    $account = WhatsappBusinessAccount::find($accountId);

    // Get all templates for the account
    Whatsapp::template()->getTemplates($account);
    ```

- **Get a template by name**
    Makes a request to the WhatsApp API to get a template by name and stores it in the database.

    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

    // Get a WhatsApp Business Account instance
    $account = WhatsappBusinessAccount::find($accountId);

    // Get template by name
    $template = Whatsapp::template()->getTemplateByName($account, 'order_confirmation');
    ```

- **Get a template by ID**
    Makes a request to the WhatsApp API to get a template by ID and stores it in the database.

    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

    // Get a WhatsApp Business Account instance
    $account = WhatsappBusinessAccount::find($accountId);

    // Get template by ID
    $template = Whatsapp::template()->getTemplateById($account, '559947779843204');
    ```

- **Delete template from API and database simultaneously**
    Makes a request to the WhatsApp API to delete the selected template. There are two deletion methods: Soft Delete and Hard Delete.

    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

    // Get a WhatsApp Business Account instance
    $account = WhatsappBusinessAccount::find($accountId);

    // Soft delete
    // Delete template by ID
    $template = Whatsapp::template()->deleteTemplateById($account, $templateId);

    // Delete template by name
    $template = Whatsapp::template()->deleteTemplateByName($account, 'order_confirmation');


    // Hard delete
    // Delete template by ID
    $template = Whatsapp::template()->deleteTemplateById($account, $templateId, true);

    // Delete template by name
    $template = Whatsapp::template()->deleteTemplateByName($account, 'order_confirmation', true);
    ```

- **Edit template in API and database simultaneously**
    Makes a request to the WhatsApp API to edit the selected template.

    ```php
    use ScriptDevelop\WhatsappManager\Models\Template;
    use ScriptDevelop\WhatsappManager\Exceptions\TemplateComponentException;
    use ScriptDevelop\WhatsappManager\Exceptions\TemplateUpdateException;

    $template = Template::find('template-id');

    try {
        $updatedTemplate = $template->edit()
            ->setName('new-template-name')
            ->changeBody('New body content {{1}}', [['New example']])
            ->removeHeader()
            ->addFooter('New footer text')
            ->removeAllButtons()
            ->addButton('URL', 'Visit site', 'https://mpago.li/2qe5G7E')
            ->addButton('QUICK_REPLY', 'Confirm')
            ->update();
        
        return response()->json($updatedTemplate);
        
    } catch (TemplateComponentException $e) {
        // Handle component error
        return response()->json(['error' => $e->getMessage()], 400);
        
    } catch (TemplateUpdateException $e) {
        // Handle update error
        return response()->json(['error' => $e->getMessage()], 500);
    }
    ```

    **Add components to templates that didn't have them:**

    ```php
    $template->edit()
        ->addHeader('TEXT', 'Added header')
        ->addFooter('New footer')
        ->addButton('PHONE_NUMBER', 'Call', '+1234567890')
        ->update();
    ```

    **Remove existing components:**
    
    ```php
    $template->edit()
        ->removeFooter()
        ->removeAllButtons()
        ->update();
    ```

    **Work with specific components:**
    
    ```php
    $editor = $template->edit();

    // Check and modify header
    if ($editor->hasHeader()) {
        $headerData = $editor->getHeader();
        if ($headerData['format'] === 'TEXT') {
            $editor->changeHeader('TEXT', 'Updated header');
        }
    } else {
        $editor->addHeader('TEXT', 'New header');
    }

    // Modify buttons
    $buttons = $editor->getButtons();
    foreach ($buttons as $index => $button) {
        if ($button['type'] === 'URL' && str_contains($button['url'], 'old-domain.com')) {
            $newUrl = str_replace('old-domain.com', 'new-domain.com', $button['url']);
            $editor->removeButtonAt($index);
            $editor->addButton('URL', $button['text'], $newUrl);
        }
    }

    $editor->update();
    ```

## Key Features of Edit Template

    1. Comprehensive component management:
        - Add, change, remove methods for each component type
        - Has methods to check existence
        - Get methods to retrieve data

    2. Robust validations:
        - Component uniqueness (only one HEADER, BODY, etc.)
        - Required components (BODY always required)
        - Button limits (maximum 10)
        - Modification restrictions (cannot change category, cannot modify approved templates)

    3. Atomic operations:
        - removeButtonAt: Deletes a specific button
        - removeAllButtons: Deletes all buttons
        - getButtons: Gets all current buttons

    4. Error handling:
        - Specific exceptions for component issues
        - Exceptions for update failures
        - Clear and descriptive error messages

    5. Intuitive workflow:
        - $template->edit() starts editing
        - Method chaining for modifications
        - update() applies changes

## ❤️ Support us with a GitHub Sponsors donation

You can support me as an open source developer on GitHub Sponsors:
- If this project has been useful to you, you can support it with a donation through
[![Sponsor](https://img.shields.io/badge/Sponsor%20me-GitHub-blue?style=for-the-badge&logo=github)](https://github.com/sponsors/djdang3r)

- Or via Mercadopago Colombia:
[![Donate via Mercado Pago](https://img.shields.io/badge/Donate%20via-Mercado%20Pago-blue?style=for-the-badge&logo=mercadopago)](https://mpago.li/2qe5G7E)
Thank you for your support 💙
---

## Create Templates in a WhatsApp Account
- ### Create Utility Templates

    Transactional templates are ideal for notifications like order confirmations, shipping updates, etc.

    ![Marketing template example](../../assets/template_1.png "Marketing Template")

    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

    // Get business account
    $account = WhatsappBusinessAccount::first();

    // Create a transactional template
    $template = Whatsapp::template()
        ->createUtilityTemplate($account)
        ->setName('order_confirmation_3')
        ->setLanguage('en_US')
        ->addHeader('TEXT', 'Order Confirmation')
        ->addBody('Your order {{1}} has been confirmed.', ['12345'])
        ->addFooter('Thank you for shopping with us!')
        ->addButton('QUICK_REPLY', 'Track Order')
        ->addButton('QUICK_REPLY', 'Contact Support')
        ->save();
    ```
---

  - ### Create Marketing Templates

    Marketing templates are useful for promotions, discounts, and mass campaigns.

    ![Marketing template example](../../assets/template_2.png "Marketing Template")

    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

    // Get business account
    $account = WhatsappBusinessAccount::first();

    // Create marketing template with text
    $template = Whatsapp::template()
        ->createMarketingTemplate($account)
        ->setName('personal_promotion_text_only')
        ->setLanguage('en')
        ->addHeader('TEXT', 'Our {{1}} is on!', ['Summer Sale'])
        ->addBody(
            'Shop now through {{1}} and use code {{2}} to get {{3}} off of all merchandise.',
            ['the end of August', '25OFF', '25%']
        )
        ->addFooter('Use the buttons below to manage your marketing subscriptions')
        ->addButton('QUICK_REPLY', 'Unsubscribe from Promos')
        ->addButton('QUICK_REPLY', 'Unsubscribe from All')
        ->save();
    ```

---

  - ### Create Marketing Templates with Images

    Marketing templates can also include images in the header to make them more attractive.

    ![Marketing template example](../../assets/template_3.png "Marketing Template")

    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

    // Get business account
    $account = WhatsappBusinessAccount::first();

    // Image path
    $imagePath = storage_path('app/public/laravel-whatsapp-manager.png');

    // Create marketing template with image
    $template = Whatsapp::template()
        ->createMarketingTemplate($account)
        ->setName('image_template_test')
        ->setLanguage('en_US')
        ->setCategory('MARKETING')
        ->addHeader('IMAGE', $imagePath)
        ->addBody('Hi {{1}}, your order {{2}} has been shipped!', ['John', '12345'])
        ->addFooter('Thank you for your purchase!')
        ->save();
    ```

---

- ### Create Marketing Templates with URL Buttons

    You can add custom URL buttons to redirect users to specific pages.

    ![Marketing template example](../../assets/template_3.png "Marketing Template")

    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

    // Get business account
    $account = WhatsappBusinessAccount::first();

    // Image path
    $imagePath = storage_path('app/public/laravel-whatsapp-manager.png');

    // Create marketing template with image and URL buttons
    $template = Whatsapp::template()
        ->createMarketingTemplate($account)
        ->setName('image_template_test_2')
        ->setLanguage('en_US')
        ->setCategory('MARKETING')
        ->addHeader('IMAGE', $imagePath)
        ->addBody('Hi {{1}}, your order {{2}} has been shipped!', ['John', '12345'])
        ->addFooter('Thank you for your purchase!')
        ->addButton('PHONE_NUMBER', 'Call Us', '+573234255686')
        ->addButton('URL', 'Track Order', 'https://mpago.li/{{1}}', ['2qe5G7E'])
        ->save();
    ```
---

- ### Create Marketing Template Variations

    You can create multiple template variations for different purposes.

    ![Marketing template example](../../assets/template_4.png "Marketing Template")

    ```php
        use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
        use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

        // Get business account
        $account = WhatsappBusinessAccount::first();

        // Create marketing template variation
        $template = Whatsapp::template()
            ->createMarketingTemplate($account)
            ->setName('personal_promotion_text_only_22')
            ->setLanguage('en')
            ->addHeader('TEXT', 'Our {{1}} is on!', ['Summer Sale'])
            ->addBody(
                'Shop now through {{1}} and use code {{2}} to get {{3}} off of all merchandise.',
                ['the end of August', '25OFF', '25%']
            )
            ->addFooter('Use the buttons below to manage your marketing subscriptions')
            ->addButton('QUICK_REPLY', 'Unsubscribe from Promos')
            ->addButton('QUICK_REPLY', 'Unsubscribe from All')
            ->save();
    ```
    # Notes

    - Verify that images used in templates comply with WhatsApp API requirements: format (JPEG, PNG), maximum allowed size, and recommended dimensions.
    - URL-type buttons can accept dynamic parameters through template variables (`{{1}}`, `{{2}}`, etc.), allowing link personalization for each recipient.
    - If you experience issues creating templates, check the log files for detailed information about possible errors and their solutions.

---
## ❤️ Support us with a GitHub Sponsors donation

You can support me as an open source developer on GitHub Sponsors:
- If this project has been useful to you, you can support it with a donation through
[![Sponsor](https://img.shields.io/badge/Sponsor%20me-GitHub-blue?style=for-the-badge&logo=github)](https://github.com/sponsors/djdang3r)

- Or via Mercadopago Colombia:
[![Donate via Mercado Pago](https://img.shields.io/badge/Donate%20via-Mercado%20Pago-blue?style=for-the-badge&logo=mercadopago)](https://mpago.li/2qe5G7E)
Thank you for your support 💙
---

## Send Messages Using Templates
  - ### Send template messages

    You can send different template messages based on the template structure.

    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;
    use ScriptDevelop\WhatsappManager\Models\WhatsappPhoneNumber;

    // Get business account
    $account = WhatsappBusinessAccount::first();
    $phone = WhatsappPhoneNumber::first();

    // Send template 1
    $message = Whatsapp::template()
        ->sendTemplateMessage($phone)
        ->to('57', '3137555908')
        ->usingTemplate('order_confirmation_4')
        ->addBody(['12345'])
        ->send();

    // Send template 2

    $message = Whatsapp::template()
        ->sendTemplateMessage($phone)
        ->to('57', '3135666627')
        ->usingTemplate('payment_link')
        ->addHeader('TEXT', '123456')
        ->addBody(['20000'])
        ->addButton('URL', 'Pay', '1QFwRV', ['[https://mpago.li/1QFwRV]'])
        ->send();

    $message = Whatsapp::template()
        ->sendTemplateMessage($phone)
        ->to('57', '3135666627')
        ->usingTemplate('payment_link')
        ->addHeader('TEXT', '123456')
        ->addBody(['20000'])
        ->addButton(
            'URL', // Button type
            'Pay', // Button text
            '1QFwRV', // Button variable (URL type only)
            ['[https://mpago.li/1QFwRV]'] // Example URL (not sent, used as reference)
        )
        ->send();
    ```

---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="03-messages.md" title="Previous section">◄◄ Message Management</a>
    </td>
    <td align="center">
      <a href="00-content.md" title="Table of contents">▲ Table of contents</a>
    </td>
    <td align="right">
      <a href="05-events.md" title="Next section">Events ►►</a>
    </td>
  </tr>
</table>
</div>

<div align="center">
<sub>WhatsApp Manager Webhook Documentation | 
<a href="https://github.com/djdang3r/whatsapp-api-manager">View on GitHub</a></sub>
</div>

---

## ❤️ Support

If you find this project useful, consider supporting its development:

[![GitHub Sponsors](https://img.shields.io/badge/Sponsor%20me-GitHub-blue?style=for-the-badge&logo=github)](https://github.com/sponsors/djdang3r)
[![Mercado Pago](https://img.shields.io/badge/Donate%20via-Mercado%20Pago-blue?style=for-the-badge&logo=mercadopago)](https://mpago.li/2qe5G7E)

## 📄 License

MIT License - See [LICENSE](LICENSE) for more details