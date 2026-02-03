## Filament

- Filament is used by this application. Follow existing conventions for how and where it's implemented.
- Filament is a Server-Driven UI (SDUI) framework for Laravel that lets you define user interfaces in PHP using structured configuration objects. Built on Livewire, Alpine.js, and Tailwind CSS.
- Use the ___SINGLE_BACKTICK___search-docs___SINGLE_BACKTICK___ tool for official documentation on Artisan commands, code examples, testing, relationships, and idiomatic practices.

### Artisan

- Use Filament-specific Artisan commands to create files. Find them with ___SINGLE_BACKTICK___list-artisan-commands___SINGLE_BACKTICK___ or ___SINGLE_BACKTICK___php artisan --help___SINGLE_BACKTICK___.
- Inspect required options and always pass ___SINGLE_BACKTICK___--no-interaction___SINGLE_BACKTICK___.

### Patterns

Use static ___SINGLE_BACKTICK___make()___SINGLE_BACKTICK___ methods to initialize components. Most configuration methods accept a ___SINGLE_BACKTICK___Closure___SINGLE_BACKTICK___ for dynamic values.

Use ___SINGLE_BACKTICK___Get $get___SINGLE_BACKTICK___ to read other form field values for conditional logic:

@verbatim
<code-snippet name="Conditional form field" lang="php">
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

Select::make('type')
    ->options(CompanyType::class)
    ->required()
    ->live(),

TextInput::make('company_name')
    ->required()
    ->visible(fn (Get $get): bool => $get('type') === 'business'),

</code-snippet>
@endverbatim

Use ___SINGLE_BACKTICK___state()___SINGLE_BACKTICK___ with a ___SINGLE_BACKTICK___Closure___SINGLE_BACKTICK___ to compute derived column values:

@verbatim
<code-snippet name="Computed table column" lang="php">
use Filament\Tables\Columns\TextColumn;

TextColumn::make('full_name')
    ->state(fn (User $record): string => "{$record->first_name} {$record->last_name}"),

</code-snippet>
@endverbatim

Actions encapsulate a button with optional modal form and logic:

@verbatim
<code-snippet name="Action with modal form" lang="php">
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;

Action::make('updateEmail')
    ->form([
        TextInput::make('email')->email()->required(),
    ])
    ->action(fn (array $data, User $record): void => $record->update($data)),

</code-snippet>
@endverbatim

### Testing

Authenticate before testing panel functionality. Filament uses Livewire, so use ___SINGLE_BACKTICK___livewire()___SINGLE_BACKTICK___ or ___SINGLE_BACKTICK___Livewire::test()___SINGLE_BACKTICK___:

@verbatim
<code-snippet name="Filament Table Test" lang="php">
    livewire(ListUsers::class)
        ->assertCanSeeTableRecords($users)
        ->searchTable($users->first()->name)
        ->assertCanSeeTableRecords($users->take(1))
        ->assertCanNotSeeTableRecords($users->skip(1));

</code-snippet>

<code-snippet name="Filament Create Resource Test" lang="php">
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Test',
            'email' => 'test@example.com',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(User::class, [
        'name' => 'Test',
        'email' => 'test@example.com',
    ]);

</code-snippet>

<code-snippet name="Testing Validation" lang="php">
    livewire(CreateUser::class)
        ->fillForm([
            'name' => null,
            'email' => 'invalid-email',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'email' => 'email',
        ])
        ->assertNotNotified();

</code-snippet>

<code-snippet name="Calling Actions" lang="php">
    use Filament\Actions\DeleteAction;
    use Filament\Actions\Testing\TestAction;

    livewire(EditUser::class, ['record' => $user->id])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    livewire(ListUsers::class)
        ->callAction(TestAction::make('promote')->table($user), [
            'role' => 'admin',
        ])
        ->assertNotified();

</code-snippet>
@endverbatim

### Common Mistakes

**Commonly Incorrect Namespaces:**
- Form fields (TextInput, Select, etc.): ___SINGLE_BACKTICK___Filament\Forms\Components\___SINGLE_BACKTICK___
- Infolist entries (for read-only views) (TextEntry, IconEntry, etc.): ___SINGLE_BACKTICK___Filament\Infolists\Components\___SINGLE_BACKTICK___
- Layout components (Grid, Section, Fieldset, Tabs, Wizard, etc.): ___SINGLE_BACKTICK___Filament\Schemas\Components\___SINGLE_BACKTICK___
- Schema utilities (Get, Set, etc.): ___SINGLE_BACKTICK___Filament\Schemas\Components\Utilities\___SINGLE_BACKTICK___
- Actions: ___SINGLE_BACKTICK___Filament\Actions\___SINGLE_BACKTICK___ (no ___SINGLE_BACKTICK___Filament\Tables\Actions\___SINGLE_BACKTICK___ etc.)
- Icons: ___SINGLE_BACKTICK___Filament\Support\Icons\Heroicon___SINGLE_BACKTICK___ enum (e.g., ___SINGLE_BACKTICK___Heroicon::PencilSquare___SINGLE_BACKTICK___)

**Recent breaking changes to Filament:**
- File visibility is ___SINGLE_BACKTICK___private___SINGLE_BACKTICK___ by default. Use ___SINGLE_BACKTICK___->visibility('public')___SINGLE_BACKTICK___ for public access.
- ___SINGLE_BACKTICK___Grid___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___Section___SINGLE_BACKTICK___, and ___SINGLE_BACKTICK___Fieldset___SINGLE_BACKTICK___ no longer span all columns by default.
