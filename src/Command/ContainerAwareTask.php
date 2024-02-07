<?php

namespace App\Command;

use Amp\Cache\LocalCache;
use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use App\Kernel;
use App\Repository\NodeRepository;
use App\Repository\WebPageRepository;
use Doctrine\Persistence\AbstractManagerRegistry;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\DebugClassLoader;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ContainerAwareTask implements Task
{
    public function __construct()
    {
        $this->env = $_ENV;
    }

    private static ?LocalCache $localCache = null;

    private array $env;

    protected function getClient(): HttpClientInterface
    {
        $client = self::$localCache->get('client');
        if ($client != null) {
            return $client;
        }
        $this->getLogger()->info('Creating client');
        $options = ['headers' => ['Content-Type' => 'text/html'], 'timeout' => 15];
        $client = HttpClient::create($options, PHP_INT_MAX, PHP_INT_MAX);
        self::$localCache->set('client', $client);
        return $client;
    }

    protected function resetEntityManager(): void
    {
        $this->getDoctrine()->resetManager();
    }

    private function getDoctrine(): ManagerRegistry
    {
        /** @var ManagerRegistry */
        return $this->getService('doctrine');
    }

    protected function getLogger(): LoggerInterface
    {
        /** @var LoggerInterface */
        return $this->getService('my.logger');
    }

    protected function getNodeRepository(): NodeRepository
    {
        /** @var NodeRepository */
        return $this->getService('my.node.repository');
    }

    protected function getWebPageRepository(): WebPageRepository
    {
        /** @var WebPageRepository */
        return $this->getService('my.webPage.repository');
    }

    protected function getService(string $name): ?object
    {
        return $this->getContainer()->get($name);
    }

    private function getContainer(): ContainerInterface
    {
        $container = self::$localCache->get('container');
        if ($container != null) {
            return $container;
        }

        if ($this->env['APP_DEBUG'] === '1') {
            umask(0000);
            Debug::enable();
            DebugClassLoader::disable();
        }
        $_ENV = $this->env;

        $kernel = new Kernel($this->env['APP_ENV'], (bool) $this->env['APP_DEBUG']);
        $kernel->boot();

        $container = $kernel->getContainer();
        self::$localCache->set('container', $container);

        return $container;
    }

    public function run(Channel $channel, Cancellation $cancellation): mixed
    {
        self::$localCache ??= new LocalCache();

        return false;
    }
}