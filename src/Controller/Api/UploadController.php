<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/** Subida de imágenes desde el panel. */
#[Route('/api/admin/uploads')]
#[IsGranted('ROLE_ADMIN')]
final class UploadController extends AbstractController
{
    private const MAX_BYTES = 8 * 1024 * 1024;
    private const VIDEO_MAX_BYTES = 100 * 1024 * 1024;

    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    private const VIDEO_MIME = [
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm' => 'webm',
    ];

    private const FOLDERS = ['services', 'icons', 'allies', 'gallery', 'reels'];

    public function __construct(
        private readonly SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    #[Route('', name: 'api_admin_uploads', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        $folder = (string) $request->request->get('folder', 'services');

        if (!\in_array($folder, self::FOLDERS, true)) {
            return $this->error('invalid_folder', sprintf('La carpeta debe ser una de: %s.', implode(', ', self::FOLDERS)));
        }

        if (!$file instanceof UploadedFile) {
            // Sin fichero suele significar que se superó post_max_size de PHP,
            // que descarta el cuerpo entero sin avisar.
            return $this->error('no_file', 'No llegó ningún archivo. Si la imagen es muy grande, prueba con una más ligera.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            return $this->error('file_too_large', 'La imagen supera los 8 MB.');
        }

        $mime = (string) $file->getMimeType();
        if (!isset(self::ALLOWED_MIME[$mime])) {
            return $this->error('invalid_type', 'Formato no admitido. Usa JPG, PNG, WebP o SVG.');
        }

        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $name = sprintf(
            '%s-%s.%s',
            $this->slugger->slug($original)->lower()->toString() ?: 'imagen',
            bin2hex(random_bytes(4)),
            self::ALLOWED_MIME[$mime],
        );

        $targetDir = $this->publicDir . '/uploads/' . $folder;

        try {
            $file->move($targetDir, $name);
        } catch (FileException $e) {
            return $this->error('upload_failed', 'No se pudo guardar la imagen: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['data' => ['path' => sprintf('/uploads/%s/%s', $folder, $name)]], Response::HTTP_CREATED);
    }

    /**
     * Subida de un vídeo público (reels de la portada). Va a public/uploads para
     * servirse directo, con un tope de tamaño mayor que las imágenes.
     */
    #[Route('/video', name: 'api_admin_uploads_video', methods: ['POST'])]
    public function uploadVideo(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        $folder = (string) $request->request->get('folder', 'reels');

        if (!\in_array($folder, self::FOLDERS, true)) {
            return $this->error('invalid_folder', sprintf('La carpeta debe ser una de: %s.', implode(', ', self::FOLDERS)));
        }

        if (!$file instanceof UploadedFile) {
            return $this->error('no_file', 'No llegó ningún archivo. Si el vídeo pesa mucho, comprímelo antes de subirlo.');
        }

        if ($file->getSize() > self::VIDEO_MAX_BYTES) {
            return $this->error('file_too_large', 'El vídeo supera los 100 MB.');
        }

        $mime = (string) $file->getMimeType();
        if (!isset(self::VIDEO_MIME[$mime])) {
            return $this->error('invalid_type', 'Formato no admitido. Usa MP4, MOV o WebM.');
        }

        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $name = sprintf(
            '%s-%s.%s',
            $this->slugger->slug($original)->lower()->toString() ?: 'reel',
            bin2hex(random_bytes(4)),
            self::VIDEO_MIME[$mime],
        );

        $targetDir = $this->publicDir . '/uploads/' . $folder;

        try {
            $file->move($targetDir, $name);
        } catch (FileException $e) {
            return $this->error('upload_failed', 'No se pudo guardar el vídeo: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['data' => ['path' => sprintf('/uploads/%s/%s', $folder, $name)]], Response::HTTP_CREATED);
    }

    private function error(string $code, string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
