<?php

namespace App\Http\Requests\Server\Channel;

use App\Models\Server\Channel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreChannelAddressRequest extends FormRequest
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
            'addressable_type' => ['required', 'string', 'in:user,space,thread'],
            'addressable_id' => ['nullable', 'string'],
            'label' => ['nullable', 'string', 'max:160'],
            'provider' => ['nullable', 'string', 'max:80'],
            'target' => ['required', 'string', 'max:255'],
            'target_type' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', 'in:'.implode(',', [Channel::StatusActive, Channel::StatusPaused, Channel::StatusDisabled])],
            'direction' => ['nullable', 'string', 'in:'.implode(',', [Channel::DirectionInbound, Channel::DirectionOutbound, Channel::DirectionBidirectional])],
            'data' => ['nullable', 'array'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
