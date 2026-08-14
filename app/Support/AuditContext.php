<?php

namespace App\Support;

use Illuminate\Http\Request;

final readonly class AuditContext
{
    public function __construct(
        public ?string $ipAddress,
        public ?string $userAgent,
        public array $metadata = [],
    ) {}

    public static function fromRequest(Request $request, array $metadata = []): self
    {
        return new self($request->ip(), $request->userAgent(), $metadata);
    }
}
