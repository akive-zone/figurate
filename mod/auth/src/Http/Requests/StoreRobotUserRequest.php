<?php

namespace Figurate\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRobotUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->canActAsHuman();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'account_uuid' => ['nullable', 'string', 'uuid'],
            'token_name' => ['nullable', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', Rule::in(array_keys(config('token-abilities', [])))],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('abilities')) {
            $this->merge([
                'abilities' => config('figurate-auth.robot_default_abilities', []),
            ]);
        }
    }
}
