@extends('admin.mfa-shell')

@section('content')
    <h1 class="text-2xl font-bold">Simpan recovery code</h1>
    <p class="mt-2 text-sm text-slate-600">Kode ini hanya ditampilkan sekali. Simpan di tempat yang aman.</p>
    <ul class="my-6 grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-4 font-mono text-sm">
        @foreach ($codes as $code)
            <li>{{ $code }}</li>
        @endforeach
    </ul>
    <a href="/admin" class="block w-full rounded-lg bg-indigo-600 px-4 py-2 text-center font-semibold text-white">Saya sudah menyimpan</a>
@endsection
