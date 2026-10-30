<?php

namespace VistiqCore\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;

class SystemSetting extends Model
{
    protected $fillable = ['group', 'value'];

    protected $hidden = ['value'];

    protected function value(): Attribute
    {
        $key = config('vistiq.shared_key', config('app.key'));
        $encrypter = new Encrypter(str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7)) : $key, config('app.cipher', 'AES-256-CBC'));

        return Attribute::make(
            get: fn (string $value): array => json_decode($encrypter->decryptString($value), true, 512, JSON_THROW_ON_ERROR),
            set: fn (array $value): string => $encrypter->encryptString(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
        );
    }
}
