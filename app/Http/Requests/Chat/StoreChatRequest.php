<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatRequest extends FormRequest
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
            'channel' => ['nullable', 'uuid', 'exists:channels,uuid'],
            'thread' => ['nullable', 'uuid', 'exists:threads,uuid'],
            'content' => ['required', 'array'],
            'content.body' => ['nullable', 'required_without:content.attachments', 'string', 'max:5000'],
            'content.attachments' => ['nullable', 'array', 'max:8'],
            'content.attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,txt,mp4,mov,mp3,wav,m4a'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'channel.exists' => 'The selected channel was not found.',
            'thread.exists' => 'The selected thread was not found.',
            'content.body.required_without' => 'Enter a text or attach media.',
            'content.attachments.max' => 'You can attach up to 8 files.',
            'content.attachments.*.max' => 'Each file must be 10MB or smaller.',
            'content.attachments.*.mimes' => 'One or more files have an unsupported type.',
        ];
    }
}
