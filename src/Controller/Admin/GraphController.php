<?php

namespace App\Controller\Admin;

use App\Entity\WebPage;
use App\Message\ExecuteWebPageMessage;
use App\Repository\WebPageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
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

    #[Route(
        path: '/api/web_pages/{id}/execute',
        name: 'web_page_post_execute',
        defaults: [
            '_api_operation_name' => '_api_/web_pages/{id}/execute_post',
        ],
        methods: ['POST'],
    )]
    public function executeWebPage(WebPage $webPage, MessageBusInterface $messageBus): Response
    {
        $messageBus->dispatch(new ExecuteWebPageMessage($webPage->getId(), true));
        return $this->json(null);
    }
}
