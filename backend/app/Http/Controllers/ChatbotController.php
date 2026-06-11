<?php

namespace App\Http\Controllers;

use App\Services\OllamaService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ChatbotController extends Controller
{
    public function __construct(private readonly OllamaService $ollama)
    {
    }

    private function faq(string $message): ?string
    {
        $m = mb_strtolower(trim($message));
        if ($m === '') {
            return null;
        }

        if (str_contains($m, 'horario') || str_contains($m, 'hora')) {
            return 'Atendemos todos los días de 8:00 a 20:00.';
        }
        if (str_contains($m, 'direcci') || str_contains($m, 'ubicaci') || str_contains($m, 'local')) {
            return 'Estamos en Huancayo. Puedes ver la ubicación exacta en la sección “Contacto”.';
        }
        if (str_contains($m, 'delivery') || str_contains($m, 'envío') || str_contains($m, 'envio')) {
            return 'Sí, hacemos delivery. El costo depende del distrito y se confirma al finalizar el pedido.';
        }
        if (str_contains($m, 'pago') || str_contains($m, 'tarjeta') || str_contains($m, 'yape')) {
            return 'Aceptamos pagos según las opciones disponibles en el checkout. Si necesitas factura/boleta, usa “Facturación”.';
        }
        if (str_contains($m, 'promoci') || str_contains($m, 'combo') || str_contains($m, 'oferta')) {
            return 'Revisa “Promociones” y “Nuevos productos” para ver combos y ofertas.';
        }

        return null;
    }

    public function health()
    {
        return response()->json([
            'statusCode' => 200,
            'ok' => true,
            'ollamaEnabled' => $this->ollama->enabled(),
            'ollamaBaseUrl' => $this->ollama->baseUrl() !== '' ? true : false,
            'ollamaModel' => $this->ollama->model(),
        ], 200);
    }

    public function ask(Request $request)
    {
        try {
            $data = $request->validate([
                'message' => ['required', 'string', 'min:1', 'max:2000'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => 'Validación fallida',
                'details' => $e->errors(),
            ], 400);
        }

        $message = (string) $data['message'];
        $fallback = $this->faq($message);
        if ($fallback) {
            return response()->json([
                'statusCode' => 200,
                'answer' => $fallback,
                'source' => 'faq',
            ], 200);
        }

        $prompt = "Eres un asistente de una panadería llamada Delicias Bakery.\n".
            "Responde breve, amable y en español.\n\n".
            "Pregunta del cliente: {$message}\n".
            "Respuesta:";

        $ai = $this->ollama->generate($prompt);
        if ($ai) {
            return response()->json([
                'statusCode' => 200,
                'answer' => $ai,
                'source' => 'ollama',
            ], 200);
        }

        return response()->json([
            'statusCode' => 200,
            'answer' => 'Puedo ayudarte con promociones, pedidos, delivery, pagos, horarios y dirección. ¿Qué necesitas?',
            'source' => 'default',
        ], 200);
    }
}

