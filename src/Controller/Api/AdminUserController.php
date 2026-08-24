<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\BookingPresenter;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\UserRepository;
use App\Storage\PrivateFileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Listado y detalle de usuarios registrados para el panel. */
#[Route('/api/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly BookingRepository $bookings,
        private readonly BookingPresenter $presenter,
        private readonly EntityManagerInterface $em,
        private readonly PrivateFileStorage $storage,
        private readonly LoggerInterface $logger,
        // Canal «audit» (config/packages/monolog.yaml): fichero var/log/audit.log.
        private readonly LoggerInterface $auditLogger,
    ) {
    }

    #[Route('', name: 'api_admin_users_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $counts = $this->bookings->countByCustomer();
        $users = $this->users->findAllOrderedByNewest();

        $data = array_map(
            fn (User $u): array => $this->present($u, $counts[$u->getId()] ?? 0),
            $users,
        );

        return new JsonResponse([
            'data' => $data,
            'meta' => ['total' => count($data)],
        ]);
    }

    #[Route('/{id}', name: 'api_admin_users_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $user = $this->users->find($id);
        if (null === $user) {
            return new JsonResponse(
                ['error' => ['code' => 'user_not_found', 'message' => 'No encontramos ese usuario.']],
                Response::HTTP_NOT_FOUND,
            );
        }

        $bookings = $this->bookings->findAllForCustomerAdmin($user);

        return new JsonResponse(['data' => [
            'user' => $this->present($user, count($bookings)),
            'bookings' => $this->presenter->bookings($bookings, forAdmin: true),
        ]]);
    }

    /**
     * Elimina un cliente y TODO lo asociado: sus reservas (con líneas,
     * asistentes, comprobantes y fotos) y los ficheros privados en disco. No se
     * puede deshacer. No se permite borrar cuentas del equipo ni la propia.
     */
    #[Route('/{id}', name: 'api_admin_users_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->users->find($id);
        if (null === $user) {
            return new JsonResponse(
                ['error' => ['code' => 'user_not_found', 'message' => 'No encontramos ese usuario.']],
                Response::HTTP_NOT_FOUND,
            );
        }

        if ($user->isAdmin()) {
            return new JsonResponse(
                ['error' => ['code' => 'cannot_delete_admin', 'message' => 'No se pueden eliminar cuentas del equipo.']],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $current = $this->getUser();
        if ($current instanceof User && $current->getId() === $user->getId()) {
            return new JsonResponse(
                ['error' => ['code' => 'cannot_delete_self', 'message' => 'No puedes eliminar tu propia cuenta.']],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $bookings = $this->bookings->findAllForCustomerAdmin($user);

        // Se guardan los datos para el registro de auditoría ANTES de borrar:
        // tras el remove+flush, el id de la entidad queda a null.
        $deletedUser = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
        ];
        $admin = $current instanceof User
            ? ['id' => $current->getId(), 'email' => $current->getEmail()]
            : ['id' => null, 'email' => null];

        // Rutas de los ficheros privados (comprobantes y fotos de vuelo). Se
        // recogen antes de borrar y se eliminan del disco al final.
        $paths = [];
        foreach ($bookings as $booking) {
            foreach ($booking->getProofs() as $proof) {
                $paths[] = $proof->getStoragePath();
            }
            foreach ($booking->getMedia() as $media) {
                $paths[] = $media->getStoragePath();
            }
        }

        // La base de datos primero. Se borran las reservas antes que el cliente
        // (la FK cliente→reserva es RESTRICT); al borrar cada reserva, la base
        // de datos cascada líneas, asistentes, comprobantes y fotos.
        foreach ($bookings as $booking) {
            $this->em->remove($booking);
        }
        $this->em->flush();

        $this->em->remove($user);
        $this->em->flush();

        // El disco al final: un fichero huérfano es inocuo; una fila apuntando a
        // un fichero inexistente, no. Los fallos se registran, no rompen el borrado.
        $filesDeleted = 0;
        foreach ($paths as $path) {
            try {
                $this->storage->delete($path);
                ++$filesDeleted;
            } catch (\RuntimeException $e) {
                $this->logger->warning('No se pudo borrar un fichero al eliminar el cliente.', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Registro de auditoría: quién eliminó a quién y cuándo (Monolog añade la
        // marca de tiempo). Va al canal «audit» → var/log/audit.log.
        $this->auditLogger->info('Cliente eliminado desde el panel.', [
            'action' => 'user.delete',
            'admin' => $admin,
            'deletedUser' => $deletedUser,
            'bookingsDeleted' => \count($bookings),
            'filesDeleted' => $filesDeleted,
        ]);

        return new JsonResponse(['data' => [
            'ok' => true,
            'bookingsDeleted' => \count($bookings),
            'filesDeleted' => $filesDeleted,
        ]]);
    }

    /** @return array<string,mixed> */
    private function present(User $user, int $bookingsCount): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'idNumber' => $user->getIdNumber(),
            'phone' => $user->getPhone(),
            'isAdmin' => $user->isAdmin(),
            'createdAt' => $user->getCreatedAt()->format(\DATE_ATOM),
            'bookingsCount' => $bookingsCount,
        ];
    }
}
