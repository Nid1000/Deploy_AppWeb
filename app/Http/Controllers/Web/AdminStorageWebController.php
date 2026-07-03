<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\GoogleCloudStorageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class AdminStorageWebController extends Controller
{
    public function __construct(
        private readonly GoogleCloudStorageService $storage,
    ) {
    }

    public function index(): View
    {
        return view('admin.storage.index', [
            'bucketName' => (string) config('services.gcs.bucket', 'almacendelicias'),
            'uploadPrefix' => (string) config('services.gcs.upload_prefix', 'uploads'),
            'signedUrlTtl' => (int) config('services.gcs.signed_url_ttl', 60),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'max:10240'],
            'prefijo' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\\-\\/]+$/'],
        ], [
            'archivo.required' => 'Selecciona un archivo para subir.',
            'archivo.max' => 'El archivo no debe superar los 10 MB.',
            'prefijo.regex' => 'El prefijo solo puede contener letras, numeros, guiones, guion bajo y slash.',
        ]);

        try {
            $uploaded = $this->storage->upload($request->file('archivo'), $data['prefijo'] ?? null);
        } catch (Throwable $exception) {
            return back()
                ->withInput($request->except('archivo'))
                ->with('error', 'No se pudo subir el archivo a Google Cloud Storage: ' . $exception->getMessage());
        }

        return back()
            ->with('success', 'Archivo subido correctamente a Google Cloud Storage.')
            ->with('gcs_uploaded', $uploaded);
    }
}
