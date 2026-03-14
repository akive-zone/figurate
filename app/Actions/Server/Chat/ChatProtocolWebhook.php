<?php

namespace App\Actions\Server\Chat;

use App\Jobs\ProcessInboundMessageWebhookJob;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\SignatureValidator\DefaultSignatureValidator;
use Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile;
use Spatie\WebhookClient\WebhookResponse\DefaultRespondsTo;

class ChatProtocolWebhook
{
    /**
     * @param  list<string>  $storeHeaders
     */
    public function __construct(
        public string $configName,
        public string $path,
        public string $signingSecret,
        public string $signatureHeaderName = 'Signature',
        public string $signatureValidator = DefaultSignatureValidator::class,
        public string $webhookProfile = ProcessEverythingWebhookProfile::class,
        public string $webhookResponse = DefaultRespondsTo::class,
        public string $webhookModel = WebhookCall::class,
        public array $storeHeaders = ['*'],
        public string $processWebhookJob = ProcessInboundMessageWebhookJob::class,
        public string $method = 'post',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toConfig(): array
    {
        return [
            'name' => $this->configName,
            'signing_secret' => $this->signingSecret,
            'signature_header_name' => $this->signatureHeaderName,
            'signature_validator' => $this->signatureValidator,
            'webhook_profile' => $this->webhookProfile,
            'webhook_response' => $this->webhookResponse,
            'webhook_model' => $this->webhookModel,
            'store_headers' => $this->storeHeaders,
            'process_webhook_job' => $this->processWebhookJob,
        ];
    }
}
