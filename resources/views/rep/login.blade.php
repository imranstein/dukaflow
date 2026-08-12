<!DOCTYPE html>
<html lang="en">
<head>
    @include('rep.partials.head', ['title' => 'DukaFlow — Sign in'])
</head>
<body class="min-h-screen bg-slate-900 text-slate-100 antialiased flex items-center justify-center p-6">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <div class="mx-auto h-12 w-12 rounded-xl bg-amber-500 flex items-center justify-center text-slate-900 font-bold text-xl">D</div>
            <h1 class="mt-4 text-xl font-semibold">DukaFlow</h1>
            <p class="text-sm text-slate-400">Sign in to start your round.</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-950 border border-red-800 text-red-200 text-sm px-4 py-3">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('rep.login.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm text-slate-300 mb-1">Email</label>
                <input id="email" name="email" type="email" autocomplete="username" required autofocus
                    value="{{ old('email') }}"
                    class="w-full rounded-lg bg-slate-800 border border-slate-700 px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-amber-500">
            </div>

            <div>
                <label for="password" class="block text-sm text-slate-300 mb-1">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                    class="w-full rounded-lg bg-slate-800 border border-slate-700 px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-amber-500">
            </div>

            <button type="submit"
                class="w-full rounded-lg bg-amber-500 text-slate-900 font-semibold py-3 active:bg-amber-600">
                Sign in
            </button>
        </form>
    </div>
</body>
</html>
