<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use App\Form\AdminPaymentType;
use App\Repository\PaymentRepository;
use App\Service\Billing\SubscriptionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/payments', name: 'app_admin_payment_')]
final class AdminPaymentController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, PaymentRepository $paymentRepository): Response
    {
        $statusValue = strtoupper(trim($request->query->getString('status')));
        $status = '' === $statusValue ? null : PaymentStatus::tryFrom($statusValue);

        return $this->render('admin/payments/index.html.twig', [
            'payments' => $paymentRepository->findForAdmin($status),
            'statuses' => PaymentStatus::cases(),
            'selectedStatus' => null === $status ? '' : $status->value,
            'paidTotalCents' => $paymentRepository->sumPaidAmountCents(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Payment $payment,
        Request $request,
        SubscriptionManager $subscriptionManager,
    ): Response {
        $form = $this->createForm(AdminPaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $subscriptionManager->synchronizePayment($payment);
            $this->addFlash('success', 'Payment and subscription status updated.');

            return $this->redirectToRoute('app_admin_payment_index');
        }

        return $this->render('admin/payments/edit.html.twig', [
            'payment' => $payment,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
