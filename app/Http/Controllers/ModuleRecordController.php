<?php

namespace App\Http\Controllers;

use App\Models\ModuleRecord;
use App\Models\RolePermission;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ModuleRecordController extends Controller
{
    public function markRead(Request $request, ModuleRecord $moduleRecord): RedirectResponse
    {
        abort_unless($request->user()->role === 'parent' && $moduleRecord->module === 'notifications' && in_array($moduleRecord->audience, ['all', 'parent'], true), 404);
        if ($moduleRecord->student_id) {
            abort_unless($request->user()->children()->whereKey($moduleRecord->student_id)->exists(), 404);
        }
        $moduleRecord->update(['status' => 'read']);

        return back()->with('success', 'Notification marked as read.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->authorizeWrite($request->user(), $data['module'], null, $data);
        $this->ensureNoTimetableConflict($request->user(), $data);
        $attributes = $this->attributes($request->user(), $data);
        $attributes['meta'] = $this->storeAttachment($request, $attributes['meta']);
        $record = ModuleRecord::create($attributes);
        $this->audit($request, 'Created '.$data['module'].' record', $record);

        return back()->with('success', str($data['module'])->headline().' record created.');
    }

    public function update(Request $request, ModuleRecord $moduleRecord): RedirectResponse
    {
        $data = $this->validated($request, $moduleRecord);
        abort_unless($data['module'] === $moduleRecord->module, 422);
        $this->authorizeWrite($request->user(), $moduleRecord->module, $moduleRecord, $data);
        $this->ensureNoTimetableConflict($request->user(), $data, $moduleRecord);
        $attributes = $this->attributes($request->user(), $data, $moduleRecord);
        $attributes['meta'] = $this->storeAttachment($request, $attributes['meta'], $moduleRecord);
        $moduleRecord->update($attributes);
        $this->audit($request, 'Updated '.$moduleRecord->module.' record', $moduleRecord);

        return back()->with('success', str($moduleRecord->module)->headline().' record updated.');
    }

    public function destroy(Request $request, ModuleRecord $moduleRecord): RedirectResponse
    {
        $this->authorizeWrite($request->user(), $moduleRecord->module, $moduleRecord);
        $this->audit($request, 'Deleted '.$moduleRecord->module.' record', $moduleRecord);
        if ($path = data_get($moduleRecord->meta, 'attachment_path')) {
            Storage::disk('local')->delete($path);
        }
        $moduleRecord->delete();

        return back()->with('success', str($moduleRecord->module)->headline().' record deleted.');
    }

    public function download(Request $request, ModuleRecord $moduleRecord): StreamedResponse
    {
        abort_unless(in_array($moduleRecord->module, ['documents', 'certificates'], true), 404);
        $user = $request->user();
        $allowed = $user->role === 'admin'
            || ($user->role === 'student' && $moduleRecord->student_id === $user->id)
            || ($user->role === 'parent' && ($moduleRecord->creator_id === $user->id || ($moduleRecord->student_id && $user->children()->whereKey($moduleRecord->student_id)->exists())))
            || ($user->role === 'teacher' && $moduleRecord->owner_id === $user->id);
        abort_unless($allowed, 404);
        $path = data_get($moduleRecord->meta, 'attachment_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, data_get($moduleRecord->meta, 'original_name', basename($path)));
    }

    private function validated(Request $request, ?ModuleRecord $record = null): array
    {
        return $request->validate([
            'module' => ['required', Rule::in($this->allowedModules($request->user()))],
            'title' => ['required', 'string', 'max:180'],
            'subtitle' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::in(['draft', 'active', 'scheduled', 'published', 'in-progress', 'complete', 'issued', 'ready', 'processing', 'configured', 'positive', 'resolved', 'open', 'closed', 'requested', 'confirmed', 'cancelled', 'read', 'unread'])],
            'occurred_at' => ['nullable', 'date'],
            'student_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'student')],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'audience' => ['required', Rule::in(['all', 'admin', 'teacher', 'student', 'parent'])],
            'day' => ['nullable', Rule::in(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'])],
            'period' => ['nullable', 'integer', 'min:1', 'max:10'],
            'room' => ['nullable', 'string', 'max:50'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
    }

    private function attributes(User $user, array $data, ?ModuleRecord $record = null): array
    {
        $meta = array_merge($record?->meta ?? [], array_filter([
            'day' => $data['day'] ?? null,
            'period' => isset($data['period']) ? (string) $data['period'] : null,
            'room' => $data['room'] ?? null,
            'progress' => $data['progress'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));

        return [
            'module' => $data['module'],
            'audience' => $user->role === 'parent' ? 'parent' : $data['audience'],
            'student_id' => $data['student_id'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'owner_id' => $user->role === 'admin' ? ($data['owner_id'] ?? $user->id) : ($record?->owner_id ?? $user->id),
            'creator_id' => $record?->creator_id ?? $user->id,
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'occurred_at' => $data['occurred_at'] ?? null,
            'meta' => $meta,
        ];
    }

    private function authorizeWrite(User $user, string $module, ?ModuleRecord $record = null, array $data = []): void
    {
        abort_unless(in_array($module, $this->allowedModules($user), true), 403);
        if ($user->role === 'admin') {
            return;
        }
        $permission = match ($module) {
            'feedback', 'behavior' => 'feedback.manage',
            'ptm' => 'ptm.manage',
            'support' => 'support.manage',
            'documents' => 'documents.manage',
            'chapters' => 'learning.manage',
            default => null,
        };
        abort_unless($permission && RolePermission::where('role', $user->role)->where('permission', $permission)->where('is_allowed', true)->exists(), 403);
        if ($record) {
            abort_unless($user->role === 'teacher' ? $record->owner_id === $user->id : $record->creator_id === $user->id, 404);
            if ($user->role === 'parent') {
                abort_unless(in_array($record->status, ['open', 'requested', 'processing', 'draft'], true), 422, 'Only open requests can be changed.');
            }
        }
        $studentId = $data['student_id'] ?? $record?->student_id;
        if ($user->role === 'parent' && $studentId) {
            abort_unless($user->children()->whereKey($studentId)->exists(), 404);
        }
        if ($user->role === 'teacher') {
            $subjectId = $data['subject_id'] ?? $record?->subject_id;
            if ($subjectId) {
                abort_unless(Subject::whereKey($subjectId)->where('teacher_id', $user->id)->exists(), 404);
            }
            if ($studentId) {
                abort_unless(User::whereKey($studentId)->whereHas('studentProfile.academicClass.subjects', fn (Builder $query) => $query->where('teacher_id', $user->id))->exists(), 404);
            }
        }
    }

    private function allowedModules(User $user): array
    {
        return match ($user->role) {
            'admin' => ['curriculum', 'timetable', 'exams', 'documents', 'settings', 'certificates', 'notifications', 'feedback', 'behavior', 'ptm', 'support', 'chapters'],
            'teacher' => ['feedback', 'behavior', 'ptm', 'chapters'],
            'parent' => ['ptm', 'support', 'documents'],
            default => [],
        };
    }

    private function storeAttachment(Request $request, array $meta, ?ModuleRecord $record = null): array
    {
        if (! $request->hasFile('attachment')) {
            return $meta;
        }
        abort_unless(in_array($request->string('module')->toString(), ['documents', 'certificates'], true), 422);
        if ($oldPath = data_get($record?->meta, 'attachment_path')) {
            Storage::disk('local')->delete($oldPath);
        }
        $file = $request->file('attachment');
        $meta['attachment_path'] = $file->store('documents', 'local');
        $meta['original_name'] = $file->getClientOriginalName();

        return $meta;
    }

    private function ensureNoTimetableConflict(User $user, array $data, ?ModuleRecord $record = null): void
    {
        if ($data['module'] !== 'timetable' || empty($data['day']) || empty($data['period'])) {
            return;
        }
        $ownerId = $user->role === 'admin' ? ($data['owner_id'] ?? $user->id) : ($record?->owner_id ?? $user->id);
        $classId = isset($data['subject_id']) ? Subject::find($data['subject_id'])?->academic_class_id : null;
        $conflict = ModuleRecord::where('module', 'timetable')->when($record, fn (Builder $query) => $query->whereKeyNot($record->id))->get()->first(function (ModuleRecord $item) use ($data, $ownerId, $classId): bool {
            if (data_get($item->meta, 'day') !== $data['day'] || (int) data_get($item->meta, 'period') !== (int) $data['period']) {
                return false;
            }
            $sameTeacher = $item->owner_id === $ownerId;
            $sameRoom = ! empty($data['room']) && data_get($item->meta, 'room') === $data['room'];
            $sameClass = $classId && $item->subject?->academic_class_id === $classId;

            return $sameTeacher || $sameRoom || $sameClass;
        });
        if ($conflict) {
            throw ValidationException::withMessages(['period' => 'This period conflicts with an existing teacher, room, or class timetable entry.']);
        }
    }

    private function audit(Request $request, string $action, ModuleRecord $record): void
    {
        $request->user()->auditLogs()->create(['action' => $action, 'subject_type' => ModuleRecord::class, 'subject_id' => $record->id, 'details' => ['module' => $record->module], 'ip_address' => $request->ip()]);
    }
}
