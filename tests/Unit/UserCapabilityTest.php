<?php

namespace Tests\Unit;

use App\Models\Server\User;
use PHPUnit\Framework\TestCase;

class UserCapabilityTest extends TestCase
{
    public function test_widget_users_can_act_as_end_users(): void
    {
        $user = new User([
            'type' => User::TypeWidget,
        ]);

        $this->assertTrue($user->canActAsEndUser());
        $this->assertTrue($user->canAccessMarketplace());
        $this->assertTrue($user->canUseInteractiveTransport());
    }

    public function test_subject_users_can_act_as_end_users(): void
    {
        $user = new User([
            'type' => User::TypeSubject,
        ]);

        $this->assertTrue($user->canActAsHuman());
        $this->assertTrue($user->canActAsEndUser());
        $this->assertTrue($user->canAccessMarketplace());
        $this->assertTrue($user->canUseInteractiveTransport());
    }

    public function test_robot_users_can_use_interactive_transport_without_marketplace_access(): void
    {
        $user = new User([
            'type' => User::TypeRobot,
        ]);

        $this->assertFalse($user->canActAsEndUser());
        $this->assertFalse($user->canAccessMarketplace());
        $this->assertTrue($user->canUseInteractiveTransport());
    }

    public function test_legacy_widget_aliases_are_not_treated_as_widget_users(): void
    {
        $legacyAliasUser = new User([
            'type' => 'gadget',
        ]);
        $legacyMachineAliasUser = new User([
            'type' => 'device',
        ]);

        $this->assertFalse($legacyAliasUser->isWidget());
        $this->assertFalse($legacyMachineAliasUser->isWidget());
        $this->assertFalse($legacyAliasUser->canActAsEndUser());
        $this->assertFalse($legacyMachineAliasUser->canActAsEndUser());
    }

    public function test_legacy_agent_alias_is_not_treated_as_robot(): void
    {
        $legacyAgentUser = new User([
            'type' => 'agent',
        ]);

        $this->assertFalse($legacyAgentUser->isRobot());
        $this->assertFalse($legacyAgentUser->canUseInteractiveTransport());
    }
}
