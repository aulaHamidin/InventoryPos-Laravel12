<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class TrialClaim extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['phone_hash'];

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new RuntimeException('Trial claims are immutable.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new RuntimeException('Trial claims are immutable.');
    }
}
