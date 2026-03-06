<?php

namespace App\Form;

use App\Entity\Production\GammeOperation;
use App\Entity\Production\PosteTravail;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class GammeOperationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ordre', IntegerType::class, [
                'label' => 'Ordre',
                'attr' => [
                    'placeholder' => '10',
                    'class' => 'form-control form-control-sm'
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\PositiveOrZero()
                ]
            ])
            ->add('code', TextType::class, [
                'label' => 'Code',
                'attr' => [
                    'placeholder' => 'Ex: OP010',
                    'class' => 'form-control form-control-sm'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le code est obligatoire']),
                    new Assert\Length(['max' => 50])
                ]
            ])
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'attr' => [
                    'placeholder' => 'Ex: Impression face avant',
                    'class' => 'form-control form-control-sm'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le libellé est obligatoire']),
                    new Assert\Length(['max' => 255])
                ]
            ])
            ->add('posteTravail', EntityType::class, [
                'label' => 'Poste de travail',
                'class' => PosteTravail::class,
                'choice_label' => function(PosteTravail $poste) {
                    return $poste->getCode() . ' - ' . $poste->getLibelle();
                },
                'attr' => ['class' => 'form-select form-select-sm'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le poste de travail est obligatoire'])
                ]
            ])
            ->add('typeTemps', ChoiceType::class, [
                'label' => 'Type de temps',
                'choices' => [
                    'Temps fixe' => GammeOperation::TYPE_TEMPS_FIXE,
                    'Formule dynamique' => GammeOperation::TYPE_TEMPS_FORMULE,
                ],
                'attr' => ['class' => 'form-select form-select-sm']
            ])
            ->add('tempsFixe', IntegerType::class, [
                'label' => 'Temps fixe (min)',
                'attr' => [
                    'placeholder' => 'Ex: 30',
                    'class' => 'form-control form-control-sm'
                ],
                'constraints' => [
                    new Assert\PositiveOrZero()
                ]
            ])
            ->add('formuleTemps', TextareaType::class, [
                'label' => 'Formule de temps',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: surface * 0.5 + 30',
                    'class' => 'form-control form-control-sm',
                    'rows' => 2
                ],
                'help' => 'Variables: surface, largeur, hauteur, quantite...'
            ])
            ->add('tempsParallele', CheckboxType::class, [
                'label' => 'Parallèle',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('controleQualite', CheckboxType::class, [
                'label' => 'Contrôle qualité',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('instructions', TextareaType::class, [
                'label' => 'Instructions',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Instructions pour l\'opérateur',
                    'class' => 'form-control form-control-sm',
                    'rows' => 2
                ]
            ])
            ->add('conditionExecution', TextareaType::class, [
                'label' => 'Condition',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: option_eclairage != "aucun"',
                    'class' => 'form-control form-control-sm',
                    'rows' => 2
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GammeOperation::class,
        ]);
    }
}
