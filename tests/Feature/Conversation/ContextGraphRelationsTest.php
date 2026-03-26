<?php

namespace Tests\Feature\Conversation;

use App\Models\Server\Space;
use App\Models\Server\SpaceRelation;
use App\Models\Server\Thread;
use App\Models\Server\ThreadRelation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContextGraphRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_space_relations_support_multiple_typed_edges_for_the_same_target(): void
    {
        $source = Space::factory()->create();
        $target = Space::factory()->create();

        $source->attachRelation($target, SpaceRelation::TypeDependsOn, 'Execution depends on target space');
        $source->attachRelation($target, SpaceRelation::TypeReferences, 'Use target space as supporting context');

        $this->assertDatabaseCount('space_relations', 2);
        $this->assertDatabaseHas('space_relations', [
            'space_id' => $source->id,
            'relationable_type' => $target->getMorphClass(),
            'relationable_id' => $target->id,
            'type' => SpaceRelation::TypeDependsOn,
        ]);
        $this->assertDatabaseHas('space_relations', [
            'space_id' => $source->id,
            'relationable_type' => $target->getMorphClass(),
            'relationable_id' => $target->id,
            'type' => SpaceRelation::TypeReferences,
        ]);
    }

    public function test_space_context_threads_expand_across_related_spaces_and_threads(): void
    {
        $sourceSpace = Space::factory()->create();
        $dependencySpace = Space::factory()->create();
        $knowledgeSpace = Space::factory()->create();

        $sourceThread = $this->makeThread($sourceSpace, 'Source thread');
        $dependencyThread = $this->makeThread($dependencySpace, 'Dependency thread');
        $knowledgeThread = $this->makeThread($knowledgeSpace, 'Knowledge thread');

        $sourceSpace->attachRelation($dependencySpace, SpaceRelation::TypeDependsOn, 'Needs dependency context');
        $dependencySpace->attachRelation($knowledgeSpace, SpaceRelation::TypeReferences, 'Needs background context');
        $dependencyThread->attachRelation($knowledgeThread, ThreadRelation::TypeReferences, 'See related implementation');

        $this->assertEqualsCanonicalizing(
            [$sourceThread->id, $dependencyThread->id, $knowledgeThread->id],
            $sourceSpace->conversationThreadIds()->all(),
        );
    }

    public function test_thread_context_threads_handle_cycles_without_duplicates(): void
    {
        $leftSpace = Space::factory()->create();
        $rightSpace = Space::factory()->create();

        $leftThread = $this->makeThread($leftSpace, 'Left thread');
        $rightThread = $this->makeThread($rightSpace, 'Right thread');

        $leftThread->attachRelation($rightThread, ThreadRelation::TypeDependsOn, 'Depends on right thread');
        $rightThread->attachRelation($leftThread, ThreadRelation::TypeReferences, 'References left thread');

        $this->assertEqualsCanonicalizing(
            [$leftThread->id, $rightThread->id],
            $leftThread->contextThreadIds()->all(),
        );
    }

    protected function makeThread(Space $space, string $title): Thread
    {
        return $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => $title,
            'phase' => 'context_open',
            'status' => 'open',
        ]);
    }
}
