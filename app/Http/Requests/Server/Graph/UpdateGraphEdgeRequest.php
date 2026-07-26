<?php

namespace App\Http\Requests\Server\Graph;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateGraphEdgeRequest extends FormRequest
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
            'edge_type' => ['sometimes', 'string', 'in:related_to,references,depends_on,blocks,derived_from,child_of'],
            'purpose' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->has('edge_type') && ! $this->has('purpose')) {
                    $validator->errors()->add('edge', 'At least one edge attribute is required.');
                }
            },
        ];
    }
}
