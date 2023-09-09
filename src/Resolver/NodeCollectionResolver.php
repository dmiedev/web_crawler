<?php

namespace App\Resolver;

use ApiPlatform\GraphQl\Resolver\QueryCollectionResolverInterface;
use App\Entity\Node;
use App\Repository\NodeRepository;

class NodeCollectionResolver implements QueryCollectionResolverInterface
{
    public function __construct(private readonly NodeRepository $nodeRepository) {}

    /**
     * @param iterable<Node> $collection
     *
     * @return iterable<Node>
     */
    public function __invoke(iterable $collection, array $context): iterable
    {
        $args = $context['args'];

        if (array_key_exists('webPages', $args)) {
            return $this->nodeRepository->findByWebPageIds($args['webPages']);
        }
        return $collection;
    }
}