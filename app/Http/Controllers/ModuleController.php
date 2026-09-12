<?php

namespace App\Http\Controllers;

use App\Models\AcademicClass;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function show(Request $request, string $module): View
    {
        $user = $request->user();
        $allowed = collect(config('lms.navigation.'.$user->role))->flatMap(fn (array $items): array => array_keys($items));
        abort_unless($allowed->contains($module), 403);
        $student = $this->studentContext($request);
        $studentId = $student?->id;

        return view('module', [
            'module' => $module,
            'title' => collect(config('lms.navigation.'.$user->role))->flatMap(fn (array $items): array => $items)->get($module, str($module)->headline()),
            'description' => config('lms.module_descriptions.'.$module, 'Review current records, status and next actions.'),
            'student' => $student,
            'children' => $user->role === 'parent' ? $user->children()->with('studentProfile.academicClass')->orderBy('users.id')->get() : collect(),
            'users' => $user->role === 'admin' ? User::with(['studentProfile.academicClass', 'children'])->orderBy('name')->get() : collect(),
            'contactOptions' => $this->contactOptions($user),
            'attendanceStudents' => $this->attendanceStudents($user),
            'classes' => $this->classes($user),
            'subjects' => $this->subjects($user, $student),
            'attendance' => $this->attendance($user, $studentId),
            'assignments' => $this->assignments($user, $student),
            'submissions' => $this->submissions($user),
            'quizzes' => $this->quizzes($user, $student),
            'attempts' => QuizAttempt::where('student_id', $studentId)->get()->keyBy('quiz_id'),
            'results' => $this->results($user, $studentId),
            'fees' => $this->fees($user, $studentId),
            'bookIssues' => $this->bookIssues($user, $studentId),
            'notices' => $this->notices($user),
            'events' => $this->events($user),
            'learningItems' => $this->learningItems($user, $student),
            'leaves' => $this->leaves($user, $studentId),
            'messages' => $this->messages($user, $module),
            'records' => $this->moduleRecords($user, $studentId, $module),
            'questionBank' => $this->questionBank($user),
            'rolePermissions' => $user->role === 'admin' ? RolePermission::orderBy('role')->orderBy('permission')->get()->groupBy('role') : collect(),
            'grantedPermissions' => $user->role === 'admin' ? collect(array_keys(config('lms.permissions'))) : RolePermission::where('role', $user->role)->where('is_allowed', true)->pluck('permission'),
            'auditLogs' => $user->role === 'admin' ? AuditLog::with('user')->latest()->limit(100)->get() : collect(),
        ]);
    }

    private function studentContext(Request $request): ?User
    {
        $user = $request->user();
        if ($user->role === 'student') {
            return $user->load('studentProfile.academicClass');
        }
        if ($user->role !== 'parent') {
            return null;
        }
        if ($request->filled('child')) {
            $request->session()->put('selected_child', $request->integer('child'));
        }
        $selectedId = $request->session()->get('selected_child');
        if ($selectedId) {
            $selected = $user->children()->with('studentProfile.academicClass')->find($selectedId);
            if ($selected) {
                return $selected;
            }
        }

        return $user->children()->with('studentProfile.academicClass')->orderBy('users.id')->first();
    }

    private function classes(User $user): Collection
    {
        return AcademicClass::withCount('students')->when($user->role === 'teacher', fn (Builder $query) => $query->whereHas('subjects', fn (Builder $subjects) => $subjects->where('teacher_id', $user->id)))->orderBy('name')->get();
    }

    private function subjects(User $user, ?User $student): Collection
    {
        return Subject::with(['teacher', 'academicClass'])
            ->when($user->role === 'teacher', fn (Builder $query) => $query->where('teacher_id', $user->id))
            ->when(in_array($user->role, ['student', 'parent'], true), fn (Builder $query) => $query->where('academic_class_id', $student?->studentProfile?->academic_class_id ?? -1))
            ->orderBy('name')->get();
    }

    private function attendanceStudents(User $user): Collection
    {
        if ($user->role === 'admin') {
            return User::where('role', 'student')->orderBy('name')->get();
        }
        if ($user->role !== 'teacher') {
            return collect();
        }

        return User::where('role', 'student')->whereHas('studentProfile.academicClass.subjects', fn (Builder $query) => $query->where('teacher_id', $user->id))->orderBy('name')->get();
    }

    private function attendance(User $user, ?int $studentId): Collection
    {
        return AttendanceRecord::with(['student', 'marker'])->latest('date')
            ->when(in_array($user->role, ['student', 'parent'], true), fn (Builder $query) => $query->where('student_id', $studentId))
            ->when($user->role === 'teacher', fn (Builder $query) => $query->whereHas('student.studentProfile.academicClass.subjects', fn (Builder $subjects) => $subjects->where('teacher_id', $user->id)))
            ->limit(60)->get();
    }

    private function assignments(User $user, ?User $student): Collection
    {
        return Assignment::with(['subject', 'submissions' => function ($query) use ($user, $student): void {
            if (in_array($user->role, ['student', 'parent'], true)) {
                $query->where('student_id', $student?->id ?? -1);
            }
        }])->latest('due_at')
            ->when($user->role === 'teacher', fn (Builder $query) => $query->where('teacher_id', $user->id))
            ->when(in_array($user->role, ['student', 'parent'], true), fn (Builder $query) => $query->where('status', 'published')->whereHas('subject', fn (Builder $subject) => $subject->where('academic_class_id', $student?->studentProfile?->academic_class_id ?? -1)))
            ->get();
    }

    private function submissions(User $user): Collection
    {
        return $user->role === 'teacher'
            ? AssignmentSubmission::with(['assignment.subject', 'student'])->whereHas('assignment', fn (Builder $query) => $query->where('teacher_id', $user->id))->latest('submitted_at')->get()
            : collect();
    }

    private function quizzes(User $user, ?User $student): Collection
    {
        return Quiz::with('subject')->latest()
            ->when($user->role === 'teacher', fn (Builder $query) => $query->where('teacher_id', $user->id))
            ->when($user->role === 'student', fn (Builder $query) => $query->where('status', 'published')->whereHas('subject', fn (Builder $subject) => $subject->where('academic_class_id', $student?->studentProfile?->academic_class_id ?? -1)))
            ->get();
    }

    private function results(User $user, ?int $studentId): Collection
    {
        return ExamResult::with(['student', 'subject'])->latest('exam_date')
            ->when(in_array($user->role, ['student', 'parent'], true), fn (Builder $query) => $query->where('student_id', $studentId)->where('is_published', true))
            ->when($user->role === 'teacher', fn (Builder $query) => $query->whereHas('subject', fn (Builder $subject) => $subject->where('teacher_id', $user->id)))
            ->get();
    }

    private function fees(User $user, ?int $studentId): Collection
    {
        return FeeRecord::with('student')->latest('due_date')->when($user->role !== 'admin', fn (Builder $query) => $query->where('student_id', $studentId ?? -1))->get();
    }

    private function bookIssues(User $user, ?int $studentId): Collection
    {
        return BookIssue::with('student')->latest('issued_on')->when($user->role !== 'admin', fn (Builder $query) => $query->where('student_id', $studentId ?? -1))->get();
    }

    private function notices(User $user): Collection
    {
        return Notice::with('author')->latest('published_at')->when($user->role !== 'admin', fn (Builder $query) => $query->whereIn('audience', ['all', $user->role]))->get();
    }

    private function events(User $user): Collection
    {
        return SchoolEvent::orderBy('starts_at')->when($user->role !== 'admin', fn (Builder $query) => $query->whereIn('audience', ['all', $user->role]))->get();
    }

    private function learningItems(User $user, ?User $student): Collection
    {
        return LearningItem::with('subject')->latest('scheduled_at')
            ->when($user->role === 'teacher', fn (Builder $query) => $query->where('teacher_id', $user->id))
            ->when($user->role === 'student', fn (Builder $query) => $query->whereHas('subject', fn (Builder $subject) => $subject->where('academic_class_id', $student?->studentProfile?->academic_class_id ?? -1)))
            ->get();
    }

    private function leaves(User $user, ?int $studentId): Collection
    {
        return LeaveRequest::with(['user', 'student'])->latest()
            ->when($user->role === 'parent', fn (Builder $query) => $query->where('student_id', $studentId))
            ->when(! in_array($user->role, ['admin', 'parent'], true), fn (Builder $query) => $query->where('user_id', $user->id))
            ->get();
    }

    private function messages(User $user, string $module): Collection
    {
        $query = Message::with(['sender', 'recipient'])->where(fn (Builder $messages) => $messages->where('sender_id', $user->id)->orWhere('recipient_id', $user->id));
        if ($module === 'doubts') {
            $query->where(fn (Builder $messages) => $messages->where('subject', 'like', '%Doubt%')->orWhere('subject', 'like', '%exercise%')->orWhere('subject', 'like', '%loop%'));
        }

        return $query->latest()->get();
    }

    private function contactOptions(User $user): Collection
    {
        if ($user->role === 'teacher') {
            $classIds = Subject::where('teacher_id', $user->id)->pluck('academic_class_id');

            return User::where(fn (Builder $query) => $query
                ->where(fn (Builder $students) => $students->where('role', 'student')->whereHas('studentProfile', fn (Builder $profile) => $profile->whereIn('academic_class_id', $classIds)))
                ->orWhere(fn (Builder $parents) => $parents->where('role', 'parent')->whereHas('children.studentProfile', fn (Builder $profile) => $profile->whereIn('academic_class_id', $classIds))))
                ->orderBy('name')->get();
        }
        if ($user->role === 'student') {
            return User::where('role', 'teacher')->whereHas('subjects', fn (Builder $query) => $query->where('academic_class_id', $user->studentProfile?->academic_class_id ?? -1))->orderBy('name')->get();
        }
        if ($user->role === 'parent') {
            $classIds = $user->children()->with('studentProfile')->get()->pluck('studentProfile.academic_class_id')->filter();

            return User::where('role', 'teacher')->whereHas('subjects', fn (Builder $query) => $query->whereIn('academic_class_id', $classIds))->orderBy('name')->get();
        }

        return collect();
    }

    private function moduleRecords(User $user, ?int $studentId, string $module): Collection
    {
        $query = ModuleRecord::with(['student', 'subject', 'owner', 'creator']);
        $user->role === 'admin' && $module === 'documents' ? $query->whereIn('module', ['documents', 'certificates']) : $query->where('module', $module);

        return $query
            ->when($user->role === 'teacher' && in_array($module, ['timetable', 'feedback'], true), fn (Builder $query) => $query->where('owner_id', $user->id))
            ->when($user->role === 'parent' && in_array($module, ['feedback', 'behavior', 'ptm', 'documents'], true), fn (Builder $query) => $query->where('student_id', $studentId))
            ->when($user->role === 'student' && $module === 'certificates', fn (Builder $query) => $query->where('student_id', $user->id))
            ->where(fn (Builder $query) => $query->where('audience', 'all')->orWhere('audience', $user->role))
            ->orderBy('occurred_at')->get();
    }

    private function questionBank(User $user): Collection
    {
        if ($user->role !== 'teacher') {
            return collect();
        }

        return Quiz::with('subject')->where('teacher_id', $user->id)->get()->flatMap(fn (Quiz $quiz) => collect($quiz->questions)->map(fn (array $question): array => $question + ['quiz' => $quiz->title, 'subject' => $quiz->subject->name]));
    }
}
