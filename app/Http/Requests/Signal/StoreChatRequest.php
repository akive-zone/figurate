<?php

namespace App\Http\Requests\Signal;

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
            'thread_id' => ['nullable', 'integer', 'exists:threads,id'],
            'content' => ['nullable', 'required_without:contents', 'string', 'max:5000'],
            'contents' => ['nullable', 'array', 'max:8'],
            'contents.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,txt,mp4,mov,mp3,wav,m4a'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required_without' => 'Enter a message or attach media.',
            'contents.max' => 'You can attach up to 8 files.',
            'contents.*.max' => 'Each file must be 10MB or smaller.',
            'contents.*.mimes' => 'One or more files have an unsupported type.',
        ];
    }
}
