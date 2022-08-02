<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Tests;

final class TestNamespaceObject
{
    public function __construct(private int $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
