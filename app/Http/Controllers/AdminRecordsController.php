<?php

namespace App\Http\Controllers;

use App\Models\AcademicClass;
use App\Models\BookIssue;
use App\Models\FeeRecord;
use App\Models\RolePermission;
use App\Models\SchoolEvent;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminRecordsController extends Controller
{
    public function updateRolePermissions(Request $request, string $role): RedirectResponse
    {
        abort_unless(in_array($role, ['teacher', 'student', 'parent'], true), 404);
        $data = $request->validate(['permissions' => ['nullable', 'array'], 'permissions.*' => [Rule::in(array_keys(config('lms.permissions')))]]);
        DB::transaction(function () use ($data, $role): void {
            foreach (array_keys(config('lms.permissions')) as $permission) {
                RolePermission::updateOrCreate(['role' => $role, 'permission' => $permission], ['is_allowed' => in_array($permission, $data['permissions'] ?? [], true)]);
            }
        });
        $this->audit($request, 'Permissions updated for '.$role, $request->user());

        return back()->with('success', str($role)->headline().' permissions updated.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $this->validateUser($request);
        $user = DB::transaction(function () use ($data): User {
            $user = User::create($this->userAttributes($data, true));
            $this->syncUserDetails($user, $data);

            return $user;
        });
        $this->audit($request, 'User created', $user);

        return back()->with('success', $user->name.' was created.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $data = $this->validateUser($request, $user);
        DB::transaction(function () use ($data, $user): void {
            $user->update($this->userAttributes($data));
            $this->syncUserDetails($user, $data);
        });
        $this->audit($request, 'User updated', $user);

        return back()->with('success', $user->name.' was updated.');
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'You cannot delete your own account.');
        try {
            $name = $user->name;
            $this->audit($request, 'User deleted', $user);
            $user->delete();

            return back()->with('success', $name.' was deleted.');
        } catch (QueryException) {
            return back()->withErrors(['record' => 'This user has linked school records. Deactivate the account instead of deleting it.']);
        }
    }

    public function storeClass(Request $request): RedirectResponse
    {
        $class = AcademicClass::create($this->validateClass($request));
        $this->audit($request, 'Class created', $class);

        return back()->with('success', 'Class created.');
    }

    public function updateClass(Request $request, AcademicClass $academicClass): RedirectResponse
    {
        $academicClass->update($this->validateClass($request));
        $this->audit($request, 'Class updated', $academicClass);

        return back()->with('success', 'Class updated.');
    }

    public function destroyClass(Request $request, AcademicClass $academicClass): RedirectResponse
    {
        return $this->deleteOrExplain($request, $academicClass, 'Class deleted.', 'This class still has students or subjects and cannot be deleted.');
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $subject = Subject::create($this->validateSubject($request));
        $this->audit($request, 'Subject created', $subject);

        return back()->with('success', 'Subject created.');
    }

    public function updateSubject(Request $request, Subject $subject): RedirectResponse
    {
        $subject->update($this->validateSubject($request, $subject));
        $this->audit($request, 'Subject updated', $subject);

        return back()->with('success', 'Subject updated.');
    }

    public function destroySubject(Request $request, Subject $subject): RedirectResponse
    {
        return $this->deleteOrExplain($request, $subject, 'Subject deleted.', 'This subject has linked learning records and cannot be deleted.');
    }

    public function storeFee(Request $request): RedirectResponse
    {
        $data = $this->validateFee($request);
        $fee = FeeRecord::create($data + ['status' => $this->feeStatus($data)]);
        $this->audit($request, 'Fee record created', $fee);

        return back()->with('success', 'Fee record created.');
    }

    public function updateFee(Request $request, FeeRecord $feeRecord): RedirectResponse
    {
        $data = $this->validateFee($request, $feeRecord);
        $feeRecord->update($data + ['status' => $this->feeStatus($data)]);
        $this->audit($request, 'Fee record updated', $feeRecord);

        return back()->with('success', 'Fee record updated.');
    }

    public function destroyFee(Request $request, FeeRecord $feeRecord): RedirectResponse
    {
        $this->audit($request, 'Fee record deleted', $feeRecord);
        $feeRecord->delete();

        return back()->with('success', 'Fee record deleted.');
    }

    public function storeBookIssue(Request $request): RedirectResponse
    {
        $issue = BookIssue::create($this->validateBookIssue($request));
        $this->audit($request, 'Library issue created', $issue);

        return back()->with('success', 'Book issue created.');
    }

    public function updateBookIssue(Request $request, BookIssue $bookIssue): RedirectResponse
    {
        $bookIssue->update($this->validateBookIssue($request, $bookIssue));
        $this->audit($request, 'Library issue updated', $bookIssue);

        return back()->with('success', 'Book issue updated.');
    }

    public function destroyBookIssue(Request $request, BookIssue $bookIssue): RedirectResponse
    {
        $this->audit($request, 'Library issue deleted', $bookIssue);
        $bookIssue->delete();

        return back()->with('success', 'Book issue deleted.');
    }

    public function storeEvent(Request $request): RedirectResponse
    {
        $event = SchoolEvent::create($this->validateEvent($request));
        $this->audit($request, 'Event created', $event);

        return back()->with('success', 'Event created.');
    }

    public function updateEvent(Request $request, SchoolEvent $schoolEvent): RedirectResponse
    {
        $schoolEvent->update($this->validateEvent($request));
        $this->audit($request, 'Event updated', $schoolEvent);

        return back()->with('success', 'Event updated.');
    }

    public function destroyEvent(Request $request, SchoolEvent $schoolEvent): RedirectResponse
    {
        $this->audit($request, 'Event deleted', $schoolEvent);
        $schoolEvent->delete();

        return back()->with('success', 'Event deleted.');
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users')->ignore($user)],
            'username' => ['required', 'alpha_dash', 'max:80', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::in(['admin', 'teacher', 'student', 'parent'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
            'academic_class_id' => ['nullable', 'exists:academic_classes,id'],
            'admission_number' => ['nullable', 'string', 'max:50', Rule::unique('student_profiles')->ignore($user?->studentProfile)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'blood_group' => ['nullable', 'string', 'max:8'],
            'house' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'child_ids' => ['nullable', 'array'],
            'child_ids.*' => [Rule::exists('users', 'id')->where('role', 'student')],
        ]);
    }

    private function userAttributes(array $data, bool $creating = false): array
    {
        $attributes = collect($data)->only(['name', 'email', 'username', 'role', 'phone', 'status'])->all();
        if (! empty($data['password'])) {
            $attributes['password'] = Hash::make($data['password']);
        } elseif ($creating) {
            $attributes['password'] = Hash::make('ChangeMe@123');
        }

        return $attributes;
    }

    private function syncUserDetails(User $user, array $data): void
    {
        if ($user->role === 'student') {
            StudentProfile::updateOrCreate(['user_id' => $user->id], collect($data)->only(['academic_class_id', 'admission_number', 'date_of_birth', 'blood_group', 'house', 'address'])->all());
        }
        if ($user->role === 'parent') {
            $user->children()->sync(collect($data['child_ids'] ?? [])->mapWithKeys(fn ($id) => [$id => ['relationship' => 'Guardian']])->all());
        }
    }

    private function validateClass(Request $request): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:80'], 'section' => ['required', 'string', 'max:20'], 'room' => ['required', 'string', 'max:50'], 'capacity' => ['required', 'integer', 'min:1', 'max:100'], 'academic_year' => ['required', 'string', 'max:20']]);
    }

    private function validateSubject(Request $request, ?Subject $subject = null): array
    {
        return $request->validate(['academic_class_id' => ['required', 'exists:academic_classes,id'], 'teacher_id' => ['required', Rule::exists('users', 'id')->where('role', 'teacher')], 'name' => ['required', 'string', 'max:100'], 'code' => ['required', 'string', 'max:30', Rule::unique('subjects')->ignore($subject)], 'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'progress' => ['required', 'integer', 'min:0', 'max:100']]);
    }

    private function validateFee(Request $request, ?FeeRecord $fee = null): array
    {
        return $request->validate(['student_id' => ['required', Rule::exists('users', 'id')->where('role', 'student')], 'fee_type' => ['required', 'string', 'max:120'], 'amount' => ['required', 'numeric', 'min:0'], 'paid_amount' => ['required', 'numeric', 'min:0', 'lte:amount'], 'due_date' => ['required', 'date'], 'reference' => ['required', 'string', 'max:100', Rule::unique('fee_records')->ignore($fee)], 'payment_method' => ['nullable', Rule::in(['Cash', 'Cheque', 'Bank transfer', 'UPI recorded externally', 'Other'])], 'paid_on' => ['nullable', 'date', 'before_or_equal:today']]);
    }

    private function feeStatus(array $data): string
    {
        if ((float) $data['paid_amount'] >= (float) $data['amount']) {
            return 'paid';
        }

        return (float) $data['paid_amount'] > 0 ? 'partial' : (now()->isAfter($data['due_date']) ? 'overdue' : 'unpaid');
    }

    private function validateBookIssue(Request $request, ?BookIssue $issue = null): array
    {
        return $request->validate(['student_id' => ['required', Rule::exists('users', 'id')->where('role', 'student')], 'book_title' => ['required', 'string', 'max:180'], 'author' => ['required', 'string', 'max:120'], 'accession_number' => ['required', 'string', 'max:60', Rule::unique('book_issues')->ignore($issue)], 'issued_on' => ['required', 'date'], 'due_on' => ['required', 'date', 'after_or_equal:issued_on'], 'returned_on' => ['nullable', 'date', 'after_or_equal:issued_on'], 'status' => ['required', Rule::in(['issued', 'returned', 'overdue'])]]);
    }

    private function validateEvent(Request $request): array
    {
        return $request->validate(['title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:2000'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'location' => ['required', 'string', 'max:120'], 'type' => ['required', 'string', 'max:50'], 'audience' => ['required', Rule::in(['all', 'teacher', 'student', 'parent'])]]);
    }

    private function deleteOrExplain(Request $request, object $record, string $success, string $error): RedirectResponse
    {
        try {
            $this->audit($request, class_basename($record).' deleted', $record);
            $record->delete();

            return back()->with('success', $success);
        } catch (QueryException) {
            return back()->withErrors(['record' => $error]);
        }
    }

    private function audit(Request $request, string $action, object $record): void
    {
        $request->user()->auditLogs()->create(['action' => $action, 'subject_type' => $record::class, 'subject_id' => $record->id, 'details' => [], 'ip_address' => $request->ip()]);
    }
}
