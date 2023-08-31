<?php

namespace App\Entity;

use App\Repository\InventoryParamhouseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryParamhouseRepository::class)]
#[ORM\Index(name: 'idx_name', columns: ['name'])]
#[ORM\Index(name: 'idx_value', columns: ['value'])]
class InventoryParamhouse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $value = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'parameters')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Inventory $code = null;

//    #[ORM\ManyToOne(inversedBy: 'parameters')]
//    #[ORM\JoinColumn(onDelete: 'CASCADE')]
//    private ?Inventory $code = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
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

    public function getCode(): ?Inventory
    {
        return $this->code;
    }

    public function setCode(?Inventory $code): static
    {
        $this->code = $code;

        return $this;
    }
}