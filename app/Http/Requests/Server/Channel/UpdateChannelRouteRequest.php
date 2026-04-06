<?php

namespace App\Http\Requests\Server\Channel;

use App\Models\Server\Channel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChannelRouteRequest extends FormRequest
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
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'label' => ['sometimes', 'nullable', 'string', 'max:160'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', [Channel::StatusActive, Channel::StatusPaused, Channel::StatusDisabled])],
            'direction' => ['sometimes', 'string', 'in:'.implode(',', [Channel::DirectionInbound, Channel::DirectionOutbound, Channel::DirectionBidirectional])],
            'transport' => ['sometimes', 'nullable', 'string', 'max:40'],
            'mode' => ['sometimes', 'nullable', 'string', 'max:40'],
            'endpoint_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'handler' => ['sometimes', 'nullable', 'string', 'max:255'],
            'auth_type' => ['sometimes', 'nullable', 'string', 'in:bearer,basic,header'],
            'credentials' => ['sometimes', 'nullable', 'array'],
            'config' => ['sometimes', 'nullable', 'array'],
            'data' => ['sometimes', 'nullable', 'array'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $config = is_array($this->input('config')) ? $this->input('config') : [];

        foreach (['transport', 'mode', 'endpoint_url', 'handler', 'auth_type', 'credentials'] as $key) {
            if ($this->has($key)) {
                $config[$key] = $this->input($key);
            }
        }

        if ($this->has('config') || $config !== []) {
            $this->merge([
                'config' => array_filter($config, fn (mixed $value): bool => $value !== null),
            ]);
        }
    }
}
