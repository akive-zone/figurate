<?php

namespace App\Http\Requests\Server\Thread;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreThreadRequest extends FormRequest
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
            'parent' => ['required', 'array:type,id'],
            'parent.type' => ['required', 'string', 'in:space,thread'],
            'parent.id' => ['required', 'string'],
            'attributes' => ['required', 'array:title,purpose,phase,status'],
            'attributes.title' => ['required', 'string', 'max:255'],
            'attributes.purpose' => ['nullable', 'string', 'max:50'],
            'attributes.phase' => ['nullable', 'string', 'max:100'],
            'attributes.status' => ['nullable', 'string', 'max:100'],
            'relations' => ['nullable', 'array', 'max:32'],
            'relations.*' => ['array:role,purpose,target'],
            'relations.*.role' => ['required', 'string', 'max:100'],
            'relations.*.purpose' => ['nullable', 'string', 'max:255'],
            'relations.*.target' => ['required', 'array:type,id'],
            'relations.*.target.type' => ['required', 'string', 'in:channel,space,thread,post'],
            'relations.*.target.id' => ['required', 'string'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $parentId = $this->input('parent.id');

                if (! is_string($parentId) || trim($parentId) === '') {
                    $validator->errors()->add('parent.id', 'A parent node is required.');
                }
            },
        ];
    }
}
