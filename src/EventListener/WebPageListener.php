<?php

namespace App\EventListener;

use App\Entity\WebPage;
use App\Message\ExecuteWebPageMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: WebPage::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: WebPage::class)]
class WebPageListener
{
    public function __construct(private readonly MessageBusInterface $messageBus) {}

    public function prePersist(WebPage $webPage, PrePersistEventArgs $args): void
    {
        if ($webPage->isActive()) {
            $this->startExecution($webPage);
        }
    }

    public function preUpdate(WebPage $webPage, PreUpdateEventArgs $args): void
    {
        $becameActive = $args->hasChangedField('active') && $args->getNewValue('active') === true;
        if ($becameActive) {
            $this->startExecution($webPage);
        }
    }

    private function startExecution(WebPage $webPage): void
    {
        $this->messageBus->dispatch(new ExecuteWebPageMessage($webPage->getId()));
    }
}