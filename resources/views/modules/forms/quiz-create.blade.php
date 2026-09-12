<div class="form-section">
    <span class="form-section-icon"><i data-lucide="settings-2" class="size-4"></i></span>
    <div><h3>Quiz settings</h3><p>Subject, timing and publication</p></div>
</div>
<div><label>Subject</label><select name="subject_id" required>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></div>
<div><label>Quiz title</label><input name="title" required></div>
<div><label>Duration</label><div class="relative"><input type="number" name="duration_minutes" min="1" value="10" class="pr-16" required><span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">minutes</span></div></div>
<div><label>Available until</label><input type="datetime-local" name="available_until" min="{{ now()->addHour()->format('Y-m-d\TH:i') }}" required></div>
<div><label>Publication status</label><select name="status"><option value="published">Published</option><option value="draft">Draft</option></select></div>

<div class="form-section">
    <span class="form-section-icon"><i data-lucide="list-checks" class="size-4"></i></span>
    <div><h3>First question</h3><p>Add one multiple-choice question to begin</p></div>
</div>
<div class="sm:col-span-2"><label>Question</label><textarea name="question" rows="3" required></textarea></div>
@foreach(['a','b','c','d'] as $option)<div><label>Option {{ strtoupper($option) }}</label><input name="option_{{ $option }}" required></div>@endforeach
<div><label>Correct answer</label><select name="answer"><option value="option_a">Option A</option><option value="option_b">Option B</option><option value="option_c">Option C</option><option value="option_d">Option D</option></select></div>
