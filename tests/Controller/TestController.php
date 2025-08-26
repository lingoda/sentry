<?php

namespace Lingoda\SentryBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

final class TestController extends AbstractController
{
    public function index(): JsonResponse
    {
        throw new \Exception('Testing error');
    }
}
