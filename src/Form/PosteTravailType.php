<?php

namespace App\Form;

use App\Entity\Production\PosteTravail;
use App\Entity\Production\CategoriePoste;
use App\Entity\Unite;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PosteTravailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Code',
                'attr' => [
                    'placeholder' => 'Ex: IMP-LATEX-1',
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
                    'placeholder' => 'Ex: Imprimante HP Latex 360',
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
                    'placeholder' => 'Description détaillée du poste de travail',
                    'class' => 'form-control',
                    'rows' => 3
                ]
            ])
            ->add('categorie', EntityType::class, [
                'label' => 'Catégorie',
                'class' => CategoriePoste::class,
                'choice_label' => 'libelle',
                'attr' => ['class' => 'form-select'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La catégorie est obligatoire'])
                ]
            ])
            ->add('coutHoraire', NumberType::class, [
                'label' => 'Coût horaire (€/h)',
                'attr' => [
                    'placeholder' => 'Ex: 45.00',
                    'class' => 'form-control',
                    'step' => '0.01'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le coût horaire est obligatoire']),
                    new Assert\Positive(['message' => 'Le coût horaire doit être positif'])
                ]
            ])
            ->add('tempsSetup', IntegerType::class, [
                'label' => 'Temps de setup (minutes)',
                'attr' => [
                    'placeholder' => 'Ex: 30',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le temps de setup est obligatoire']),
                    new Assert\PositiveOrZero(['message' => 'Le temps de setup doit être positif ou nul'])
                ]
            ])
            ->add('tempsNettoyage', IntegerType::class, [
                'label' => 'Temps de nettoyage (minutes)',
                'attr' => [
                    'placeholder' => 'Ex: 15',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le temps de nettoyage est obligatoire']),
                    new Assert\PositiveOrZero(['message' => 'Le temps de nettoyage doit être positif ou nul'])
                ]
            ])
            ->add('capaciteJournaliere', NumberType::class, [
                'label' => 'Capacité maximale journalière',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: 50',
                    'class' => 'form-control',
                    'step' => '0.01'
                ],
                'help' => 'Production maximale par jour. Choisissez l\'unité ci-dessous.'
            ])
            ->add('uniteCapacite', EntityType::class, [
                'label' => 'Unité de capacité',
                'class' => Unite::class,
                'choice_label' => function(Unite $unite) {
                    return $unite->getNom() . ' (' . $unite->getSymbole() . ')';
                },
                'placeholder' => '-- Sélectionner une unité --',
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'help' => 'Unité de mesure de la capacité (m², unités, ml, etc.)'
            ])
            ->add('necessiteOperateur', CheckboxType::class, [
                'label' => 'Nécessite un opérateur',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('polyvalent', CheckboxType::class, [
                'label' => 'Poste polyvalent',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('actif', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PosteTravail::class,
        ]);
    }
}
