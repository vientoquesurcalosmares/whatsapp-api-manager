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
        'profile_picture_url' => 'nullable|array',
        'profile_picture_url.url' => 'required_with:profile_picture_url|url|max:512',
        'vertical' => 'nullable|string|in:UNDEFINED,OTHER,PROFESSIONAL_SERVICES',
        'websites' => 'nullable|array',
        'websites.*.url' => 'required|url|max:512',
        'websites.*.type' => 'nullable|string|in:WEB,MOBILE_APP'
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
            'profile_picture_url' => $data['profile_picture_url']['url'] ?? null,
            'vertical' => $data['vertical'] ?? 'OTHER',
            'websites' => $this->parseWebsites($data['websites'] ?? [])
        ];
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