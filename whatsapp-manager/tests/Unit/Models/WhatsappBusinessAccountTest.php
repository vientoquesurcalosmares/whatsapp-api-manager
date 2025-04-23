<?php

namespace ScriptDevelop\WhatsappManager\Tests\Unit\Models;

use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;
use ScriptDevelop\WhatsappManager\Tests\TestCase;

class WhatsappBusinessAccountTest extends TestCase
{
    /** @test */
    public function it_creates_a_business_account()
    {
        $account = WhatsappBusinessAccount::create([
            'whatsapp_business_id' => 'test_123',
            'name' => 'Test Account',
            'api_token' => 'fake_token',
            'phone_number_id' => '123456'
        ]);

        $this->assertDatabaseHas('whatsapp_business_accounts', [
            'name' => 'Test Account',
            'phone_number_id' => '123456'
        ]);
    }
}