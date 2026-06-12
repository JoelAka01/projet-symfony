<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Entity\Audit;
use App\Service\Audit\AuditInsightsBuilder;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final class AuditPdfGenerator
{
    public function __construct(
        private readonly Environment $twig,
        private readonly AuditInsightsBuilder $insightsBuilder,
    ) {}

    public function generate(Audit $audit): string
    {
        $html = $this->twig->render('audit/pdf.html.twig', [
            'audit' => $audit,
            'project' => $audit->getProject(),
            'insights' => $this->insightsBuilder->build($audit),
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }
}
