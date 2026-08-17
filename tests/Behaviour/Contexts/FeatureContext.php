<?php

namespace Tests\Behaviour\Contexts;

use App\Models\Server\SanctumUser;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Laravel\Ai\AiManager;
use Laravel\Ai\Gateway\FakeTextGateway;
use Laravel\Ai\Gateway\OpenAi\OpenAiGateway;
use Laravel\Ai\Providers\OpenAiProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class FeatureContext implements Context
{
    protected static ?Application $application = null;

    protected ?User $subject = null;

    protected ?string $accessToken = null;

    /**
     * @var array<string, scalar|null>
     */
    protected array $memory = [];

    /**
     * @var array<string, string>
     */
    protected array $nextRequestHeaders = [];

    protected ?Response $response = null;

    protected ?string $originalAiProvider = null;

    /**
     * @var array<string, mixed>
     */
    protected array $responseData = [];

    #[BeforeScenario]
    public function prepareScenario(): void
    {
        $application = $this->application();
        $application->make('config')->set('broadcasting.default', 'null');
        $application->make(ConsoleKernel::class)->call('migrate:fresh', [
            '--force' => true,
        ]);
    }

    #[AfterScenario]
    public function finishScenario(): void
    {
        if ($this->originalAiProvider !== null) {
            $this->application()->make('config')->set('ai.default', $this->originalAiProvider);
            $this->application()->make(AiManager::class)->forgetInstance('behaviour');
            $this->originalAiProvider = null;
        }

        $this->application()->make('auth')->forgetGuards();
        $this->application()->forgetScopedInstances();
    }

    #[Given('an API subject exists')]
    public function anApiSubjectExists(): void
    {
        $this->subject = User::factory()->create();
    }

    #[Given('the client :client has these abilities:')]
    public function theClientHasTheseAbilities(string $client, TableNode $abilities): void
    {
        $subject = $this->subject ?? throw new RuntimeException('Create an API subject before authorizing a client.');
        $abilityValues = array_map(
            static fn (array $row): string => (string) reset($row),
            $abilities->getRows(),
        );

        $this->accessToken = SanctumUser::query()
            ->findOrFail($subject->getKey())
            ->createToken("api:{$client}", $abilityValues)
            ->plainTextToken;
    }

    #[Given('the next request has header :header with value :value')]
    public function theNextRequestHasHeaderWithValue(string $header, string $value): void
    {
        $this->nextRequestHeaders[$header] = $this->interpolate($value);
    }

    #[Given('an accessible space exists as :name')]
    public function anAccessibleSpaceExistsAs(string $name): void
    {
        $subject = $this->subject ?? throw new RuntimeException('Create an API subject before creating an accessible space.');
        $space = Space::factory()->create();

        SpaceActorState::query()->create([
            'space_id' => $space->getKey(),
            'thread_id' => null,
            'actorable_type' => $subject->getMorphClass(),
            'actorable_id' => $subject->getKey(),
            'status' => SpaceActorState::StatusActive,
        ]);

        $this->memory[$name] = $space->uuid;
    }

    #[Given('an accessible automated thread exists')]
    public function anAccessibleAutomatedThreadExists(): void
    {
        $this->anAccessibleSpaceExistsAs('space_id');

        $space = Space::query()
            ->where('uuid', $this->memory['space_id'])
            ->firstOrFail();
        $thread = $space->threads()->create([
            'title' => 'Automated contract review',
            'purpose' => Thread::PurposeMain,
            'phase' => Thread::PhaseInitial,
            'status' => 'open',
        ]);
        $thread->actors()->create([
            'actorable_type' => ThreadActor::ActorCoordinator,
            'actorable_id' => null,
            'role' => ThreadActor::RolePresenter,
            'status' => ThreadActor::StatusActive,
            'priority' => 1,
        ]);

        $this->memory['thread_id'] = $thread->uuid;
    }

    #[Given('the deterministic AI provider responds with:')]
    public function theDeterministicAiProviderRespondsWith(PyStringNode $response): void
    {
        $application = $this->application();
        $config = $application->make('config');
        $this->originalAiProvider ??= (string) $config->get('ai.default');
        $config->set('ai.default', 'behaviour');
        $config->set('ai.providers.behaviour', [
            'driver' => 'behaviour',
            'key' => 'behaviour-test-key',
        ]);

        $manager = $application->make(AiManager::class);
        $manager->extend(
            'behaviour',
            function (Application $application, array $providerConfig) use ($response): OpenAiProvider {
                $gateway = (new FakeTextGateway([$response->getRaw()]))
                    ->preventStrayPrompts();

                return (new OpenAiProvider(
                    new OpenAiGateway($application->make(Dispatcher::class)),
                    $providerConfig,
                    $application->make(Dispatcher::class),
                ))->useTextGateway($gateway);
            },
        );
        $manager->forgetInstance('behaviour');
    }

    #[When('the client sends a :method request to :path')]
    public function theClientSendsARequestTo(string $method, string $path): void
    {
        $this->sendRequest($method, $path);
    }

    #[When('the client sends a :method request to :path with JSON:')]
    public function theClientSendsARequestToWithJson(string $method, string $path, PyStringNode $json): void
    {
        $content = $this->interpolate($json->getRaw());
        $payload = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new RuntimeException('The request JSON must decode to an object or array.');
        }

        $this->sendRequest($method, $path, $payload);
    }

    #[Then('the response status should be :status')]
    public function theResponseStatusShouldBe(int $status): void
    {
        $response = $this->response ?? throw new RuntimeException('No API response is available.');

        if ($response->getStatusCode() !== $status) {
            throw new RuntimeException(sprintf(
                "Expected response status %d, received %d.\n%s",
                $status,
                $response->getStatusCode(),
                $response->getContent(),
            ));
        }
    }

    #[Then('the response field :field should equal :expected')]
    public function theResponseFieldShouldEqual(string $field, string $expected): void
    {
        $actual = data_get($this->responseData, $field);
        $expectedValue = $this->normalizeExpectedValue($this->interpolate($expected));

        if ($actual !== $expectedValue) {
            throw new RuntimeException(sprintf(
                'Expected response field "%s" to equal %s, received %s.',
                $field,
                json_encode($expectedValue, JSON_THROW_ON_ERROR),
                json_encode($actual, JSON_THROW_ON_ERROR),
            ));
        }
    }

    #[Then('the response field :field should not be empty')]
    public function theResponseFieldShouldNotBeEmpty(string $field): void
    {
        $actual = data_get($this->responseData, $field);

        if ($actual === null || $actual === '' || $actual === []) {
            throw new RuntimeException(sprintf(
                'Expected response field "%s" not to be empty, received %s in response %s.',
                $field,
                json_encode($actual, JSON_THROW_ON_ERROR),
                json_encode($this->responseData, JSON_THROW_ON_ERROR),
            ));
        }
    }

    #[Then('the response list :field should contain :expected')]
    public function theResponseListShouldContain(string $field, string $expected): void
    {
        $items = data_get($this->responseData, $field);
        $expectedValue = $this->normalizeExpectedValue($this->interpolate($expected));

        if (! is_array($items) || ! in_array($expectedValue, $items, true)) {
            throw new RuntimeException(sprintf(
                'Expected response list "%s" to contain %s, received %s.',
                $field,
                json_encode($expectedValue, JSON_THROW_ON_ERROR),
                json_encode($items, JSON_THROW_ON_ERROR),
            ));
        }
    }

    #[Then('the response header :header should equal :expected')]
    public function theResponseHeaderShouldEqual(string $header, string $expected): void
    {
        $response = $this->response ?? throw new RuntimeException('No API response is available.');
        $actual = $response->headers->get($header);
        $expectedValue = $this->interpolate($expected);

        if ($actual !== $expectedValue) {
            throw new RuntimeException(sprintf(
                'Expected response header "%s" to equal "%s", received "%s".',
                $header,
                $expectedValue,
                $actual,
            ));
        }
    }

    #[Then('I remember response field :field as :name')]
    public function iRememberResponseFieldAs(string $field, string $name): void
    {
        $value = data_get($this->responseData, $field);

        if (! is_scalar($value) && $value !== null) {
            throw new RuntimeException("Response field \"{$field}\" cannot be stored as a scalar value.");
        }

        $this->memory[$name] = $value;
    }

    #[Then('I remember field :valueField from the response item in :listField where :matchField equals :expected as :name')]
    public function iRememberFieldFromTheResponseItemWhereEqualsAs(
        string $valueField,
        string $listField,
        string $matchField,
        string $expected,
        string $name,
    ): void {
        $items = data_get($this->responseData, $listField);
        $expectedValue = $this->normalizeExpectedValue($this->interpolate($expected));

        if (! is_array($items)) {
            throw new RuntimeException("Response field \"{$listField}\" is not a list.");
        }

        foreach ($items as $item) {
            if (! is_array($item) || data_get($item, $matchField) !== $expectedValue) {
                continue;
            }

            $value = data_get($item, $valueField);

            if (! is_scalar($value) && $value !== null) {
                throw new RuntimeException("Response item field \"{$valueField}\" cannot be stored as a scalar value.");
            }

            $this->memory[$name] = $value;

            return;
        }

        throw new RuntimeException(sprintf(
            'No response item in "%s" has field "%s" equal to %s.',
            $listField,
            $matchField,
            json_encode($expectedValue, JSON_THROW_ON_ERROR),
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function sendRequest(string $method, string $path, array $payload = []): void
    {
        $this->application()->make('auth')->forgetGuards();
        $this->application()->forgetScopedInstances();

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($this->accessToken !== null) {
            $server['HTTP_AUTHORIZATION'] = "Bearer {$this->accessToken}";
        }

        foreach ($this->nextRequestHeaders as $header => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $header))] = $value;
        }

        $request = Request::create(
            $this->interpolate($path),
            strtoupper($method),
            server: $server,
            content: $payload === [] ? null : json_encode($payload, JSON_THROW_ON_ERROR),
        );
        $kernel = $this->application()->make(HttpKernel::class);
        $this->response = $kernel->handle($request);
        $kernel->terminate($request, $this->response);
        $this->nextRequestHeaders = [];

        $content = $this->response->getContent();
        $decoded = is_string($content) && $content !== ''
            ? json_decode($content, true)
            : [];
        $this->responseData = is_array($decoded) ? $decoded : [];
    }

    protected function application(): Application
    {
        if (self::$application instanceof Application) {
            return self::$application;
        }

        $this->setTestingEnvironment();

        /** @var Application $application */
        $application = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $application->instance('request', Request::create('http://localhost/'));
        $application->make(HttpKernel::class)->bootstrap();

        return self::$application = $application;
    }

    protected function setTestingEnvironment(): void
    {
        $variables = [
            'APP_ENV' => 'testing',
            'BCRYPT_ROUNDS' => '4',
            'BROADCAST_CONNECTION' => 'null',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'PULSE_ENABLED' => 'false',
            'TELESCOPE_ENABLED' => 'false',
            'NIGHTWATCH_ENABLED' => 'false',
        ];

        foreach ($variables as $name => $value) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    protected function interpolate(string $value): string
    {
        return preg_replace_callback(
            '/\{\{([a-zA-Z0-9_]+)\}\}/',
            function (array $matches): string {
                $name = $matches[1];

                if (! array_key_exists($name, $this->memory)) {
                    throw new RuntimeException("No remembered value exists for \"{$name}\".");
                }

                return (string) $this->memory[$name];
            },
            $value,
        ) ?? $value;
    }

    protected function normalizeExpectedValue(string $value): string|int|float|bool|null
    {
        return match (true) {
            $value === 'true' => true,
            $value === 'false' => false,
            $value === 'null' => null,
            filter_var($value, FILTER_VALIDATE_INT) !== false => (int) $value,
            filter_var($value, FILTER_VALIDATE_FLOAT) !== false => (float) $value,
            default => $value,
        };
    }
}
