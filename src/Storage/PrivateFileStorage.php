<?php

declare(strict_types=1);

namespace App\Storage;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Almacén de ficheros FUERA del document root.
 *
 * public/ se sirve tal cual a cualquiera que conozca la URL; un comprobante
 * bancario no puede vivir ahí. Esto guarda bajo var/uploads (o el directorio
 * que diga APP_PRIVATE_UPLOAD_DIR en producción, que debe estar fuera de la
 * carpeta de despliegue) y solo se sirve a través de endpoints autenticados.
 */
final class PrivateFileStorage
{
    public function __construct(
        #[Autowire('%app.private_upload_dir%')]
        private readonly string $baseDir,
    ) {
    }

    /**
     * Guarda el fichero y devuelve su ruta relativa y su huella.
     *
     * El nombre lo genera el servidor (aleatorio), nunca el cliente: así la
     * ruta guardada en la base de datos es de fiar por construcción.
     *
     * @return array{path: string, checksum: string}
     */
    public function store(UploadedFile $file, string $prefix, string $extension): array
    {
        $checksum = hash_file('sha256', $file->getPathname());
        if (false === $checksum) {
            throw new \RuntimeException('No se pudo leer el archivo subido.');
        }

        $relativeDir = sprintf('%s/%s', trim($prefix, '/'), (new \DateTimeImmutable())->format('Y/m'));
        $name = bin2hex(random_bytes(8)) . '.' . $extension;

        $file->move($this->baseDir . '/' . $relativeDir, $name);

        return ['path' => $relativeDir . '/' . $name, 'checksum' => $checksum];
    }

    /**
     * Ruta absoluta de un fichero guardado, comprobando con realpath que no se
     * sale del almacén. La entrada viene de la base de datos (la generó el
     * servidor), pero la comprobación cierra el path traversal por construcción.
     */
    public function absolutePath(string $relativePath): string
    {
        $absolute = realpath($this->baseDir . '/' . $relativePath);
        $base = realpath($this->baseDir);

        if (false === $absolute || false === $base || !str_starts_with($absolute, $base . \DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('El archivo no existe o está fuera del almacén.');
        }

        return $absolute;
    }

    public function exists(string $relativePath): bool
    {
        try {
            $this->absolutePath($relativePath);

            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }
}
