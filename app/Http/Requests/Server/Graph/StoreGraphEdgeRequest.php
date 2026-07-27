<?php

namespace App\Http\Requests\Server\Graph;

use App\Support\Graph\GraphEdgeExplorer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'source_type' => ['required', 'string', 'in:space,thread,post'],
            'source_id' => ['required', 'string'],
            'target_type' => ['required', 'string', 'in:space,thread,post'],
            'target_id' => ['required', 'string'],
            'edge_type' => ['required', 'string', 'max:100', Rule::notIn(GraphEdgeExplorer::ReservedEdgeTypes)],
            'purpose' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_type.in' => 'The source type must be space, thread, or post.',
            'target_type.in' => 'The target type must be space, thread, or post.',
            'edge_type.in' => 'The edge type is not supported.',
        ];
    }
}
