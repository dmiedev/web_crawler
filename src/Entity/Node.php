<?php

namespace App\Entity;

use App\Repository\NodeRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NodeRepository::class)]
class Node
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $url = null;

    #[ORM\Column]
    private ?DateTimeImmutable $crawlTime = null;

    #[ORM\ManyToOne(inversedBy: 'nodes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?WebPage $owner = null;

    #[ORM\ManyToMany(targetEntity: self::class)]
    private Collection $links;

    public function __construct()
    {
        $this->links = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function getCrawlTime(): ?DateTimeImmutable
    {
        return $this->crawlTime;
    }

    public function setCrawlTime(DateTimeImmutable $crawlTime): static
    {
        $this->crawlTime = $crawlTime;

        return $this;
    }

    public function getOwner(): ?WebPage
    {
        return $this->owner;
    }

    public function setOwner(?WebPage $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getLinks(): Collection
    {
        return $this->links;
    }

    public function addLink(self $link): static
    {
        if (!$this->links->contains($link)) {
            $this->links->add($link);
        }

        return $this;
    }

    public function removeLink(self $link): static
    {
        $this->links->removeElement($link);

        return $this;
    }
}
