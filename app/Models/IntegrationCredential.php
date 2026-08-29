<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class IntegrationCredential extends Model
{
    protected $fillable = [
        'integration_id',
        'credential_type',
        'label',
        'encrypted_value',
        'masked_hint',
        'metadata',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'encrypted_value',
    ];

    public const CREDENTIAL_TYPES = [
        'api_token',
        'username_password',
        'ssh_key',
        'shared_secret',
        'certificate',
        'oauth',
        'none',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function setSecretValue(string $value): void
    {
        $this->encrypted_value = Crypt::encryptString($value);
        $this->masked_hint = $this->generateMaskedHint($value);
    }

    public function getSecretValue(): string
    {
        return Crypt::decryptString($this->encrypted_value);
    }

    private function generateMaskedHint(string $value): string
    {
        $len = strlen($value);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return str_repeat('*', max(8, $len - 4)) . substr($value, -4);
    }
}
