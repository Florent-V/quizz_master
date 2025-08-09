<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class CategoryHierarchy extends Constraint
{
    public string $maxLevelMessage          = 'Cette catégorie ne peut pas avoir d\'enfants (niveau maximum atteint).';
    public string $selfParentMessage        = 'Une catégorie ne peut pas être son propre parent.';
    public string $circularReferenceMessage = 'Cette hiérarchie créerait une référence circulaire.';

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        ?string $maxLevelMessage = null,
        ?string $selfParentMessage = null,
        ?string $circularReferenceMessage = null,
        ?array $groups = null,
        mixed $payload = null,
        array $options = [],
    ) {
        parent::__construct($options, $groups, $payload);

        $this->maxLevelMessage          = $maxLevelMessage          ?? $this->maxLevelMessage;
        $this->selfParentMessage        = $selfParentMessage        ?? $this->selfParentMessage;
        $this->circularReferenceMessage = $circularReferenceMessage ?? $this->circularReferenceMessage;
    }
}
