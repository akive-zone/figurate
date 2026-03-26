<?php

namespace App\Http\Requests\Server\Graph;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreGraphEdgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source_type' => ['required', 'string', 'in:space,thread'],
            'source_id' => ['required', 'uuid'],
            'target_type' => ['required', 'string', 'in:space,thread'],
            'target_id' => ['required', 'uuid'],
            'edge_type' => ['required', 'string', 'in:related_to,references,depends_on,blocks,derived_from,child_of'],
            'purpose' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_type.in' => 'The source type must be either space or thread.',
            'target_type.in' => 'The target type must be either space or thread.',
            'edge_type.in' => 'The edge type is not supported.',
        ];
    }
}
