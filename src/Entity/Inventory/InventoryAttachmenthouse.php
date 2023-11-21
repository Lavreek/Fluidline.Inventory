<?php
namespace App\Entity\Inventory;

use App\Repository\Inventory\InventoryAttachmenthouseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryAttachmenthouseRepository::class)]
class InventoryAttachmenthouse
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $model = null;

    #[ORM\OneToOne(inversedBy: 'attachments', cascade: [
        'persist'
    ])]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Inventory $code = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): static
    {
        $this->model = $model;

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
