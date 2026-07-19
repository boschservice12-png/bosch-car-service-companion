<?php

declare(strict_types=1);

namespace App\Customer\Presentation;

use App\Customer\Application\OwnerVehicleImportService;
use App\Shared\Infrastructure\Spreadsheet\SimpleXlsxReader;
use App\Shared\Presentation\ValidationFailedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Importul bazei de clienți: service-ul încarcă tabelul (Excel .xlsx sau .csv)
 * cu proprietari + vehicule; sistemul creează/actualizează conturile,
 * vehiculele și legăturile de proprietate și întoarce un raport per rând.
 */
final class AdminImportController extends AbstractController
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly OwnerVehicleImportService $importer,
        private readonly SimpleXlsxReader $xlsx,
    ) {
    }

    #[Route('/api/admin/import/clients', name: 'api_admin_import_clients', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw ValidationFailedException::fromArray(['file' => ['Încărcați un fișier .xlsx sau .csv.']]);
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationFailedException::fromArray(['file' => ['Fișier prea mare (max. 5 MB).']]);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        try {
            $rows = match ($extension) {
                'xlsx' => $this->xlsx->rows($file->getPathname()),
                'csv' => $this->csvRows($file->getPathname()),
                default => throw new \InvalidArgumentException('Format neacceptat — folosiți .xlsx sau .csv.'),
            };

            return $this->json($this->importer->import($rows));
        } catch (\InvalidArgumentException $e) {
            throw ValidationFailedException::fromArray(['file' => [$e->getMessage()]]);
        }
    }

    /** @return list<list<string>> Suportă separatorul virgulă sau punct-și-virgulă. */
    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \InvalidArgumentException('Fișierul CSV nu poate fi citit.');
        }

        $first = (string) fgets($handle);
        $separator = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        rewind($handle);

        // Elimină BOM-ul UTF-8, dacă există.
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $rows = [];
        while (($data = fgetcsv($handle, 0, $separator, '"', '\\')) !== false) {
            $rows[] = array_map(static fn ($v): string => (string) $v, $data);
        }
        fclose($handle);

        return $rows;
    }
}
