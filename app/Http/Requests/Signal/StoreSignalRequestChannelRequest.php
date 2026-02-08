<?php

namespace App\Http\Requests\Signal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSignalRequestChannelRequest extends FormRequest
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
            'flow_type' => ['required', 'string', Rule::in(['ubuy', 'upwork', 'uber'])],
            'profile_id' => ['required_if:flow_type,ubuy', 'nullable', 'integer', 'exists:profiles,id'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'initial_message' => ['nullable', 'string', 'max:5000'],
            'contents' => ['nullable', 'array', 'max:8'],
            'contents.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,txt'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'flow_type.required' => 'Select how this request should be routed.',
            'flow_type.in' => 'Choose a valid routing mode.',
            'profile_id.required_if' => 'Select a provider for direct match mode.',
            'profile_id.exists' => 'The selected profile is not available.',
            'title.required' => 'Add a short title for your request.',
            'description.required' => 'Describe what you need help with.',
            'initial_message.max' => 'Your first message is too long.',
            'contents.max' => 'You can upload up to 8 files.',
            'contents.*.max' => 'Each file must be 10MB or smaller.',
            'contents.*.mimes' => 'Only images, PDF, DOC, DOCX, or TXT files are allowed.',
        ];
    }
}
