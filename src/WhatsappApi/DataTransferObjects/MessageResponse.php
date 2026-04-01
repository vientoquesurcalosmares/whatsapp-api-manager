<?php

namespace ScriptDevelop\WhatsappManager\WhatsappApi\DataTransferObjects;

use ScriptDevelop\WhatsappManager\WhatsappApi\Exceptions\ApiException;

class MessageResponse
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $status,
        // wa_id: número de teléfono del destinatario. Puede estar ausente si se envió por BSUID
        // y el usuario tiene activada la función de nombres de usuario.
        public readonly ?string $waId = null,
        // bsuid: identificador BSUID del destinatario. Presente cuando se envió por BSUID
        // o cuando WhatsApp puede asociar el contacto a su BSUID.
        public readonly ?string $bsuid = null,
        // parentBsuid: BSUID principal, solo si el negocio tiene portfolios vinculados.
        public readonly ?string $parentBsuid = null,
        // recipientId: valor de `input` en la respuesta — puede ser teléfono o BSUID
        // según cómo se envió el mensaje.
        public readonly ?string $recipientId = null,
        public readonly ?string $timestamp = null,
        public readonly array $originalResponse = [],
        public readonly ?ApiException $error = null
    ) {}

    public static function fromApiResponse(array $data): self
    {
        // La respuesta tiene la estructura: { contacts: [{ input, wa_id?, user_id? }], messages: [{ id }] }
        $contact = $data['contacts'][0] ?? [];

        return new self(
            messageId:        $data['messages'][0]['id'] ?? $data['id'] ?? '',
            status:           $data['status'] ?? 'sent',
            waId:             $contact['wa_id'] ?? null,
            bsuid:            $contact['user_id'] ?? null,
            parentBsuid:      $contact['parent_user_id'] ?? null,
            recipientId:      $contact['input'] ?? $data['to'] ?? null,
            timestamp:        $data['timestamp'] ?? null,
            originalResponse: $data
        );
    }

    public static function fromError(ApiException $exception): self
    {
        return new self(
            messageId:        '',
            status:           'error',
            error:            $exception,
            originalResponse: $exception->getDetails()
        );
    }

    public function isSuccess(): bool
    {
        return in_array($this->status, ['sent', 'delivered', 'read', 'accepted']);
    }
}
