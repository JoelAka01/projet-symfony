<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CmsConnection;
use App\Entity\Project;
use App\Exception\CmsIntegrationException;
use App\Form\CmsConnectionType;
use App\Security\Voter\ProjectVoter;
use App\Service\Cms\CmsConnectionConfigurator;
use App\Service\Cms\CmsConnectionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CmsConnectionController extends AbstractController
{
    #[Route('/projects/{project}/cms', name: 'app_cms_connection_index', methods: ['GET'])]
    public function index(Project $project): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE, $project);

        return $this->render('cms_connection/index.html.twig', [
            'project' => $project,
            'connections' => $project->getCmsConnections(),
        ]);
    }

    #[Route('/projects/{project}/cms/new', name: 'app_cms_connection_new', methods: ['GET', 'POST'])]
    public function new(
        Project $project,
        Request $request,
        CmsConnectionConfigurator $configurator,
        CmsConnectionService $connectionService,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE, $project);

        $connection = new CmsConnection();
        $connection->setProject($project);
        $form = $this->createForm(CmsConnectionType::class, $connection);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $configurator->configure($connection, $this->credentials($form));
                $entityManager->persist($connection);
                $entityManager->flush();

                try {
                    $result = $connectionService->test($connection);
                    $this->addFlash('success', $result->message);
                } catch (\Throwable $exception) {
                    $this->addFlash('error', 'Connection saved, but the real CMS test failed: ' . $exception->getMessage());
                }

                return $this->redirectToRoute('app_cms_connection_index', ['project' => $project->getId()]);
            } catch (CmsIntegrationException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('cms_connection/form.html.twig', [
            'project' => $project,
            'connection' => $connection,
            'form' => $form,
            'pageTitle' => 'Connect a CMS',
        ]);
    }

    #[Route('/projects/{project}/cms/{connection}/edit', name: 'app_cms_connection_edit', methods: ['GET', 'POST'])]
    public function edit(
        Project $project,
        CmsConnection $connection,
        Request $request,
        CmsConnectionConfigurator $configurator,
        CmsConnectionService $connectionService,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->assertConnectionProject($project, $connection);
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE, $project);

        $form = $this->createForm(CmsConnectionType::class, $connection);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $configurator->configure($connection, $this->credentials($form));
                $connection->setIsActive(true);
                $entityManager->flush();

                try {
                    $result = $connectionService->test($connection);
                    $this->addFlash('success', $result->message);
                } catch (\Throwable $exception) {
                    $this->addFlash('error', 'Settings saved, but the real CMS test failed: ' . $exception->getMessage());
                }

                return $this->redirectToRoute('app_cms_connection_index', ['project' => $project->getId()]);
            } catch (CmsIntegrationException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('cms_connection/form.html.twig', [
            'project' => $project,
            'connection' => $connection,
            'form' => $form,
            'pageTitle' => 'Edit CMS connection',
        ]);
    }

    #[Route('/projects/{project}/cms/{connection}/test', name: 'app_cms_connection_test', methods: ['POST'])]
    public function test(
        Project $project,
        CmsConnection $connection,
        Request $request,
        CmsConnectionService $connectionService,
    ): RedirectResponse {
        $this->assertConnectionProject($project, $connection);
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE, $project);
        $this->assertCsrf('test_cms_' . $connection->getId(), $request);

        try {
            $result = $connectionService->test($connection);
            $this->addFlash('success', $result->message);
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Real CMS connection test failed: ' . $exception->getMessage());
        }

        return $this->redirectToRoute('app_cms_connection_index', ['project' => $project->getId()]);
    }

    #[Route('/projects/{project}/cms/{connection}/delete', name: 'app_cms_connection_delete', methods: ['POST'])]
    public function delete(
        Project $project,
        CmsConnection $connection,
        Request $request,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        $this->assertConnectionProject($project, $connection);
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE, $project);
        $this->assertCsrf('delete_cms_' . $connection->getId(), $request);

        $entityManager->remove($connection);
        $entityManager->flush();
        $this->addFlash('success', 'CMS connection deleted.');

        return $this->redirectToRoute('app_cms_connection_index', ['project' => $project->getId()]);
    }

    /** @return array<string, mixed> */
    private function credentials(\Symfony\Component\Form\FormInterface $form): array
    {
        return [
            'username' => $form->get('username')->getData(),
            'applicationPassword' => $form->get('applicationPassword')->getData(),
            'accessToken' => $form->get('accessToken')->getData(),
            'shopifyBlogId' => $form->get('shopifyBlogId')->getData(),
            'authorName' => $form->get('authorName')->getData(),
        ];
    }

    private function assertConnectionProject(Project $project, CmsConnection $connection): void
    {
        if ($connection->getProject() !== $project) {
            throw $this->createNotFoundException('CMS connection not found for this project.');
        }
    }

    private function assertCsrf(string $tokenId, Request $request): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Invalid CMS action CSRF token.');
        }
    }
}
