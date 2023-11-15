<?php

namespace App\Entity\Inventory;

use App\Repository\InventoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryRepository::class)]
#[ORM\Index(columns: ['code'], name: 'idx_code')]
#[ORM\Index(name: 'idx_serial', columns: ['serial'])]
#[ORM\Index(name: 'idx_type', columns: ['type'])]
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

    #[ORM\OneToOne(
        mappedBy: 'code', targetEntity: InventoryPricehouse::class, cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private ?InventoryPricehouse $price = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created = null;

    #[ORM\OneToMany(
        mappedBy: 'code', targetEntity: InventoryParamhouse::class, cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $parameters;

    #[ORM\OneToOne(
        mappedBy: 'code', targetEntity: InventoryAttachmenthouse::class, cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private ?InventoryAttachmenthouse $attachments = null;

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

    public function getPrice(): ?InventoryPricehouse
    {
        return $this->price;
    }

    public function setPrice(?InventoryPricehouse $price): static
    {
        $this->price = $price;

        return $this;
    }

    /**
     * @return Collection<int, InventoryParamhouse>
     */
    public function getParameters(): Collection
    {
        return $this->parameters;
    }

    public function addParameter(InventoryParamhouse $parameter): static
    {
        if (!$this->parameters->contains($parameter)) {
            $this->parameters->add($parameter);
            $parameter->setCode($this);
        }

        return $this;
    }

    public function removeParameter(InventoryParamhouse $parameter): static
    {
        if ($this->parameters->removeElement($parameter)) {
            // set the owning side to null (unless already changed)
            if ($parameter->getCode() === $this) {
                $parameter->setCode(null);
            }
        }

        return $this;
    }

    public function getAttachments(): ?InventoryAttachmenthouse
    {
        return $this->attachments;
    }

    public function setAttachments(?InventoryAttachmenthouse $attachments): static
    {
        // unset the owning side of the relation if necessary
        if ($attachments === null && $this->attachments !== null) {
            $this->attachments->setCode(null);
        }

        // set the owning side of the relation if necessary
        if ($attachments !== null && $attachments->getCode() !== $this) {
            $attachments->setCode($this);
        }

        $this->attachments = $attachments;

        return $this;
    }
}
