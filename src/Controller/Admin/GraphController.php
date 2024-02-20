<?php

namespace App\Controller\Admin;

use App\Repository\WebPageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;

class GraphController extends AbstractController
{
    #[Route('/graph', name: 'app_admin_graph_index')]
    public function graph(
        ChartBuilderInterface $chartBuilder,
        WebPageRepository $webPageRepository,
    ): Response
    {
        $chart = $chartBuilder->createChart('forceDirectedGraph')
            ->setData([
                'labels' => [],
                'datasets' => [
                    [
                        'pointRadius' => 5,
                        'data' => [],
                        'directed' => true,
                        'edges' => [],
                        'pointBackgroundColor' => [],
                    ],
                ],
            ])
            ->setOptions([
                'plugins' => [
                    'zoom' => [
                        'pan' => ['enabled' => true],
                        'zoom' => [
                            'wheel' => ['enabled' => true],
                            'pinch' => ['enabled' => true],
                            'mode' => 'xy',
                        ],
                    ],
                    'datalabels' => ['display' => false],
                    'legend' => ['display' => false],
                ],
            ]);

        return $this->render('graph.html.twig', [
            'chart' => $chart,
            'webPages' => $webPageRepository->findAll(),
        ]);
    }
}