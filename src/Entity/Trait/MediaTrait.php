<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\HttpFoundation\File\File;

trait MediaTrait
{
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $imageName = null;

    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;

        // La propriété `updatedAt` doit exister sur l'entité qui utilise ce trait.
        // C'est le cas si elle utilise aussi le trait TimestampableEntity de Gedmo.
        // @phpstan-ignore-next-line
        if (null !== $imageFile && property_exists($this, 'updatedAt')) {
            $this->updatedAt = new \DateTime();
        }
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageName(?string $imageName): void
    {
        $this->imageName = $imageName;
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }
}
