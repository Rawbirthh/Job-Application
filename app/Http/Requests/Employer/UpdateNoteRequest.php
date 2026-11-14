<?php

namespace App\Http\Requests\Employer;

use App\Models\ApplicationNote;
use App\Models\JobApplication;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNoteRequest extends FormRequest
{
    private ?ApplicationNote $resolvedNote = null;

    public function authorize(): bool
    {
        $application = $this->route('application');

        if (! $application instanceof JobApplication) {
            return false;
        }

        $noteId = $this->route('note');
        $note = ApplicationNote::query()
            ->where('application_id', $application->id)
            ->find(is_numeric($noteId) ? (int) $noteId : 0);

        if ($note === null) {
            // Guessed or mismatched {note} (incl. soft-deleted): 404, not 403.
            abort(404);
        }

        $this->resolvedNote = $note;

        return $this->user()?->can('update', $note) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $body = $this->input('body');

        $this->merge(['body' => is_string($body) ? trim($body) : '']);
    }

    /**
     * The note resolved and scoped to the route application during authorization.
     */
    public function note(): ApplicationNote
    {
        if ($this->resolvedNote === null) {
            throw new ModelNotFoundException;
        }

        return $this->resolvedNote;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => __('A note cannot be empty.'),
            'body.max' => __('A note cannot exceed 5,000 characters.'),
        ];
    }
}
