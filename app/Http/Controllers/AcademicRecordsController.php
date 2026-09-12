<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ExamResult;
use App\Models\LearningItem;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicRecordsController extends Controller
{
    public function updateAssignment(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->authorizeSubjectOwner($request, $assignment->subject_id, $assignment->teacher_id);
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'instructions' => ['required', 'string', 'max:3000'], 'due_at' => ['required', 'date'], 'max_marks' => ['required', 'integer', 'min:1', 'max:500'], 'status' => ['required', Rule::in(['draft', 'published', 'closed'])]]);
        $assignment->update($data);
        $this->audit($request, 'Assignment updated', $assignment);

        return back()->with('success', 'Assignment updated.');
    }

    public function destroyAssignment(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->authorizeSubjectOwner($request, $assignment->subject_id, $assignment->teacher_id);
        abort_unless($request->user()->role === 'admin' || $assignment->status === 'draft', 422, 'Teachers can delete draft assignments only.');
        $this->audit($request, 'Assignment deleted', $assignment);
        $assignment->delete();

        return back()->with('success', 'Assignment deleted.');
    }

    public function gradeSubmission(Request $request, AssignmentSubmission $assignmentSubmission): RedirectResponse
    {
        $assignmentSubmission->load('assignment');
        $this->authorizeSubjectOwner($request, $assignmentSubmission->assignment->subject_id, $assignmentSubmission->assignment->teacher_id);
        $data = $request->validate(['marks' => ['required', 'numeric', 'min:0', 'max:'.$assignmentSubmission->assignment->max_marks], 'feedback' => ['required', 'string', 'min:3', 'max:2000']]);
        $assignmentSubmission->update($data + ['status' => 'graded']);
        $this->audit($request, 'Submission graded', $assignmentSubmission);

        return back()->with('success', 'Submission graded and feedback released.');
    }

    public function storeQuiz(Request $request): RedirectResponse
    {
        $data = $this->validateQuiz($request);
        $this->authorizeSubjectOwner($request, (int) $data['subject_id']);
        $quiz = Quiz::create($this->quizAttributes($request, $data));
        $this->audit($request, 'Quiz created', $quiz);

        return back()->with('success', 'Quiz created.');
    }

    public function updateQuiz(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeSubjectOwner($request, $quiz->subject_id, $quiz->teacher_id);
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'duration_minutes' => ['required', 'integer', 'min:1', 'max:180'], 'available_until' => ['required', 'date'], 'status' => ['required', Rule::in(['draft', 'published', 'closed'])]]);
        $quiz->update($data);
        $this->audit($request, 'Quiz updated', $quiz);

        return back()->with('success', 'Quiz updated.');
    }

    public function destroyQuiz(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeSubjectOwner($request, $quiz->subject_id, $quiz->teacher_id);
        abort_unless($request->user()->role === 'admin' || $quiz->status === 'draft', 422, 'Teachers can delete draft quizzes only.');
        $this->audit($request, 'Quiz deleted', $quiz);
        $quiz->delete();

        return back()->with('success', 'Quiz deleted.');
    }

    public function storeLearningItem(Request $request): RedirectResponse
    {
        $data = $this->validateLearningItem($request);
        $this->authorizeSubjectOwner($request, (int) $data['subject_id']);
        $item = LearningItem::create($data + ['teacher_id' => $request->user()->id]);
        $this->audit($request, 'Learning item created', $item);

        return back()->with('success', 'Learning item created.');
    }

    public function updateLearningItem(Request $request, LearningItem $learningItem): RedirectResponse
    {
        $this->authorizeSubjectOwner($request, $learningItem->subject_id, $learningItem->teacher_id);
        $data = $this->validateLearningItem($request);
        $this->authorizeSubjectOwner($request, (int) $data['subject_id']);
        $learningItem->update($data);
        $this->audit($request, 'Learning item updated', $learningItem);

        return back()->with('success', 'Learning item updated.');
    }

    public function destroyLearningItem(Request $request, LearningItem $learningItem): RedirectResponse
    {
        $this->authorizeSubjectOwner($request, $learningItem->subject_id, $learningItem->teacher_id);
        abort_unless($request->user()->role === 'admin' || $learningItem->status === 'draft', 422, 'Teachers can delete draft learning items only.');
        $this->audit($request, 'Learning item deleted', $learningItem);
        $learningItem->delete();

        return back()->with('success', 'Learning item deleted.');
    }

    public function storeResult(Request $request): RedirectResponse
    {
        $data = $this->validateResult($request);
        $this->authorizeSubjectOwner($request, (int) $data['subject_id']);
        $this->authorizeStudentSubject((int) $data['student_id'], (int) $data['subject_id']);
        $result = ExamResult::create($data + ['grade' => $this->grade((float) $data['marks'], (float) $data['max_marks'])]);
        $this->audit($request, 'Exam result created', $result);

        return back()->with('success', 'Exam result saved.');
    }

    public function updateResult(Request $request, ExamResult $examResult): RedirectResponse
    {
        $this->authorizeSubjectOwner($request, $examResult->subject_id);
        $data = $this->validateResult($request, $examResult);
        $this->authorizeSubjectOwner($request, (int) $data['subject_id']);
        $this->authorizeStudentSubject((int) $data['student_id'], (int) $data['subject_id']);
        $examResult->update($data + ['grade' => $this->grade((float) $data['marks'], (float) $data['max_marks'])]);
        $this->audit($request, 'Exam result updated', $examResult);

        return back()->with('success', 'Exam result updated.');
    }

    public function destroyResult(Request $request, ExamResult $examResult): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $this->audit($request, 'Exam result deleted', $examResult);
        $examResult->delete();

        return back()->with('success', 'Exam result deleted.');
    }

    private function validateQuiz(Request $request): array
    {
        return $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'], 'title' => ['required', 'string', 'max:150'], 'duration_minutes' => ['required', 'integer', 'min:1', 'max:180'],
            'available_until' => ['required', 'date', 'after:now'], 'status' => ['required', Rule::in(['draft', 'published'])],
            'question' => ['required', 'string', 'max:1000'], 'option_a' => ['required', 'string', 'max:250'], 'option_b' => ['required', 'string', 'max:250'],
            'option_c' => ['required', 'string', 'max:250'], 'option_d' => ['required', 'string', 'max:250'], 'answer' => ['required', Rule::in(['option_a', 'option_b', 'option_c', 'option_d'])],
        ]);
    }

    private function quizAttributes(Request $request, array $data): array
    {
        $answer = $data[$data['answer']];

        $teacherId = $request->user()->role === 'teacher' ? $request->user()->id : Subject::findOrFail($data['subject_id'])->teacher_id;

        return ['subject_id' => $data['subject_id'], 'teacher_id' => $teacherId, 'title' => $data['title'], 'duration_minutes' => $data['duration_minutes'], 'available_until' => $data['available_until'], 'status' => $data['status'], 'questions' => [['question' => $data['question'], 'options' => [$data['option_a'], $data['option_b'], $data['option_c'], $data['option_d']], 'answer' => $answer]]];
    }

    private function validateLearningItem(Request $request): array
    {
        return $request->validate(['subject_id' => ['required', 'exists:subjects,id'], 'type' => ['required', Rule::in(['content', 'live-class', 'recording', 'lesson-plan'])], 'title' => ['required', 'string', 'max:180'], 'description' => ['required', 'string', 'max:3000'], 'url' => ['nullable', 'url', 'max:500'], 'scheduled_at' => ['nullable', 'date'], 'status' => ['required', Rule::in(['draft', 'published', 'complete'])]]);
    }

    private function validateResult(Request $request, ?ExamResult $result = null): array
    {
        return $request->validate(['student_id' => ['required', Rule::exists('users', 'id')->where('role', 'student')], 'subject_id' => ['required', 'exists:subjects,id'], 'exam_name' => ['required', 'string', 'max:150'], 'exam_date' => ['required', 'date'], 'marks' => ['required', 'numeric', 'min:0', 'lte:max_marks'], 'max_marks' => ['required', 'numeric', 'min:1', 'max:1000'], 'is_published' => ['required', 'boolean']]);
    }

    private function authorizeSubjectOwner(Request $request, int $subjectId, ?int $ownerId = null): void
    {
        if ($request->user()->role === 'admin') {
            return;
        }
        abort_unless($request->user()->role === 'teacher' && Subject::whereKey($subjectId)->where('teacher_id', $request->user()->id)->exists(), 404);
        if ($ownerId) {
            abort_unless($ownerId === $request->user()->id, 404);
        }
    }

    private function authorizeStudentSubject(int $studentId, int $subjectId): void
    {
        $classId = User::findOrFail($studentId)->studentProfile?->academic_class_id;
        abort_unless(Subject::whereKey($subjectId)->where('academic_class_id', $classId)->exists(), 422, 'The student is not enrolled in this subject.');
    }

    private function grade(float $marks, float $maximum): string
    {
        $percentage = $marks / $maximum * 100;

        return match (true) {
            $percentage >= 90 => 'A+', $percentage >= 80 => 'A', $percentage >= 70 => 'B+', $percentage >= 60 => 'B', $percentage >= 50 => 'C', default => 'Needs improvement',
        };
    }

    private function audit(Request $request, string $action, object $record): void
    {
        $request->user()->auditLogs()->create(['action' => $action, 'subject_type' => $record::class, 'subject_id' => $record->id, 'details' => [], 'ip_address' => $request->ip()]);
    }
}
