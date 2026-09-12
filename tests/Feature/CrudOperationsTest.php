<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\BookIssue;
use App\Models\ExamResult;
use App\Models\FeeRecord;
use App\Models\LearningItem;
use App\Models\Message;
use App\Models\ModuleRecord;
use App\Models\Quiz;
use App\Models\RolePermission;
use App\Models\SchoolEvent;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CrudOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_people_classes_and_subjects(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('role', 'admin')->firstOrFail();
        $teacher = User::where('email', 'teacher@greenfield.demo')->firstOrFail();

        $this->actingAs($admin)->post('/lms/users', ['name' => 'Demo Coordinator', 'email' => 'coordinator@example.test', 'username' => 'demo-coordinator', 'role' => 'teacher', 'status' => 'active', 'password' => 'SecureDemo123'])->assertSessionHas('success');
        $createdUser = User::where('email', 'coordinator@example.test')->firstOrFail();
        $this->actingAs($admin)->patch('/lms/users/'.$createdUser->id, ['name' => 'Updated Coordinator', 'email' => 'coordinator@example.test', 'username' => 'demo-coordinator', 'role' => 'teacher', 'status' => 'inactive'])->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $createdUser->id, 'name' => 'Updated Coordinator', 'status' => 'inactive']);
        $this->actingAs($admin)->delete('/lms/users/'.$createdUser->id)->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $createdUser->id]);

        $this->actingAs($admin)->post('/lms/academic-classes', ['name' => 'Grade 9', 'section' => 'C', 'room' => 'C-301', 'capacity' => 30, 'academic_year' => '2026-27'])->assertSessionHas('success');
        $class = AcademicClass::where('name', 'Grade 9')->firstOrFail();
        $this->actingAs($admin)->patch('/lms/academic-classes/'.$class->id, ['name' => 'Grade 9', 'section' => 'C', 'room' => 'C-302', 'capacity' => 32, 'academic_year' => '2026-27'])->assertSessionHas('success');
        $this->actingAs($admin)->post('/lms/subjects', ['academic_class_id' => $class->id, 'teacher_id' => $teacher->id, 'name' => 'Robotics', 'code' => 'ROB-9C', 'color' => '#2563eb', 'progress' => 10])->assertSessionHas('success');
        $subject = Subject::where('code', 'ROB-9C')->firstOrFail();
        $this->actingAs($admin)->patch('/lms/subjects/'.$subject->id, ['academic_class_id' => $class->id, 'teacher_id' => $teacher->id, 'name' => 'Applied Robotics', 'code' => 'ROB-9C', 'color' => '#0f766e', 'progress' => 15])->assertSessionHas('success');
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'name' => 'Applied Robotics', 'progress' => 15]);
        $this->actingAs($admin)->delete('/lms/subjects/'.$subject->id)->assertSessionHas('success');
        $this->actingAs($admin)->delete('/lms/academic-classes/'.$class->id)->assertSessionHas('success');
        $this->assertDatabaseMissing('academic_classes', ['id' => $class->id]);
    }

    public function test_admin_can_create_update_and_delete_operational_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('role', 'admin')->firstOrFail();
        $student = User::where('email', 'student@greenfield.demo')->firstOrFail();

        $this->actingAs($admin)->post('/lms/fee-records', ['student_id' => $student->id, 'fee_type' => 'Demo Field Trip', 'amount' => 1200, 'paid_amount' => 0, 'due_date' => now()->addWeek()->toDateString(), 'reference' => 'CRUD-FEE-1'])->assertSessionHas('success');
        $fee = FeeRecord::where('reference', 'CRUD-FEE-1')->firstOrFail();
        $this->actingAs($admin)->patch('/lms/fee-records/'.$fee->id, ['student_id' => $student->id, 'fee_type' => 'Updated Field Trip', 'amount' => 1200, 'paid_amount' => 600, 'due_date' => now()->addWeek()->toDateString(), 'reference' => 'CRUD-FEE-1', 'payment_method' => 'Cash', 'paid_on' => now()->toDateString()])->assertSessionHas('success');
        $this->assertDatabaseHas('fee_records', ['id' => $fee->id, 'status' => 'partial']);
        $this->actingAs($admin)->delete('/lms/fee-records/'.$fee->id)->assertSessionHas('success');

        $this->actingAs($admin)->post('/lms/book-issues', ['student_id' => $student->id, 'book_title' => 'CRUD Book', 'author' => 'Demo Author', 'accession_number' => 'CRUD-LIB-1', 'issued_on' => now()->toDateString(), 'due_on' => now()->addWeek()->toDateString(), 'status' => 'issued'])->assertSessionHas('success');
        $issue = BookIssue::where('accession_number', 'CRUD-LIB-1')->firstOrFail();
        $this->actingAs($admin)->patch('/lms/book-issues/'.$issue->id, ['student_id' => $student->id, 'book_title' => 'CRUD Book Revised', 'author' => 'Demo Author', 'accession_number' => 'CRUD-LIB-1', 'issued_on' => now()->toDateString(), 'due_on' => now()->addWeek()->toDateString(), 'returned_on' => now()->toDateString(), 'status' => 'returned'])->assertSessionHas('success');
        $this->actingAs($admin)->delete('/lms/book-issues/'.$issue->id)->assertSessionHas('success');

        $this->actingAs($admin)->post('/lms/school-events', ['title' => 'CRUD Event', 'description' => 'Created by the feature test.', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour(), 'location' => 'Hall', 'type' => 'academic', 'audience' => 'all'])->assertSessionHas('success');
        $event = SchoolEvent::where('title', 'CRUD Event')->firstOrFail();
        $this->actingAs($admin)->patch('/lms/school-events/'.$event->id, ['title' => 'CRUD Event Updated', 'description' => 'Updated by the feature test.', 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour(), 'location' => 'Auditorium', 'type' => 'academic', 'audience' => 'student'])->assertSessionHas('success');
        $this->actingAs($admin)->delete('/lms/school-events/'.$event->id)->assertSessionHas('success');
        $this->assertDatabaseMissing('school_events', ['id' => $event->id]);
    }

    public function test_teacher_can_manage_owned_learning_work_and_grading(): void
    {
        $this->seed(DatabaseSeeder::class);
        $teacher = User::where('email', 'teacher@greenfield.demo')->firstOrFail();
        $student = User::where('email', 'student@greenfield.demo')->firstOrFail();
        $subject = Subject::where('teacher_id', $teacher->id)->firstOrFail();

        $this->actingAs($teacher)->post('/lms/assignments', ['subject_id' => $subject->id, 'title' => 'CRUD Assignment', 'instructions' => 'Complete this detailed practice task.', 'due_at' => now()->addDays(3), 'max_marks' => 20, 'status' => 'draft'])->assertSessionHas('success');
        $assignment = Assignment::where('title', 'CRUD Assignment')->firstOrFail();
        $this->actingAs($teacher)->patch('/lms/assignments/'.$assignment->id, ['title' => 'CRUD Assignment Updated', 'instructions' => 'Complete this updated detailed practice task.', 'due_at' => now()->addDays(4), 'max_marks' => 25, 'status' => 'draft'])->assertSessionHas('success');
        $this->actingAs($teacher)->delete('/lms/assignments/'.$assignment->id)->assertSessionHas('success');

        $this->actingAs($teacher)->post('/lms/quizzes', ['subject_id' => $subject->id, 'title' => 'CRUD Quiz', 'duration_minutes' => 10, 'available_until' => now()->addWeek(), 'status' => 'draft', 'question' => 'What is two plus two?', 'option_a' => '2', 'option_b' => '3', 'option_c' => '4', 'option_d' => '5', 'answer' => 'option_c'])->assertSessionHas('success');
        $quiz = Quiz::where('title', 'CRUD Quiz')->firstOrFail();
        $this->actingAs($teacher)->patch('/lms/quizzes/'.$quiz->id, ['title' => 'CRUD Quiz Updated', 'duration_minutes' => 12, 'available_until' => now()->addDays(8), 'status' => 'draft'])->assertSessionHas('success');
        $this->actingAs($teacher)->delete('/lms/quizzes/'.$quiz->id)->assertSessionHas('success');

        $this->actingAs($teacher)->post('/lms/learning-items', ['subject_id' => $subject->id, 'type' => 'lesson-plan', 'title' => 'CRUD Lesson Plan', 'description' => 'Objectives, activity and an exit assessment.', 'status' => 'draft'])->assertSessionHas('success');
        $item = LearningItem::where('title', 'CRUD Lesson Plan')->firstOrFail();
        $this->actingAs($teacher)->patch('/lms/learning-items/'.$item->id, ['subject_id' => $subject->id, 'type' => 'lesson-plan', 'title' => 'CRUD Lesson Plan Updated', 'description' => 'Updated objectives, activity and an exit assessment.', 'status' => 'draft'])->assertSessionHas('success');
        $this->actingAs($teacher)->delete('/lms/learning-items/'.$item->id)->assertSessionHas('success');

        $this->actingAs($teacher)->post('/lms/exam-results', ['student_id' => $student->id, 'subject_id' => $subject->id, 'exam_name' => 'CRUD Assessment', 'exam_date' => now()->toDateString(), 'marks' => 18, 'max_marks' => 20, 'is_published' => 0])->assertSessionHas('success');
        $result = ExamResult::where('exam_name', 'CRUD Assessment')->firstOrFail();
        $this->actingAs($teacher)->patch('/lms/exam-results/'.$result->id, ['student_id' => $student->id, 'subject_id' => $subject->id, 'exam_name' => 'CRUD Assessment', 'exam_date' => now()->toDateString(), 'marks' => 19, 'max_marks' => 20, 'is_published' => 1])->assertSessionHas('success');
        $this->assertDatabaseHas('exam_results', ['id' => $result->id, 'marks' => 19, 'grade' => 'A+']);

        $submission = AssignmentSubmission::whereHas('assignment', fn ($query) => $query->where('teacher_id', $teacher->id))->where('status', 'submitted')->firstOrFail();
        $this->actingAs($teacher)->patch('/lms/submissions/'.$submission->id.'/grade', ['marks' => 18, 'feedback' => 'Well structured and clearly explained.'])->assertSessionHas('success');
        $this->assertDatabaseHas('assignment_submissions', ['id' => $submission->id, 'status' => 'graded', 'marks' => 18]);
    }

    public function test_student_can_resubmit_manage_messages_and_update_profile(): void
    {
        $this->seed(DatabaseSeeder::class);
        $student = User::where('email', 'student@greenfield.demo')->firstOrFail();
        $teacher = User::where('email', 'teacher@greenfield.demo')->firstOrFail();
        $assignment = Assignment::where('teacher_id', $teacher->id)->where('due_at', '>', now())->firstOrFail();

        $this->actingAs($student)->post('/lms/assignments/'.$assignment->id.'/submissions', ['answer' => 'My first complete response with all working shown.'])->assertSessionHas('success');
        $this->actingAs($student)->post('/lms/assignments/'.$assignment->id.'/submissions', ['answer' => 'My revised complete response with clearer working shown.'])->assertSessionHas('success');
        $this->assertDatabaseHas('assignment_submissions', ['assignment_id' => $assignment->id, 'student_id' => $student->id, 'answer' => 'My revised complete response with clearer working shown.']);

        $this->actingAs($student)->post('/lms/messages', ['recipient_id' => $teacher->id, 'subject' => 'CRUD Student Doubt', 'body' => 'Could you please clarify the final step?'])->assertSessionHas('success');
        $message = Message::where('subject', 'CRUD Student Doubt')->firstOrFail();
        $this->actingAs($student)->delete('/lms/messages/'.$message->id)->assertSessionHas('success');
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);

        $this->actingAs($student)->patch('/lms/profile', ['name' => 'Aarav Kapoor Updated', 'email' => $student->email, 'phone' => '+91 99999 00001'])->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $student->id, 'name' => 'Aarav Kapoor Updated']);
    }

    public function test_parent_can_manage_requests_documents_and_notifications(): void
    {
        $this->seed(DatabaseSeeder::class);
        $parent = User::where('email', 'parent@greenfield.demo')->firstOrFail();
        $student = $parent->children()->orderBy('users.id')->firstOrFail();
        $teacher = User::where('email', 'teacher@greenfield.demo')->firstOrFail();

        $this->actingAs($parent)->post('/lms/support', ['student_id' => $student->id, 'category' => 'Technical', 'priority' => 'Normal', 'subject' => 'CRUD Support', 'details' => 'Please help with the learner dashboard display.'])->assertSessionHas('success');
        $support = ModuleRecord::where('title', 'CRUD Support')->firstOrFail();
        $this->actingAs($parent)->patch('/lms/module-records/'.$support->id, ['module' => 'support', 'audience' => 'parent', 'student_id' => $student->id, 'title' => 'CRUD Support Updated', 'subtitle' => 'Technical · Normal', 'description' => 'The issue now occurs only on the tablet.', 'status' => 'open'])->assertSessionHas('success');
        $this->actingAs($parent)->delete('/lms/module-records/'.$support->id)->assertSessionHas('success');

        $this->actingAs($parent)->post('/lms/ptm', ['student_id' => $student->id, 'teacher_id' => $teacher->id, 'preferred_at' => now()->addDays(3), 'topic' => 'Discuss current mathematics learning goals.'])->assertSessionHas('success');
        $ptm = ModuleRecord::where('module', 'ptm')->where('creator_id', $parent->id)->latest('id')->firstOrFail();
        $this->actingAs($parent)->patch('/lms/module-records/'.$ptm->id, ['module' => 'ptm', 'audience' => 'parent', 'student_id' => $student->id, 'title' => $ptm->title, 'subtitle' => 'Preferred afternoon slot', 'description' => 'Discuss revised mathematics learning goals.', 'status' => 'requested', 'occurred_at' => now()->addDays(4)])->assertSessionHas('success');

        $this->actingAs($parent)->post('/lms/module-records', ['module' => 'documents', 'audience' => 'parent', 'student_id' => $student->id, 'title' => 'CRUD Identity Document', 'subtitle' => 'Parent submitted', 'description' => 'Identity document submitted for verification.', 'status' => 'processing'])->assertSessionHas('success');
        $document = ModuleRecord::where('title', 'CRUD Identity Document')->firstOrFail();
        $this->actingAs($parent)->delete('/lms/module-records/'.$document->id)->assertSessionHas('success');

        $notification = ModuleRecord::where('module', 'notifications')->where('status', 'unread')->firstOrFail();
        $this->actingAs($parent)->patch('/lms/notifications/'.$notification->id.'/read')->assertSessionHas('success');
        $this->assertDatabaseHas('module_records', ['id' => $notification->id, 'status' => 'read']);
    }

    public function test_permission_changes_are_enforced_on_write_endpoints(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('role', 'admin')->firstOrFail();
        $teacher = User::where('email', 'teacher@greenfield.demo')->firstOrFail();
        $student = User::where('email', 'student@greenfield.demo')->firstOrFail();
        $enabled = RolePermission::where('role', 'teacher')->where('is_allowed', true)->pluck('permission')->reject(fn ($permission) => $permission === 'attendance.manage')->values()->all();

        $this->actingAs($admin)->put('/lms/roles/teacher/permissions', ['permissions' => $enabled])->assertSessionHas('success');
        $this->actingAs($teacher)->post('/lms/attendance', ['student_id' => $student->id, 'date' => now()->toDateString(), 'status' => 'present'])->assertForbidden();
        $this->actingAs($teacher)->get('/lms/modules/attendance')->assertOk()->assertDontSee('Manage attendance');
    }

    public function test_documents_are_private_downloadable_and_removed_with_their_record(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $parent = User::where('email', 'parent@greenfield.demo')->firstOrFail();
        $student = $parent->children()->firstOrFail();

        $this->actingAs($parent)->post('/lms/module-records', [
            'module' => 'documents', 'audience' => 'parent', 'student_id' => $student->id,
            'title' => 'Transfer Certificate Scan', 'status' => 'processing',
            'attachment' => UploadedFile::fake()->create('transfer-certificate.pdf', 120, 'application/pdf'),
        ])->assertSessionHas('success');

        $document = ModuleRecord::where('title', 'Transfer Certificate Scan')->firstOrFail();
        Storage::disk('local')->assertExists($document->meta['attachment_path']);
        $this->actingAs($parent)->get('/lms/module-records/'.$document->id.'/download')->assertOk();
        $path = $document->meta['attachment_path'];
        $this->actingAs($parent)->delete('/lms/module-records/'.$document->id)->assertSessionHas('success');
        Storage::disk('local')->assertMissing($path);
    }

    public function test_timetable_rejects_teacher_room_or_class_collisions(): void
    {
        $this->seed(DatabaseSeeder::class);
        ModuleRecord::where('module', 'timetable')->delete();
        $admin = User::where('role', 'admin')->firstOrFail();
        $subject = Subject::with('teacher')->firstOrFail();
        $payload = [
            'module' => 'timetable', 'audience' => 'all', 'title' => 'Period One', 'status' => 'scheduled',
            'subject_id' => $subject->id, 'owner_id' => $subject->teacher_id, 'day' => 'Mon', 'period' => 1, 'room' => 'A-101',
        ];

        $this->actingAs($admin)->post('/lms/module-records', $payload)->assertSessionHas('success');
        $this->actingAs($admin)->post('/lms/module-records', [...$payload, 'title' => 'Conflicting Period'])->assertSessionHasErrors('period');
        $this->assertDatabaseMissing('module_records', ['title' => 'Conflicting Period']);
    }

    public function test_students_cannot_see_or_submit_teacher_drafts(): void
    {
        $this->seed(DatabaseSeeder::class);
        $student = User::where('email', 'student@greenfield.demo')->firstOrFail();
        $subject = Subject::where('academic_class_id', $student->studentProfile->academic_class_id)->firstOrFail();
        $draft = Assignment::create([
            'subject_id' => $subject->id, 'teacher_id' => $subject->teacher_id, 'title' => 'Private Teacher Draft',
            'instructions' => 'This must remain private until it is published.', 'due_at' => now()->addWeek(), 'max_marks' => 10, 'status' => 'draft',
        ]);

        $this->actingAs($student)->get('/lms/modules/assignments')->assertOk()->assertDontSee('Private Teacher Draft');
        $this->actingAs($student)->post('/lms/assignments/'.$draft->id.'/submissions', ['answer' => 'This draft should not accept student work.'])->assertNotFound();
    }

    public function test_stakeholder_create_actions_use_modal_forms_and_not_manage_labels(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach ([
            ['admin', '/lms/modules/users', 'Create user'],
            ['teacher', '/lms/modules/assignments', 'Create assignment'],
            ['student', '/lms/modules/messages', 'Create message'],
            ['parent', '/lms/modules/support', 'Create support ticket'],
        ] as [$role, $url, $submitLabel]) {
            $user = User::where('role', $role)->firstOrFail();
            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertSee('Create</button>', false)
                ->assertSee('x-show="modal===\'create\'"', false)
                ->assertSee($submitLabel)
                ->assertDontSee('>Manage</a>', false);
        }
    }
}
