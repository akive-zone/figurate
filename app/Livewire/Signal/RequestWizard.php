<?php

namespace App\Livewire\Signal;

use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Models\Server\Profile;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\ThreadActor;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class RequestWizard extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use WithFileUploads;

    public ?array $data = [];

    public ?int $draftRequestId = null;

    public function mount(): void
    {
        $this->form->fill([
            'flow_type' => 'ubuy',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Request Basics')
                        ->schema([
                            Select::make('flow_type')
                                ->label('How should we route this request?')
                                ->options([
                                    'ubuy' => 'Direct Match',
                                    'upwork' => 'Open Bids',
                                    'uber' => 'Auto Assign',
                                ])
                                ->native(false)
                                ->required()
                                ->live(),
                            Select::make('profile_id')
                                ->label('Preferred Worker (Direct Match)')
                                ->options(fn (): array => Profile::query()
                                    ->where('status', 'approved')
                                    ->orderBy('display_name')
                                    ->pluck('display_name', 'id')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->required(fn (Get $get): bool => $get('flow_type') === 'ubuy')
                                ->visible(fn (Get $get): bool => $get('flow_type') === 'ubuy')
                                ->validationMessages([
                                    'required' => 'Pick a worker when using Direct Match.',
                                ]),
                            TextInput::make('title')
                                ->maxLength(160)
                                ->required(),
                            Textarea::make('description')
                                ->rows(6)
                                ->maxLength(5000)
                                ->required(),
                        ]),
                    Step::make('Context')
                        ->schema([
                            Textarea::make('initial_message')
                                ->rows(4)
                                ->maxLength(5000)
                                ->placeholder('Add context for the worker / agents...'),
                            FileUpload::make('contents')
                                ->label('Reference Files')
                                ->multiple()
                                ->maxFiles(8)
                                ->maxSize(10240)
                                ->acceptedFileTypes([
                                    'image/jpeg',
                                    'image/png',
                                    'image/webp',
                                    'application/pdf',
                                    'application/msword',
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'text/plain',
                                ])
                                ->directory('request-drafts')
                                ->storeFileNamesIn('content_file_names'),
                        ]),
                ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        Gate::authorize('create', ServiceRequest::class);
        Gate::authorize('create', Message::class);

        $requestRecord = DB::transaction(function () use ($data, $user): ServiceRequest {
            $requestRecord = ServiceRequest::query()->create([
                'flow_type' => $data['flow_type'],
                'title' => $data['title'],
                'description' => $data['description'],
                'status' => 'draft',
            ]);

            $requestRecord->users()->attach($user->id, [
                'action' => ServiceRequest::ActionAsker,
                'status' => 'active',
            ]);

            if (! empty($data['profile_id'])) {
                $requestRecord->profiles()->attach((int) $data['profile_id'], [
                    'action' => ServiceRequest::ActionTargetProfile,
                    'status' => 'active',
                ]);
            }

            $attachments = collect($data['contents'] ?? [])->values();

            if (! empty($data['initial_message']) || $attachments->isNotEmpty()) {
                $message = $requestRecord->messages()->create([
                    'senderable_type' => $user->getMorphClass(),
                    'senderable_id' => $user->getKey(),
                    'type' => 'text',
                    'body' => $data['initial_message'] ?? 'Draft context uploaded.',
                    'attachments' => null,
                    'meta' => ['source' => 'draft_context'],
                ]);

                $disk = config('filesystems.default');

                $attachments->each(function (string $path, int $index) use ($data, $message, $disk): void {
                    if (! Storage::disk($disk)->exists($path)) {
                        return;
                    }

                    $originalName = $data['content_file_names'][$index] ?? basename($path);
                    $extension = pathinfo($path, PATHINFO_EXTENSION);
                    $fileName = $extension !== ''
                        ? pathinfo($originalName, PATHINFO_FILENAME).'.'.$extension
                        : $originalName;

                    $message->addMediaFromDisk($path, $disk)
                        ->usingName(pathinfo($originalName, PATHINFO_FILENAME))
                        ->usingFileName($fileName)
                        ->toMediaCollection('attachments');
                });

                if ($message->getMedia('attachments')->isNotEmpty()) {
                    $message->syncAttachmentPayload();
                }
            }

            return $requestRecord;
        });

        $this->draftRequestId = $requestRecord->id;
    }

    public function startChannel(): mixed
    {
        $user = auth()->user();

        if (! $user || ! $this->draftRequestId) {
            abort(403);
        }

        Gate::authorize('create', Channel::class);
        Gate::authorize('update', ServiceRequest::query()->findOrFail($this->draftRequestId));

        $result = DB::transaction(function () use ($user): array {
            $requestRecord = ServiceRequest::query()
                ->with(['channels', 'messages' => fn ($query) => $query->latest('id')->limit(1)])
                ->findOrFail($this->draftRequestId);

            $existingChannel = $requestRecord->channels()->first();

            if ($existingChannel) {
                $threadId = $requestRecord->threads()->latest('id')->value('id');

                return ['channel_id' => $existingChannel->id, 'thread_id' => $threadId];
            }

            $targetProfileId = $requestRecord->profiles()
                ->wherePivot('action', ServiceRequest::ActionTargetProfile)
                ->value('profiles.id');

            $channel = Channel::query()->create([
                'requester_id' => $user->id,
                'profile_id' => $targetProfileId ?: null,
                'status' => 'open',
            ]);

            $channel->requests()->attach($requestRecord->id);

            $mainThread = $requestRecord->threads()->create([
                'created_by' => $user->id,
                'title' => 'Project Main',
                'phase' => 'request_intake',
                'status' => 'open',
            ]);

            $mainThread->actors()->create([
                'actorable_type' => ThreadActor::ActorRequestAgent,
                'actorable_id' => null,
                'role' => ThreadActor::RolePrimaryHandler,
                'status' => ThreadActor::StatusActive,
                'priority' => 1,
                'config' => null,
            ]);

            $draftContextMessage = $requestRecord->messages->first();

            if ($draftContextMessage) {
                $threadMessage = $mainThread->messages()->create([
                    'senderable_type' => $draftContextMessage->senderable_type,
                    'senderable_id' => $draftContextMessage->senderable_id,
                    'type' => 'text',
                    'body' => $draftContextMessage->body,
                    'attachments' => null,
                    'meta' => ['source' => 'request_open_from_draft'],
                ]);

                $draftContextMessage->getMedia('attachments')
                    ->each(fn ($media) => $media->copy($threadMessage, 'attachments'));

                if ($threadMessage->getMedia('attachments')->isNotEmpty()) {
                    $threadMessage->syncAttachmentPayload();
                } else {
                    $threadMessage->forceFill([
                        'attachments' => $draftContextMessage->attachments,
                    ])->save();
                }

                $channel->forceFill([
                    'last_message_at' => now(),
                ])->save();
            }

            $requestRecord->forceFill([
                'status' => 'open',
            ])->save();

            return ['channel_id' => $channel->id, 'thread_id' => $mainThread->id];
        });

        return redirect()->to("/signal/chat/{$result['channel_id']}?thread={$result['thread_id']}");
    }

    public function render(): View
    {
        return view('livewire.signal.request-wizard');
    }
}
