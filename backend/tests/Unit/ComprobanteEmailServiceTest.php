<?php

namespace Tests\Unit;

use App\Services\ComprobanteEmailService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ComprobanteEmailServiceTest extends TestCase
{
    public function test_it_sends_the_receipt_pdf_to_the_registered_email(): void
    {
        config()->set([
            'services.resend.key' => 're_test_key',
            'mail.from.address' => 'no-reply@saborcentral.com',
            'mail.from.name' => 'Delicias del centro',
        ]);

        Http::fake([
            'https://api.resend.com/emails' => Http::response(['id' => 'email-id'], 200),
        ]);

        $pdfPath = tempnam(sys_get_temp_dir(), 'receipt-');
        file_put_contents($pdfPath, '%PDF test receipt');

        $result = app(ComprobanteEmailService::class)->send(
            (object) [
                'nombre' => 'Maria',
                'apellido' => 'Perez',
                'email' => 'maria@example.com',
            ],
            (object) [
                'id' => 25,
                'total' => 89.50,
            ],
            'boleta',
            'B001-00000025',
            $pdfPath,
            9
        );

        $this->assertTrue($result['enviado']);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.resend.com/emails'
                && $request->hasHeader('Idempotency-Key', 'comprobante-9')
                && in_array('maria@example.com', $request['to'], true)
                && $request['attachments'][0]['content'] === base64_encode('%PDF test receipt')
                && $request['attachments'][0]['filename'] !== '';
        });

        unlink($pdfPath);
    }

    public function test_it_does_not_call_resend_without_a_valid_registered_email(): void
    {
        Http::fake();

        $result = app(ComprobanteEmailService::class)->send(
            (object) ['email' => ''],
            (object) ['id' => 25, 'total' => 89.50],
            'boleta',
            'B001-00000025',
            __FILE__,
            9
        );

        $this->assertFalse($result['enviado']);
        Http::assertNothingSent();
    }
}
