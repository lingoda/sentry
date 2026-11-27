<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Tests\Sentry\Fixture\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class Address
{
    public function __construct(
        #[ORM\Column]
        private string $street,
        #[ORM\Column]
        private string $city,
    ) {
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getCity(): string
    {
        return $this->city;
    }
}
