<?php

namespace App\Http\Requests\Server\Form;

use App\Models\Server\Post;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest as BaseFormRequest;
use Illuminate\Validation\Validator;

class FormRequest extends BaseFormRequest
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
            'body' => ['required', 'array:type,id,parent,attributes,relations'],
            'body.type' => ['required', 'string', 'in:space,thread,post'],
            'body.id' => ['nullable', 'string'],
            'body.parent' => ['nullable', 'array:type,id'],
            'body.parent.type' => ['nullable', 'required_with:body.parent.id', 'string', 'in:space,thread,post'],
            'body.parent.id' => ['nullable', 'required_with:body.parent.type', 'string'],
            'body.attributes' => ['nullable', 'array:status,title,purpose,phase,post_type,tag,text,payload,meta,occurred_at,attachments,actions,errors,extra,conversation_persistence'],
            'body.attributes.status' => ['nullable', 'string', 'max:100'],
            'body.attributes.title' => ['nullable', 'string', 'max:255'],
            'body.attributes.purpose' => ['nullable', 'string', 'max:50'],
            'body.attributes.phase' => ['nullable', 'string', 'max:100'],
            'body.attributes.post_type' => ['nullable', 'string', 'max:100'],
            'body.attributes.tag' => ['nullable', 'string', 'max:100'],
            'body.attributes.text' => ['nullable', 'string', 'max:5000'],
            'body.attributes.payload' => ['nullable', 'array'],
            'body.attributes.meta' => ['nullable', 'array'],
            'body.attributes.occurred_at' => ['nullable', 'date'],
            'body.attributes.attachments' => ['nullable', 'array', 'max:8'],
            'body.attributes.attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,txt,mp4,mov,mp3,wav,m4a'],
            'body.attributes.actions' => ['nullable', 'array', 'max:16'],
            'body.attributes.actions.*' => ['array'],
            'body.attributes.errors' => ['nullable', 'array', 'max:16'],
            'body.attributes.errors.*' => ['array'],
            'body.attributes.extra' => ['nullable', 'array'],
            'body.attributes.conversation_persistence' => ['nullable', 'string', 'max:80'],
            'body.relations' => ['nullable', 'array', 'max:32'],
            'body.relations.*' => ['array:role,purpose,target'],
            'body.relations.*.role' => ['required', 'string', 'max:100'],
            'body.relations.*.purpose' => ['nullable', 'string', 'max:255'],
            'body.relations.*.target' => ['required', 'array:type,id'],
            'body.relations.*.target.type' => ['required', 'string', 'in:channel,space,thread,post'],
            'body.relations.*.target.id' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'A body describing what should be formed is required.',
            'body.attributes.attachments.max' => 'You can attach up to 8 files.',
            'body.attributes.attachments.*.max' => 'Each file must be 10MB or smaller.',
            'body.attributes.attachments.*.mimes' => 'One or more files have an unsupported type.',
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $body = $this->input('body');

                if (! is_array($body)) {
                    return;
                }

                $type = $body['type'] ?? null;
                $id = $body['id'] ?? null;
                $parentType = data_get($body, 'parent.type');
                $parentId = data_get($body, 'parent.id');
                $isExisting = is_string($id) && trim($id) !== '';

                if ($isExisting && is_array($body['parent'] ?? null)) {
                    $validator->errors()->add('body.parent', 'An existing node cannot be assigned a new hierarchical parent through Form.');
                }

                if (! $isExisting && $type === 'space' && $parentType !== null && $parentType !== 'space') {
                    $validator->errors()->add('body.parent.type', 'A Space node may only be contained by another Space.');
                }

                if (
                    ! $isExisting
                    && in_array($type, ['thread', 'post'], true)
                    && (! is_string($parentId) || trim($parentId) === '')
                ) {
                    $validator->errors()->add('body.parent.id', 'A parent node is required.');
                }

                if (! $isExisting && $type === 'thread' && ! in_array($parentType, ['space', 'thread'], true)) {
                    $validator->errors()->add('body.parent.type', 'A Thread node must be contained by a Space or Thread.');
                }

                if (! $isExisting && $type === 'post' && ! in_array($parentType, ['space', 'thread', 'post'], true)) {
                    $validator->errors()->add('body.parent.type', 'A Post node must be contained by a Space, Thread, or Post.');
                }

                if (
                    ! $isExisting
                    && $type === 'thread'
                    && ! is_string(data_get($body, 'attributes.title'))
                ) {
                    $validator->errors()->add('body.attributes.title', 'A Thread title is required.');
                }

                $postType = data_get($body, 'attributes.post_type', Post::TypeMessage);
                if (
                    ! $isExisting
                    && $type === 'post'
                    && $parentType === 'thread'
                    && $postType === Post::TypeMessage
                    && ! is_string(data_get($body, 'attributes.text'))
                ) {
                    $validator->errors()->add('body.attributes.text', 'A message Post formed under a Thread requires text.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $body = $this->input('body');

        if (! is_array($body)) {
            return;
        }

        $text = data_get($body, 'attributes.text');

        if (is_string($text)) {
            data_set($body, 'attributes.text', trim($text));
        }

        $this->merge(['body' => $body]);
    }
}
