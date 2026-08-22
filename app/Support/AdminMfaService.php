<?php

namespace App\Support;

use App\Models\Admin;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class AdminMfaService
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function ensureSecret(Admin $admin): string
    {
        if (is_string($admin->two_factor_secret) && $admin->two_factor_secret !== '') {
            return $admin->two_factor_secret;
        }

        $secret = $this->google2fa->generateSecretKey(32);
        $admin->forceFill(['two_factor_secret' => $secret])->save();

        return $secret;
    }

    public function qrSvg(Admin $admin): string
    {
        $uri = $this->google2fa->getQRCodeUrl((string) config('app.name'), $admin->email, $this->ensureSecret($admin));
        $renderer = new ImageRenderer(new RendererStyle(240, 2), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($uri);
    }

    /** @return list<string> */
    public function confirm(Admin $admin, string $code): array
    {
        return DB::transaction(function () use ($admin, $code): array {
            $admin = Admin::query()->lockForUpdate()->findOrFail($admin->getKey());
            $step = $this->google2fa->verifyKeyNewer($this->ensureSecret($admin), $code, $admin->two_factor_last_used_step ?? 0, 1);
            abort_if($step === false, 422, 'Kode autentikasi tidak valid.');

            $codes = collect(range(1, 8))->map(fn (): string => strtoupper(Str::random(5).'-'.Str::random(5)))->all();
            $admin->forceFill([
                'two_factor_confirmed_at' => now(),
                'two_factor_last_used_step' => $step,
                'two_factor_recovery_code_hashes' => array_map(fn (string $recovery): string => Hash::make($recovery), $codes),
            ])->save();

            return $codes;
        }, 3);
    }

    public function verify(Admin $admin, string $code): bool
    {
        return DB::transaction(function () use ($admin, $code): bool {
            $admin = Admin::query()->lockForUpdate()->findOrFail($admin->getKey());
            if (! $admin->two_factor_confirmed_at || ! is_string($admin->two_factor_secret)) {
                return false;
            }

            $step = $this->google2fa->verifyKeyNewer($admin->two_factor_secret, $code, $admin->two_factor_last_used_step, 1);
            if ($step !== false) {
                $admin->forceFill(['two_factor_last_used_step' => $step])->save();

                return true;
            }

            $hashes = $admin->two_factor_recovery_code_hashes ?? [];
            foreach ($hashes as $index => $hash) {
                if (Hash::check(strtoupper(trim($code)), $hash)) {
                    unset($hashes[$index]);
                    $admin->forceFill(['two_factor_recovery_code_hashes' => array_values($hashes)])->save();

                    return true;
                }
            }

            return false;
        }, 3);
    }
}
