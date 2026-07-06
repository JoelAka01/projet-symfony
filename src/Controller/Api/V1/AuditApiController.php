<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\Audit;
use App\Security\Voter\ProjectVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;


#[Route('/api/v1')]
final class AuditApiController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
    ) {}

    #[Route('/audits/{id}/summary', name: 'api_v1_audit_summary', methods: ['GET'])]
    public function summary(Audit $audit): JsonResponse
    {
        $project = $audit->getProject();
        if (null === $project) {
            throw $this->createNotFoundException('Audit has no associated project.');
        }

        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        // compact summary for  consumers. explicitly use symfony serializer + groups
        // (demonstrates dedicated controller + Serializer as per requirements).
        $summary = [
            'id' => $audit->getId(),
            'status' => $audit->getStatus()->value,
            'seoScore' => $audit->getSeoScore(),
            'pagesCrawled' => $audit->getPagesCrawled(),
            'pagesFailed' => $audit->getPagesFailed(),
            'aiAnalysis' => $audit->getMetadata()['ai_analysis'] ?? null,
            'project' => [
                'id' => $project->getId(),
                'name' => $project->getName(),
            ],
            'domain' => $audit->getDomain()?->getRootDomain(),
            'startedAt' => $audit->getCrawlStartedAt()?->format(\DateTimeInterface::ATOM),
            'finishedAt' => $audit->getCrawlFinishedAt()?->format(\DateTimeInterface::ATOM),
            'error' => $audit->getErrorMessage(),
        ];

        $json = $this->serializer->serialize($summary, 'json', [
            'groups' => ['audit:read', 'api:read'],
        ]);

        return new JsonResponse($json, 200, [], true);
    }
}
