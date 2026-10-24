<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EndpointPushPermitido implements ValidationRule
{
    private const HOSTS = [
        'fcm.googleapis.com',
        'android.googleapis.com',
        'updates.push.services.mozilla.com',
        'web.push.apple.com',
    ];

    private const SUFIJOS = [
        '.push.services.mozilla.com',
        '.push.apple.com',
        '.notify.windows.com',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->esPermitido($value)) {
            $fail('El endpoint de notificaciones no pertenece a un servicio de push reconocido.');
        }
    }

    public function esPermitido(mixed $endpoint): bool
    {
        $partes = is_string($endpoint) ? parse_url($endpoint) : false;

        if (! is_array($partes)) {
            return false;
        }

        $host = strtolower($partes['host'] ?? '');

        if (strtolower($partes['scheme'] ?? '') !== 'https'
            || $host === ''
            || isset($partes['user'])
            || isset($partes['pass'])
            || (isset($partes['port']) && (int) $partes['port'] !== 443)) {
            return false;
        }

        if (in_array($host, self::HOSTS, true)) {
            return true;
        }

        foreach (self::SUFIJOS as $sufijo) {
            if (str_ends_with($host, $sufijo)) {
                return true;
            }
        }

        return false;
    }
}
