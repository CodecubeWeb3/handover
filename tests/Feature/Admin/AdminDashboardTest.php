<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_dashboard(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk()->assertSee('Moderation overview');
    }

    public function test_parent_cannot_view_dashboard(): void
    {
        $parent = User::factory()->create(['role' => UserRole::Parent->value]);

        $this->actingAs($parent)->get('/admin')->assertForbidden();
    }
}