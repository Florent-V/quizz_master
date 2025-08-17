<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use App\Entity\Difficulty;
use App\Enum\GameMode;
use App\Repository\CategoryRepository;
use App\Repository\DifficultyRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @template-extends AbstractType<void>
 */
class QuizConfigurationFormType extends AbstractType
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly DifficultyRepository $difficultyRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', EntityType::class, [
                'class'         => Category::class,
                'label'         => 'Catégorie',
                'choice_label'  => 'name',
                'placeholder'   => 'Toutes les catégories',
                'required'      => false,
                'query_builder' => fn () => $this->categoryRepository
                    ->createQueryBuilder('c')
                    ->where('c.parent IS NULL')
                    ->andWhere('c.deletedAt IS NULL')
                    ->orderBy('c.name', 'ASC'),
            ])
            ->add('subCategory', EntityType::class, [
                'class'        => Category::class,
                'label'        => 'Sous-catégorie',
                'choice_label' => 'name',
                'placeholder'  => 'Toutes les sous-catégories',
                'required'     => false,
                'choices'      => $options['subCategories'] ?? [],
                'disabled'     => empty($options['subCategories']),
            ])
            ->add('difficulties', EntityType::class, [
                'class'        => Difficulty::class,
                'label'        => 'Difficultés',
                'multiple'     => true,
                'expanded'     => false,
                'choice_label' => static function (Difficulty $difficulty) use ($options): string {
                    $difficultyCounts = $options['difficultyCounts']            ?? [];
                    $count            = $difficultyCounts[$difficulty->getId()] ?? 0;

                    return sprintf('%s (%d questions)', $difficulty->getName(), $count);
                },
                'choice_attr' => static function (Difficulty $difficulty) use ($options): array {
                    $difficultyCounts = $options['difficultyCounts']            ?? [];
                    $count            = $difficultyCounts[$difficulty->getId()] ?? 0;

                    return [
                        'data-question-count' => $count,
                    ];
                },
                'choices'  => $this->difficultyRepository->findAll(),
                'required' => false,
                'attr'     => [
                    'class'    => 'form-select',
                    'multiple' => true,
                    'size'     => 1,
                ],
            ])
            ->add('gameMode', EnumType::class, [
                'class'        => GameMode::class,
                'label'        => 'Mode de jeu',
                'placeholder'  => 'Choisir un mode de jeu',
                'choice_label' => static fn (GameMode $gameMode): string => $gameMode->getLabel(),
                'required'     => true,
            ])
            ->add('pseudo', TextType::class, [
                'label'    => 'Pseudo',
                'required' => !$options['is_logged_in'],
                'disabled' => $options['is_logged_in'],
                'attr'     => [
                    'placeholder' => 'Entrez votre pseudo',
                    'class'       => 'input input-bordered w-full',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'       => null,
            'subCategories'    => [],
            'difficultyCounts' => [],
            'is_logged_in'     => false,
        ]);

        $resolver->setAllowedTypes('subCategories', 'array');
        $resolver->setAllowedTypes('difficultyCounts', 'array');
        $resolver->setAllowedTypes('is_logged_in', 'bool');
    }
}
