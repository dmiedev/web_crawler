<?php

namespace App\Command;

use App\Repository\ExecutionRepository;
use App\Repository\WebPageRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:crawl-web-page', description: 'Crawls a given web page')]
class CrawlWebPageCommand extends Command
{
    public function __construct(
        private readonly WebPageRepository $webPageRepository,
        private readonly ExecutionRepository $executionRepository,
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
        // $io = new SymfonyStyle($input, $output);

        $webPageId = $input->getArgument('webPageId');
        if (!$webPageId) {
            return Command::FAILURE;
        }
        $webPage = $this->webPageRepository->find($webPageId);
        if (!$webPage->isActive()) {
            return Command::SUCCESS;
        }
        $execution = $this->executionRepository->createNewExecution($webPage);

        return Command::SUCCESS;
    }
}
