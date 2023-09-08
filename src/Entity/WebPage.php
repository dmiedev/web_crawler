<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use App\Repository\WebPageRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ApiResource(
    graphQlOperations: [
        new QueryCollection(paginationEnabled: false)
    ]
)]
#[ORM\Entity(repositoryClass: WebPageRepository::class)]
class WebPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $label = null;

    #[ORM\Column(length: 255)]
    private ?string $url = null;

    #[ORM\Column(length: 255)]
    private ?string $regexp = null;

    #[ORM\Column]
    private ?bool $active = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY)]
    private array $tags = [];

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?DateTimeInterface $periodicity = null;

    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Node::class, orphanRemoval: true)]
    private Collection $nodes;

    #[ORM\OneToMany(mappedBy: 'webPage', targetEntity: Execution::class, orphanRemoval: true)]
    private Collection $executions;

    public function __construct()
    {
        $this->nodes = new ArrayCollection();
        $this->executions = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->label;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, mixed $payload): void
    {
        if (@preg_match($this->regexp, null) === false) {
            $context->buildViolation('Invalid regular expression!')
                ->atPath('regexp')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getRegexp(): ?string
    {
        return $this->regexp;
    }

    public function setRegexp(string $regexp): static
    {
        $this->regexp = $regexp;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): static
    {
        $this->tags = $tags;

        return $this;
    }

    public function getPeriodicity(): ?DateTimeInterface
    {
        return $this->periodicity;
    }

    public function setPeriodicity(DateTimeInterface $periodicity): static
    {
        $this->periodicity = $periodicity;

        return $this;
    }

    /**
     * @return Collection<int, Node>
     */
    public function getNodes(): Collection
    {
        return $this->nodes;
    }

    public function addNode(Node $node): static
    {
        if (!$this->nodes->contains($node)) {
            $this->nodes->add($node);
            $node->setOwner($this);
        }

        return $this;
    }

    public function removeNode(Node $node): static
    {
        if ($this->nodes->removeElement($node)) {
            // set the owning side to null (unless already changed)
            if ($node->getOwner() === $this) {
                $node->setOwner(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Execution>
     */
    public function getExecutions(): Collection
    {
        return $this->executions;
    }

    public function addExecution(Execution $execution): static
    {
        if (!$this->executions->contains($execution)) {
            $this->executions->add($execution);
            $execution->setWebPage($this);
        }

        return $this;
    }

    public function removeExecution(Execution $execution): static
    {
        if ($this->executions->removeElement($execution)) {
            // set the owning side to null (unless already changed)
            if ($execution->getWebPage() === $this) {
                $execution->setWebPage(null);
            }
        }

        return $this;
    }

    public function getLastExecutionTime(): ?DateTimeImmutable
    {
        $execution = $this->getExecutions()->last();
        if (!$execution) {
            return null;
        }
        return $execution->getEndTime() ?? $execution->getStartTime();
    }

    public function getLastExecutionStatus(): ?ExecutionStatus
    {
        $execution = $this->getExecutions()->last();
        if (!$execution) {
            return null;
        }
        return $execution->last()->getStatus();
    }
}
