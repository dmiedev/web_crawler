<?php

namespace App\Command;

use Amp\Parallel\Context\ThreadContextFactory;
use Amp\Parallel\Worker\ContextWorkerFactory;
use Amp\Parallel\Worker\ContextWorkerPool;
use Amp\Parallel\Worker\WorkerPool;
use Amp\Sync\Barrier;
use App\Message\ExecuteWebPageMessage;
use App\Repository\ExecutionRepository;
use App\Repository\NodeRepository;
use App\Repository\WebPageRepository;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsCommand(name: 'app:crawl-web-page', description: 'Crawls a given web page')]
class CrawlWebPageCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const MAX_WORKERS = 16;

    private int $crawledCount = 0;

    private ?WorkerPool $workerPool = null;

    private ?Barrier $barrier = null;

    public function __construct(
        private readonly WebPageRepository $webPageRepository,
        private readonly ExecutionRepository $executionRepository,
        private readonly NodeRepository $nodeRepository,
        private readonly MessageBusInterface $messageBus,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('webPageId', InputArgument::REQUIRED, 'Web page ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $webPageId = $input->getArgument('webPageId');
        if (!$webPageId) {
            $this->logger->error('Web page crawl command was not provided web page ID!');
            return Command::FAILURE;
        }

        $webPage = $this->webPageRepository->find($webPageId);
        if ($webPage == null || !$webPage->isActive()) {
            $this->logger->notice('Aborting web page crawl command that was run for non-active web page.', [
                'webPageId' => $webPageId,
            ]);
            return Command::SUCCESS;
        }

        $this->logger->info('Staring crawling web page.', ['webPageId' => $webPageId]);
        $execution = $this->executionRepository->createNewExecution($webPage);
        $this->nodeRepository->deleteAllNodes($webPage);

        $this->barrier = new Barrier(1);
        $this->workerPool = new ContextWorkerPool(
             self::MAX_WORKERS,
            new ContextWorkerFactory(contextFactory: new ThreadContextFactory()),
        );
        $this->submitTask(new CrawlWebPageTask($webPage->getUrl(), $webPage->getId()));
        $this->barrier->await();

        $this->logger->info('Finishing crawling execution.', ['webPageId' => $webPageId]);
        $this->executionRepository->finishExecution($execution, $this->crawledCount);
        $this->messageBus->dispatch(new ExecuteWebPageMessage($webPage->getId()), [
            new DelayStamp($webPage->getPeriodicityMillis()),
        ]);
        $this->workerPool->shutdown();
        return Command::SUCCESS;
    }

    private function submitTask(CrawlWebPageTask $task): void
    {
        $this->workerPool->submit($task)
            ->getFuture()
            ->map(function ($tasks) {
                $this->crawledCount++;
                foreach ($tasks as $task) {
                    $this->barrier->register();
                    $this->submitTask($task);
                }
                $this->barrier->arrive();
                return $tasks;
            });
    }
}
