<?php

namespace App\Service;

use App\Entity\Document;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class DocumentStorageService
{
    public function __construct(
        private readonly string $documentsDirectory,
        private readonly string $legacyDocumentsDirectory,
        private readonly SluggerInterface $slugger
    ) {
    }

    public function storeUploadedFile(UploadedFile $file): array
    {
        $originalName = $file->getClientOriginalName();
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeName = (string) $this->slugger->slug($baseName ?: 'document');
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $filename = $safeName . '-' . bin2hex(random_bytes(8)) . '.' . strtolower($extension);

        $this->ensureDirectory($this->documentsDirectory);
        $file->move($this->documentsDirectory, $filename);

        $path = $this->documentsDirectory . DIRECTORY_SEPARATOR . $filename;

        return [
            'filename' => $filename,
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => mime_content_type($path) ?: $file->getClientMimeType(),
            'size' => filesize($path) ?: $file->getSize(),
            'checksum' => hash_file('sha256', $path),
        ];
    }

    public function moveConvertedFile(string $filename): ?array
    {
        $filename = basename($filename);
        $source = $this->resolvePath($filename);

        if ($source === null) {
            return null;
        }

        $target = $this->documentsDirectory . DIRECTORY_SEPARATOR . $filename;
        $this->ensureDirectory($this->documentsDirectory);

        if ($source !== $target) {
            @rename($source, $target);
        }

        return [
            'filename' => $filename,
            'path' => is_file($target) ? $target : $source,
            'original_name' => $filename,
            'mime_type' => mime_content_type(is_file($target) ? $target : $source) ?: null,
            'size' => filesize(is_file($target) ? $target : $source) ?: null,
            'checksum' => hash_file('sha256', is_file($target) ? $target : $source),
        ];
    }

    public function resolvePath(string|Document $documentOrFilename): ?string
    {
        $filename = $documentOrFilename instanceof Document
            ? $documentOrFilename->getCheminFichier()
            : $documentOrFilename;

        $filename = basename($filename);
        if ($filename === '') {
            return null;
        }

        foreach ([$this->documentsDirectory, $this->legacyDocumentsDirectory] as $directory) {
            $path = $directory . DIRECTORY_SEPARATOR . $filename;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function getPublicLegacyPath(string $filename): string
    {
        return $this->legacyDocumentsDirectory . DIRECTORY_SEPARATOR . basename($filename);
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }
}
