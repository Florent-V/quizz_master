<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Proposal;
use App\Repository\ProposalRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProposalService
{
    public function __construct(
        private readonly ProposalRepository $proposalRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function restore(int $proposalId): void
    {
        // Désactiver temporairement le filtre SoftDeleteable
        $this->entityManager->getFilters()->disable('softdeleteable');
        // Récupérer la catégorie
        $proposal = $this->proposalRepository->find($proposalId);

        if (!$proposal instanceof Proposal) {
            throw new \InvalidArgumentException('Catégorie non trouvée');
        }

        if (null === $proposal->getDeletedAt()) {
            throw new \LogicException('Cette catégorie n\'est pas supprimée');
        }

        $proposal->setDeletedAt(null);
        $this->entityManager->persist($proposal);
        $this->entityManager->flush();

        // Réactiver le filtre
        $this->entityManager->getFilters()->enable('softdeleteable');
    }

    public function duplicate(int $proposalId): ?Proposal
    {
        $proposal = $this->proposalRepository->find($proposalId);
        if (!$proposal instanceof Proposal) {
            throw new \InvalidArgumentException('Catégorie non trouvée');
        }

        $duplicate = new Proposal();
        $duplicate->setContent($proposal->getContent());
        $duplicate->setIsCorrect($proposal->isCorrect());
        $duplicate->setQuestion($proposal->getQuestion());

        $this->entityManager->persist($duplicate);
        $this->entityManager->flush();

        return $duplicate;
    }

    public function toggleCorrect(int $proposalId): ?Proposal
    {
        $proposal = $this->proposalRepository->find($proposalId);

        if (!$proposal instanceof Proposal) {
            throw new \InvalidArgumentException('Catégorie non trouvée');
        }

        $wasCorrect = $proposal->isCorrect();
        $proposal->setIsCorrect(!$wasCorrect);



        // Vérifier qu'il y a au moins une réponse correcte
        if (!$wasCorrect) { // On vient de rendre cette proposition correcte
            $this->entityManager->persist($proposal);
            $this->entityManager->flush();

            return $proposal;
        }

        // Si on vient de rendre incorrecte, vérifier qu'il reste au moins une correcte
        $question = $proposal->getQuestion();

        $otherCorrectProposals = $question->getProposals()->filter(
            fn ($p) => $p->getId() !== $proposal->getId() && $p->isCorrect()
        );

        if ($otherCorrectProposals->isEmpty()) {
            throw new \LogicException('Il doit y avoir au moins une proposition correcte par question');
        }

        $this->entityManager->persist($proposal);
        $this->entityManager->flush();

        return $proposal;
    }
}
