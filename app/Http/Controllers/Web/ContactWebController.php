<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\BackendApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactWebController extends Controller
{
    public function __construct(
        private readonly BackendApiClient $api,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'email'],
            'telefono' => ['required', 'regex:/^9\d{8}$/'],
            'mensaje' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'telefono.required' => 'Ingresa tu número de celular.',
            'telefono.regex' => 'El número debe tener 9 dígitos y empezar con 9.',
        ]);

        $response = $this->api->post('contacto', $data);
        if (!$response->successful()) {
            return redirect()->to(route('web.home') . '#contacto')
                ->withInput()
                ->with('error', $this->api->errorMessage($response, 'No se pudo enviar el mensaje.'));
        }

        return redirect()->to(route('web.home') . '#contacto')
            ->with('success', 'Tu mensaje fue enviado y te contactaremos pronto.');
    }
}
