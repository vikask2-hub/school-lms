<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\FeeRecord;
use App\Models\ModuleRecord;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_records_attendance(): void
    {
        $this->seed(DatabaseSeeder::class);
        $teacher = User::where('email', 'teacher@greenfield.demo')->firstOrFail();
        $student = User::where('email', 'student@greenfield.demo')->firstOrFail();
        $date = now()->toDateString();

        $this->actingAs($teacher)->post('/lms/attendance', ['student_id' => $student->id, 'date' => $date, 'status' => 'present'])->assertSessionHas('success');

        $this->assertDatabaseHas('attendance_records', ['student_id' => $student->id, 'date' => now()->startOfDay()->format('Y-m-d H:i:s'), 'status' => 'present']);
    }

    public function test_student_submits_an_open_assignment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $student = User::where('email', 'student@greenfield.demo')->firstOrFail();
        $assignment = Assignment::where('due_at', '>', now())->firstOrFail();

        $this->actingAs($student)->post("/lms/assignments/{$assignment->id}/submissions", ['answer' => 'A complete worked response showing each algebraic step.'])->assertSessionHas('success');

        $this->assertDatabaseHas('assignment_submissions', ['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'submitted']);
    }

    public function test_quiz_attempt_is_scored_and_cannot_be_repeated(): void
    {
        $this->seed(DatabaseSeeder::class);
        $student = User::where('email', 'student@greenfield.demo')->firstOrFail();
        $quiz = Quiz::where('title', 'Algebra Quick Check')->firstOrFail();

        $this->actingAs($student)->post("/lms/quizzes/{$quiz->id}/attempts", ['answers' => ['4', '3a + 6', '7']])->assertSessionHas('success');
        $this->assertDatabaseHas('quiz_attempts', ['quiz_id' => $quiz->id, 'student_id' => $student->id, 'score' => 3, 'total' => 3]);

        $this->actingAs($student)->post("/lms/quizzes/{$quiz->id}/attempts", ['answers' => ['4', '3a + 6', '7']])->assertUnprocessable();
    }

    public function test_admin_records_partial_offline_fee_payment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'principal@greenfield.demo')->firstOrFail();
        $fee = FeeRecord::where('status', 'partial')->firstOrFail();

        $this->actingAs($admin)->post("/lms/fees/{$fee->id}/payments", ['amount' => 1000, 'payment_method' => 'Cash', 'reference' => 'CASH-TEST-01', 'paid_on' => now()->toDateString()])->assertSessionHas('success');

        $this->assertDatabaseHas('fee_records', ['id' => $fee->id, 'paid_amount' => 16000, 'reference' => 'CASH-TEST-01', 'status' => 'partial']);
    }

    public function test_parent_requests_leave_for_linked_child(): void
    {
        $this->seed(DatabaseSeeder::class);
        $parent = User::where('email', 'parent@greenfield.demo')->firstOrFail();
        $student = $parent->children()->firstOrFail();

        $this->actingAs($parent)->post('/lms/leave', ['student_id' => $student->id, 'from_date' => now()->addDays(3)->toDateString(), 'to_date' => now()->addDays(4)->toDateString(), 'reason' => 'Family commitment outside the city.'])->assertSessionHas('success');

        $this->assertDatabaseHas('leave_requests', ['user_id' => $parent->id, 'student_id' => $student->id, 'status' => 'pending']);
    }

    public function test_parent_creates_support_ticket_and_ptm_request(): void
    {
        $this->seed(DatabaseSeeder::class);
        $parent = User::where('email', 'parent@greenfield.demo')->firstOrFail();
        $student = $parent->children()->orderBy('users.id')->firstOrFail();
        $teacher = User::where('email', 'teacher@greenfield.demo')->firstOrFail();

        $this->actingAs($parent)->post('/lms/support', ['student_id' => $student->id, 'category' => 'Technical', 'priority' => 'High', 'subject' => 'Cannot open a resource', 'details' => 'The science resource link does not open on the tablet.'])->assertSessionHas('success');
        $this->assertDatabaseHas('module_records', ['module' => 'support', 'owner_id' => $parent->id, 'title' => 'Cannot open a resource', 'status' => 'open']);

        $this->actingAs($parent)->post('/lms/ptm', ['student_id' => $student->id, 'teacher_id' => $teacher->id, 'preferred_at' => now()->addDays(3)->format('Y-m-d H:i:s'), 'topic' => 'Discuss the next mathematics learning goals.'])->assertSessionHas('success');
        $this->assertDatabaseHas('module_records', ['module' => 'ptm', 'student_id' => $student->id, 'owner_id' => $teacher->id, 'status' => 'requested']);
    }

    public function test_expanded_demo_contains_multiple_examples_for_specialist_modules(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['timetable', 'curriculum', 'feedback', 'behavior', 'ptm', 'documents', 'certificates', 'notifications', 'support', 'settings', 'exams', 'chapters'] as $module) {
            $this->assertGreaterThanOrEqual(3, ModuleRecord::where('module', $module)->count(), $module.' should have multiple demo examples.');
        }
    }
}
