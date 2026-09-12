<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $identity = $request->string('email')->toString();
        $field = filter_var($identity, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$field => $identity, 'password' => $request->string('password')->toString(), 'status' => 'active'], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match an active account.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->session()->forget('selected_child');
        $this->logLogin($request);

        return redirect()->intended(route('dashboard'));
    }

    public function demo(Request $request, string $role): RedirectResponse
    {
        abort_unless(config('lms.demo_mode'), 404);
        abort_unless(in_array($role, ['admin', 'teacher', 'student', 'parent'], true), 404);

        Auth::login(User::where('role', $role)->where('email', $role === 'admin' ? 'principal@greenfield.demo' : $role.'@greenfield.demo')->firstOrFail());
        $request->session()->regenerate();
        $request->session()->forget('selected_child');
        $this->logLogin($request);

        return redirect()->route('dashboard');
    }

    public function forgot(): View
    {
        return view('auth.forgot-password');
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        return back()->with('status', 'For this portfolio demo, password reset instructions are simulated. Please use the demo credentials on the login page.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function logLogin(Request $request): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'Signed in',
            'subject_type' => User::class,
            'subject_id' => $request->user()?->id,
            'details' => ['agent' => Str::limit((string) $request->userAgent(), 120)],
            'ip_address' => $request->ip(),
        ]);
    }
}
