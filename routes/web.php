<?php

use App\Http\Controllers\AcademicRecordsController;
use App\Http\Controllers\AdminRecordsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ModuleRecordController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::redirect('/portfolio', 'https://tech4projects.online/')->name('portfolio');
Route::redirect('/', '/lms');

Route::prefix('lms')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
        Route::post('/demo-login/{role}', [LoginController::class, 'demo'])->middleware('throttle:login')->name('demo.login');
        Route::get('/forgot-password', [LoginController::class, 'forgot'])->name('password.request');
        Route::post('/forgot-password', [LoginController::class, 'sendReset'])->middleware('throttle:5,1')->name('password.email');
    });

    Route::middleware('auth')->group(function (): void {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/modules/{module}', [ModuleController::class, 'show'])->name('modules.show');

        Route::post('/attendance', [WorkflowController::class, 'attendance'])->middleware(['role:admin,teacher', 'permission:attendance.manage'])->name('attendance.store');
        Route::patch('/attendance/{attendanceRecord}', [WorkflowController::class, 'updateAttendance'])->middleware(['role:admin,teacher', 'permission:attendance.manage'])->name('attendance.update');
        Route::delete('/attendance/{attendanceRecord}', [WorkflowController::class, 'destroyAttendance'])->middleware(['role:admin,teacher', 'permission:attendance.manage'])->name('attendance.destroy');
        Route::post('/assignments', [WorkflowController::class, 'assignment'])->middleware(['role:admin,teacher', 'permission:assignments.manage'])->name('assignments.store');
        Route::patch('/assignments/{assignment}', [AcademicRecordsController::class, 'updateAssignment'])->middleware(['role:admin,teacher', 'permission:assignments.manage'])->name('assignments.update');
        Route::delete('/assignments/{assignment}', [AcademicRecordsController::class, 'destroyAssignment'])->middleware(['role:admin,teacher', 'permission:assignments.manage'])->name('assignments.destroy');
        Route::post('/assignments/{assignment}/submissions', [WorkflowController::class, 'submission'])->middleware(['role:student', 'permission:assignments.submit'])->name('submissions.store');
        Route::patch('/submissions/{assignmentSubmission}/grade', [AcademicRecordsController::class, 'gradeSubmission'])->middleware(['role:admin,teacher', 'permission:assignments.manage'])->name('submissions.grade');
        Route::post('/quizzes', [AcademicRecordsController::class, 'storeQuiz'])->middleware(['role:admin,teacher', 'permission:quizzes.manage'])->name('quizzes.store');
        Route::patch('/quizzes/{quiz}', [AcademicRecordsController::class, 'updateQuiz'])->middleware(['role:admin,teacher', 'permission:quizzes.manage'])->name('quizzes.update');
        Route::delete('/quizzes/{quiz}', [AcademicRecordsController::class, 'destroyQuiz'])->middleware(['role:admin,teacher', 'permission:quizzes.manage'])->name('quizzes.destroy');
        Route::post('/quizzes/{quiz}/attempts', [WorkflowController::class, 'quizAttempt'])->middleware(['role:student', 'permission:quizzes.attempt'])->name('quizzes.attempt');
        Route::post('/learning-items', [AcademicRecordsController::class, 'storeLearningItem'])->middleware(['role:admin,teacher', 'permission:learning.manage'])->name('learning-items.store');
        Route::patch('/learning-items/{learningItem}', [AcademicRecordsController::class, 'updateLearningItem'])->middleware(['role:admin,teacher', 'permission:learning.manage'])->name('learning-items.update');
        Route::delete('/learning-items/{learningItem}', [AcademicRecordsController::class, 'destroyLearningItem'])->middleware(['role:admin,teacher', 'permission:learning.manage'])->name('learning-items.destroy');
        Route::post('/exam-results', [AcademicRecordsController::class, 'storeResult'])->middleware(['role:admin,teacher', 'permission:marks.enter'])->name('exam-results.store');
        Route::patch('/exam-results/{examResult}', [AcademicRecordsController::class, 'updateResult'])->middleware(['role:admin,teacher', 'permission:marks.enter'])->name('exam-results.update');
        Route::delete('/exam-results/{examResult}', [AcademicRecordsController::class, 'destroyResult'])->middleware('role:admin,teacher')->name('exam-results.destroy');
        Route::post('/fees/{feeRecord}/payments', [WorkflowController::class, 'feePayment'])->middleware('role:admin')->name('fees.payments.store');
        Route::post('/leave', [WorkflowController::class, 'leave'])->middleware('permission:leave.manage')->name('leave.store');
        Route::patch('/leave/{leaveRequest}', [WorkflowController::class, 'reviewLeave'])->middleware('role:admin')->name('leave.review');
        Route::patch('/leave/{leaveRequest}/cancel', [WorkflowController::class, 'cancelLeave'])->middleware(['role:teacher,student,parent', 'permission:leave.manage'])->name('leave.cancel');
        Route::post('/messages', [WorkflowController::class, 'message'])->middleware(['role:teacher,student,parent', 'permission:messages.manage'])->name('messages.store');
        Route::delete('/messages/{message}', [WorkflowController::class, 'destroyMessage'])->middleware(['role:teacher,student,parent', 'permission:messages.manage'])->name('messages.destroy');
        Route::post('/notices', [WorkflowController::class, 'notice'])->middleware(['role:admin,teacher', 'permission:notices.manage'])->name('notices.store');
        Route::patch('/notices/{notice}', [WorkflowController::class, 'updateNotice'])->middleware(['role:admin,teacher', 'permission:notices.manage'])->name('notices.update');
        Route::delete('/notices/{notice}', [WorkflowController::class, 'destroyNotice'])->middleware(['role:admin,teacher', 'permission:notices.manage'])->name('notices.destroy');
        Route::post('/support', [WorkflowController::class, 'support'])->middleware(['role:parent', 'permission:support.manage'])->name('support.store');
        Route::post('/ptm', [WorkflowController::class, 'ptm'])->middleware(['role:parent', 'permission:ptm.manage'])->name('ptm.store');
        Route::patch('/profile', [WorkflowController::class, 'updateProfile'])->middleware('permission:profile.update')->name('profile.update');

        Route::middleware('role:admin')->group(function (): void {
            Route::post('/users', [AdminRecordsController::class, 'storeUser'])->name('users.store');
            Route::patch('/users/{user}', [AdminRecordsController::class, 'updateUser'])->name('users.update');
            Route::delete('/users/{user}', [AdminRecordsController::class, 'destroyUser'])->name('users.destroy');
            Route::post('/academic-classes', [AdminRecordsController::class, 'storeClass'])->name('academic-classes.store');
            Route::patch('/academic-classes/{academicClass}', [AdminRecordsController::class, 'updateClass'])->name('academic-classes.update');
            Route::delete('/academic-classes/{academicClass}', [AdminRecordsController::class, 'destroyClass'])->name('academic-classes.destroy');
            Route::post('/subjects', [AdminRecordsController::class, 'storeSubject'])->name('subjects.store');
            Route::patch('/subjects/{subject}', [AdminRecordsController::class, 'updateSubject'])->name('subjects.update');
            Route::delete('/subjects/{subject}', [AdminRecordsController::class, 'destroySubject'])->name('subjects.destroy');
            Route::post('/fee-records', [AdminRecordsController::class, 'storeFee'])->name('fee-records.store');
            Route::patch('/fee-records/{feeRecord}', [AdminRecordsController::class, 'updateFee'])->name('fee-records.update');
            Route::delete('/fee-records/{feeRecord}', [AdminRecordsController::class, 'destroyFee'])->name('fee-records.destroy');
            Route::post('/book-issues', [AdminRecordsController::class, 'storeBookIssue'])->name('book-issues.store');
            Route::patch('/book-issues/{bookIssue}', [AdminRecordsController::class, 'updateBookIssue'])->name('book-issues.update');
            Route::delete('/book-issues/{bookIssue}', [AdminRecordsController::class, 'destroyBookIssue'])->name('book-issues.destroy');
            Route::post('/school-events', [AdminRecordsController::class, 'storeEvent'])->name('school-events.store');
            Route::patch('/school-events/{schoolEvent}', [AdminRecordsController::class, 'updateEvent'])->name('school-events.update');
            Route::delete('/school-events/{schoolEvent}', [AdminRecordsController::class, 'destroyEvent'])->name('school-events.destroy');
            Route::put('/roles/{role}/permissions', [AdminRecordsController::class, 'updateRolePermissions'])->name('roles.permissions.update');
        });

        Route::middleware('role:admin,teacher,parent')->group(function (): void {
            Route::post('/module-records', [ModuleRecordController::class, 'store'])->name('module-records.store');
            Route::patch('/module-records/{moduleRecord}', [ModuleRecordController::class, 'update'])->name('module-records.update');
            Route::delete('/module-records/{moduleRecord}', [ModuleRecordController::class, 'destroy'])->name('module-records.destroy');
        });
        Route::patch('/notifications/{moduleRecord}/read', [ModuleRecordController::class, 'markRead'])->middleware('role:parent')->name('notifications.read');
        Route::get('/module-records/{moduleRecord}/download', [ModuleRecordController::class, 'download'])->name('module-records.download');
    });
});
