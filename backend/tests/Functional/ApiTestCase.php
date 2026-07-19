<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Shared\Security\CsrfProtectionSubscriber;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

/**
 * Bază pentru testele funcționale de API: clientul de test pornește cu
 * perechea cookie+antet CSRF (P0-05) și cu antetul X-Requested-With —
 * exact cum trimit frontendurile reale. Protecția CSRF în sine este
 * testată separat, negativ și pozitiv, în CsrfProtectionTest.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected const CSRF_TOKEN = 'test-csrf-token';

    protected static function createClient(array $options = [], array $server = []): KernelBrowser
    {
        $client = parent::createClient($options, $server);
        $client->getCookieJar()->set(new Cookie(CsrfProtectionSubscriber::COOKIE, self::CSRF_TOKEN));
        $client->setServerParameter('HTTP_X_CSRF_TOKEN', self::CSRF_TOKEN);
        $client->setServerParameter('HTTP_X_REQUESTED_WITH', 'XMLHttpRequest');

        return $client;
    }
}
