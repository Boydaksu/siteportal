<?php

declare(strict_types=1);

namespace SitePortal\Core\Auth\Models;

use SitePortal\Core\Auth\Rbac\PlatformRole;

final class User
{
    private int $id;
    private string $email;
    private string $passwordHash;
    private PlatformRole $role;
    private string $locale;
    private string $status;
    private ?string $emailVerifiedAt;
    private ?string $mfaSecret;
    private bool $mfaEnabled;
    /** @var list<string> */
    private array $mfaRecoveryCodes;
    private ?string $createdAt;
    private ?string $updatedAt;

    /**
     * @param list<string> $mfaRecoveryCodes
     */
    public function __construct(
        int $id,
        string $email,
        string $passwordHash,
        PlatformRole $role,
        string $locale = 'tr-TR',
        string $status = 'active',
        ?string $emailVerifiedAt = null,
        ?string $mfaSecret = null,
        bool $mfaEnabled = false,
        array $mfaRecoveryCodes = [],
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->role = $role;
        $this->locale = $locale;
        $this->status = $status;
        $this->emailVerifiedAt = $emailVerifiedAt;
        $this->mfaSecret = $mfaSecret;
        $this->mfaEnabled = $mfaEnabled;
        $this->mfaRecoveryCodes = $mfaRecoveryCodes;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $roleStr = (string) ($data['role'] ?? 'user');
        $role = PlatformRole::tryFrom($roleStr) ?? PlatformRole::USER;

        /** @var list<string> $recoveryCodes */
        $recoveryCodes = [];
        if (!empty($data['mfa_recovery_codes'])) {
            if (is_array($data['mfa_recovery_codes'])) {
                $recoveryCodes = array_values(array_filter(
                    $data['mfa_recovery_codes'],
                    fn($item): bool => is_string($item)
                ));
            } elseif (is_string($data['mfa_recovery_codes'])) {
                $decoded = json_decode($data['mfa_recovery_codes'], true);
                if (is_array($decoded)) {
                    $recoveryCodes = array_values(array_filter(
                        $decoded,
                        fn($item): bool => is_string($item)
                    ));
                }
            }
        }

        return new self(
            id: (int) ($data['id'] ?? 0),
            email: (string) ($data['email'] ?? ''),
            passwordHash: (string) ($data['password_hash'] ?? ''),
            role: $role,
            locale: (string) ($data['locale'] ?? 'tr-TR'),
            status: (string) ($data['status'] ?? 'active'),
            emailVerifiedAt: isset($data['email_verified_at']) ? (string) $data['email_verified_at'] : null,
            mfaSecret: isset($data['mfa_secret']) ? (string) $data['mfa_secret'] : null,
            mfaEnabled: !empty($data['mfa_enabled']),
            mfaRecoveryCodes: $recoveryCodes,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getRole(): PlatformRole
    {
        return $this->role;
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function getEmailVerifiedAt(): ?string
    {
        return $this->emailVerifiedAt;
    }

    public function isMfaEnabled(): bool
    {
        return $this->mfaEnabled && !empty($this->mfaSecret);
    }

    public function getMfaSecret(): ?string
    {
        return $this->mfaSecret;
    }

    /**
     * @return list<string>
     */
    public function getMfaRecoveryCodes(): array
    {
        return $this->mfaRecoveryCodes;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role->value,
            'locale' => $this->locale,
            'status' => $this->status,
            'email_verified_at' => $this->emailVerifiedAt,
            'mfa_enabled' => $this->mfaEnabled,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
