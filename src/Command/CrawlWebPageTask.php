<?php

namespace App\Command;

use Amp\Cache\AtomicCache;
use Amp\Cache\LocalCache;
use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use Amp\Sync\LocalKeyedMutex;
use App\Entity\Node;
use App\Entity\WebPage;
use App\Kernel;
use App\Repository\NodeRepository;
use App\Repository\WebPageRepository;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\DebugClassLoader;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CrawlWebPageTask implements Task
{
    public function __construct(
        public readonly string $url,
        public readonly int $webPageId,
        public readonly ?int $parentNodeId = null,
    )
    {
        $this->env = $_ENV;
    }

    private array $env;

    private const TITLE_REGEXP = '/<title[^>]*>(.*?)<\/title>/siU';
    private const LINK_REGEXP = '/<a\s[^>]*href\s*=\s*?([\"\']??)\s*([^\"\' >]*?)\s*\1[^>]*>.*<\/a>/siU';

    private static ?LocalCache $localCache = null;
    private static ?LocalKeyedMutex $mutex = null;


    public function run(Channel $channel, Cancellation $cancellation): array
    {
        self::$localCache ??= new LocalCache();
        self::$mutex ??= new LocalKeyedMutex();

        // TODO: mutex appears to be not always working...
        $node = $this->getNodeRepository()->findOneBy(['url' => $this->url, 'owner' => $this->webPageId]);
         if ($node != null) {
            return [];
         }

        $webPage = $this->getWebPageRepository()->find($this->webPageId);

        try {
            $response = $this->getClient()->request('GET', $this->url);
            $content = $response->getContent();
        } catch (HttpExceptionInterface|TransportExceptionInterface) {
            $this->getLogger()->notice('Failed to access new node.', ['url' => $this->url]);
            $lock = self::$mutex->acquire('nodes');
            try {
                $parentNode = $this->parentNodeId != null
                    ? $this->getNodeRepository()->find($this->parentNodeId)
                    : null;
                $this->createNode($webPage, $this->url, 'Unavailable page', $parentNode, false);
                $this->getNodeRepository()->saveChanges();
            } finally {
                $lock->release();
            }
            return [];
        }

        $title = preg_match(self::TITLE_REGEXP, $content, $titleMatches)
            ? trim($titleMatches[1])
            : "Untitled page";

        $links = [];
        if (preg_match_all(self::LINK_REGEXP, $content, $matches)) {
            foreach ($matches[2] as $linkUrl) {
                $redirect = trim(joinUrl($this->url, $linkUrl));
                if (!str_starts_with($redirect, 'http://') && !str_starts_with($redirect, 'https://')) {
                    continue;
                }
                if (str_contains($redirect, '#')) {
                    $redirect = strstr($redirect, '#', true);
                }
                if (substr_count($redirect, '/') === 2) {
                    $redirect .= '/';
                }
                $links[] = $redirect;
            }
        }
        $links = array_unique($links);
        $this->getLogger()->info('Found links', ['links' => $links]);

        $tasks = [];
        $lock = self::$mutex->acquire('nodes');
        try {
            $this->getLogger()->info('Acquiring lock!');
            $parentNode = $this->parentNodeId != null
                ? $this->getNodeRepository()->find($this->parentNodeId)
                : null;
            $this->getLogger()->info('Creating a new node', ['url' => $this->url, 'parentNodeId' => $parentNode?->getId()]);
            $newNode = $this->createNode($webPage, $this->url, $title, $parentNode);
            $this->getNodeRepository()->saveChanges();

            foreach ($links as $link) {
                $linkNode = $this->getNodeRepository()->findOneBy(['url' => $link, 'owner' => $this->webPageId]);
                if ($linkNode == null) {
                    if (preg_match($webPage->getRegexp(), $link)) {
                        $this->getLogger()->info('Creating a task', ['linkUrl' => $link, 'parentNodeId' => $newNode->getId()]);
                        $tasks[] = new CrawlWebPageTask($link, $this->webPageId, $newNode->getId());
                    } else {
                        $this->createNode($webPage, $link, null, $newNode, false);
                    }
                } else {
                    $this->getNodeRepository()->addLink($newNode, $linkNode);
                }
            }
            $this->getNodeRepository()->saveChanges();
        } finally {
            $this->getLogger()->info('Releasing lock!');
            $lock->release();
        }

        return $tasks;
    }

    private function createNode(WebPage $webPage, string $url, ?string $title = null, ?Node $parentNode = null, bool $crawled = true): Node
    {
        $crawlTime = $crawled ? new DateTimeImmutable() : null;
        return $this
            ->getNodeRepository()
            ->createNewNode($webPage, $url, $title, $parentNode, $crawlTime);
    }

    private function getClient(): HttpClientInterface
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

    private function getLogger(): LoggerInterface
    {
        /** @var LoggerInterface */
        return $this->getService('my.logger');
    }

    private function getNodeRepository(): NodeRepository
    {
        /** @var NodeRepository */
        return $this->getService('my.node.repository');
    }

    private function getWebPageRepository(): WebPageRepository
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
}


