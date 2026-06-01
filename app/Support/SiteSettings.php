<?php

namespace App\Support;

class SiteSettings
{
    public static function path(): string
    {
        return storage_path('app/site-settings.json');
    }

    public static function get(): array
    {
        $path = self::path();
        if (!is_file($path)) {
            return self::defaults();
        }

        $json = file_get_contents($path);
        $data = json_decode((string) $json, true);

        return is_array($data) ? array_merge(self::defaults(), $data) : self::defaults();
    }

    public static function put(array $settings): void
    {
        $path = self::path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode(array_merge(self::defaults(), $settings), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public static function defaults(): array
    {
        return [
            'moneda' => 'PEN',
            'prefijo' => 'S/.',
            'branding' => 'Delicias',
        ];
    }
}
