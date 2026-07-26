<?php

namespace App\Http\Requests\Server\Graph;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateGraphNodeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attributes' => ['required', 'array:status,title,purpose,phase,post_type,tag,text,payload,meta,occurred_at'],
            'attributes.status' => ['sometimes', 'string', 'max:100'],
            'attributes.title' => ['sometimes', 'string', 'max:255'],
            'attributes.purpose' => ['sometimes', 'string', 'max:50'],
            'attributes.phase' => ['sometimes', 'string', 'max:100'],
            'attributes.post_type' => ['sometimes', 'string', 'max:100'],
            'attributes.tag' => ['sometimes', 'nullable', 'string', 'max:100'],
            'attributes.text' => ['sometimes', 'nullable', 'string'],
            'attributes.payload' => ['sometimes', 'array'],
            'attributes.meta' => ['sometimes', 'array'],
            'attributes.occurred_at' => ['sometimes', 'date'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = (string) $this->route('type');
                $attributes = $this->input('attributes');

                if (! is_array($attributes) || $attributes === []) {
                    $validator->errors()->add('attributes', 'At least one node attribute is required.');

                    return;
                }

                $allowed = match ($type) {
                    'space' => ['status'],
                    'thread' => ['title', 'purpose', 'phase', 'status'],
                    'post' => ['post_type', 'tag', 'text', 'payload', 'meta', 'status', 'occurred_at'],
                    default => [],
                };

                foreach (array_keys($attributes) as $attribute) {
                    if (! in_array($attribute, $allowed, true)) {
                        $validator->errors()->add(
                            "attributes.{$attribute}",
                            "The {$attribute} attribute cannot be updated for a {$type} node.",
                        );
                    }
                }
            },
        ];
    }
}
