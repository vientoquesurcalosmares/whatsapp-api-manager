<?php

namespace ScriptDevelop\WhatsappManager\Contracts;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

interface WebhookProcessorInterface
{
    public function handle(Request $request): Response|JsonResponse;
    public function verifyWebhook(Request $request, string $verifyToken): Response|JsonResponse;
    public function processIncomingMessage(Request $request): JsonResponse;
}