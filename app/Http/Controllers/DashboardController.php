<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\BookIssue;
use App\Models\ExamResult;
use App\Models\FeeRecord;
use App\Models\Message;
use App\Models\Notice;
use App\Models\SchoolEvent;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $student = $this->studentContext($request);
        $studentId = $student?->id;
        $attendance = $studentId ? AttendanceRecord::where('student_id', $studentId)->get() : collect();
        $attendanceRate = $attendance->isEmpty() ? 0 : round($attendance->whereIn('status', ['present', 'late'])->count() / $attendance->count() * 100, 1);
        $publishedResults = $studentId ? ExamResult::with('subject')->where('student_id', $studentId)->where('is_published', true)->get() : collect();
        $todayAttendance = AttendanceRecord::whereDate('date', AttendanceRecord::max('date'))->get();
        $schoolAttendanceRate = $todayAttendance->isEmpty() ? 0 : round($todayAttendance->whereIn('status', ['present', 'late'])->count() / $todayAttendance->count() * 100, 1);
        $classId = $student?->studentProfile?->academic_class_id;

        $stats = match ($user->role) {
            'admin' => [
                ['label' => 'Active students', 'value' => User::where('role', 'student')->where('status', 'active')->count(), 'change' => '+4.2%', 'tone' => 'blue'],
                ['label' => 'Teaching staff', 'value' => User::where('role', 'teacher')->count(), 'change' => '94% present', 'tone' => 'violet'],
                ['label' => 'Attendance today', 'value' => $schoolAttendanceRate.'%', 'change' => $todayAttendance->count().' records', 'tone' => 'emerald'],
                ['label' => 'Fees outstanding', 'value' => '₹'.number_format((float) FeeRecord::selectRaw('SUM(amount - paid_amount) as total')->value('total')), 'change' => 'Record only', 'tone' => 'amber'],
            ],
            'teacher' => [
                ['label' => 'My subjects', 'value' => Subject::where('teacher_id', $user->id)->count(), 'change' => 'Grade 8A', 'tone' => 'blue'],
                ['label' => 'Assignments live', 'value' => Assignment::where('teacher_id', $user->id)->where('status', 'published')->count(), 'change' => '1 due soon', 'tone' => 'violet'],
                ['label' => 'Class attendance', 'value' => $schoolAttendanceRate.'%', 'change' => 'Latest school day', 'tone' => 'emerald'],
                ['label' => 'Open doubts', 'value' => Message::where('recipient_id', $user->id)->where('is_read', false)->count(), 'change' => 'Needs response', 'tone' => 'amber'],
            ],
            default => [
                ['label' => 'Attendance', 'value' => $attendanceRate.'%', 'change' => 'This term', 'tone' => 'emerald'],
                ['label' => 'Average score', 'value' => $publishedResults->isEmpty() ? '—' : round($publishedResults->avg(fn (ExamResult $result): float => $result->marks / $result->max_marks * 100)).'%', 'change' => 'Published results', 'tone' => 'blue'],
                ['label' => 'Assignments due', 'value' => Assignment::whereBetween('due_at', [now(), now()->addDays(7)])->whereHas('subject', fn ($query) => $query->where('academic_class_id', $classId ?? -1))->count(), 'change' => 'Next 7 days', 'tone' => 'violet'],
                ['label' => 'Fee balance', 'value' => '₹'.number_format((float) FeeRecord::where('student_id', $studentId)->selectRaw('SUM(amount - paid_amount) as total')->value('total')), 'change' => 'No online payment', 'tone' => 'amber'],
            ],
        };

        return view('dashboard', [
            'stats' => $stats,
            'student' => $student,
            'children' => $user->role === 'parent' ? $user->children()->with('studentProfile.academicClass')->orderBy('users.id')->get() : collect(),
            'subjects' => Subject::with('teacher')->when($user->role === 'teacher', fn ($query) => $query->where('teacher_id', $user->id))->when(in_array($user->role, ['student', 'parent'], true), fn ($query) => $query->where('academic_class_id', $classId ?? -1))->orderBy('name')->get(),
            'assignments' => Assignment::with('subject')->when($user->role === 'teacher', fn ($query) => $query->where('teacher_id', $user->id))->when(in_array($user->role, ['student', 'parent'], true), fn ($query) => $query->whereHas('subject', fn ($subject) => $subject->where('academic_class_id', $classId ?? -1)))->latest('due_at')->limit(4)->get(),
            'notices' => Notice::when($user->role !== 'admin', fn ($query) => $query->whereIn('audience', ['all', $user->role]))->latest('published_at')->limit(3)->get(),
            'events' => SchoolEvent::where('starts_at', '>', now())->when($user->role !== 'admin', fn ($query) => $query->whereIn('audience', ['all', $user->role]))->orderBy('starts_at')->limit(4)->get(),
            'results' => $publishedResults,
            'bookIssue' => $studentId ? BookIssue::where('student_id', $studentId)->where('status', 'issued')->first() : null,
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
}
