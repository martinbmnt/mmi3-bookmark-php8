<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

final class UserDto
{
    public function __construct(
        #[Assert\NotBlank(
            message: "Field 'email' is required and cannot be empty",
            groups: ["create"]
        )]
        #[Assert\Email(
            message: "Field 'email' must be a valid email address"
        )]
        public readonly ?string $email = null,
        #[Assert\NotBlank(
            message: "Field 'password' is required and cannot be empty",
            groups: ["create"]
        )]
        public readonly ?string $password = null,
        public ?array $roles = null,
    ) {
    }

    #[Assert\Callback(groups: ["create"])]
    public function setDefaultRoles(): void
    {
        $this->roles = $this->roles ?: ["ROLE_USER"];
    }
}
