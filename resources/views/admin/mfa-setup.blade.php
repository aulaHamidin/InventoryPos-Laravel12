@extends('admin.mfa-shell')

@section('content')
    <h1 class="text-2xl font-bold">Aktifkan autentikasi dua langkah</h1>
    <p class="mt-2 text-sm text-slate-600">Pindai QR dengan aplikasi authenticator, lalu masukkan kode enam digit.</p>
    <div class="mx-auto my-6 w-fit rounded-xl border bg-white p-3">{!! $qrSvg !!}</div>
    <form method="POST" action="{{ route('admin.mfa.confirm') }}" class="space-y-4">
        @csrf
        <label class="block text-sm font-medium" for="code">Kode autentikasi</label>
        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required class="w-full rounded-lg border px-3 py-2">
        @error('code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white">Konfirmasi</button>
    </form>
@endsection
