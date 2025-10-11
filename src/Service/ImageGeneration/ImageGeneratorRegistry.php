<?php

declare(strict_types=1);

namespace App\Service\ImageGeneration;

class ImageGeneratorRegistry
{
    /** @var iterable<ImageGeneratorInterface> */
    private iterable $generators = [];

    /**
     * @param iterable<ImageGeneratorInterface> $generators
     */
    public function __construct(iterable $generators)
    {
        $this->generators = $generators;
    }

    public function getGenerator(string $name): ImageGeneratorInterface
    {

        foreach ($this->generators as $generator) {
            if ($generator->supports($name) && $generator->isAvailable()) {
                return $generator;
            }
        }

        throw new \InvalidArgumentException(
            sprintf('No available image generator found for name: %s', $name)
        );
    }

    /**
     * Retourne tous les générateurs disponibles.
     *
     * @return array<int, ImageGeneratorInterface>
     */
    public function getAvailableGenerators(): array
    {
        $available = [];
        foreach ($this->generators as $generator) {
            if ($generator->isAvailable()) {
                $available[] = $generator;
            }
        }

        return $available;
    }

    /**
     * Retourne tous les générateurs, disponibles ou non.
     *
     * @return array<int, ImageGeneratorInterface>
     */
    public function getAllGenerators(): array
    {
        return iterator_to_array($this->generators);
    }

    /**
     * Retourne le premier générateur disponible.
     */
    public function getFirstAvailableGenerator(): ?ImageGeneratorInterface
    {
        foreach ($this->generators as $generator) {
            if ($generator->isAvailable()) {
                return $generator;
            }
        }

        return null;
    }

    /**
     * Retourne des informations sur tous les générateurs.
     *
     * @return array<int, array{name: string, available: bool, class: string}>
     */
    public function getGeneratorsInfo(): array
    {
        $info = [];
        foreach ($this->generators as $generator) {
            $info[] = [
                'name'      => $generator->getName(),
                'available' => $generator->isAvailable(),
                'class'     => get_class($generator),
            ];
        }

        return $info;
    }
}
