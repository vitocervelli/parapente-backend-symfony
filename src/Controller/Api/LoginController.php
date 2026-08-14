<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * La ruta debe existir para que `json_login` la intercepte, pero el cuerpo del
 * método nunca llega a ejecutarse: el firewall responde antes con el token o
 * con un 401.
 */
final class LoginController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function __invoke(): JsonResponse
    {
        throw new \LogicException('Lo gestiona el firewall json_login; no debería llegarse aquí.');
    }
}
