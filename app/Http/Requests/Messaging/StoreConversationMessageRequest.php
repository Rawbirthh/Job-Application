<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationMessageRequest extends FormRequest
{
    /**
     * Authorization is enforced by the controller via ConversationPolicy::send
     * (per the repo pattern where policies perform resource authorization).
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $body = $this->input('body');

        $this->merge(['body' => is_string($body) ? trim($body) : '']);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => __('A message cannot be empty.'),
            'body.max' => __('A message cannot exceed 2,000 characters.'),
        ];
    }
}
