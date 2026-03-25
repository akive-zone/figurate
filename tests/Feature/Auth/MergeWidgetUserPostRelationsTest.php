<?php

namespace Tests\Feature\Auth;

use App\Features\Actions\Auth\MergeWidgetUserIntoSubject;
use App\Features\Actions\Auth\MergeWidgetUserIntoWidget;
use App\Models\Server\User;
use Figurate\FulfillmentManager\Models\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergeWidgetUserPostRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_to_subject_merge_migrates_post_relations(): void
    {
        $widgetUser = $this->makeUser(User::TypeWidget, 'widget-to-subject@example.invalid');
        $subjectUser = $this->makeUser(User::TypeSubject, 'subject@example.com');
        $request = Request::factory()->create();
        $request->attachRelation($widgetUser, Request::ActionAsker);

        app(MergeWidgetUserIntoSubject::class)->execute($widgetUser, $subjectUser);

        $this->assertDatabaseMissing('post_relations', [
            'post_id' => $request->id,
            'relationable_type' => $widgetUser->getMorphClass(),
            'relationable_id' => $widgetUser->id,
            'role' => Request::ActionAsker,
        ]);
        $this->assertDatabaseHas('post_relations', [
            'post_id' => $request->id,
            'relationable_type' => $subjectUser->getMorphClass(),
            'relationable_id' => $subjectUser->id,
            'role' => Request::ActionAsker,
        ]);
    }

    public function test_widget_to_widget_merge_deduplicates_post_relations(): void
    {
        $sourceWidgetUser = $this->makeUser(User::TypeWidget, 'source@example.invalid');
        $targetWidgetUser = $this->makeUser(User::TypeWidget, 'target@example.invalid');
        $request = Request::factory()->create();
        $request->attachRelation($sourceWidgetUser, Request::ActionAsker);
        $request->attachRelation($targetWidgetUser, Request::ActionAsker);

        app(MergeWidgetUserIntoWidget::class)->execute($sourceWidgetUser, $targetWidgetUser);

        $this->assertDatabaseMissing('post_relations', [
            'post_id' => $request->id,
            'relationable_type' => $sourceWidgetUser->getMorphClass(),
            'relationable_id' => $sourceWidgetUser->id,
            'role' => Request::ActionAsker,
        ]);
        $this->assertSame(1, $request->relations()
            ->where('relationable_type', $targetWidgetUser->getMorphClass())
            ->where('relationable_id', $targetWidgetUser->id)
            ->where('role', Request::ActionAsker)
            ->count());
    }

    protected function makeUser(string $type, string $email): User
    {
        return User::query()->create([
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password123',
            'type' => $type,
            'status' => 'active',
        ]);
    }
}
