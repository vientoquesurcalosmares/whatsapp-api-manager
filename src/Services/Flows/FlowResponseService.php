<?php

namespace ScriptDevelop\WhatsappManager\Services\Flows;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use ScriptDevelop\WhatsappManager\Models\WhatsappFlowResponse;
use ScriptDevelop\WhatsappManager\Models\WhatsappFlowSession;

class FlowResponseService
{
    /**
     * Campos del nfm_reply que NO son respuestas de usuario.
     */
    protected array $skipKeys = ['flow_token', 'version', 'screen'];

    /**
     * Pickers de media raw que Meta envía con id+file_name (ya procesados por FlowMediaService).
     * Se omiten aquí para no duplicar — la clave procesada es {field}_files.
     */
    protected array $rawMediaPickers = ['photo_picker', 'document_picker'];

    /**
     * Persiste todos los campos del nfm_reply como WhatsappFlowResponse individuales.
     * Skip: claves en $skipKeys, raw pickers, y claves vacías.
     * Los campos _files y _file (generados por FlowMediaService) se persisten con
     * el nombre original del campo (sin el sufijo).
     *
     * @return Collection<WhatsappFlowResponse>
     */
    public function saveCompletion(WhatsappFlowSession $session, array $decodedNfmReply): Collection
    {
        return $this->saveFromNfmReply($session, $decodedNfmReply, null, null);
    }

    /**
     * Persiste todos los campos del nfm_reply como WhatsappFlowResponse individuales.
     *
     * @return Collection<WhatsappFlowResponse>
     */
    public function saveFromNfmReply(
        WhatsappFlowSession $session,
        array               $decodedResponse,
        ?Model              $phoneNumber,
        ?Model              $contact
    ): Collection {
        $responseModel  = config('whatsapp.models.flow_response', WhatsappFlowResponse::class);
        $savedResponses = collect();
        $screenName     = $decodedResponse['screen'] ?? null;

        // Resolve phone/contact from session if not provided
        $phoneNumberId = $phoneNumber
            ? $phoneNumber->getKey()
            : $session->phone_number_id;

        $contactId = $contact
            ? $contact->getKey()
            : $session->contact_id;

        foreach ($decodedResponse as $fieldKey => $fieldValue) {
            // Skip campos técnicos
            if (in_array($fieldKey, $this->skipKeys, true)) {
                continue;
            }

            // Skip raw media pickers (photo_picker / document_picker)
            // ya que se procesan por FlowMediaService y quedan como {field}_files
            if (in_array($fieldKey, $this->rawMediaPickers, true)) {
                continue;
            }

            // Detectar si es una clave _files o _file (media ya procesada por FlowMediaService)
            $isFilesKey = str_ends_with($fieldKey, '_files') || str_ends_with($fieldKey, '_file');

            if ($isFilesKey) {
                // Guardar con el nombre original del campo (sin sufijo)
                $originalField = str_ends_with($fieldKey, '_files')
                    ? substr($fieldKey, 0, -6)
                    : substr($fieldKey, 0, -5);

                $fieldType    = $this->parseFieldType($originalField, $fieldValue);
                $rawValue     = is_array($fieldValue) ? json_encode($fieldValue) : (string) $fieldValue;
                $displayValue = $this->formatDisplayValue($fieldValue, $fieldType);

                try {
                    $response = $responseModel::create([
                        'session_id'      => $session->flow_session_id,
                        'screen_id'       => null,
                        'screen_name'     => $screenName,
                        'element_name'    => $originalField,
                        'response_value'  => $rawValue,
                        'raw_value'       => $rawValue,
                        'display_value'   => $displayValue,
                        'field_type'      => $fieldType,
                        'phone_number_id' => $phoneNumberId,
                        'contact_id'      => $contactId,
                        'responded_at'    => now(),
                    ]);
                    $savedResponses->push($response);
                } catch (\Throwable $e) {
                    Log::channel('whatsapp')->error(
                        "FlowResponseService: error guardando campo [{$originalField}]: " . $e->getMessage()
                    );
                }
                continue;
            }

            // Campo normal
            $fieldType    = $this->parseFieldType($fieldKey, $fieldValue);
            $rawValue     = is_array($fieldValue) ? json_encode($fieldValue) : (string) $fieldValue;
            $displayValue = $this->formatDisplayValue($fieldValue, $fieldType);

            try {
                $response = $responseModel::create([
                    'session_id'      => $session->flow_session_id,
                    'screen_id'       => null,
                    'screen_name'     => $screenName,
                    'element_name'    => $fieldKey,
                    'response_value'  => $rawValue,
                    'raw_value'       => $rawValue,
                    'display_value'   => $displayValue,
                    'field_type'      => $fieldType,
                    'phone_number_id' => $phoneNumberId,
                    'contact_id'      => $contactId,
                    'responded_at'    => now(),
                ]);
                $savedResponses->push($response);
            } catch (\Throwable $e) {
                Log::channel('whatsapp')->error(
                    "FlowResponseService: error guardando campo [{$fieldKey}]: " . $e->getMessage()
                );
            }
        }

        return $savedResponses;
    }

