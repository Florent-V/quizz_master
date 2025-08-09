<?php

declare(strict_types=1);

namespace App\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Exception\EntityNotFoundException;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Intercepts NotFoundHttpException (404) for soft-deleted entities in EasyAdmin.
 *
 * When a user tries to access the 'detail' or 'edit' page of a soft-deleted
 * entity, this listener prevents the 404 page. Instead, it redirects the
 * user to the index page with a helpful flash message.
 */
#[AsEventListener(event: 'kernel.exception')]
final class SoftDeleteableDetailRedirectListener
{
    // Actions that attempt to load a single entity and would cause a 404 if it's soft-deleted.
    private const array TARGET_ACTIONS = [Action::DETAIL, Action::EDIT];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    /**
     * @SuppressWarnings("PHPMD.CyclomaticComplexity")
     */
    public function __invoke(ExceptionEvent $event): void
    {
        // 1. Guard: Only act on NotFoundHttpException (404).
        if (!$event->getThrowable() instanceof EntityNotFoundException) {
            return;
        }


        $request = $event->getRequest();

        // 2. Guard: Check if we are in a relevant EasyAdmin context.
        $crudControllerFqcn = $request->attributes->get('crudControllerFqcn');
        $action             = $request->attributes->get('crudAction');
        $entityId           = $request->attributes->get('entityId');

        if (
            !$crudControllerFqcn
            || !$action
            || !$entityId
            || !is_subclass_of($crudControllerFqcn, AbstractCrudController::class)
            || !in_array($action, self::TARGET_ACTIONS, true)
        ) {
            return;
        }

        // 3. Logic: Check if the entity exists but is soft-deleted.
        $entity = $this->findSoftDeletedEntity($crudControllerFqcn, $entityId);

        if ($entity && method_exists($entity, 'getDeletedAt') && null !== $entity->getDeletedAt()) {
            // 4. Action: The entity is in the trash bin. Redirect with a flash message.
            $this->addFlashMessage($request->getSession());

            $redirectUrl = $this->adminUrlGenerator
                ->setController($crudControllerFqcn)
                ->setAction(Action::INDEX)
                ->generateUrl();

            $event->setResponse(new RedirectResponse($redirectUrl));
        }

        // If the entity is not found at all, do nothing and let the normal 404 page render.
    }

    /**
     * Finds an entity by its ID, temporarily disabling the softdeleteable filter.
     */
    private function findSoftDeletedEntity(string $crudControllerFqcn, string $entityId): ?object
    {
        /** @var class-string<object> $entityFqcn */
        $entityFqcn = $crudControllerFqcn::getEntityFqcn();
        $this->em->getFilters()->disable('softdeleteable');

        try {
            $entity = $this->em->getRepository($entityFqcn)->find($entityId);
        } finally {
            // IMPORTANT: Always re-enable the filter to avoid side effects.
            $this->em->getFilters()->enable('softdeleteable');
        }

        return $entity;
    }

    private function addFlashMessage(SessionInterface $session): void
    {
        // @phpstan-ignore-next-line
        $session->getFlashBag()->add(
            'warning',
            'Cette entité est actuellement dans la corbeille. ' .
            'Vous devez la restaurer avant de pouvoir la modifier ou voir ses détails.'
        );
    }
}
