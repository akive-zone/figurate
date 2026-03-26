<?php

namespace App\Http\Requests\Server\Graph;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class QueryGraphEdgesRequest extends FormRequest
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
            'node_type' => ['required', 'string', 'in:space,thread'],
            'node_id' => ['required', 'uuid'],
            'direction' => ['nullable', 'string', 'in:outgoing,incoming,both'],
            'edge_type' => ['nullable', 'string', 'in:related_to,references,depends_on,blocks,derived_from,child_of'],
            'target_type' => ['nullable', 'string', 'in:space,thread'],
            'depth' => ['nullable', 'integer', 'min:1', 'max:5'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'node_type.in' => 'The node type must be either space or thread.',
            'direction.in' => 'The direction must be outgoing, incoming, or both.',
            'edge_type.in' => 'The edge type is not supported.',
            'target_type.in' => 'The target type must be either space or thread.',
        ];
    }
}
