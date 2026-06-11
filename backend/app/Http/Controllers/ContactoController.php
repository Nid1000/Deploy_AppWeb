<?php

namespace App\Http\Controllers;

use App\Mail\ContactoRecibidoAdmin;
use App\Mail\ContactoRecibidoUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ContactoController extends Controller
{
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'nombre' => ['required', 'string', 'min:2', 'max:80'],
                'email' => ['required', 'email'],
                'telefono' => ['nullable', 'string', 'min:6', 'max:20'],
                'mensaje' => ['required', 'string', 'min:5', 'max:1000'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => 'Validación fallida',
                'details' => $e->errors(),
            ], 400);
        }

        Log::info('Nuevo contacto', $data);

        // Correo a soporte + acuse de recibo al usuario (si el mailer está configurado).
        try {
            $toAddress = (string) env('CONTACT_TO_ADDRESS', env('MAIL_FROM_ADDRESS'));
            $toName = (string) env('CONTACT_TO_NAME', 'Soporte');
            if ($toAddress !== '') {
                Mail::to($toAddress, $toName)->send(new ContactoRecibidoAdmin($data));
            }

            $fromUserEmail = (string) ($data['email'] ?? '');
            if ($fromUserEmail !== '') {
                Mail::to($fromUserEmail)->send(new ContactoRecibidoUsuario($data));
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar correo de contacto', ['error' => $e->getMessage()]);
        }

        $id = (string) round(microtime(true) * 1000);
        return response()->json([
            'ok' => true,
            'id' => $id,
            'message' => 'Mensaje recibido. ¡Gracias por contactarnos!',
        ], 201);
    }
}
