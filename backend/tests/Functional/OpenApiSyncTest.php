<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * P1-05 — documentația OpenAPI (docs/api/openapi.yaml) este ținută în sincron
 * cu routerul real, în AMBELE direcții:
 *  - orice rută /api reală trebuie să fie documentată (metodă + cale);
 *  - orice intrare documentată trebuie să existe în router (fără rute-fantomă).
 *
 * Testul pică cu un diff explicit — cine adaugă/șterge o rută actualizează
 * și documentul, altfel CI rămâne roșu.
 *
 * @group functional
 */
final class OpenApiSyncTest extends KernelTestCase
{
    private const METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    public function testSpecMatchesRouterBothWays(): void
    {
        $specFile = \dirname(__DIR__, 2).'/../docs/api/openapi.yaml';
        self::assertFileExists($specFile);
        /** @var array{paths?: array<string, array<string, mixed>>} $spec */
        $spec = Yaml::parseFile($specFile);
        self::assertIsArray($spec['paths'] ?? null, 'Documentul are secțiunea paths.');

        $documented = [];
        foreach ($spec['paths'] as $path => $item) {
            foreach (self::METHODS as $method) {
                if (isset($item[$method])) {
                    $documented[] = strtoupper($method).' /api'.$path;
                }
            }
        }

        self::bootKernel();
        /** @var RouterInterface $router */
        $router = self::getContainer()->get(RouterInterface::class);
        $real = [];
        foreach ($router->getRouteCollection() as $route) {
            $path = $route->getPath();
            if (!str_starts_with($path, '/api')) {
                continue;
            }
            $methods = array_diff($route->getMethods(), ['HEAD']);
            self::assertNotEmpty($methods, sprintf('Ruta %s trebuie să declare explicit metodele HTTP.', $path));
            foreach ($methods as $method) {
                $real[] = strtoupper($method).' '.$path;
            }
        }

        sort($documented);
        sort($real);

        $missingFromSpec = array_values(array_diff($real, $documented));
        $staleInSpec = array_values(array_diff($documented, $real));

        self::assertSame([], $missingFromSpec, "Rute reale nedocumentate în docs/api/openapi.yaml:\n".implode("\n", $missingFromSpec));
        self::assertSame([], $staleInSpec, "Intrări din docs/api/openapi.yaml fără rută reală (șterse/redenumite):\n".implode("\n", $staleInSpec));
        self::assertSame($real, $documented);
    }
}
