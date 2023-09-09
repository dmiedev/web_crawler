<?php

namespace App\MessageHandler;

use App\Entity\ExecutionStatus;
use App\Message\ExecuteWebPageMessage;
use App\Repository\WebPageRepository;
use DateTimeImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
class ExecuteWebPageMessageHandler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly WebPageRepository $webPageRepository,
        private readonly string $projectDir,
    ) {}

    public function __invoke(ExecuteWebPageMessage $message): void
    {
        $webPageId = $message->getWebPageId();
        $webPage = $this->webPageRepository->find($webPageId);

        if ($webPage == null || !$webPage->isActive()) {
            $this->logger->notice('Skipping execution because web page is inactive or has been deleted.', [
                'webPageId' => $webPageId
            ]);
            return;
        }

        $lastExecution = $webPage->getExecutions()->last();

        if ($lastExecution) {
            if ($lastExecution->getStatus() == ExecutionStatus::Running || $lastExecution->getEndTime() == null) {
                $this->logger->notice('Skipping execution as there is already a running one.', [
                    'webPageId' => $webPageId,
                ]);
                return;
            }

            if (!$message->overridesSchedule()) {
                $shouldExecuteAt = $lastExecution->getEndTime()->add($webPage->getPeriodicityInterval());
                if (new DateTimeImmutable('now') < $shouldExecuteAt) {
                    $this->logger->notice('Skipping an outdated execution.', ['webPageId' => $webPageId]);
                    return;
                }
            }
        }

        $this->logger->info('Starting a process for web page crawling.', ['webPageId' => $webPageId]);
        $process = new Process(['bin/console', 'app:crawl-web-page', $message->getWebPageId()], $this->projectDir);
        $process->start();
    }
}
