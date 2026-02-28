<?php

namespace App\Support\Passkeys;

use Spatie\LaravelPasskeys\Actions\ConfigureCeremonyStepManagerFactoryAction as BaseConfigureCeremonyStepManagerFactoryAction;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;

class ConfigureCeremonyStepManagerFactoryAction extends BaseConfigureCeremonyStepManagerFactoryAction
{
    public function execute(): CeremonyStepManagerFactory
    {
        $ceremonyStepManagerFactory = parent::execute();

        if (app()->environment(['local', 'testing'])) {
            $allowedOrigins = [
                (string) parse_url((string) config('app.url'), PHP_URL_HOST),
                'localhost',
                '127.0.0.1',
                '::1',
            ];

            $allowedOrigins = array_values(array_filter(array_unique($allowedOrigins), static function (?string $origin): bool {
                return is_string($origin) && $origin !== '';
            }));

            if ($allowedOrigins !== []) {
                $ceremonyStepManagerFactory->setAllowedOrigins($allowedOrigins, true);
            }
        }

        return $ceremonyStepManagerFactory;
    }
}
