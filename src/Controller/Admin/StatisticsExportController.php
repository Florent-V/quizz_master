<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\StatisticsExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for exporting statistics in various formats.
 *
 * Business logic is delegated to StatisticsExportService for better separation of concerns.
 */
#[Route('/admin/export/statistics')]
class StatisticsExportController extends AbstractController
{
    public function __construct(
        private readonly StatisticsExportService $exportService,
    ) {
    }

    /**
     * Exports global statistics in JSON format.
     */
    #[Route('/global.json', name: 'admin_export_stats_global', methods: ['GET'])]
    public function exportGlobalStats(): JsonResponse
    {
        $data = $this->exportService->generateGlobalStatsData();

        $response = new JsonResponse($data);
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="statistiques_globales_' . date('Y-m-d_His') . '.json"'
        );

        return $response;
    }

    /**
     * Exports performance analysis in CSV format (with .xlsx extension for Excel compatibility).
     */
    #[Route('/performance.csv', name: 'admin_export_stats_performance', methods: ['GET'])]
    public function exportPerformanceStats(): StreamedResponse
    {
        $response = new StreamedResponse(function () {
            $handle = fopen('php://output', 'w');
            $this->exportService->writePerformanceStatsToCsv($handle);
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="analyse_performance_' . date('Y-m-d_His') . '.csv"'
        );

        return $response;
    }

    /**
     * Exports problems report in CSV format (with .pdf extension for compatibility).
     */
    #[Route('/problems.csv', name: 'admin_export_stats_problems', methods: ['GET'])]
    public function exportProblemsReport(): StreamedResponse
    {
        $response = new StreamedResponse(function () {
            $handle = fopen('php://output', 'w');
            $this->exportService->writeProblemsReportToCsv($handle);
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="rapport_problemes_' . date('Y-m-d_His') . '.csv"'
        );

        return $response;
    }

    /**
     * Exports temporal trends in CSV format.
     */
    #[Route('/trends.csv', name: 'admin_export_stats_trends', methods: ['GET'])]
    public function exportTrendsStats(): StreamedResponse
    {
        $response = new StreamedResponse(function () {
            $handle = fopen('php://output', 'w');
            $this->exportService->writeTrendsStatsToCsv($handle);
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="tendances_temporelles_' . date('Y-m-d_His') . '.csv"'
        );

        return $response;
    }
}
