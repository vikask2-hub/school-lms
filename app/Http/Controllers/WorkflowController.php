<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\FeeRecord;
use App\Models\LeaveRequest;
use App\Models\Message;
use App\Models\ModuleRecord;
use App\Models\Notice;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    public function attendance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', Rule::exists('users', 'id')->where('role', 'student')],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);
        if ($request->user()->role === 'teacher') {
            abort_unless(User::whereKey($data['student_id'])->whereHas('studentProfile.academicClass.subjects', fn ($query) => $query->where('teacher_id', $request->user()->id))->exists(), 403);
        }
        $record = AttendanceRecord::where('student_id', $data['student_id'])->whereDate('date', $data['date'])->first();
        if ($record) {
            $record->update($data + ['marked_by' => $request->user()->id]);
        } else {
            $record = AttendanceRecord::create($data + ['marked_by' => $request->user()->id]);
        }
        $this->audit($request, 'Attendance recorded', $record);

        return back()->with('success', 'Attendance saved successfully.');
    }

    public function updateAttendance(Request $request, AttendanceRecord $attendanceRecord): RedirectResponse
    {
        if ($request->user()->role === 'teacher') {
            abort_unless(User::whereKey($attendanceRecord->student_id)->whereHas('studentProfile.academicClass.subjects', fn ($query) => $query->where('teacher_id', $request->user()->id))->exists(), 404);
        }
        $data = $request->validate(['status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])], 'remarks' => ['nullable', 'string', 'max:255']]);
        $attendanceRecord->update($data + ['marked_by' => $request->user()->id]);
        $this->audit($request, 'Attendance corrected', $attendanceRecord);

        return back()->with('success', 'Attendance record updated.');
    }

    public function destroyAttendance(Request $request, AttendanceRecord $attendanceRecord): RedirectResponse
    {
        if ($request->user()->role === 'teacher') {
            abort_unless($attendanceRecord->marked_by === $request->user()->id, 404);
        }
        $this->audit($request, 'Attendance record deleted', $attendanceRecord);
        $attendanceRecord->delete();

        return back()->with('success', 'Attendance record deleted.');
    }

    public function assignment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:150'],
            'instructions' => ['required', 'string', 'max:3000'],
            'due_at' => ['required', 'date', 'after:now'],
            'max_marks' => ['required', 'integer', 'min:1', 'max:500'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
        ]);
        if ($request->user()->role === 'teacher') {
            abort_unless(Subject::whereKey($data['subject_id'])->where('teacher_id', $request->user()->id)->exists(), 403);
        }
        $teacherId = $request->user()->role === 'teacher' ? $request->user()->id : Subject::findOrFail($data['subject_id'])->teacher_id;
        $assignment = Assignment::create($data + ['teacher_id' => $teacherId, 'status' => $data['status'] ?? 'published']);
        $this->audit($request, 'Assignment published', $assignment);

        return back()->with('success', $assignment->status === 'draft' ? 'Assignment saved as a draft.' : 'Assignment published to the class.');
    }

    public function submission(Request $request, Assignment $assignment): RedirectResponse
    {
        abort_unless($assignment->subject()->where('academic_class_id', $request->user()->studentProfile?->academic_class_id ?? -1)->exists(), 403);
        abort_unless($assignment->status === 'published', 404);
        abort_if($assignment->due_at->isPast(), 422, 'This assignment is closed.');
        $data = $request->validate(['answer' => ['required', 'string', 'min:20', 'max:10000']]);
        $submission = AssignmentSubmission::updateOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => $request->user()->id],
            $data + ['submitted_at' => now(), 'status' => 'submitted', 'marks' => null, 'feedback' => null],
        );
        $this->audit($request, 'Assignment submitted', $submission);

        return back()->with('success', 'Your assignment was submitted successfully.');
    }

    public function quizAttempt(Request $request, Quiz $quiz): RedirectResponse
    {
        abort_unless($quiz->subject()->where('academic_class_id', $request->user()->studentProfile?->academic_class_id ?? -1)->exists(), 403);
        abort_unless($quiz->status === 'published', 404);
        abort_if($quiz->available_until?->isPast(), 422, 'This quiz is no longer available.');
        abort_if(QuizAttempt::where('quiz_id', $quiz->id)->where('student_id', $request->user()->id)->exists(), 422, 'You already completed this quiz.');
        $answers = $request->validate(['answers' => ['required', 'array', 'size:'.count($quiz->questions)], 'answers.*' => ['required', 'string']])['answers'];
        $score = collect($quiz->questions)->filter(fn (array $question, int $index): bool => ($answers[$index] ?? null) === $question['answer'])->count();
        $attempt = QuizAttempt::create(['quiz_id' => $quiz->id, 'student_id' => $request->user()->id, 'answers' => $answers, 'score' => $score, 'total' => count($quiz->questions), 'completed_at' => now()]);
        $this->audit($request, 'Quiz completed', $attempt);

        return back()->with('success', "Quiz completed — you scored {$score}/".count($quiz->questions).'.');
    }

    public function feePayment(Request $request, FeeRecord $feeRecord): RedirectResponse
    {
        $outstanding = (float) $feeRecord->amount - (float) $feeRecord->paid_amount;
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$outstanding],
            'payment_method' => ['required', Rule::in(['Cash', 'Cheque', 'Bank transfer'])],
            'reference' => ['required', 'string', 'max:100'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
        ]);
        DB::transaction(function () use ($data, $feeRecord, $request): void {
            $record = FeeRecord::whereKey($feeRecord->id)->lockForUpdate()->firstOrFail();
            $record->update([
                'paid_amount' => (float) $record->paid_amount + (float) $data['amount'],
                'status' => (float) $record->paid_amount + (float) $data['amount'] >= (float) $record->amount ? 'paid' : 'partial',
                'payment_method' => $data['payment_method'], 'reference' => $data['reference'], 'paid_on' => $data['paid_on'],
            ]);
            $this->audit($request, 'Offline fee payment recorded', $record);
        });

        return back()->with('success', 'Offline payment added to the fee ledger.');
    }

    public function leave(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['nullable', 'exists:users,id'], 'from_date' => ['required', 'date', 'after_or_equal:today'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'], 'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        if ($request->user()->role === 'parent') {
            abort_unless($request->user()->children()->whereKey($data['student_id'] ?? 0)->exists(), 404);
        }
        $leave = LeaveRequest::create($data + ['user_id' => $request->user()->id, 'status' => 'pending']);
        $this->audit($request, 'Leave requested', $leave);

        return back()->with('success', 'Leave request submitted for review.');
    }

    public function reviewLeave(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])]]);
        $leaveRequest->update($data + ['reviewed_by' => $request->user()->id]);
        $this->audit($request, 'Leave request '.$data['status'], $leaveRequest);

        return back()->with('success', 'Leave request updated.');
    }

    public function cancelLeave(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless($leaveRequest->user_id === $request->user()->id, 404);
        abort_unless($leaveRequest->status === 'pending', 422, 'Only pending requests can be cancelled.');
        $leaveRequest->update(['status' => 'cancelled']);
        $this->audit($request, 'Leave request cancelled', $leaveRequest);

        return back()->with('success', 'Leave request cancelled.');
    }

    public function message(Request $request): RedirectResponse
    {
        $data = $request->validate(['recipient_id' => ['required', 'exists:users,id'], 'subject' => ['required', 'string', 'max:150'], 'body' => ['required', 'string', 'min:5', 'max:3000']]);
        $recipient = User::findOrFail($data['recipient_id']);
        abort_unless($this->canMessage($request->user(), $recipient), 403);
        Message::create($data + ['sender_id' => $request->user()->id]);

        return back()->with('success', 'Message sent securely.');
    }

    public function destroyMessage(Request $request, Message $message): RedirectResponse
    {
        abort_unless($message->sender_id === $request->user()->id, 404);
        $message->delete();

        return back()->with('success', 'Message removed from the conversation.');
    }

    public function support(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['nullable', 'exists:users,id'],
            'category' => ['required', Rule::in(['Profile & records', 'Academics', 'Transport', 'Fees', 'Technical'])],
            'priority' => ['required', Rule::in(['Normal', 'High', 'Urgent'])],
            'subject' => ['required', 'string', 'max:150'],
            'details' => ['required', 'string', 'min:10', 'max:3000'],
        ]);
        if ($data['student_id'] ?? null) {
            abort_unless($request->user()->children()->whereKey($data['student_id'])->exists(), 404);
        }
        $record = ModuleRecord::create([
            'module' => 'support', 'audience' => 'parent', 'student_id' => $data['student_id'] ?? null, 'owner_id' => $request->user()->id, 'creator_id' => $request->user()->id,
            'title' => $data['subject'], 'subtitle' => $data['category'].' · '.$data['priority'], 'description' => $data['details'],
            'status' => 'open', 'occurred_at' => now(), 'meta' => ['ticket' => 'SUP-'.now()->format('His')],
        ]);
        $this->audit($request, 'Support ticket created', $record);

        return back()->with('success', 'Support ticket created. The school office can now track it.');
    }

    public function ptm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:users,id'], 'teacher_id' => ['required', 'exists:users,id'],
            'preferred_at' => ['required', 'date', 'after:now'], 'topic' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        abort_unless($request->user()->children()->whereKey($data['student_id'])->exists(), 404);
        $student = User::with('studentProfile')->findOrFail($data['student_id']);
        abort_unless(Subject::where('academic_class_id', $student->studentProfile?->academic_class_id)->where('teacher_id', $data['teacher_id'])->exists(), 403);
        $teacher = User::findOrFail($data['teacher_id']);
        $record = ModuleRecord::create([
            'module' => 'ptm', 'audience' => 'parent', 'student_id' => $student->id, 'owner_id' => $teacher->id, 'creator_id' => $request->user()->id,
            'title' => 'Meeting request with '.$teacher->name, 'subtitle' => date('d M · g:i A', strtotime($data['preferred_at'])),
            'description' => $data['topic'], 'status' => 'requested', 'occurred_at' => $data['preferred_at'], 'meta' => [],
        ]);
        $this->audit($request, 'PTM requested', $record);

        return back()->with('success', 'Meeting request sent to the teacher.');
    }

    public function notice(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'body' => ['required', 'string', 'max:3000'], 'audience' => ['required', Rule::in(['all', 'teacher', 'student', 'parent'])], 'priority' => ['required', Rule::in(['normal', 'important', 'urgent'])]]);
        $notice = Notice::create($data + ['author_id' => $request->user()->id, 'published_at' => now()]);
        $this->audit($request, 'Notice published', $notice);

        return back()->with('success', 'Notice published.');
    }

    public function updateNotice(Request $request, Notice $notice): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin' || $notice->author_id === $request->user()->id, 404);
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'body' => ['required', 'string', 'max:3000'], 'audience' => ['required', Rule::in(['all', 'teacher', 'student', 'parent'])], 'priority' => ['required', Rule::in(['normal', 'important', 'urgent'])]]);
        $notice->update($data);
        $this->audit($request, 'Notice updated', $notice);

        return back()->with('success', 'Notice updated.');
    }

    public function destroyNotice(Request $request, Notice $notice): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin' || $notice->author_id === $request->user()->id, 404);
        $this->audit($request, 'Notice deleted', $notice);
        $notice->delete();

        return back()->with('success', 'Notice deleted.');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:180', Rule::unique('users')->ignore($user)], 'phone' => ['nullable', 'string', 'max:30'], 'password' => ['nullable', 'string', 'min:8', 'max:100', 'confirmed']]);
        $user->update(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null] + (! empty($data['password']) ? ['password' => Hash::make($data['password'])] : []));
        $this->audit($request, 'Profile updated', $user);

        return back()->with('success', 'Profile updated.');
    }

    private function audit(Request $request, string $action, object $subject): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'subject_type' => $subject::class, 'subject_id' => $subject->id, 'details' => [], 'ip_address' => $request->ip()]);
    }

    private function canMessage(User $sender, User $recipient): bool
    {
        if ($sender->role === 'teacher' && $recipient->role === 'student') {
            return Subject::where('teacher_id', $sender->id)->where('academic_class_id', $recipient->studentProfile?->academic_class_id ?? -1)->exists();
        }
        if ($sender->role === 'teacher' && $recipient->role === 'parent') {
            return $recipient->children()->whereHas('studentProfile.academicClass.subjects', fn ($query) => $query->where('teacher_id', $sender->id))->exists();
        }
        if ($sender->role === 'student' && $recipient->role === 'teacher') {
            return Subject::where('teacher_id', $recipient->id)->where('academic_class_id', $sender->studentProfile?->academic_class_id ?? -1)->exists();
        }
        if ($sender->role === 'parent' && $recipient->role === 'teacher') {
            return $sender->children()->whereHas('studentProfile.academicClass.subjects', fn ($query) => $query->where('teacher_id', $recipient->id))->exists();
        }

        return false;
    }
}
