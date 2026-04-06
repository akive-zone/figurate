<?php

namespace App\Http\Requests\Server\Channel;

use App\Models\Server\Channel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreChannelRouteRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:120'],
            'label' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'string', 'in:'.implode(',', [Channel::StatusActive, Channel::StatusPaused, Channel::StatusDisabled])],
            'direction' => ['nullable', 'string', 'in:'.implode(',', [Channel::DirectionInbound, Channel::DirectionOutbound, Channel::DirectionBidirectional])],
            'transport' => ['nullable', 'string', 'max:40'],
            'mode' => ['nullable', 'string', 'max:40'],
            'endpoint_url' => ['nullable', 'string', 'max:2048'],
            'handler' => ['nullable', 'string', 'max:255'],
            'auth_type' => ['nullable', 'string', 'in:bearer,basic,header'],
            'credentials' => ['nullable', 'array'],
            'config' => ['nullable', 'array'],
            'data' => ['nullable', 'array'],
            'meta' => ['nullable', 'array'],
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

        $this->merge([
            'config' => array_filter($config, fn (mixed $value): bool => $value !== null),
        ]);
    }
}
