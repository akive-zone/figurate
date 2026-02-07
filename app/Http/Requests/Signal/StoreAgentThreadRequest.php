<?php

namespace App\Http\Requests\Signal;

use App\Models\Server\Thread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentThreadRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'phase' => ['required', 'string', 'max:60'],
            'agent_key' => ['required', 'string', Rule::in([Thread::AgentRequest, Thread::AgentOrder])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Thread title is required.',
            'phase.required' => 'Thread phase is required.',
            'agent_key.in' => 'Thread agent must be request_agent or order_agent.',
        ];
    }
}
