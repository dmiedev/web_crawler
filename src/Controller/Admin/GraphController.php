<?php

namespace App\Controller\Admin;

use App\Repository\NodeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;

class GraphController extends AbstractController
{
    private const MODE_WEB = 'web';
    private const MODE_DOMAIN = 'domain';

    #[Route('/graph', name: 'app_admin_graph_index')]
    public function graph(
        ChartBuilderInterface $chartBuilder,
        NodeRepository $nodeRepository,
        Request $request,
    ): Response
    {
        $mode = $request->query->get('mode');
        $mode = in_array($mode, [static::MODE_WEB, static::MODE_DOMAIN], true) ? $mode : static::MODE_WEB;

        $nodes = $nodeRepository->findNodes();

        $graphNodes = [];
        $graphNodeLength = 0;
        $graphLabels = [];
        $graphEdges = [];
        $urlToIndex = [];
        $urlToHost = [];
        $graphNodeColors = [];

        foreach ($nodes as $node) {
            if ($mode === static::MODE_WEB) {
                $nodeUrl = $node->getUrl();
            } else {
                $url = $node->getUrl();
                $host = parse_url($url, PHP_URL_HOST);
                $urlToHost[$url] = $host;
                $nodeUrl = $host;
            }
            if (!isset($urlToIndex[$nodeUrl])) {
                $graphNodes[] = [];
                $urlToIndex[$nodeUrl] = $graphNodeLength++;
                $graphLabels[] = $mode === static::MODE_WEB ? $node->getTitle() ?? $nodeUrl : $nodeUrl;
                $graphNodeColors[] = $node->getCrawlTime() !== null ? 'steelblue' : 'grey';
            }
        }
        foreach ($nodes as $node) {
            $nodeUrl = $mode === static::MODE_WEB ? $node->getUrl() : $urlToHost[$node->getUrl()];
            foreach ($node->getLinks() as $link) {
                $linkUrl = $mode === static::MODE_WEB ? $link->getUrl() : $urlToHost[$link->getUrl()];
                $graphEdges[] = [
                    'source' => $urlToIndex[$nodeUrl],
                    'target' => $urlToIndex[$linkUrl],
                ];
            }
        }

        $chart = $chartBuilder->createChart('forceDirectedGraph')
            ->setData([
                'labels' => $graphLabels,
                'datasets' => [
                    [
                        'pointRadius' => 5,
                        'data' => $graphNodes,
                        'directed' => true,
                        'edges' => $graphEdges,
                        'pointBackgroundColor' => $graphNodeColors,
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
                ],
            ]);
        return $this->render('graph.html.twig', ['chart' => $chart]);
    }
}