    /**
     * Persiste datos intermedios de data_exchange.
     * NO crea WhatsappFlowResponse — solo actualiza la sesión.
     * Los WhatsappFlowResponse se crean únicamente al completar (nfm_reply final).
     */
    public function saveIntermediate(
        WhatsappFlowSession $session,
        string $screen,
        array  $screenData
    ): void {
        try {
            $current = $session->intermediate_data ?? [];
            $current[$screen] = $screenData;

            $session->update([
                'intermediate_data' => $current,
                'current_screen'    => $screen,
            ]);
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error(
                'FlowResponseService::saveIntermediate error: ' . $e->getMessage(),
                ['session_id' => $session->flow_session_id, 'screen' => $screen]
            );
        }
    }

    /**
     * Infiere el tipo de un campo según su nombre y valor.
     *
     * - Array con mime_type → 'image' o 'document'
     * - JSON string de media → 'image' o 'document'
     * - bool → 'boolean'
     * - array de strings → 'multiselect'
     * - string fecha ISO → 'date'
     * - string numérico → 'number'
     * - default → 'text'
     */
    public function parseFieldType(string $fieldName, mixed $value): string
    {
        // Array con estructura de media (array de objetos con mime_type)
        if (is_array($value) && isset($value[0]) && is_array($value[0])) {
            $firstItem = $value[0];
            if (isset($firstItem['mime_type'])) {
                $mime = $firstItem['mime_type'];
                return str_starts_with($mime, 'image/') ? 'image' : 'document';
            }
        }

        // JSON string de media ya serializado (clave _files)
        if (is_string($value) && str_starts_with($value, '[{')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && isset($decoded[0]['mime_type'])) {
                $mime = $decoded[0]['mime_type'];
                return str_starts_with($mime, 'image/') ? 'image' : 'document';
            }
            // Array con mime
            if (is_array($decoded) && isset($decoded[0]['mime'])) {
                $mime = $decoded[0]['mime'];
                return str_starts_with($mime, 'image/') ? 'image' : 'document';
            }
        }

        // Booleano PHP
        if (is_bool($value)) {
            return 'boolean';
        }

        // Array de strings/ids → multiselect
        if (is_array($value)) {
            return 'multiselect';
        }

        $str = (string) $value;

        // Fecha ISO YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
            return 'date';
        }

        // Numérico puro (sin espacios)
        if (is_numeric($str) && !str_contains($str, ' ')) {
            return 'number';
        }

        return 'text';
    }

    /**
     * Convierte un valor raw a string legible para UI.
     *
     * - boolean → 'Sí'/'No'
     * - date → formato d/m/Y
     * - multiselect → join con coma
     * - image/document → nombre(s) de archivo
     * - null → ''
     * - resto → (string)
     */
    public function formatDisplayValue(mixed $rawValue, string $fieldType): string
    {
        if ($rawValue === null) {
            return '';
        }

        switch ($fieldType) {
            case 'boolean':
                return $rawValue ? 'Sí' : 'No';

            case 'date':
                try {
                    return Carbon::parse((string) $rawValue)->format('d/m/Y');
                } catch (\Exception $e) {
                    return (string) $rawValue;
                }

            case 'multiselect':
                if (is_array($rawValue)) {
                    return implode(', ', array_map('strval', $rawValue));
                }
                $decoded = json_decode((string) $rawValue, true);
                return is_array($decoded)
                    ? implode(', ', array_map('strval', $decoded))
                    : (string) $rawValue;

            case 'image':
            case 'document':
                if (is_array($rawValue)) {
                    $names = array_filter(array_column($rawValue, 'file_name'));
                    if (!$names) {
                        $names = array_filter(array_column($rawValue, 'original_name'));
                    }
                    return $names ? implode(', ', $names) : '[Archivo]';
                }
                $decoded = json_decode((string) $rawValue, true);
                if (is_array($decoded)) {
                    $names = array_filter(array_column($decoded, 'file_name'));
                    if (!$names) {
                        $names = array_filter(array_column($decoded, 'original_name'));
                    }
                    return $names ? implode(', ', $names) : '[Archivo]';
                }
                return '[Archivo]';

            default:
                return (string) $rawValue;
        }
    }
}
