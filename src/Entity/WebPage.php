<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\WebPageRepository;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Post(
            routeName: 'web_page_post_execute',
            openapiContext: [
                'summary' => 'Executes a WebPage resource.',
                'parameters' => [
                    [
                        'in' => 'path',
                        'name' => 'id',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                        'description' => 'WebPage identifier',
                    ],
                ],
                'requestBody' => ['required' => false, 'content' => []],
                'responses' => [
                    '200' => ['description' => 'WebPage executed', 'content' => null],
                    '404' => ['description' => 'Resource not found'],
                ],
            ],
            name: 'execute',
        ),
        new Put(),
        new Delete(),
        new Patch(),
    ],
    denormalizationContext: [
        'groups' => ['web_page:write'],
        'swagger_definition_name' => 'Write',
    ],
    graphQlOperations: [
        new QueryCollection(paginationEnabled: false)
    ],
)]
#[ORM\Entity(repositoryClass: WebPageRepository::class)]
class WebPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['web_page:write'])]
    private ?string $label = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['web_page:write'])]
    private ?string $url = null;

    #[ORM\Column(length: 255)]
    #[Groups(['web_page:write'])]
    private ?string $regexp = null;

    #[ORM\Column]
    #[Groups(['web_page:write'])]
    private ?bool $active = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY)]
    #[Groups(['web_page:write'])]
    private array $tags = [];

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Groups(['web_page:write'])]
    private ?DateTimeInterface $periodicity = null;

    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Node::class, orphanRemoval: true)]
    private Collection $nodes;

    #[ORM\OneToMany(mappedBy: 'webPage', targetEntity: Execution::class, orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
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

    public function getPeriodicityInterval(): ?DateInterval
    {
        if ($this->periodicity == null) {
            return null;
        }
        $hours = $this->periodicity->format('H');
        $minutes = $this->periodicity->format('i');
        return DateInterval::createFromDateString($hours . ' hours + ' . $minutes . ' minutes');
    }

    public function getPeriodicityMillis(): ?int
    {
        if ($this->periodicity == null) {
            return null;
        }
        $hours = intval($this->periodicity->format('H'));
        $minutes = intval($this->periodicity->format('i'));
        return ($hours * 60 + $minutes) * 60000;
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
        return $execution->getStatus();
    }
}
