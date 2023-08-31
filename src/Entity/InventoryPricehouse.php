<?php

namespace App\Entity;

use App\Repository\InventoryPricehouseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryPricehouseRepository::class)]
#[ORM\Index(columns: ['value'], name: 'idx_value')]
class InventoryPricehouse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $value = null;

    #[ORM\Column(length: 255)]
    private ?string $currency = null;

    #[ORM\Column]
    private ?int $warehouse = null;

    #[ORM\OneToOne(mappedBy: 'price', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Inventory $code = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getWarehouse(): ?int
    {
        return $this->warehouse;
    }

    public function setWarehouse(int $warehouse): static
    {
        $this->warehouse = $warehouse;

        return $this;
    }

    public function getCode(): ?Inventory
    {
        return $this->code;
    }

    public function setCode(?Inventory $code): static
    {
        // unset the owning side of the relation if necessary
        if ($code === null && $this->code !== null) {
            $this->code->setPrice(null);
        }

        // set the owning side of the relation if necessary
        if ($code !== null && $code->getPrice() !== $this) {
            $code->setPrice($this);
        }

        $this->code = $code;

        return $this;
    }
}
