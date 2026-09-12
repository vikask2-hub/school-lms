<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('lms.school_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
</head>
@php
    $searchItems = collect(config('lms.navigation.'.auth()->user()->role))->flatMap(fn ($items) => collect($items)->map(fn ($label, $key) => ['label' => $label, 'url' => $key === 'dashboard' ? route('dashboard') : route('modules.show', $key)]))->values();
@endphp
<body x-data="{ sidebar: false, search: '', navItems: @js($searchItems), get matches() { return this.search.length < 2 ? [] : this.navItems.filter(item => item.label.toLowerCase().includes(this.search.toLowerCase())).slice(0,6) } }" class="min-h-screen">
    @php
        $nav = config('lms.navigation.'.auth()->user()->role);
        $icons = ['dashboard'=>'layout-dashboard','reports'=>'chart-no-axes-combined','users'=>'users','roles'=>'shield-check','academics'=>'school','classes'=>'school','subjects'=>'book-open','curriculum'=>'list-tree','timetable'=>'calendar-clock','attendance'=>'user-check','exams'=>'file-check-2','exam-marks'=>'file-pen-line','results'=>'award','report-card'=>'notebook-tabs','notices'=>'megaphone','fees'=>'wallet-cards','library'=>'library','calendar'=>'calendar-days','leave'=>'calendar-off','documents'=>'files','settings'=>'settings','audit'=>'history','lesson-plans'=>'notebook-pen','content'=>'library-big','homework'=>'book-check','assignments'=>'clipboard-list','submissions'=>'inbox','quizzes'=>'circle-help','question-bank'=>'list-checks','performance'=>'trending-up','feedback'=>'message-square-heart','doubts'=>'messages-square','messages'=>'message-circle','resources'=>'folder-open','profile'=>'circle-user-round','progress'=>'chart-spline','chapters'=>'book-marked','live-classes'=>'video','recordings'=>'circle-play','study-materials'=>'file-down','certificates'=>'badge-check','child-profile'=>'contact','behavior'=>'sparkles','ptm'=>'handshake','notifications'=>'bell','support'=>'life-buoy'];
    @endphp
    <div class="flex min-h-screen">
        <div x-show="sidebar" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-sm lg:hidden" @click="sidebar=false"></div>
        <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'" :inert="!sidebar && window.innerWidth < 1024" class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200 bg-[#f1f5f3] transition-transform duration-300 lg:translate-x-0">
            <div class="flex h-20 items-center gap-3 px-6">
                <x-portfolio-back-button class="min-w-0 flex-1" />
                <button @click="sidebar=false" class="ml-auto lg:hidden" aria-label="Close navigation"><i data-lucide="x" class="size-5"></i></button>
            </div>
            <nav class="flex-1 overflow-y-auto px-4 pb-6">
                @foreach($nav as $group => $items)
                    <p class="mb-2 mt-5 px-3 text-[10px] font-bold tracking-[.16em] text-slate-400 uppercase">{{ $group }}</p>
                    <div class="grid gap-1">
                        @foreach($items as $key => $label)
                            <a href="{{ $key === 'dashboard' ? route('dashboard') : route('modules.show', $key) }}" class="nav-link {{ request()->routeIs('dashboard') && $key === 'dashboard' || request()->route('module') === $key ? 'nav-link-active' : '' }}">
                                <i data-lucide="{{ $icons[$key] ?? 'circle' }}" class="size-[18px]"></i><span>{{ $label }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>
            <div class="border-t border-slate-200 p-4">
                @if(config('lms.demo_mode'))<div class="mb-3 flex items-center gap-2 rounded-xl bg-brand-50 px-3 py-2 text-xs font-semibold text-brand-700"><span class="size-2 animate-pulse rounded-full bg-brand-500"></span> Demo environment</div>@endif
                <div class="flex items-center gap-3 rounded-xl p-2">
                    <div class="grid size-10 shrink-0 place-items-center rounded-xl text-sm font-bold text-white" style="background:{{ auth()->user()->avatar_color }}">{{ collect(explode(' ', auth()->user()->name))->map(fn($n) => mb_substr($n,0,1))->take(2)->join('') }}</div>
                    <div class="min-w-0"><p class="truncate text-sm font-bold text-ink">{{ auth()->user()->name }}</p><p class="truncate text-xs capitalize text-slate-500">{{ auth()->user()->role === 'admin' ? 'Principal / Admin' : auth()->user()->role }}</p></div>
                    <form method="POST" action="{{ route('logout') }}" class="ml-auto">@csrf<button class="rounded-lg p-2 text-slate-400 hover:bg-white hover:text-rose-600" aria-label="Sign out"><i data-lucide="log-out" class="size-[18px]"></i></button></form>
                </div>
            </div>
        </aside>

        <main class="min-w-0 flex-1 pt-20 lg:pl-72">
            <header class="fixed inset-x-0 top-0 z-30 flex h-20 items-center gap-4 border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur-xl sm:px-8 lg:left-72">
                <button @click="sidebar=true" class="rounded-xl p-2 hover:bg-slate-100 lg:hidden" aria-label="Open navigation"><i data-lucide="menu" class="size-5"></i></button>
                <div class="relative hidden max-w-md flex-1 md:block"><i data-lucide="search" class="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i><input x-model="search" @keydown.enter.prevent="if(matches[0]) location.href=matches[0].url" class="border-0 bg-slate-100/80 py-2.5 pl-10 focus:bg-white" placeholder="Find a module…" aria-label="Find a module" autocomplete="off"><div x-show="matches.length" x-cloak @click.outside="search=''" class="absolute left-0 right-0 top-12 z-50 overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-xl"><template x-for="item in matches" :key="item.url"><a :href="item.url" class="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold hover:bg-slate-50"><span x-text="item.label"></span><i data-lucide="arrow-right" class="size-4 text-slate-400"></i></a></template></div></div>
                <div class="ml-auto flex items-center gap-2">
                    <span class="hidden rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-500 sm:inline">AY {{ config('lms.academic_year') }}</span>
                    <a href="{{ route('modules.show', auth()->user()->role === 'parent' ? 'notifications' : 'notices') }}" class="relative rounded-xl border border-slate-200 bg-white p-2.5 text-slate-500 hover:bg-slate-50" aria-label="{{ auth()->user()->role === 'parent' ? 'Notifications' : 'Notices' }}"><i data-lucide="bell" class="size-5"></i><span class="absolute right-2 top-2 size-2 rounded-full bg-rose-500 ring-2 ring-white"></span></a>
                </div>
            </header>
            <div class="mx-auto max-w-[1480px] p-4 sm:p-6 lg:p-8">
                @if(session('success'))<div class="mb-5 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800"><i data-lucide="circle-check" class="size-5"></i>{{ session('success') }}</div>@endif
                @if($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><p class="font-bold">Please correct the following:</p><ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                @yield('content')
            </div>
        </main>
    </div>
    <script>document.addEventListener('DOMContentLoaded', () => window.lucide?.createIcons());</script>
</body>
</html>
