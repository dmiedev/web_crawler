<?php

namespace App\Controller\Admin;

use App\Repository\NodeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;

class GraphController extends AbstractController
{
    #[Route('/graph', name: 'app_admin_graph_index')]
    public function graph(
        ChartBuilderInterface $chartBuilder,
        NodeRepository $nodeRepository,
    ): Response
    {
        $chart = $chartBuilder->createChart('forceDirectedGraph');

        $nodes = array_merge($nodeRepository->findCrawledNodes(), $nodeRepository->findUncrawledNodes());
        $nodesCount = count($nodes);

        $graphNodes = [];
        $graphLabels = [];
        $graphEdges = [];
        $urlToIndex = [];
        $graphNodeColors = [];

        for ($index = 0; $index < $nodesCount; $index++) {
            $node = $nodes[$index];
            if (!isset($urlToIndex[$node->getUrl()])) {
                $graphNodes[] = [];
                $urlToIndex[$node->getUrl()] = $index;
                $graphLabels[] = $node->getTitle() ?? $node->getUrl();
                $graphNodeColors[] = $node->getCrawlTime() !== null ? 'steelblue' : 'grey';
            }
        }

        for ($index = 0; $index < $nodesCount; $index++) {
            $node = $nodes[$index];
            foreach ($node->getLinks() as $link) {
                $graphEdges[] = [
                    'source' => $urlToIndex[$node->getUrl()],
                    'target' => $urlToIndex[$link->getUrl()],
                ];
            }
        }

        $chart->setData([
            'labels' => $graphLabels,
            'datasets' => [
                [
                    'pointRadius' => 5,
                    'data' => $graphNodes,
                    'edges' => $graphEdges,
                    'pointBackgroundColor' => $graphNodeColors,
                ],
            ],
        ]);

        $chart->setOptions([
            'plugins' => [
                'zoom' => [
                    'zoom' => [
                        'wheel' => ['enabled' => true],
                        'pinch' => ['enabled' => true],
                        'mode' => 'xy',
                    ],
                ],
                'datalabels' => ['display' => false],
                'dragData' => [
                    'dragX' => true,
                    'showTooltip' => true,
                    'round' => 1,
                ],
            ],
        ]);

        return $this->render('graph.html.twig', ['chart' => $chart]);
    }
}