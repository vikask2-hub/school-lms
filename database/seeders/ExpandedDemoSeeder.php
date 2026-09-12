<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\BookIssue;
use App\Models\ExamResult;
use App\Models\FeeRecord;
use App\Models\LearningItem;
use App\Models\LeaveRequest;
use App\Models\Message;
use App\Models\ModuleRecord;
use App\Models\Notice;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\RolePermission;
use App\Models\SchoolEvent;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;

class ExpandedDemoSeeder extends Seeder
{
    public function run(): void
    {
        $principal = User::where('email', 'principal@greenfield.demo')->firstOrFail();
        $teacher = User::where('email', 'teacher@greenfield.demo')->firstOrFail();
        $teacherTwo = User::where('email', 'priya@greenfield.demo')->firstOrFail();
        $aarav = User::where('email', 'student@greenfield.demo')->firstOrFail();
        $ananya = User::where('email', 'ananya@greenfield.demo')->firstOrFail();
        $parent = User::where('email', 'parent@greenfield.demo')->firstOrFail();
        $subjects = Subject::get()->keyBy('name');

        foreach ([
            ['Science Lab Observation', 'Record the reaction, observations and conclusion from the acids and bases lab.', 'Science', $teacherTwo, 6, 30],
            ['Character Sketch: Portia', 'Write a structured character sketch with two quotations from the text.', 'English', $teacherTwo, 8, 20],
            ['Climate Zones Infographic', 'Create an infographic comparing tropical, temperate and polar climate zones.', 'Social Science', $teacherTwo, 11, 25],
            ['Python Pattern Challenge', 'Submit a program that prints three requested number patterns.', 'Computer Science', $teacher, 14, 20],
        ] as [$title, $instructions, $subjectName, $owner, $days, $marks]) {
            Assignment::updateOrCreate(['title' => $title], [
                'subject_id' => $subjects[$subjectName]->id,
                'teacher_id' => $owner->id,
                'instructions' => $instructions,
                'due_at' => now()->addDays($days)->setTime(23, 59),
                'max_marks' => $marks,
                'status' => 'published',
            ]);
        }

        $demoAssignments = Assignment::orderBy('id')->get();
        foreach ([
            [$demoAssignments->firstWhere('title', 'Linear Equations Practice'), $ananya, 'I solved each equation by performing the same operation on both sides and checked every answer.', 'submitted', null],
            [$demoAssignments->firstWhere('title', 'Science Lab Observation'), $aarav, 'The indicator changed from blue to red in the acidic sample. I recorded all steps and safety notes.', 'graded', 27],
            [$demoAssignments->firstWhere('title', 'Science Lab Observation'), $ananya, 'My observation table compares colour, pH and the neutralisation reaction for all samples.', 'submitted', null],
            [$demoAssignments->firstWhere('title', 'Character Sketch: Portia'), $aarav, 'Portia is intelligent and resourceful. Her courtroom speech reveals both logic and compassion.', 'graded', 18],
            [$demoAssignments->firstWhere('title', 'Climate Zones Infographic'), $ananya, 'The infographic uses temperature and rainfall bands with examples from each climate zone.', 'submitted', null],
        ] as [$assignment, $student, $answer, $status, $marks]) {
            if (! $assignment) {
                continue;
            }
            AssignmentSubmission::updateOrCreate(
                ['assignment_id' => $assignment->id, 'student_id' => $student->id],
                ['answer' => $answer, 'submitted_at' => now()->subDays(random_int(1, 4)), 'marks' => $marks, 'feedback' => $marks ? 'Clear reasoning and well-organised work.' : null, 'status' => $status],
            );
        }

        foreach ([
            ['Science Concepts Check', 'Science', $teacherTwo, 12, 9, [['question' => 'Which gas is released during photosynthesis?', 'options' => ['Oxygen', 'Nitrogen', 'Hydrogen', 'Carbon dioxide'], 'answer' => 'Oxygen'], ['question' => 'A substance with pH 3 is?', 'options' => ['Acidic', 'Basic', 'Neutral', 'Metallic'], 'answer' => 'Acidic']]],
            ['Grammar in Context', 'English', $teacherTwo, 10, 12, [['question' => 'Choose the adverb.', 'options' => ['Quick', 'Quickly', 'Quicker', 'Quickness'], 'answer' => 'Quickly'], ['question' => 'Which sentence is passive?', 'options' => ['Maya wrote the note.', 'The note was written by Maya.', 'Maya is writing.', 'Write the note.'], 'answer' => 'The note was written by Maya.']]],
            ['Digital Citizenship', 'Computer Science', $teacher, 8, 15, [['question' => 'Which password is strongest?', 'options' => ['school123', 'password', 'G7!mQ2#vL9', 'aarav'], 'answer' => 'G7!mQ2#vL9'], ['question' => 'What should you do with a suspicious link?', 'options' => ['Open it', 'Forward it', 'Report and delete it', 'Reply'], 'answer' => 'Report and delete it']]],
        ] as [$title, $subjectName, $owner, $minutes, $days, $questions]) {
            Quiz::updateOrCreate(['title' => $title], ['subject_id' => $subjects[$subjectName]->id, 'teacher_id' => $owner->id, 'duration_minutes' => $minutes, 'available_until' => now()->addDays($days), 'questions' => $questions, 'status' => 'published']);
        }

        $scienceQuiz = Quiz::where('title', 'Science Concepts Check')->first();
        QuizAttempt::updateOrCreate(['quiz_id' => $scienceQuiz->id, 'student_id' => $ananya->id], ['answers' => ['Oxygen', 'Acidic'], 'score' => 2, 'total' => 2, 'completed_at' => now()->subDay()]);

        foreach ([[$aarav, [88, 84, 91, 79, 94, 86]], [$ananya, [92, 89, 87, 93, 90, 88]]] as [$student, $marks]) {
            foreach ($subjects->values() as $index => $subject) {
                $mark = $marks[$index];
                ExamResult::updateOrCreate(
                    ['student_id' => $student->id, 'subject_id' => $subject->id, 'exam_name' => 'Term I Examination'],
                    ['exam_date' => now()->subMonth()->addDays($index), 'marks' => $mark, 'max_marks' => 100, 'grade' => $mark >= 90 ? 'A+' : ($mark >= 80 ? 'A' : 'B+'), 'is_published' => true],
                );
            }
        }

        foreach ([
            [$ananya, 'Term II Tuition Fee', 28500, 28500, -3, 'paid', 'GIS-FEE-2026-1843'],
            [$ananya, 'Transport Fee · Term II', 9000, 4500, 16, 'partial', 'GIS-FEE-2026-1910'],
            [$aarav, 'Laboratory & Technology Fee', 3200, 0, 20, 'unpaid', 'GIS-FEE-2026-1922'],
        ] as [$student, $type, $amount, $paid, $days, $status, $reference]) {
            FeeRecord::updateOrCreate(['student_id' => $student->id, 'fee_type' => $type], ['amount' => $amount, 'paid_amount' => $paid, 'due_date' => now()->addDays($days), 'status' => $status, 'reference' => $reference, 'payment_method' => $paid ? 'Bank transfer' : null, 'paid_on' => $paid ? now()->subDays(5) : null]);
        }

        foreach ([
            [$aarav, 'The Boy Who Harnessed the Wind', 'William Kamkwamba', 'LIB-004314', 5, 9, 'issued'],
            [$ananya, 'The Blue Umbrella', 'Ruskin Bond', 'LIB-004332', 3, 11, 'issued'],
            [$ananya, 'Women in Science', 'Rachel Ignotofsky', 'LIB-003981', 28, -14, 'returned'],
        ] as [$student, $title, $author, $number, $issued, $due, $status]) {
            BookIssue::updateOrCreate(['accession_number' => $number], ['student_id' => $student->id, 'book_title' => $title, 'author' => $author, 'issued_on' => now()->subDays($issued), 'due_on' => now()->addDays($due), 'returned_on' => $status === 'returned' ? now()->subDays(15) : null, 'status' => $status]);
        }

        foreach ([
            ['content', 'Cell Structure Visual Notes', 'Labelled diagrams and a concise organelle comparison chart.', 'Science', $teacherTwo, null, -2],
            ['content', 'The Merchant of Venice Reading Guide', 'Act summaries, vocabulary and reflection prompts.', 'English', $teacherTwo, null, -4],
            ['content', 'Climate and Vegetation Atlas', 'Map-based revision pack with key case studies.', 'Social Science', $teacherTwo, null, -3],
            ['recording', 'Acids, Bases and Salts Recap', 'A 24-minute experiment recap with safety reminders.', 'Science', $teacherTwo, 'https://www.youtube.com/', -1],
            ['recording', 'Introduction to Python Loops', 'Worked examples for for-loops and while-loops.', 'Computer Science', $teacher, 'https://www.youtube.com/', -5],
            ['live-class', 'Science Viva Practice', 'Join a small-group practice session before the lab assessment.', 'Science', $teacherTwo, 'https://meet.google.com/', 3],
            ['live-class', 'English Reading Circle', 'Guided discussion on character motivation and theme.', 'English', $teacherTwo, 'https://meet.google.com/', 5],
            ['lesson-plan', 'Week 7: Acids and Bases', 'Inquiry activity, demonstration and exit check.', 'Science', $teacherTwo, null, 1],
            ['lesson-plan', 'Week 7: Python Iteration', 'Unplugged warm-up, coding lab and peer review.', 'Computer Science', $teacher, null, 2],
        ] as [$type, $title, $description, $subjectName, $owner, $url, $days]) {
            LearningItem::updateOrCreate(['type' => $type, 'title' => $title], ['subject_id' => $subjects[$subjectName]->id, 'teacher_id' => $owner->id, 'description' => $description, 'url' => $url, 'scheduled_at' => now()->addDays($days), 'status' => 'published']);
        }

        foreach ([
            ['Mid-term examination schedule published', 'The subject-wise schedule and room allocation are now available.', 'all', 'important', -2, $principal],
            ['Library reading challenge', 'Complete any three books this month and submit a short reflection.', 'student', 'normal', -3, $teacherTwo],
            ['Transport route timing update', 'Route 4 will depart ten minutes earlier from Monday.', 'parent', 'urgent', -1, $principal],
        ] as [$title, $body, $audience, $priority, $hours, $author]) {
            Notice::updateOrCreate(['title' => $title], ['author_id' => $author->id, 'body' => $body, 'audience' => $audience, 'priority' => $priority, 'published_at' => now()->addHours($hours)]);
        }

        foreach ([
            ['Inter-house Science Quiz', 'Team preliminaries and rapid-fire final.', 7, 9, 'Innovation Lab', 'academic', 'all'],
            ['Term II Mathematics Examination', 'Grade 8 written examination.', 18, 9, 'Room B-204', 'exam', 'student'],
            ['Sports Day Trials', 'Athletics selection trials for all houses.', 12, 7, 'School Ground', 'sports', 'all'],
            ['Teacher Development Workshop', 'Formative assessment strategies.', 15, 10, 'Conference Hall', 'training', 'teacher'],
        ] as [$title, $description, $days, $hour, $location, $type, $audience]) {
            SchoolEvent::updateOrCreate(['title' => $title], ['description' => $description, 'starts_at' => now()->addDays($days)->setTime($hour, 0), 'ends_at' => now()->addDays($days)->setTime($hour + 2, 0), 'location' => $location, 'type' => $type, 'audience' => $audience]);
        }

        foreach ([
            [$parent, $ananya, 21, 21, 'Dental appointment.', 'approved'],
            [$teacher, null, 24, 24, 'Professional certification examination.', 'pending'],
            [$parent, $aarav, -18, -17, 'Seasonal illness and doctor-advised rest.', 'approved'],
        ] as [$requester, $student, $from, $to, $reason, $status]) {
            LeaveRequest::updateOrCreate(['user_id' => $requester->id, 'student_id' => $student?->id, 'reason' => $reason], ['from_date' => now()->addDays($from), 'to_date' => now()->addDays($to), 'status' => $status, 'reviewed_by' => $status === 'approved' ? $principal->id : null]);
        }

        foreach ([
            [$teacher, $parent, 'Science project participation', 'Ananya has volunteered to lead the research section of her team project.'],
            [$parent, $teacher, 'Question about revision plan', 'Could you suggest the three most important algebra topics to revise this week?'],
            [$teacherTwo, $parent, 'English reading update', 'Both learners are contributing thoughtfully during the reading circle.'],
            [$ananya, $teacherTwo, 'Doubt: pH scale', 'Why is the pH scale logarithmic instead of linear?'],
            [$teacherTwo, $ananya, 'Re: Doubt: pH scale', 'Each whole pH value represents a tenfold change in hydrogen ion concentration.'],
            [$aarav, $teacher, 'Python loop exercise', 'Can I use a while-loop instead of a for-loop for question 5?'],
        ] as [$sender, $recipient, $subject, $body]) {
            Message::updateOrCreate(['sender_id' => $sender->id, 'recipient_id' => $recipient->id, 'subject' => $subject], ['body' => $body, 'is_read' => $recipient->id === $parent->id]);
        }

        $permissionDefaults = [
            'teacher' => ['attendance.manage', 'assignments.manage', 'quizzes.manage', 'learning.manage', 'marks.enter', 'feedback.manage', 'notices.manage', 'messages.manage', 'profile.update'],
            'student' => ['assignments.submit', 'quizzes.attempt', 'messages.manage', 'profile.update'],
            'parent' => ['messages.manage', 'leave.manage', 'ptm.manage', 'support.manage', 'documents.manage', 'profile.update'],
        ];
        foreach (['teacher', 'student', 'parent'] as $role) {
            foreach (array_keys(config('lms.permissions')) as $permission) {
                RolePermission::updateOrCreate(['role' => $role, 'permission' => $permission], ['is_allowed' => in_array($permission, $permissionDefaults[$role], true)]);
            }
        }

        $this->seedModuleRecords($principal, $teacher, $teacherTwo, $aarav, $ananya, $parent, $subjects);
    }

