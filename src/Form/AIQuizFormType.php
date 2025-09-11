<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\AIQuizDTO;
use App\Entity\Difficulty;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<AIQuizDTO>
 */
class AIQuizFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('theme', TextType::class, [
                'label' => 'Thème du quiz',
                'attr'  => [
                    'placeholder' => 'Ex: L\'histoire de la seconde guerre mondiale',
                    'class'       => 'input input-bordered w-full',
                ],
                'row_attr' => [
                    'class' => 'form-control w-full',
                ],
            ])
            ->add('difficulty', EntityType::class, [
                'class'        => Difficulty::class,
                'choice_label' => 'name',
                'label'        => 'Difficulté',
                'placeholder'  => 'Choisir une difficulté',
                'attr'         => [
                    'class' => 'select select-bordered w-full',
                ],
                'row_attr' => [
                    'class' => 'form-control w-full',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AIQuizDTO::class,
        ]);
    }
}
