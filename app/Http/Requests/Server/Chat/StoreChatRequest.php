<?php

namespace App\Http\Requests\Server\Chat;

use Illuminate\Contracts\Validation\ValidationRule;
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'space' => ['nullable', 'uuid', 'exists:spaces,uuid'],
            'thread' => ['nullable', 'uuid', 'exists:threads,uuid'],
            'content' => ['required', 'array:text,attachments,actions,errors'],
            'content.text' => ['nullable', 'required_without_all:content.attachments,content.actions,content.errors', 'string', 'max:5000'],
            'content.actions' => ['nullable', 'array', 'max:16'],
            'content.actions.*' => ['array:protocol,name,id,surfaceId,sourceComponentId,timestamp,context,values'],
            'content.attachments' => ['nullable', 'array', 'max:8'],
            'content.attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,txt,mp4,mov,mp3,wav,m4a'],
            'content.actions.*.protocol' => ['nullable', 'string', 'max:40'],
            'content.actions.*.name' => ['nullable', 'string', 'max:120'],
            'content.actions.*.id' => ['nullable', 'string', 'max:120'],
            'content.actions.*.surfaceId' => ['nullable', 'string', 'max:160'],
            'content.actions.*.sourceComponentId' => ['nullable', 'string', 'max:160'],
            'content.actions.*.timestamp' => ['nullable', 'string', 'max:64'],
            'content.actions.*.context' => ['nullable', 'array'],
            'content.actions.*.values' => ['nullable', 'array'],
            'content.errors' => ['nullable', 'array', 'max:16'],
            'content.errors.*' => ['array:protocol,code,path,message,userAction'],
            'content.errors.*.protocol' => ['nullable', 'string', 'max:40'],
            'content.errors.*.code' => ['nullable', 'string', 'max:160'],
            'content.errors.*.path' => ['nullable', 'string', 'max:200'],
            'content.errors.*.message' => ['nullable', 'string', 'max:2000'],
            'content.errors.*.userAction' => ['nullable', 'array:protocol,name,id,surfaceId,sourceComponentId,timestamp,context,values'],
            'content.errors.*.userAction.protocol' => ['nullable', 'string', 'max:40'],
            'content.errors.*.userAction.name' => ['nullable', 'string', 'max:120'],
            'content.errors.*.userAction.id' => ['nullable', 'string', 'max:120'],
            'content.errors.*.userAction.surfaceId' => ['nullable', 'string', 'max:160'],
            'content.errors.*.userAction.sourceComponentId' => ['nullable', 'string', 'max:160'],
            'content.errors.*.userAction.timestamp' => ['nullable', 'string', 'max:64'],
            'content.errors.*.userAction.context' => ['nullable', 'array'],
            'content.errors.*.userAction.values' => ['nullable', 'array'],
            'extra' => ['nullable', 'array'],
            'extra.a2ui' => ['nullable', 'array:config,surface'],
            'extra.a2ui.config' => ['nullable', 'array:a2uiClientDataModel,a2uiClientCapabilities'],
            'extra.a2ui.config.a2uiClientDataModel' => ['nullable', 'string', 'max:40'],
            'extra.a2ui.config.a2uiClientCapabilities' => ['nullable', 'array:supportedCatalogIds,acceptsInlineCatalogs'],
            'extra.a2ui.config.a2uiClientCapabilities.supportedCatalogIds' => ['nullable', 'array', 'max:64'],
            'extra.a2ui.config.a2uiClientCapabilities.supportedCatalogIds.*' => ['string', 'max:160'],
            'extra.a2ui.config.a2uiClientCapabilities.acceptsInlineCatalogs' => ['nullable', 'boolean'],
            'extra.a2ui.surface' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'space.exists' => 'The selected space was not found.',
            'thread.exists' => 'The selected thread was not found.',
            'content.text.required_without_all' => 'Enter a text, submit an action, report an error, or attach media.',
            'content.actions.max' => 'You can submit up to 16 actions at once.',
            'content.errors.max' => 'You can submit up to 16 client errors at once.',
            'content.array' => 'Unsupported content fields were provided.',
            'extra.array' => 'Unsupported extra fields were provided.',
            'content.attachments.max' => 'You can attach up to 8 files.',
            'content.attachments.*.max' => 'Each file must be 10MB or smaller.',
            'content.attachments.*.mimes' => 'One or more files have an unsupported type.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $content = $this->input('content');
        $extra = $this->input('extra');

        if (is_array($content)) {
            $content['text'] = $this->trimmedString($content['text'] ?? null);
            $this->merge(['content' => $content]);
        }

        if (is_array($extra)) {
            if (is_array(data_get($extra, 'a2ui.config'))) {
                data_set(
                    $extra,
                    'a2ui.config.a2uiClientDataModel',
                    $this->trimmedString(data_get($extra, 'a2ui.config.a2uiClientDataModel'))
                );

                $supportedCatalogIds = data_get($extra, 'a2ui.config.a2uiClientCapabilities.supportedCatalogIds');
                if (is_array($supportedCatalogIds)) {
                    $normalizedCatalogIds = collect($supportedCatalogIds)
                        ->map(fn (mixed $catalogId): ?string => $this->trimmedString($catalogId))
                        ->filter(fn (mixed $catalogId): bool => is_string($catalogId) && $catalogId !== '')
                        ->values()
                        ->all();

                    data_set($extra, 'a2ui.config.a2uiClientCapabilities.supportedCatalogIds', $normalizedCatalogIds);
                }
            }
            $this->merge(['extra' => $extra]);
        }
    }

    protected function trimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