function joinUrl(string $base, string $rel): string
{
    if (!$base) {
        return $rel;
    }
    if (!$rel) {
        return $base;
    }

    $uses_relative = ['', 'ftp', 'http', 'gopher', 'nntp', 'imap',
        'wais', 'file', 'https', 'shttp', 'mms',
        'prospero', 'rtsp', 'rtspu', 'sftp',
        'svn', 'svn+ssh', 'ws', 'wss'];

    $pbase = parse_url($base);
    $prel = parse_url($rel);

    if ($prel === false || preg_match('/^[a-z0-9\-.]*[^a-z0-9\-.:][a-z0-9\-.]*:/i', $rel)) {
        /*
            Either parse_url couldn't parse this, or the original URL
            fragment had an invalid scheme character before the first :,
            which can confuse parse_url
        */
        $prel = ['path' => $rel];
    }

    if (array_key_exists('path', $pbase) && $pbase['path'] === '/') {
        unset($pbase['path']);
    }

    if (isset($prel['scheme'])) {
        if ($prel['scheme'] != $pbase['scheme'] || !in_array($prel['scheme'], $uses_relative)) {
            return $rel;
        }
    }

    $merged = array_merge($pbase, $prel);

    // Handle relative paths:
    //   'path/to/file.ext'
    //   './path/to/file.ext'
    if (array_key_exists('path', $prel) && !str_starts_with($prel['path'], '/')) {

        // Normalize: './path/to/file.ext' => 'path/to/file.ext'
        if (str_starts_with($prel['path'], './')) {
            $prel['path'] = substr($prel['path'], 2);
        }

        if (array_key_exists('path', $pbase)) {
            $dir = preg_replace('@/[^/]*$@', '', $pbase['path']);
            $merged['path'] = $dir . '/' . $prel['path'];
        } else {
            $merged['path'] = '/' . $prel['path'];
        }
    }

    if (array_key_exists('path', $merged)) {
        // Get the path components, and remove the initial empty one
        $pathParts = explode('/', $merged['path']);
        array_shift($pathParts);

        $path = [];
        $prevPart = '';
        foreach ($pathParts as $part) {
            if ($part == '..' && count($path) > 0) {
                // Cancel out the parent directory (if there's a parent to cancel)
                $parent = array_pop($path);
                // But if it was also a parent directory, leave it in
                if ($parent == '..') {
                    $path[] = $parent;
                    $path[] = $part;
                }
            } else if ($prevPart != '' || ($part != '.' && $part != '')) {
                // Don't include empty or current-directory components
                if ($part == '.') {
                    $part = '';
                }
                $path[] = $part;
            }
            $prevPart = $part;
        }
        $merged['path'] = '/' . implode('/', $path);
    }

    $ret = '';
    if (isset($merged['scheme'])) {
        $ret .= $merged['scheme'] . ':';
    }
    if (isset($merged['scheme']) || isset($merged['host'])) {
        $ret .= '//';
    }
    if (isset($prel['host'])) {
        $hostSource = $prel;
    } else {
        $hostSource = $pbase;
    }
    // username, password, and port are associated with the hostname, not merged
    if (isset($hostSource['host'])) {
        if (isset($hostSource['user'])) {
            $ret .= $hostSource['user'];
            if (isset($hostSource['pass'])) {
                $ret .= ':' . $hostSource['pass'];
            }
            $ret .= '@';
        }
        $ret .= $hostSource['host'];
        if (isset($hostSource['port'])) {
            $ret .= ':' . $hostSource['port'];
        }
    }
    if (isset($merged['path'])) {
        $ret .= $merged['path'];
    }
    if (isset($prel['query'])) {
        $ret .= '?' . $prel['query'];
    }
    if (isset($prel['fragment'])) {
        $ret .= '#' . $prel['fragment'];
    }
    return $ret;
}