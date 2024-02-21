<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ExecutionRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(mercure: true, graphQlOperations: [])]
#[ORM\Entity(repositoryClass: ExecutionRepository::class)]
class Execution
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'executions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?WebPage $webPage = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column]
    private ?DateTimeImmutable $startTime = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $endTime = null;

    #[ORM\Column]
    private ?int $crawledCount = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWebPage(): ?WebPage
    {
        return $this->webPage;
    }

    public function setWebPage(?WebPage $webPage): static
    {
        $this->webPage = $webPage;

        return $this;
    }

    public function getStatus(): ?ExecutionStatus
    {
        if ($this->status != null) {
            return ExecutionStatus::tryFrom($this->status);
        }
        return null;
    }

    public function setStatus(?string $status): static
    {
        $newStatus = ExecutionStatus::tryFrom($status);
        if ($newStatus != null) {
            $this->status = $newStatus->value;
        }

        return $this;
    }

    public function setStatusEnum(ExecutionStatus $status): static
    {
        return $this->setStatus($status->value);
    }

    public function getStartTime(): ?DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(DateTimeImmutable $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(?DateTimeImmutable $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function getCrawledCount(): ?int
    {
        return $this->crawledCount;
    }

    public function setCrawledCount(int $crawledCount): static
    {
        $this->crawledCount = $crawledCount;

        return $this;
    }
}
