<?php

namespace ScriptDevelop\WhatsappManager\WhatsappApi\Validators;

use Illuminate\Support\Facades\Validator;
use ScriptDevelop\WhatsappManager\Exceptions\InvalidApiResponseException;

class BusinessProfileValidator
{
    protected array $rules = [
        'about' => 'nullable|string|max:512',
        'address' => 'nullable|string|max:256',
        'description' => 'nullable|string|max:512',
        'email' => 'nullable|email|max:128',
        'profile_picture_url' => 'nullable', // Permitir string o array
        'profile_picture_url.url' => 'required_with:profile_picture_url|url|max:512', // Solo si es array
        'vertical' => 'nullable|string|in:UNDEFINED,OTHER,PROFESSIONAL_SERVICES',
        'websites' => 'nullable|array',
        'websites.*.url' => 'required_with:websites|url|max:512',
        'websites.*.type' => 'nullable|string|in:WEB,MOBILE_APP',
        'messaging_product' => 'nullable|string'
    ];

    /**
     * @throws InvalidApiResponseException
     */
    public function validate(array $apiData): array
    {
        $validator = Validator::make($apiData, $this->rules);

        if ($validator->fails()) {
            throw InvalidApiResponseException::fromValidationError(
                $validator->errors()->first(),
                $validator->errors()->toArray()
            );
        }

        return $this->formatValidData($validator->validated());
    }

    private function formatValidData(array $data): array
    {
        return [
            'about' => $data['about'] ?? null,
            'address' => $data['address'] ?? null,
            'description' => $data['description'] ?? null,
            'email' => $data['email'] ?? null,
            'profile_picture_url' => $this->parseProfilePicture($data),
            'vertical' => $data['vertical'] ?? 'OTHER',
            'websites' => $this->parseWebsites($data['websites'] ?? []),
            'messaging_product' => $data['messaging_product'] ?? 'whatsapp'
        ];
    }

    private function parseProfilePicture(array $data): ?string
    {
        $url = $data['profile_picture_url'] ?? null;
        
        return is_array($url) 
            ? ($url['url'] ?? null)
            : $url;
    }

    private function parseWebsites(array $websites): array
    {
        return array_map(function ($website) {
            return [
                'url' => $website['url'],
                'type' => $website['type'] ?? 'WEB'
            ];
        }, $websites);
    }
}