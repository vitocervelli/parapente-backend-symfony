<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Resuelve el cliente de una reserva creada por el panel: reutiliza la cuenta
 * si ya existe ese correo, o crea una cuenta de cliente ligera si no.
 *
 * Las cuentas creadas aquí nacen con una contraseña aleatoria inservible: la
 * reserva queda ligada a un cliente real (el modelo lo exige), pero no se puede
 * entrar con esa cuenta hasta que el cliente establezca su contraseña.
 */
final class CustomerProvisioner
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function findOrCreate(string $email, ?string $fullName, ?string $phone): User
    {
        $email = strtolower(trim($email));
        if ('' === $email) {
            throw BookingException::invalid('Falta el correo del cliente.');
        }

        $existing = $this->users->findOneBy(['email' => $email]);
        if (null !== $existing) {
            // Se completan los datos que falten sin pisar los que el cliente ya tenga.
            if (null !== $fullName && '' !== $fullName && ('' === (string) $existing->getFullName())) {
                $existing->setFullName($fullName);
            }
            if (null !== $phone && '' !== $phone && ('' === (string) $existing->getPhone())) {
                $existing->setPhone($phone);
            }
            $this->em->flush();

            return $existing;
        }

        $user = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_CUSTOMER'])
            ->setFullName($fullName)
            ->setPhone($phone);
        $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(16))));

        $violations = $this->validator->validate($user);
        if (count($violations) > 0) {
            throw BookingException::invalid($violations->get(0)->getMessage());
        }

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
