<?php

namespace App\Http\Requests\Server\Channel;

use App\Models\Server\Channel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChannelAddressRequest extends FormRequest
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
            'label' => ['sometimes', 'nullable', 'string', 'max:160'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:80'],
            'target' => ['sometimes', 'string', 'max:255'],
            'target_type' => ['sometimes', 'nullable', 'string', 'max:80'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', [Channel::StatusActive, Channel::StatusPaused, Channel::StatusDisabled])],
            'direction' => ['sometimes', 'string', 'in:'.implode(',', [Channel::DirectionInbound, Channel::DirectionOutbound, Channel::DirectionBidirectional])],
            'data' => ['sometimes', 'nullable', 'array'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
