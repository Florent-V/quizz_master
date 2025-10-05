<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\Role;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<User>
 */
#[IsGranted(Role::ADMIN->value)]
class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        /** @var User $user */
        $user       = $this->getUser();
        $rolesField = ChoiceField::new('roles')
            ->setChoices([
                'User'        => 'ROLE_USER',
                'Admin'       => 'ROLE_ADMIN',
                'Super Admin' => 'ROLE_SUPER_ADMIN',
            ])
            ->allowMultipleChoices()
//            ->renderExpanded()
        ;

        // Vérifier que l'on est bien en mode édition
        if (Crud::PAGE_EDIT === $pageName) {
            $entityInstance = $this->getContext()->getEntity()->getInstance();

            if ($entityInstance && $user->getId() === $entityInstance->getId()) {
                $rolesField->setFormTypeOption('disabled', true);
            }
        }

        return [
            EmailField::new('email'),
            TextField::new('userName'),
            TextField::new('firstName'),
            TextField::new('lastName'),
            TextField::new('password')->hideOnIndex(),
            //            ArrayField::new('roles'),
            $rolesField,
        ];
    }
}

//
// ?php
//
// declare(strict_types=1);
//
// namespace App\Controller\Admin;
//
// use App\Entity\User;
// use App\Enum\Role;
// use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
// use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
// use App\Entity\User;
// use App\Enum\Role;
// use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
// use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
// use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
// use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
// use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
// use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
// use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
// use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
// use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
// use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
// use Symfony\Component\Form\Extension\Core\Type\PasswordType;
// use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
// use Symfony\Component\Security\Http\Attribute\IsGranted;
//
// /**
// * @extends AbstractCrudController<User>
// */
// #[IsGranted(Role::ADMIN->value)]
// class UserCrudController extends AbstractCrudController
// {
//    public function __construct(
//        private readonly UserPasswordHasherInterface $passwordHasher
//    ) {
//    }
//
//    public static function getEntityFqcn(): string
//    {
//        return User::class;
//    }
//
//    public function configureFields(string $pageName): iterable
//    {
//        /** @var User $currentUser */
//        $currentUser = $this->getUser();
//
//        yield EmailField::new('email');
//        yield TextField::new('userName');
//        yield TextField::new('firstName')->hideOnIndex();
//        yield TextField::new('lastName')->hideOnIndex();
//        yield TextField::new('phone')->hideOnIndex();
//
//        yield TextField::new('password')
//            ->setFormType(PasswordType::class)
//            ->setRequired($pageName === Crud::PAGE_NEW)
//            ->onlyOnForms()
//            ->setLabel('New Password')
//            ->setHelp('Leave blank to keep current password.');
//
//        $rolesField = ChoiceField::new('roles')
//            ->setChoices(array_combine(Role::getValues(), Role::getValues()))
// // Assumes Role::getValues() returns ['ROLE_USER', 'ROLE_ADMIN', ...]
//            ->allowMultipleChoices()
//            ->renderExpanded(false); // ou true selon préférence
//
//        if ($pageName === Crud::PAGE_EDIT) {
//            $entityInstance = $this->getContext()->getEntity()->getInstance();
//            if ($entityInstance && $currentUser && $currentUser->getId() === $entityInstance->getId()) {
//                $rolesField->setFormTypeOption('disabled', true)
//                    ->setHelp('You cannot change your own roles.');
//            }
//        }
//        yield $rolesField;
//
//        yield ImageField::new('picture')
//            ->setBasePath('/uploads/images/profiles')
//            ->setUploadDir('public/uploads/images/profiles')
//            ->setUploadedFileNamePattern('[randomhash].[extension]')
//            ->setFormTypeOptions(['attr' => ['accept' => 'image/jpeg, image/png, image/webp']])
//            ->hideOnIndex();
//
//        yield BooleanField::new('isVerified');
//
//        yield DateTimeField::new('createdAt')
//            ->hideOnForm()
//            ->setFormat('dd/MM/yyyy HH:mm');
//        yield DateTimeField::new('updatedAt')
//            ->hideOnForm()
//            ->setFormat('dd/MM/yyyy HH:mm');
//    }
//
//    public function configureCrud(Crud $crud): Crud
//    {
//        return $crud
//            ->setEntityLabelInSingular('User')
//            ->setEntityLabelInPlural('Users')
//            ->setSearchFields(['email', 'userName', 'firstName', 'lastName'])
//            ->setDefaultSort(['createdAt' => 'DESC']);
//    }
//
//    public function configureActions(Actions $actions): Actions
//    {
//        return $actions
//            ->add(Crud::PAGE_INDEX, Action::DETAIL)
//            // On pourrait vouloir désactiver la suppression des utilisateurs ou la restreindre
//            // ->disable(Action::DELETE)
//            ;
//    }
//
//    // Gérer le hachage du mot de passe si nécessaire (si pas géré par un listener Doctrine)
//    // Cette méthode est un exemple, il est souvent préférable de gérer cela via des listeners Doctrine
//    // pour séparer les préoccupations. EasyAdminBundle >4.7.0 offre PersistSubscriber et UpdateSubscriber
//    // qui peuvent être plus propres.
//    /*
//    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
//    {
//        if (!$entityInstance instanceof User) {
//            return;
//        }
//
//        $plainPassword = $entityInstance->getPassword();
// // Suppose que getPassword() renvoie le mot de passe en clair du formulaire
//                                                       // et qu'il y a un champ plainPassword dans l'entité ou que
//                                                       // le champ password est temporairement utilisé pour ça.
//                                                       // Il faudra adapter l'entité User si on fait ça ici.
//
//        if (!empty($plainPassword)) {
//            $hashedPassword = $this->passwordHasher->hashPassword($entityInstance, $plainPassword);
//            $entityInstance->setPassword($hashedPassword);
//        }
//
//        parent::persistEntity($entityManager, $entityInstance);
//    }
//
//    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
//    {
//        if (!$entityInstance instanceof User) {
//            return;
//        }
//
//        // Similaire à persistEntity, il faut récupérer le mot de passe en clair s'il a été soumis
//        // Souvent, on utilise un champ non mappé pour le nouveau mot de passe dans le formulaire.
//        // $newPassword = $this->getContext()->getRequest()->request->all('User')['password'] ?? null;
// // Ceci est un exemple très brut
//
//        // Pour cet exemple, je vais supposer que si le champ password du formulaire est rempli,
//        // il est dans $entityInstance->getPassword() et doit être haché.
//        // ATTENTION: L'entité User a un champ `password` qui est normalement le mot de passe haché.
//        // Pour changer le mot de passe, il faut un champ temporaire (ex: `plainPassword`) dans le formulaire.
//        // Le code ci-dessous est simplifié et suppose que setPassword est appelé avec le mdp en clair.
//
//        $form = $this->getContext()->getCrud()->getEditForm();
//        $plainPassword = $form->get('password')->getData(); // Accès plus propre au champ du formulaire
//
//        if (!empty($plainPassword)) {
//            $hashedPassword = $this->passwordHasher->hashPassword($entityInstance, $plainPassword);
//            $entityInstance->setPassword($hashedPassword);
//        }
//
//        parent::updateEntity($entityManager, $entityInstance);
//    }
//    */
// }
