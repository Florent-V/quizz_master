<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

/**
 * @extends AbstractType<void>
 */
class QuizImportFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('json_file', FileType::class, [
                'label'              => 'import.form.json_file.label', // Clé de traduction
                'translation_domain' => 'messages', // Spécifier le domaine si ce n'est pas 'messages'
                'mapped'             => false,
                'required'           => true,
                'constraints'        => [
                    new File([
                        'maxSize'   => '10240k', // 10MB
                        'mimeTypes' => [
                            'application/json',
                            'text/plain',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid JSON file (application/json or text/plain).',
                        // Ce message pourrait aussi être une clé de traduction
                    ]),
                ],
                'attr' => [
                    'accept' => '.json,application/json,text/plain',
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label'              => 'import.form.submit.label', // Clé de traduction
                'translation_domain' => 'messages',
                'attr'               => ['class' => 'btn btn-primary mt-3'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
