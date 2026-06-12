<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Billing\PlanCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(PlanCatalog $planCatalog): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_project_index');
        }

        return $this->render('home/index.html.twig', [
            'plans' => $planCatalog->all(),
        ]);
    }
}
