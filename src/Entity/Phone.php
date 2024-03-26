<?php

namespace App\Entity;

use App\Repository\PhoneRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Hateoas\Configuration\Annotation as Hateoas;

/**
 * @Hateoas\Relation(
 *      "self",
 *      href = @Hateoas\Route(
 *          "api_detail_phone ",
 *          parameters = { "id" = "expr(object.getId())" }
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="phone:details")
 * )
 *
 * @Hateoas\Relation(
 *      "update",
 *      href = @Hateoas\Route(
 *          "api_update_phone",
 *          parameters = { "id" = "expr(object.getId())" }
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="phone:details", excludeIf = "expr(not is_granted('ROLE_ADMIN'))"),
 * )
 *
 * @Hateoas\Relation(
 *      "delete",
 *      href = @Hateoas\Route(
 *          "api_delete_phone",
 *          parameters = { "id" = "expr(object.getId())" }
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="phone:details", excludeIf = "expr(not is_granted('ROLE_ADMIN'))"),
 * )
 */
#[ORM\Entity(repositoryClass: PhoneRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Phone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['phone:details'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['phone:details'])]
    private ?string $model = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['phone:details'])]
    private ?string $manufacturer = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['phone:details'])]
    private ?string $processor = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['phone:details'])]
    private ?string $ram = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['phone:details'])]
    private ?string $storageCapacity = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['phone:details'])]
    private ?string $cameraDetails = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['phone:details'])]
    private ?string $batteryLife = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['phone:details'])]
    private ?string $screenSize = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Regex("/^\d+(?:\.\d{1,2})?$/")]
    #[Groups(['phone:details'])]
    private ?string $price = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Assert\Regex("/^\d*$/", message: 'Stock quantity must be an integer.')]
    #[Groups(['phone:details'])]
    private ?string $stockQuantity = null;

    #[ORM\Column]
    #[Assert\Type("\DateTimeImmutable")]
    #[Groups(['phone:details'])]
    private ?\DateTimeImmutable $releaseDate = null;

    public function __construct()
    {
        $this->releaseDate = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getManufacturer(): ?string
    {
        return $this->manufacturer;
    }

    public function setManufacturer(string $manufacturer): static
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }

    public function getProcessor(): ?string
    {
        return $this->processor;
    }

    public function setProcessor(string $processor): static
    {
        $this->processor = $processor;

        return $this;
    }

    public function getRam(): ?string
    {
        return $this->ram;
    }

    public function setRam(string $ram): static
    {
        $this->ram = $ram;

        return $this;
    }

    public function getStorageCapacity(): ?string
    {
        return $this->storageCapacity;
    }

    public function setStorageCapacity(string $storageCapacity): static
    {
        $this->storageCapacity = $storageCapacity;

        return $this;
    }

    public function getCameraDetails(): ?string
    {
        return $this->cameraDetails;
    }

    public function setCameraDetails(string $cameraDetails): static
    {
        $this->cameraDetails = $cameraDetails;

        return $this;
    }

    public function getBatteryLife(): ?string
    {
        return $this->batteryLife;
    }

    public function setBatteryLife(string $batteryLife): static
    {
        $this->batteryLife = $batteryLife;

        return $this;
    }

    public function getScreenSize(): ?string
    {
        return $this->screenSize;
    }

    public function setScreenSize(string $screenSize): static
    {
        $this->screenSize = $screenSize;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getStockQuantity(): ?string
    {
        return $this->stockQuantity;
    }

    public function setStockQuantity(?string $stockQuantity): static
    {
        $this->stockQuantity = $stockQuantity;

        return $this;
    }

    public function getReleaseDate(): ?\DateTimeImmutable
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(\DateTimeImmutable $releaseDate): static
    {
        $this->releaseDate = $releaseDate;

        return $this;
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->releaseDate = new \DateTimeImmutable();
    }
}
