<?php

namespace App\Http\Requests\Server\Auth;

use App\TokenAbility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentUserRequest extends FormRequest
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
            'token_name' => ['nullable', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', Rule::in(TokenAbility::values())],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('abilities')) {
            $this->merge([
                'abilities' => TokenAbility::defaultAgentAbilities(),
            ]);
        }
    }
}
