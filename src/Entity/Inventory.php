<?php

namespace App\Entity;

use App\Repository\InventoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use phpDocumentor\Reflection\Types\True_;

#[ORM\Entity(repositoryClass: InventoryRepository::class)]
class Inventory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\OneToMany(mappedBy: 'code', targetEntity: InventoryParamhouse::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $parameters;

    public function __construct()
    {
        $this->parameters = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return Collection<int, InventoryParamhouse>
     */
    public function getParameters(): Collection
    {
        return $this->parameters;
    }

    public function addParameters(InventoryParamhouse $parameters): static
    {
        if (!$this->parameters->contains($parameters)) {
            $this->parameters->add($parameters);
            $parameters->setCodeId($this);
        }

        return $this;
    }

    public function removeParameters(InventoryParamhouse $parameters): static
    {
        if ($this->parameters->removeElement($parameters)) {
            // set the owning side to null (unless already changed)
            if ($parameters->getCodeId() === $this) {
                $parameters->setCodeId(null);
            }
        }

        return $this;
    }
}
