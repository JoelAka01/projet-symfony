<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\SubscriptionPlan;
use App\Form\CheckoutType;
use App\Repository\SubscriptionRepository;
use App\Service\Billing\AnalysisQuotaManager;
use App\Service\Billing\PlanCatalog;
use App\Service\Billing\SubscriptionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BillingController extends AbstractController
{
    #[Route('/pricing', name: 'app_pricing', methods: ['GET'])]
    public function pricing(
        Request $request,
        PlanCatalog $planCatalog,
        SubscriptionRepository $subscriptionRepository,
        AnalysisQuotaManager $quotaManager,
    ): Response {
        $user = $this->getUser();

        return $this->render('billing/pricing.html.twig', [
            'plans' => $planCatalog->all(),
            'activeSubscription' => $user instanceof User ? $subscriptionRepository->findActiveForUser($user) : null,
            'allowance' => $user instanceof User ? $quotaManager->getAllowance($user, $request->getClientIp()) : null,
        ]);
    }

    #[Route('/billing/checkout/{plan}', name: 'app_billing_checkout', methods: ['GET', 'POST'])]
    public function checkout(
        string $plan,
        Request $request,
        PlanCatalog $planCatalog,
        SubscriptionManager $subscriptionManager,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $selectedPlan = SubscriptionPlan::tryFrom(strtoupper($plan));
        if (!$selectedPlan instanceof SubscriptionPlan) {
            throw $this->createNotFoundException('Subscription plan not found.');
        }

        $form = $this->createForm(CheckoutType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $subscriptionManager->purchase(
                $user,
                $selectedPlan,
                (string) $form->get('cardNumber')->getData(),
            );
            $this->addFlash('success', sprintf('%s plan activated. The payment was simulated successfully.', $selectedPlan->label()));

            return $this->redirectToRoute('app_pricing');
        }

        return $this->render('billing/checkout.html.twig', [
            'form' => $form,
            'plan' => $planCatalog->get($selectedPlan),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
