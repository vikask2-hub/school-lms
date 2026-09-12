<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_dashboard_and_navigation_page_renders(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
            $user = User::where('role', $role)->firstOrFail();
            $this->actingAs($user)->get('/lms/dashboard')->assertOk();
            $modules = collect(config('lms.navigation.'.$role))->flatMap(fn (array $items): array => array_keys($items))->reject(fn (string $module): bool => $module === 'dashboard');
            foreach ($modules as $module) {
                $this->actingAs($user)->get('/lms/modules/'.$module)->assertOk()->assertDontSee('No view configured');
            }
        }
    }

    public function test_teacher_and_parent_views_are_scoped_to_their_context(): void
    {
        $this->seed(DatabaseSeeder::class);
        $teacher = User::where('email', 'teacher@greenfield.demo')->firstOrFail();
        $parent = User::where('email', 'parent@greenfield.demo')->firstOrFail();

        $this->actingAs($teacher)->get('/lms/modules/classes')->assertOk()->assertSee('Grade 8')->assertDontSee('Grade 7');
        $this->actingAs($teacher)->get('/lms/modules/subjects')->assertOk()->assertSee('Mathematics')->assertSee('Computer Science')->assertDontSee('English');
        $this->actingAs($parent)->get('/lms/modules/results')->assertOk()->assertSee('Aarav Kapoor')->assertSee('88/100')->assertDontSee('No published results are available');
    }

    public function test_student_is_forbidden_from_admin_user_management(): void
    {
        $this->seed(DatabaseSeeder::class);
        $student = User::where('email', 'student@greenfield.demo')->firstOrFail();

        $this->actingAs($student)->get('/lms/modules/users')->assertForbidden();
    }

    public function test_parent_cannot_request_leave_for_an_unlinked_student(): void
    {
        $this->seed(DatabaseSeeder::class);
        $parent = User::where('email', 'parent@greenfield.demo')->firstOrFail();
        $unlinked = User::create(['name' => 'Unlinked Learner', 'email' => 'unlinked@example.test', 'role' => 'student', 'password' => 'password']);

        $this->actingAs($parent)->post('/lms/leave', [
            'student_id' => $unlinked->id,
            'from_date' => now()->addDay()->toDateString(),
            'to_date' => now()->addDays(2)->toDateString(),
            'reason' => 'A valid reason that must still remain private.',
        ])->assertNotFound();

        $this->assertDatabaseMissing('leave_requests', ['student_id' => $unlinked->id]);
    }

    public function test_teacher_cannot_record_offline_fee_payment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $teacher = User::where('email', 'teacher@greenfield.demo')->firstOrFail();

        $this->actingAs($teacher)->post('/lms/fees/1/payments', [])->assertForbidden();
    }
}
