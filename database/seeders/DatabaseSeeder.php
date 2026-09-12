<?php

namespace Database\Seeders;

use App\Models\AcademicClass;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\BookIssue;
use App\Models\ExamResult;
use App\Models\FeeRecord;
use App\Models\LearningItem;
use App\Models\LeaveRequest;
use App\Models\Message;
use App\Models\Notice;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SchoolEvent;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $principal = User::create(['name' => 'Dr. Maya Sharma', 'email' => 'principal@greenfield.demo', 'username' => 'principal', 'role' => 'admin', 'phone' => '+91 98765 41001', 'avatar_color' => '#0f766e', 'password' => Hash::make('Demo@123')]);
        $teacher = User::create(['name' => 'Arjun Mehta', 'email' => 'teacher@greenfield.demo', 'username' => 'teacher', 'role' => 'teacher', 'phone' => '+91 98765 41002', 'avatar_color' => '#2563eb', 'password' => Hash::make('Demo@123')]);
        $student = User::create(['name' => 'Aarav Kapoor', 'email' => 'student@greenfield.demo', 'username' => 'student', 'role' => 'student', 'phone' => '+91 98765 41003', 'avatar_color' => '#7c3aed', 'password' => Hash::make('Demo@123')]);
        $parent = User::create(['name' => 'Neha Kapoor', 'email' => 'parent@greenfield.demo', 'username' => 'parent', 'role' => 'parent', 'phone' => '+91 98765 41004', 'avatar_color' => '#ea580c', 'password' => Hash::make('Demo@123')]);
        $teacherTwo = User::create(['name' => 'Priya Nair', 'email' => 'priya@greenfield.demo', 'username' => 'priya.nair', 'role' => 'teacher', 'password' => Hash::make('Demo@123')]);

        $class = AcademicClass::create(['name' => 'Grade 8', 'section' => 'A', 'room' => 'B-204', 'capacity' => 35, 'academic_year' => '2026-27']);
        AcademicClass::create(['name' => 'Grade 7', 'section' => 'B', 'room' => 'A-108', 'capacity' => 32, 'academic_year' => '2026-27']);
        StudentProfile::create(['user_id' => $student->id, 'academic_class_id' => $class->id, 'admission_number' => 'GIS-2024-0817', 'date_of_birth' => '2013-03-18', 'blood_group' => 'B+', 'house' => 'Emerald', 'address' => '42 Lake View Road, Bengaluru']);
        $studentTwo = User::create(['name' => 'Ananya Rao', 'email' => 'ananya@greenfield.demo', 'username' => 'ananya.rao', 'role' => 'student', 'avatar_color' => '#0891b2', 'password' => Hash::make('Demo@123')]);
        StudentProfile::create(['user_id' => $studentTwo->id, 'academic_class_id' => $class->id, 'admission_number' => 'GIS-2024-0818', 'date_of_birth' => '2013-07-07', 'blood_group' => 'O+', 'house' => 'Sapphire', 'address' => 'Indiranagar, Bengaluru']);
        DB::table('parent_student')->insert([
            ['parent_id' => $parent->id, 'student_id' => $student->id, 'relationship' => 'Mother', 'created_at' => now(), 'updated_at' => now()],
            ['parent_id' => $parent->id, 'student_id' => $studentTwo->id, 'relationship' => 'Guardian', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $subjects = collect([
            ['name' => 'Mathematics', 'code' => 'MAT-8A', 'color' => '#2563eb', 'progress' => 72, 'teacher_id' => $teacher->id],
            ['name' => 'Science', 'code' => 'SCI-8A', 'color' => '#0f766e', 'progress' => 64, 'teacher_id' => $teacherTwo->id],
            ['name' => 'English', 'code' => 'ENG-8A', 'color' => '#7c3aed', 'progress' => 81, 'teacher_id' => $teacherTwo->id],
            ['name' => 'Social Science', 'code' => 'SST-8A', 'color' => '#ea580c', 'progress' => 58, 'teacher_id' => $teacherTwo->id],
            ['name' => 'Computer Science', 'code' => 'CSC-8A', 'color' => '#0891b2', 'progress' => 76, 'teacher_id' => $teacher->id],
            ['name' => 'Hindi', 'code' => 'HIN-8A', 'color' => '#db2777', 'progress' => 69, 'teacher_id' => $teacherTwo->id],
        ])->map(fn (array $data): Subject => Subject::create($data + ['academic_class_id' => $class->id]));

        foreach (range(1, 22) as $offset) {
            $date = now()->subWeekdays(22 - $offset)->toDateString();
            AttendanceRecord::create(['student_id' => $student->id, 'marked_by' => $teacher->id, 'date' => $date, 'status' => in_array($offset, [6, 17], true) ? 'absent' : ($offset === 12 ? 'late' : 'present')]);
            AttendanceRecord::create(['student_id' => $studentTwo->id, 'marked_by' => $teacher->id, 'date' => $date, 'status' => $offset === 9 ? 'absent' : 'present']);
        }

        Assignment::create(['subject_id' => $subjects[0]->id, 'teacher_id' => $teacher->id, 'title' => 'Linear Equations Practice', 'instructions' => 'Solve questions 1–12 and explain your method for the final two problems.', 'due_at' => now()->addDays(3)->setTime(23, 59), 'max_marks' => 20]);
        $graded = Assignment::create(['subject_id' => $subjects[4]->id, 'teacher_id' => $teacher->id, 'title' => 'Build a Semantic Web Page', 'instructions' => 'Create a one-page profile using semantic HTML elements.', 'due_at' => now()->subDays(5), 'max_marks' => 25]);
        AssignmentSubmission::create(['assignment_id' => $graded->id, 'student_id' => $student->id, 'answer' => 'I used header, nav, main, article and footer elements with accessible labels.', 'submitted_at' => now()->subDays(7), 'marks' => 23, 'feedback' => 'Excellent semantic structure. Add a skip link next time.', 'status' => 'graded']);

        Quiz::create(['subject_id' => $subjects[0]->id, 'teacher_id' => $teacher->id, 'title' => 'Algebra Quick Check', 'duration_minutes' => 10, 'available_until' => now()->addWeek(), 'questions' => [
            ['question' => 'What is x if 2x + 4 = 12?', 'options' => ['2', '4', '6', '8'], 'answer' => '4'],
            ['question' => 'Which expression equals 3(a + 2)?', 'options' => ['3a + 2', '3a + 5', '3a + 6', 'a + 6'], 'answer' => '3a + 6'],
            ['question' => 'What is the coefficient of y in 7y - 3?', 'options' => ['-3', '3', '7', 'y'], 'answer' => '7'],
        ]]);
        $completedQuiz = Quiz::create(['subject_id' => $subjects[4]->id, 'teacher_id' => $teacher->id, 'title' => 'HTML Foundations', 'duration_minutes' => 8, 'available_until' => now()->addDays(10), 'questions' => [['question' => 'Which element is the primary page heading?', 'options' => ['h1', 'head', 'header', 'title'], 'answer' => 'h1']]]);
        QuizAttempt::create(['quiz_id' => $completedQuiz->id, 'student_id' => $student->id, 'answers' => ['h1'], 'score' => 1, 'total' => 1, 'completed_at' => now()->subDays(2)]);

        foreach ($subjects->take(5) as $index => $subject) {
            $mark = [88, 84, 91, 79, 94][$index];
            ExamResult::create(['student_id' => $student->id, 'subject_id' => $subject->id, 'exam_name' => 'Term I Examination', 'exam_date' => now()->subMonth()->addDays($index), 'marks' => $mark, 'max_marks' => 100, 'grade' => $mark >= 90 ? 'A+' : ($mark >= 80 ? 'A' : 'B+'), 'is_published' => true]);
        }

        FeeRecord::create(['student_id' => $student->id, 'fee_type' => 'Term II Tuition Fee', 'amount' => 28500, 'paid_amount' => 15000, 'due_date' => now()->addDays(12), 'status' => 'partial', 'reference' => 'GIS-FEE-2026-1842', 'payment_method' => 'Bank transfer', 'paid_on' => now()->subDays(8)]);
        FeeRecord::create(['student_id' => $student->id, 'fee_type' => 'Annual Activities Fee', 'amount' => 4500, 'paid_amount' => 4500, 'due_date' => now()->subMonth(), 'status' => 'paid', 'reference' => 'GIS-FEE-2026-1331', 'payment_method' => 'Cash', 'paid_on' => now()->subMonth()->subDays(4)]);
        BookIssue::create(['student_id' => $student->id, 'book_title' => 'A Brief History of Time', 'author' => 'Stephen Hawking', 'accession_number' => 'LIB-004281', 'issued_on' => now()->subDays(6), 'due_on' => now()->addDays(8)]);

        foreach ([['content', 'Polynomials: Concept Notes', 'Factorisation patterns with worked examples.', null, null], ['live-class', 'Algebra Doubt Clinic', 'Optional revision session before the quick check.', 'https://meet.google.com/', now()->addDays(2)->setTime(16, 0)], ['recording', 'Linear Equations – Recorded Lesson', 'Complete lesson recording and chapter recap.', 'https://www.youtube.com/', now()->subDays(2)], ['lesson-plan', 'Week 6: Linear Equations', 'Learning objectives, activity plan and exit ticket.', null, now()->startOfWeek()]] as [$type, $title, $description, $url, $scheduledAt]) {
            LearningItem::create(['subject_id' => $subjects[0]->id, 'teacher_id' => $teacher->id, 'type' => $type, 'title' => $title, 'description' => $description, 'url' => $url, 'scheduled_at' => $scheduledAt]);
        }

        Notice::create(['author_id' => $principal->id, 'title' => 'Founders Day celebrations', 'body' => 'Students should report in house uniform by 8:15 AM. Parents are warmly invited to the cultural showcase.', 'audience' => 'all', 'priority' => 'important', 'published_at' => now()->subHours(3)]);
        Notice::create(['author_id' => $teacher->id, 'title' => 'Grade 8 mathematics revision', 'body' => 'Revision worksheets are now available in Study Material. Bring geometry instruments on Monday.', 'audience' => 'student', 'priority' => 'normal', 'published_at' => now()->subDay()]);
        SchoolEvent::create(['title' => 'Founders Day Showcase', 'description' => 'Annual cultural and innovation showcase.', 'starts_at' => now()->addDays(5)->setTime(9, 0), 'ends_at' => now()->addDays(5)->setTime(13, 0), 'location' => 'School Auditorium', 'type' => 'cultural']);
        SchoolEvent::create(['title' => 'Parent-Teacher Meeting', 'description' => 'Term I progress discussion.', 'starts_at' => now()->addDays(9)->setTime(10, 0), 'ends_at' => now()->addDays(9)->setTime(15, 0), 'location' => 'Grade 8 Block', 'type' => 'ptm', 'audience' => 'parent']);
        LeaveRequest::create(['user_id' => $parent->id, 'student_id' => $student->id, 'from_date' => now()->addDays(14), 'to_date' => now()->addDays(15), 'reason' => 'Family wedding outside the city.', 'status' => 'pending']);
        Message::create(['sender_id' => $student->id, 'recipient_id' => $teacher->id, 'subject' => 'Doubt: exercise 4.2', 'body' => 'Could you explain why we change the sign when moving a term across the equals sign?']);
        Message::create(['sender_id' => $teacher->id, 'recipient_id' => $parent->id, 'subject' => 'Aarav’s mathematics progress', 'body' => 'Aarav is participating well and has shown a strong improvement in algebra this month.', 'is_read' => true]);

        $this->call(ExpandedDemoSeeder::class);
    }
}
