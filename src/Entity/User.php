<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[UniqueEntity(fields: ['email'], message: 'Ya existe una cuenta con ese correo.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email = '';

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(length: 255)]
    private string $password = '';

    #[ORM\Column(name: 'full_name', length: 160, nullable: true)]
    #[Assert\Length(max: 160)]
    private ?string $fullName = null;

    /** Cédula o documento de identidad. */
    #[ORM\Column(name: 'id_number', length: 40, nullable: true)]
    private ?string $idNumber = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** SHA-256 del token de recuperación (nunca se guarda el token en claro). */
    #[ORM\Column(name: 'reset_token_hash', length: 64, nullable: true)]
    private ?string $resetTokenHash = null;

    #[ORM\Column(name: 'reset_token_expires_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $resetTokenExpiresAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getIdNumber(): ?string
    {
        return $this->idNumber;
    }

    public function setIdNumber(?string $idNumber): static
    {
        $this->idNumber = $idNumber;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isAdmin(): bool
    {
        return \in_array('ROLE_ADMIN', $this->getRoles(), true);
    }

    /** Nombre para mostrar: el real si lo hay, si no la parte local del correo. */
    public function getDisplayName(): string
    {
        if (null !== $this->fullName && '' !== $this->fullName) {
            return $this->fullName;
        }

        return strstr($this->email, '@', true) ?: $this->email;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getResetTokenHash(): ?string
    {
        return $this->resetTokenHash;
    }

    public function setResetTokenHash(?string $resetTokenHash): static
    {
        $this->resetTokenHash = $resetTokenHash;

        return $this;
    }

    public function getResetTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->resetTokenExpiresAt;
    }

    public function setResetTokenExpiresAt(?\DateTimeImmutable $resetTokenExpiresAt): static
    {
        $this->resetTokenExpiresAt = $resetTokenExpiresAt;

        return $this;
    }

    /** Emite un token de recuperación: guarda su hash y su caducidad, y devuelve el token en claro. */
    public function startPasswordReset(string $rawToken, \DateTimeImmutable $expiresAt): static
    {
        $this->resetTokenHash = hash('sha256', $rawToken);
        $this->resetTokenExpiresAt = $expiresAt;

        return $this;
    }

    /** Limpia el token una vez usado (o al fijar una contraseña nueva). */
    public function clearPasswordReset(): static
    {
        $this->resetTokenHash = null;
        $this->resetTokenExpiresAt = null;

        return $this;
    }

    public function isResetTokenValid(\DateTimeImmutable $now): bool
    {
        return null !== $this->resetTokenHash
            && null !== $this->resetTokenExpiresAt
            && $this->resetTokenExpiresAt > $now;
    }
}
