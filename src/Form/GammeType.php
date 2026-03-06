<?php

namespace App\Form;

use App\Entity\Production\Gamme;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class GammeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Code',
                'attr' => [
                    'placeholder' => 'Ex: GAMME-ENSEIGNE-DRAPEAU',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le code est obligatoire']),
                    new Assert\Length(['max' => 50])
                ]
            ])
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'attr' => [
                    'placeholder' => 'Ex: Fabrication enseigne drapeau LED',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le libellé est obligatoire']),
                    new Assert\Length(['max' => 255])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Description détaillée de la gamme de fabrication',
                    'class' => 'form-control',
                    'rows' => 3
                ]
            ])
            ->add('version', TextType::class, [
                'label' => 'Version',
                'attr' => [
                    'placeholder' => 'Ex: 1.0',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La version est obligatoire']),
                    new Assert\Length(['max' => 20])
                ]
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Brouillon' => Gamme::STATUT_BROUILLON,
                    'Validée' => Gamme::STATUT_VALIDEE,
                    'Obsolète' => Gamme::STATUT_OBSOLETE,
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes techniques',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Notes internes',
                    'class' => 'form-control',
                    'rows' => 4
                ]
            ])
            ->add('actif', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('operations', CollectionType::class, [
                'entry_type' => GammeOperationType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
                'attr' => ['class' => 'operations-collection']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Gamme::class,
        ]);
    }
}
