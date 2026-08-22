<?php

namespace App\Support;

use RuntimeException;

final class IdentityHasher
{
    public function __construct(private readonly ?string $key = null) {}

    public function phone(string $phone): string
    {
        $canonical = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($canonical, '0')) {
            $canonical = '62'.substr($canonical, 1);
        }

        if ($canonical === '') {
            throw new RuntimeException('Nomor telepon tidak valid.');
        }

        return $this->value($canonical);
    }

    public function value(string $identity): string
    {
        $key = $this->key ?? (string) config('services.identity_hash_key');
        if ($key === '') {
            throw new RuntimeException('IDENTITY_HASH_KEY wajib dikonfigurasi.');
        }
        if ($identity === '') {
            throw new RuntimeException('Identity value tidak valid.');
        }

        return hash_hmac('sha256', $identity, $key);
    }
}
