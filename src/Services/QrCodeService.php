<?php

namespace ScriptDevelop\WhatsappManager\Services;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use ScriptDevelop\WhatsappManager\Support\WhatsappModelResolver;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;

class QrCodeService
{
    protected ApiClient $apiClient;

    public function __construct(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    /**
     * Resuelve el modelo Phone a partir del ID.
     */
    protected function resolvePhone(string $phoneNumberId): Model
    {
        $phone = WhatsappModelResolver::phone_number()->find($phoneNumberId);
        if (!$phone) {
            throw new Exception("Teléfono no encontrado localmente.");
        }
        return $phone;
    }

    /**
     * Sincroniza y trae todos los códigos QR para el número de teléfono.
     * Retorna Data o Null en caso de fallo, encapsulando errores.
     */
    public function syncAll(string $phoneNumberId): ?\Illuminate\Database\Eloquent\Collection
    {
        try {
            $phone = $this->resolvePhone($phoneNumberId);
            $endpoint = Endpoints::build(Endpoints::GET_QR_CODES, ['phone_number_id' => $phone->api_phone_number_id]);

            $response = $this->apiClient->request('GET', $endpoint, headers: [
                'Authorization' => "Bearer {$phone->businessAccount->api_token}"
            ]);

            $qrs = $response['data'] ?? [];
            $qrIds = [];
            foreach ($qrs as $qr) {
                $qrIds[] = $qr['code'];
                WhatsappModelResolver::qr_code()->updateOrCreate(
                    [
                        'phone_number_id' => $phone->phone_number_id,
                        'code' => $qr['code'],
                    ],
                    [
                        'prefilled_message' => $qr['prefilled_message'] ?? null,
                        'deep_link_url' => $qr['deep_link_url'] ?? '',
                    ]
                );
            }

            // Eliminar QRs locales que ya no existan remotamente
            if (!empty($qrIds)) {
                WhatsappModelResolver::qr_code()
                    ->where('phone_number_id', $phone->phone_number_id)
                    ->whereNotIn('code', $qrIds)
                    ->delete();
            }

            return WhatsappModelResolver::qr_code()->where('phone_number_id', $phone->phone_number_id)->get();
        } catch (Exception $e) {
            Log::error("QrCodeService syncAll error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Crea un nuevo código QR en Meta y lo almacena localmente.
     */
    public function create(string $phoneNumberId, string $prefilledMessage, string $format = 'SVG'): ?Model
    {
        try {
            $phone = $this->resolvePhone($phoneNumberId);
            $endpoint = Endpoints::build(Endpoints::CREATE_QR_CODE, ['phone_number_id' => $phone->api_phone_number_id]);

            $response = $this->apiClient->request('POST', $endpoint, data: [
                'prefilled_message' => $prefilledMessage,
                'generate_qr_image' => $format
            ], headers: [
                'Authorization' => "Bearer {$phone->businessAccount->api_token}"
            ]);

            if (empty($response['code'])) {
                return null;
            }

            return WhatsappModelResolver::qr_code()->create([
                'phone_number_id' => $phone->phone_number_id,
                'code' => $response['code'],
                'prefilled_message' => $response['prefilled_message'] ?? $prefilledMessage,
                'deep_link_url' => $response['deep_link_url'] ?? '',
                'qr_image_url' => $response['qr_image_url'] ?? null,
            ]);
        } catch (Exception $e) {
            Log::error("QrCodeService create error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene un código QR específico con su imagen generada.
     */
    public function get(string $phoneNumberId, string $code, string $format = 'SVG'): ?Model
    {
        try {
            $phone = $this->resolvePhone($phoneNumberId);
            $endpoint = Endpoints::build(Endpoints::GET_QR_CODE, [
                'phone_number_id' => $phone->api_phone_number_id,
                'qr_code_id' => $code
            ]) . "?fields=prefilled_message,deep_link_url,qr_image_url.format($format)";

            $response = $this->apiClient->request('GET', $endpoint, headers: [
                'Authorization' => "Bearer {$phone->businessAccount->api_token}"
            ]);

            if (empty($response['data'][0])) {
                return null;
            }

            $data = $response['data'][0];

            return WhatsappModelResolver::qr_code()->updateOrCreate(
                [
                    'phone_number_id' => $phone->phone_number_id,
                    'code' => $code,
                ],
                [
                    'prefilled_message' => $data['prefilled_message'] ?? null,
                    'deep_link_url' => $data['deep_link_url'] ?? '',
                    'qr_image_url' => $data['qr_image_url'] ?? null,
                ]
            );
        } catch (Exception $e) {
            Log::error("QrCodeService get error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Actualiza el mensaje predefinido de un código QR.
     */
    public function update(string $phoneNumberId, string $code, string $prefilledMessage): ?Model
    {
        try {
            $phone = $this->resolvePhone($phoneNumberId);
            $endpoint = Endpoints::build(Endpoints::UPDATE_QR_CODE, ['phone_number_id' => $phone->api_phone_number_id]);

            $response = $this->apiClient->request('POST', $endpoint, data: [
                'code' => $code,
                'prefilled_message' => $prefilledMessage
            ], headers: [
                'Authorization' => "Bearer {$phone->businessAccount->api_token}"
            ]);

            if (empty($response['code'])) {
                return null;
            }

            $qrModel = WhatsappModelResolver::qr_code()
                            ->where('phone_number_id', $phone->phone_number_id)
                            ->where('code', $code)
                            ->first();

            if ($qrModel) {
                $qrModel->update([
                    'prefilled_message' => $response['prefilled_message'] ?? $prefilledMessage,
                    'deep_link_url' => $response['deep_link_url'] ?? $qrModel->deep_link_url,
                ]);
            }

            return $this->get($phoneNumberId, $code);
        } catch (Exception $e) {
            Log::error("QrCodeService update error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Elimina un código QR de Meta y localmente.
     */
    public function delete(string $phoneNumberId, string $code): bool
    {
        try {
            $phone = $this->resolvePhone($phoneNumberId);
            $endpoint = Endpoints::build(Endpoints::DELETE_QR_CODE, [
                'phone_number_id' => $phone->api_phone_number_id,
                'qr_code_id' => $code
            ]);

            $response = $this->apiClient->request('DELETE', $endpoint, headers: [
                'Authorization' => "Bearer {$phone->businessAccount->api_token}"
            ]);

            if (isset($response['success']) && $response['success']) {
                WhatsappModelResolver::qr_code()
                    ->where('phone_number_id', $phone->phone_number_id)
                    ->where('code', $code)
                    ->delete();
                return true;
            }
            return false;
        } catch (Exception $e) {
            Log::error("QrCodeService delete error: " . $e->getMessage());
            return false;
        }
    }
}
