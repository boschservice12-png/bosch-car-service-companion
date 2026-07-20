<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Document\Application\Message\ScanDocument;
use App\Document\Application\ScanDocumentHandler;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

/**
 * Blocul 1 — worker Messenger.
 *
 * În demo, scanarea documentelor și notificările sunt joburi ASINCRONE: ele
 * sunt puse pe transportul „async" și trebuie consumate de un worker separat.
 * Fără worker, un document ar rămâne veșnic PENDING (neservibil).
 *
 * Aici verificăm lanțul pe care îl execută workerul:
 *  1. încărcarea unui document DISPECERIZEAZĂ un mesaj ScanDocument pe „async";
 *  2. consumarea mesajului (ce face workerul) duce documentul din PENDING în
 *     CLEAN — deci nu rămâne blocat;
 *  3. transportul de eșec „failed" există și e inspectabil (mesajele care
 *     eșuează de max_retries ori ajung acolo, nu se pierd în tăcere).
 *
 * @group functional
 */
final class MessengerWorkerTest extends ApiTestCase
{
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testUploadEnqueuesScanConsumedToCleanAndFailedTransportExists(): void
    {
        $client = static::createClient();
        $email = 'wrk-'.uniqid().'@example.test';
        $this->register($client, $email);
        $this->login($client, $email);

        // 1) Încărcare → document PENDING + mesaj pe transportul async.
        $client->request('POST', '/api/documents', files: ['file' => $this->upload('buletin.png')]);
        self::assertResponseStatusCodeSame(201);
        $documentId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        /** @var InMemoryTransport $async */
        $async = static::getContainer()->get('messenger.transport.async');
        $queued = $async->getSent();
        self::assertCount(1, $queued, 'Încărcarea pune exact un mesaj pe transportul async.');
        $message = $queued[0]->getMessage();
        self::assertInstanceOf(ScanDocument::class, $message);
        self::assertSame($documentId, $message->documentId);

        // 2) Consumarea mesajului (ceea ce face workerul) — documentul NU rămâne PENDING.
        /** @var ScanDocumentHandler $handler */
        $handler = static::getContainer()->get(ScanDocumentHandler::class);
        $handler($message);

        $client->request('GET', '/api/documents/'.$documentId.'/download-url');
        self::assertResponseIsSuccessful('După consumare documentul devine servibil (CLEAN), nu rămâne blocat PENDING.');

        // 3) Transportul de eșec există și e configurat (inspectabil cu
        //    `messenger:failed:show`), deci mesajele eșuate nu se pierd.
        self::assertTrue(
            static::getContainer()->has('messenger.transport.failed'),
            'Transportul „failed" trebuie să existe pentru mesajele care eșuează definitiv.',
        );
    }

    private function upload(string $name): UploadedFile
    {
        $path = sys_get_temp_dir().'/bcsc_wrk_'.uniqid().'_'.$name;
        file_put_contents($path, base64_decode(self::PNG_BASE64));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    /** @return array<string, string> */
    private function json(): array
    {
        return ['CONTENT_TYPE' => 'application/json'];
    }

    private function register(KernelBrowser $client, string $email): void
    {
        $client->request('POST', '/api/auth/register', server: $this->json(), content: json_encode([
            'email' => $email, 'password' => 'Parola1234', 'consent' => true,
        ]));
        self::assertResponseStatusCodeSame(201);
    }

    private function login(KernelBrowser $client, string $email): void
    {
        $client->request('POST', '/api/auth/login', server: $this->json(), content: json_encode([
            'email' => $email, 'password' => 'Parola1234',
        ]));
        self::assertResponseIsSuccessful();
    }
}
