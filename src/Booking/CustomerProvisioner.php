<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\ReadablePasswordGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Resuelve el cliente de una reserva creada por el panel: reutiliza la cuenta
 * si ya existe ese correo, o crea una cuenta de cliente ligera si no.
 *
 * Las cuentas nuevas nacen con una contraseña TEMPORAL fácil de teclear, que se
 * devuelve en claro (dentro de ProvisionedCustomer) para que el llamador se la
 * envíe por correo. El cliente entra con ella y la cambia desde su perfil.
 */
final class CustomerProvisioner
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
        private readonly ReadablePasswordGenerator $passwordGenerator,
    ) {
    }

    public function findOrCreate(string $email, ?string $fullName, ?string $phone): ProvisionedCustomer
    {
        $email = strtolower(trim($email));
        if ('' === $email) {
            throw BookingException::invalid('Falta el correo del cliente.');
        }

        $existing = $this->users->findOneBy(['email' => $email]);
        if (null !== $existing) {
            // Se completan los datos que falten sin pisar los que el cliente ya tenga.
            // NUNCA se toca la contraseña de una cuenta que ya existe.
            if (null !== $fullName && '' !== $fullName && ('' === (string) $existing->getFullName())) {
                $existing->setFullName($fullName);
            }
            if (null !== $phone && '' !== $phone && ('' === (string) $existing->getPhone())) {
                $existing->setPhone($phone);
            }
            $this->em->flush();

            return new ProvisionedCustomer($existing);
        }

        $temporaryPassword = $this->passwordGenerator->generate();

        $user = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_CUSTOMER'])
            ->setFullName($fullName)
            ->setPhone($phone);
        $user->setPassword($this->hasher->hashPassword($user, $temporaryPassword));

        $violations = $this->validator->validate($user);
        if (count($violations) > 0) {
            throw BookingException::invalid($violations->get(0)->getMessage());
        }

        $this->em->persist($user);
        $this->em->flush();

        return new ProvisionedCustomer($user, $temporaryPassword);
    }
}
