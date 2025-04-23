<?php

namespace ScriptDevelop\WhatsappManager\Tests\Unit\Services;

use Mockery;
use Mockery\MockInterface;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\Repositories\WhatsappBusinessAccountRepository;
use ScriptDevelop\WhatsappManager\Services\WhatsappService;
use ScriptDevelop\WhatsappManager\Tests\TestCase;

class WhatsappServiceTest extends TestCase
{
    /** @test */
    public function it_sends_a_text_message()
    {
        // Mock ApiClient con parámetros del constructor
        /** @var ApiClient&MockInterface $mockApiClient */
        $mockApiClient = Mockery::mock(ApiClient::class, [
            'https://graph.facebook.com', // $baseUrl
            'v19.0'                       // $version
        ]);
        
        $mockApiClient->shouldReceive('request')
            ->once()
            ->withArgs([
                'POST',
                '{phone_number_id}/messages',
                ['phone_number_id' => '123456'],
                [
                    'messaging_product' => 'whatsapp',
                    'to' => '+123456789',
                    'type' => 'text',
                    'text' => ['body' => 'Hola Mundo']
                ],
                ['Authorization' => 'Bearer fake_token']
            ])
            ->andReturn(['messages' => [['id' => 'wamid.XXX']]]);

        // Mock Repository
        /** @var WhatsappBusinessAccountRepository&MockInterface $mockRepo */
        $mockRepo = Mockery::mock(WhatsappBusinessAccountRepository::class);
        $mockRepo->shouldReceive('find')
            ->with('test_123')
            ->andReturn(new \ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount([
                'whatsapp_business_id' => 'test_123',
                'phone_number_id' => '123456',
                'api_token' => 'fake_token'
            ]));

        // Instanciar servicio
        $service = new WhatsappService($mockApiClient, $mockRepo);

        // Ejecutar prueba
        $response = $service->forAccount('test_123')->sendTextMessage('+123456789', 'Hola Mundo');

        $this->assertArrayHasKey('messages', $response);
    }
}