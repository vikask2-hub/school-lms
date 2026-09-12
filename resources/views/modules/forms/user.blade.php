@php
    $profile = $person?->studentProfile;
    $selectedRole = old('role', $person?->role ?? 'student');
@endphp
<div class="contents" x-data="{ selectedRole: @js($selectedRole) }">
    <div class="form-section">
        <span class="form-section-icon"><i data-lucide="user-round" class="size-4"></i></span>
        <div><h3>Account information</h3><p>Identity, sign-in and account access</p></div>
    </div>
    <div><label>Full name</label><input name="name" value="{{ old('name',$person?->name) }}" required></div>
    <div><label>Email</label><input type="email" name="email" value="{{ old('email',$person?->email) }}" required></div>
    <div><label>Username</label><input name="username" value="{{ old('username',$person?->username) }}" required></div>
    <div><label>Role</label><select name="role" x-model="selectedRole">@foreach(['admin','teacher','student','parent'] as $value)<option value="{{ $value }}" @selected($selectedRole===$value)>{{ str($value)->headline() }}</option>@endforeach</select></div>
    <div><label>Phone</label><input name="phone" value="{{ old('phone',$person?->phone) }}"></div>
    <div><label>Status</label><select name="status"><option value="active" @selected(old('status',$person?->status ?? 'active')==='active')>Active</option><option value="inactive" @selected(old('status',$person?->status)==='inactive')>Inactive</option></select></div>
    <div class="sm:col-span-2"><label>{{ $person ? 'New password (optional)' : 'Temporary password' }}</label><input type="password" name="password" minlength="8" @required(!$person) autocomplete="new-password"><p class="mt-1.5 text-xs text-slate-400">Use at least 8 characters.</p></div>

    <div class="form-section" x-show="selectedRole==='student'">
        <span class="form-section-icon"><i data-lucide="graduation-cap" class="size-4"></i></span>
        <div><h3>Student information</h3><p>Enrollment, personal and academic details</p></div>
    </div>
    <div x-show="selectedRole==='student'"><label>Student class</label><select name="academic_class_id"><option value="">Select class</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected(old('academic_class_id',$profile?->academic_class_id)===$class->id)>{{ $class->name }}{{ $class->section }}</option>@endforeach</select></div>
    <div x-show="selectedRole==='student'"><label>Admission number</label><input name="admission_number" value="{{ old('admission_number',$profile?->admission_number) }}"></div>
    <div x-show="selectedRole==='student'"><label>Date of birth</label><input type="date" name="date_of_birth" value="{{ old('date_of_birth',$profile?->date_of_birth?->toDateString()) }}"></div>
    <div x-show="selectedRole==='student'"><label>House</label><input name="house" value="{{ old('house',$profile?->house) }}"></div>
    <div x-show="selectedRole==='student'"><label>Blood group</label><input name="blood_group" value="{{ old('blood_group',$profile?->blood_group) }}"></div>
    <div class="sm:col-span-2" x-show="selectedRole==='student'"><label>Address</label><textarea name="address" rows="2">{{ old('address',$profile?->address) }}</textarea></div>

    <div class="form-section" x-show="selectedRole==='parent'">
        <span class="form-section-icon"><i data-lucide="users" class="size-4"></i></span>
        <div><h3>Parent linkage</h3><p>Connect this account to one or more learners</p></div>
    </div>
    <div class="sm:col-span-2" x-show="selectedRole==='parent'"><label>Linked children</label><select name="child_ids[]" multiple class="min-h-28">@foreach($users->where('role','student') as $child)<option value="{{ $child->id }}" @selected($person?->role==='parent' && $person->children->contains($child))>{{ $child->name }}</option>@endforeach</select><p class="mt-1.5 text-xs text-slate-400">Hold Ctrl/Cmd to select multiple learners.</p></div>
</div>
