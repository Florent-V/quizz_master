<?php

declare(strict_types=1);

namespace App\Serializer;

use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class ImageUrlHelper
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function addImageUrl(array $data, object $entity, string $field = 'imageFile'): array
    {
        if (method_exists($entity, 'getImageName') && $entity->getImageName()) {
            // le champ $field correspond au mapping Vich
            $data['imageUrl'] = $this->uploaderHelper->asset($entity, $field);
        }

        return $data;
    }
}
