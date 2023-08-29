<?php

namespace App\Entity;

use App\Repository\InventoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
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
    private ?string $serial = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    private ?string $code = null;

    #[ORM\OneToMany(
        mappedBy: 'code', targetEntity: InventoryParamhouse::class, cascade: ['persist', 'remove'], orphanRemoval: true
    )]
    private Collection $parameters;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $created = null;

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

    public function getSerial(): ?string
    {
        return $this->serial;
    }

    public function setSerial(string $serial): static
    {
        $this->serial = $serial;

        return $this;
    }

    public function getCreated(): ?\DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated(\DateTimeInterface $created): static
    {
        $this->created = $created;

        return $this;
    }
}
