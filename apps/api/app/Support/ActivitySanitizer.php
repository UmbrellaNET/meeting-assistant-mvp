<?php

namespace App\Support;

class ActivitySanitizer
{
    /**
     * @param  mixed  $value
     * @return mixed
     */
    public function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeArray($value);
        }

        if ($value instanceof \Illuminate\Support\Collection) {
            return $value->map(fn (mixed $item) => $this->sanitize($item));
        }

        return $value;
    }

    /**
     * @param  array<string|int, mixed>  $payload
     * @return array<string|int, mixed>
     */
    public function sanitizeArray(array $payload): array
    {
        $sensitive = collect(config('activity.sensitive_keys', []))
            ->map(fn (string $key) => strtolower($key))
            ->all();

        $clean = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitive, true)) {
                $clean[$key] = '[redacted]';
                continue;
            }

            $clean[$key] = $this->sanitize($value);
        }

        return $clean;
    }
}