    private function seedModuleRecords(User $principal, User $teacher, User $teacherTwo, User $aarav, User $ananya, User $parent, $subjects): void
    {
        $records = [
            ['timetable', 'all', null, $subjects['Mathematics'], $teacher, 'Mathematics', 'Monday · Period 1', '08:30–09:15 · Grade 8A · Room B-204', 'scheduled', 4, ['day' => 'Mon', 'period' => '1', 'room' => 'B-204']],
            ['timetable', 'all', null, $subjects['Science'], $teacherTwo, 'Science', 'Monday · Period 2', '09:20–10:05 · Grade 8A · Lab 2', 'scheduled', 4, ['day' => 'Mon', 'period' => '2', 'room' => 'Lab 2']],
            ['timetable', 'all', null, $subjects['English'], $teacherTwo, 'English', 'Tuesday · Period 1', '08:30–09:15 · Grade 8A · Room B-204', 'scheduled', 5, ['day' => 'Tue', 'period' => '1', 'room' => 'B-204']],
            ['timetable', 'all', null, $subjects['Computer Science'], $teacher, 'Computer Science', 'Wednesday · Period 3', '10:20–11:05 · Grade 8A · Computer Lab', 'scheduled', 6, ['day' => 'Wed', 'period' => '3', 'room' => 'Computer Lab']],
            ['timetable', 'all', null, $subjects['Social Science'], $teacherTwo, 'Social Science', 'Thursday · Period 4', '11:10–11:55 · Grade 8A · Room B-204', 'scheduled', 7, ['day' => 'Thu', 'period' => '4', 'room' => 'B-204']],
            ['timetable', 'all', null, $subjects['Hindi'], $teacherTwo, 'Hindi', 'Friday · Period 2', '09:20–10:05 · Grade 8A · Room B-204', 'scheduled', 8, ['day' => 'Fri', 'period' => '2', 'room' => 'B-204']],

            ['curriculum', 'all', null, $subjects['Mathematics'], $teacher, 'Algebraic Expressions', 'Unit 4 · 8 lessons', '6 of 8 lessons complete', 'in-progress', -3, ['progress' => 75, 'standard' => 'CBSE 8.NS.2']],
            ['curriculum', 'all', null, $subjects['Science'], $teacherTwo, 'Acids, Bases and Salts', 'Unit 5 · 7 lessons', '4 of 7 lessons complete', 'in-progress', -2, ['progress' => 57, 'standard' => 'CBSE 8.CH.5']],
            ['curriculum', 'all', null, $subjects['English'], $teacherTwo, 'Drama and Dialogue', 'Unit 3 · 6 lessons', '5 of 6 lessons complete', 'in-progress', -4, ['progress' => 83, 'standard' => 'CBSE 8.EN.3']],
            ['curriculum', 'all', null, $subjects['Computer Science'], $teacher, 'Programming with Python', 'Unit 4 · 9 lessons', '6 of 9 lessons complete', 'in-progress', -1, ['progress' => 67, 'standard' => 'Digital Skills 8.4']],

            ['feedback', 'all', $aarav, $subjects['Mathematics'], $teacher, 'Strong algebraic reasoning', 'Academic · Positive', 'Aarav explains each step clearly and is ready for extension problems.', 'published', -2, ['rating' => 5]],
            ['feedback', 'all', $aarav, $subjects['English'], $teacherTwo, 'Reading response', 'Academic · Improvement', 'Use direct evidence from the text to strengthen written responses.', 'published', -5, ['rating' => 4]],
            ['feedback', 'all', $ananya, $subjects['Science'], $teacherTwo, 'Excellent lab teamwork', 'Academic · Positive', 'Ananya records observations precisely and supports her group.', 'published', -1, ['rating' => 5]],
            ['feedback', 'all', $ananya, $subjects['Mathematics'], $teacher, 'Check calculations carefully', 'Academic · Improvement', 'The method is strong; a final substitution check will prevent small errors.', 'published', -7, ['rating' => 4]],

            ['behavior', 'parent', $aarav, null, $teacher, 'Peer mentoring', 'Emerald house · +3 points', 'Helped two classmates debug their HTML project.', 'positive', -4, ['points' => 3]],
            ['behavior', 'parent', $aarav, null, $teacherTwo, 'Preparedness reminder', 'Classroom habit', 'Remembered materials after one reminder; follow-up complete.', 'resolved', -9, ['points' => 0]],
            ['behavior', 'parent', $ananya, null, $teacherTwo, 'Science lab leadership', 'Sapphire house · +5 points', 'Led safety checks and shared equipment responsibly.', 'positive', -2, ['points' => 5]],
            ['behavior', 'parent', $ananya, null, $principal, 'Assembly contribution', 'Sapphire house · +2 points', 'Presented the thought for the day with confidence.', 'positive', -6, ['points' => 2]],

            ['ptm', 'parent', $aarav, $subjects['Mathematics'], $teacher, 'Mathematics progress review', '13 Sep · 10:20 AM', '15-minute meeting · Grade 8 Block', 'confirmed', 9, ['slot' => '10:20 AM']],
            ['ptm', 'parent', $ananya, $subjects['Science'], $teacherTwo, 'Science learning review', '13 Sep · 11:00 AM', '15-minute meeting · Grade 8 Block', 'confirmed', 9, ['slot' => '11:00 AM']],
            ['ptm', 'parent', $aarav, $subjects['English'], $teacherTwo, 'English writing goals', '18 Sep · 3:30 PM', 'Requested follow-up video meeting', 'requested', 14, ['slot' => '3:30 PM']],

            ['documents', 'all', $aarav, null, $principal, 'Term I report card', 'Academic record · PDF', 'Verified and available in the student record.', 'ready', -12, ['size' => '284 KB']],
            ['documents', 'all', $aarav, null, $principal, 'Fee payment receipt', 'Receipt · PDF', 'Receipt GIS-FEE-2026-1331 for annual activities fee.', 'ready', -20, ['size' => '96 KB']],
            ['documents', 'all', $ananya, null, $principal, 'Student identity card', 'Identity document · PDF', 'Valid for academic year 2026-27.', 'ready', -25, ['size' => '142 KB']],
            ['documents', 'all', $ananya, null, $principal, 'Transfer certificate request', 'Administrative request', 'Submitted for office verification.', 'processing', -2, ['size' => null]],

            ['certificates', 'student', $aarav, null, $principal, 'Inter-house Coding Challenge', 'Certificate of achievement', 'Awarded for first place in the Grade 8 coding challenge.', 'issued', -35, ['certificate_no' => 'GIS-CERT-26041']],
            ['certificates', 'student', $aarav, null, $principal, 'Perfect Attendance · August', 'Certificate of merit', 'Recognises punctual and consistent participation.', 'issued', -4, ['certificate_no' => 'GIS-CERT-26102']],
            ['certificates', 'student', $ananya, null, $principal, 'Young Scientist Showcase', 'Certificate of excellence', 'Awarded for the water filtration working model.', 'issued', -18, ['certificate_no' => 'GIS-CERT-26088']],

            ['notifications', 'parent', null, null, $principal, 'Transport timing changed', 'Urgent · 1 hour ago', 'Route 4 departs ten minutes earlier from Monday.', 'unread', 0, ['icon' => 'bus']],
            ['notifications', 'parent', $aarav, null, $teacher, 'Assignment graded', 'Aarav · Mathematics', 'Linear Equations Practice feedback is available.', 'unread', -1, ['icon' => 'clipboard-check']],
            ['notifications', 'parent', $ananya, null, $teacherTwo, 'PTM slot confirmed', 'Ananya · Science', 'Your 11:00 AM appointment is confirmed.', 'read', -2, ['icon' => 'calendar-check']],
            ['notifications', 'parent', $aarav, null, $principal, 'Fee reminder', 'Aarav · Due in 12 days', 'A balance of ₹13,500 remains on the Term II fee record.', 'read', -3, ['icon' => 'receipt']],

            ['support', 'parent', null, null, $parent, 'Update emergency contact', 'Profile & records · Normal', 'Please replace the secondary contact number on both learner profiles.', 'open', -2, ['ticket' => 'SUP-1042']],
            ['support', 'parent', $aarav, null, $parent, 'Bus tracking access', 'Transport · High', 'The live route card is not appearing in the mobile view.', 'in-progress', -5, ['ticket' => 'SUP-1038']],
            ['support', 'parent', $ananya, null, $parent, 'Receipt name correction', 'Fees · Normal', 'The receipt should include the learner middle name.', 'resolved', -12, ['ticket' => 'SUP-1026']],

            ['settings', 'admin', null, null, $principal, 'Academic year', 'School calendar', '2026-27 · 1 April 2026 to 31 March 2027', 'configured', -30, ['value' => '2026-27']],
            ['settings', 'admin', null, null, $principal, 'Attendance rules', 'Academic policy', 'Late arrival counts present; alerts trigger below 75%.', 'configured', -20, ['value' => '75% threshold']],
            ['settings', 'admin', null, null, $principal, 'Grading scale', 'Assessment policy', 'A+ 90–100 · A 80–89 · B+ 70–79 · B 60–69', 'configured', -18, ['value' => 'CBSE scale']],
            ['settings', 'admin', null, null, $principal, 'Notification channels', 'Communications', 'In-app and email notifications enabled; SMS disabled.', 'configured', -10, ['value' => '2 active']],

            ['exams', 'all', null, $subjects['Mathematics'], $teacher, 'Term II Mathematics', '18 Sep · 9:00–11:00 AM', 'Room B-204 · 100 marks', 'scheduled', 18, ['duration' => '2 hours']],
            ['exams', 'all', null, $subjects['Science'], $teacherTwo, 'Term II Science', '20 Sep · 9:00–11:00 AM', 'Room B-204 · 100 marks', 'scheduled', 20, ['duration' => '2 hours']],
            ['exams', 'all', null, $subjects['English'], $teacherTwo, 'Term II English', '22 Sep · 9:00–11:00 AM', 'Room B-204 · 100 marks', 'scheduled', 22, ['duration' => '2 hours']],
            ['exams', 'all', null, $subjects['Computer Science'], $teacher, 'Computer Science Practical', '24 Sep · 10:00–11:30 AM', 'Computer Lab · 50 marks', 'scheduled', 24, ['duration' => '90 minutes']],

            ['chapters', 'student', null, $subjects['Mathematics'], $teacher, 'Linear Equations', 'Chapter 4 · Mathematics', '6 lessons · 5 completed', 'in-progress', -2, ['progress' => 83]],
            ['chapters', 'student', null, $subjects['Science'], $teacherTwo, 'Acids, Bases and Salts', 'Chapter 5 · Science', '7 lessons · 4 completed', 'in-progress', -1, ['progress' => 57]],
            ['chapters', 'student', null, $subjects['English'], $teacherTwo, 'The Merchant of Venice', 'Unit 3 · English', '6 lessons · 5 completed', 'in-progress', -3, ['progress' => 83]],
            ['chapters', 'student', null, $subjects['Computer Science'], $teacher, 'Loops in Python', 'Chapter 4 · Computer Science', '5 lessons · 3 completed', 'in-progress', 0, ['progress' => 60]],
        ];

        foreach ($records as [$module, $audience, $student, $subject, $owner, $title, $subtitle, $description, $status, $days, $meta]) {
            ModuleRecord::updateOrCreate(
                ['module' => $module, 'title' => $title, 'student_id' => $student?->id],
                ['audience' => $audience, 'subject_id' => $subject?->id, 'owner_id' => $owner?->id, 'subtitle' => $subtitle, 'description' => $description, 'status' => $status, 'occurred_at' => now()->addDays($days), 'meta' => $meta],
            );
        }

        ModuleRecord::where('module', 'support')->update(['creator_id' => $parent->id]);
        ModuleRecord::where('module', 'ptm')->where('status', 'requested')->update(['creator_id' => $parent->id]);
    }
}
