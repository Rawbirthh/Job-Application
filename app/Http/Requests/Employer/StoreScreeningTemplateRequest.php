<?php

namespace App\Http\Requests\Employer;

use App\Models\ScreeningQuestion;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreScreeningTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
        if (is_string($this->input('description'))) {
            $desc = trim($this->input('description'));
            $this->merge(['description' => $desc === '' ? null : $desc]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'question_ids' => ['required', 'array', 'min:1', 'max:50'],
            'question_ids.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ids = $this->input('question_ids');

            if (! is_array($ids)) {
                return;
            }

            if (count($ids) !== count(array_unique($ids))) {
                $validator->errors()->add('question_ids', __('Duplicate questions are not allowed in a template.'));

                return;
            }

            /** @var User|null $user */
            $user = $this->user();
            if (! $user) {
                return;
            }

            $ownedIds = ScreeningQuestion::query()
                ->where('owner_user_id', $user->id)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->all();

            if (count($ownedIds) !== count($ids)) {
                $validator->errors()->add('question_ids', __('One or more selected questions are invalid.'));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function templateData(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ];
    }

    /**
     * @return array<int, int>
     */
    public function questionIds(): array
    {
        return array_values($this->validated()['question_ids']);
    }
}
