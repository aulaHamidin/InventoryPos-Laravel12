<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\LoginAction;
use App\Actions\Auth\LogoutAction;
use App\Http\Controllers\Controller;
use App\Support\AuditContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request, LoginAction $action): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'], 'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->success(
            $action->execute($data['email'], $data['password'], $data['device_name'] ?? 'api', AuditContext::fromRequest($request)),
            'Login berhasil.',
        );
    }

    public function logout(Request $request, LogoutAction $action): JsonResponse
    {
        $action->execute($request->user(), AuditContext::fromRequest($request));

        return $this->success(null, 'Logout berhasil.');
    }
}
