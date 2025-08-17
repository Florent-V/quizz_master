<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Proposal;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

readonly class ProposalNormalizer implements NormalizerInterface
{
    public function __construct(
        private ObjectNormalizer $normalizer,
        private ImageUrlHelper $imageHelper,
    ) {
    }

    public function normalize(
        mixed $data,
        ?string $format = null,
        array $context = [],
    ): \ArrayObject|array|string|int|float|bool|null {
        if (!$data instanceof Proposal) {
            return $this->normalizer->normalize($data, $format, $context);
        }

        $result = $this->normalizer->normalize($data, $format, $context);

        return $this->imageHelper->addImageUrl((array) $result, $data, 'imageFile');
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Proposal;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Proposal::class => true,
        ];
    }
}
