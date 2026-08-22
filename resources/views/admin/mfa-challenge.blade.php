@extends('admin.mfa-shell')

@section('content')
    <h1 class="text-2xl font-bold">Verifikasi keamanan</h1>
    <p class="mt-2 text-sm text-slate-600">Masukkan kode authenticator atau satu recovery code.</p>
    <form method="POST" action="{{ route('admin.mfa.verify') }}" class="mt-6 space-y-4">
        @csrf
        <label class="block text-sm font-medium" for="code">Kode</label>
        <input id="code" name="code" autocomplete="one-time-code" maxlength="32" required autofocus class="w-full rounded-lg border px-3 py-2">
        @error('code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white">Lanjutkan</button>
    </form>
@endsection
