<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Security\Voter\ProjectVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function index(
        ProjectRepository $projectRepository,
        AuthorizationCheckerInterface $authorizationChecker,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $projects = array_values(array_filter(
            $projectRepository->findDashboardProjectsForUser($user),
            fn(Project $project): bool => $authorizationChecker->isGranted(ProjectVoter::VIEW, $project),
        ));

        return $this->render('dashboard/index.html.twig', [
            'projects' => $projects,
        ]);
    }
}
