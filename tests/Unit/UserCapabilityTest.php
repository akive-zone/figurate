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
}
