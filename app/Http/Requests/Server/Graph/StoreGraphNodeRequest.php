<?php

namespace App\Http\Requests\Server\Graph;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreGraphNodeRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:space,thread,post'],
            'parent' => ['nullable', 'array:type,id'],
            'parent.type' => ['nullable', 'required_with:parent.id', 'string', 'in:space,thread,post'],
            'parent.id' => ['nullable', 'required_with:parent.type', 'string'],
            'attributes' => ['nullable', 'array:status,title,purpose,phase,post_type,tag,text,payload,meta,occurred_at'],
            'attributes.status' => ['nullable', 'string', 'max:100'],
            'attributes.title' => ['nullable', 'string', 'max:255'],
            'attributes.purpose' => ['nullable', 'string', 'max:50'],
            'attributes.phase' => ['nullable', 'string', 'max:100'],
            'attributes.post_type' => ['nullable', 'string', 'max:100'],
            'attributes.tag' => ['nullable', 'string', 'max:100'],
            'attributes.text' => ['nullable', 'string'],
            'attributes.payload' => ['nullable', 'array'],
            'attributes.meta' => ['nullable', 'array'],
            'attributes.occurred_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = $this->input('type');
                $parentType = $this->input('parent.type');
                $parentId = $this->input('parent.id');

                if ($type === 'space' && $parentType !== null && $parentType !== 'space') {
                    $validator->errors()->add('parent.type', 'A Space node may only be contained by another Space.');
                }

                if (in_array($type, ['thread', 'post'], true) && (! is_string($parentId) || trim($parentId) === '')) {
                    $validator->errors()->add('parent.id', 'A parent node is required.');
                }

                if ($type === 'thread' && ! in_array($parentType, ['space', 'thread'], true)) {
                    $validator->errors()->add('parent.type', 'A Thread node must be contained by a Space or Thread.');
                }

                if ($type === 'post' && ! in_array($parentType, ['space', 'thread', 'post'], true)) {
                    $validator->errors()->add('parent.type', 'A Post node must be contained by a Space, Thread, or Post.');
                }

                if ($type === 'thread' && ! is_string($this->input('attributes.title'))) {
                    $validator->errors()->add('attributes.title', 'A Thread title is required.');
                }
            },
        ];
    }
}
