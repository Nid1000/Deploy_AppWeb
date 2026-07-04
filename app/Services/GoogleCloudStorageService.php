<?php

namespace App\Services;

use Google\Cloud\Storage\StorageClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleCloudStorageService
{
    public function upload(UploadedFile $file, ?string $prefix = null): array
    {
        $bucketName = $this->bucketName();
        $objectName = $this->objectName($file, $prefix);
        $stream = fopen($file->getRealPath(), 'r');

        if ($stream === false) {
            throw new RuntimeException('No se pudo leer el archivo temporal.');
        }

        try {
            $object = $this->client()
                ->bucket($bucketName)
                ->upload($stream, [
                    'name' => $objectName,
                    'metadata' => [
                        'contentType' => $file->getMimeType() ?: 'application/octet-stream',
                    ],
                ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return [
            'bucket' => $bucketName,
            'object' => $objectName,
            'gs_uri' => 'gs://' . $bucketName . '/' . $objectName,
            'signed_url' => $object->signedUrl($this->signedUrlExpiration(), [
                'version' => 'v4',
            ]),
        ];
    }

    private function client(): StorageClient
    {
        $options = array_filter([
            'projectId' => config('services.gcs.project_id'),
            'keyFilePath' => config('services.gcs.key_file'),
        ]);

        return new StorageClient($options);
    }

    private function bucketName(): string
    {
        $bucket = trim((string) config('services.gcs.bucket', ''));
        if ($bucket === '') {
            throw new RuntimeException('Configura GCS_BUCKET_NAME para usar Google Cloud Storage.');
        }

        return $bucket;
    }

    private function objectName(UploadedFile $file, ?string $prefix): string
    {
        $configuredPrefix = trim((string) ($prefix ?: config('services.gcs.upload_prefix', 'uploads')), '/');
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $safeName = Str::slug($originalName) ?: 'archivo';
        $filename = Str::uuid()->toString() . '-' . $safeName . ($extension !== '' ? '.' . $extension : '');

        return ($configuredPrefix !== '' ? $configuredPrefix . '/' : '') . $filename;
    }

    private function signedUrlExpiration(): \DateTimeImmutable
    {
        $minutes = max(1, (int) config('services.gcs.signed_url_ttl', 60));

        return new \DateTimeImmutable('+' . $minutes . ' minutes');
    }
}
