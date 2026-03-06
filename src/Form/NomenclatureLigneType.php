<?php

namespace App\Form;

use App\Entity\Production\NomenclatureLigne;
use App\Entity\Production\Nomenclature;
use App\Entity\Produit;
use App\Entity\Unite;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class NomenclatureLigneType extends AbstractType
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
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Matière première' => NomenclatureLigne::TYPE_MATIERE_PREMIERE,
                    'Sous-ensemble' => NomenclatureLigne::TYPE_SOUS_ENSEMBLE,
                    'Fourniture' => NomenclatureLigne::TYPE_FOURNITURE,
                    'Main d\'œuvre' => NomenclatureLigne::TYPE_MAIN_OEUVRE,
                ],
                'attr' => ['class' => 'form-select form-select-sm']
            ])
            ->add('designation', TextType::class, [
                'label' => 'Désignation',
                'attr' => [
                    'placeholder' => 'Ex: Caisson aluminium',
                    'class' => 'form-control form-control-sm'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La désignation est obligatoire']),
                    new Assert\Length(['max' => 255])
                ]
            ])
            ->add('produitSimple', EntityType::class, [
                'label' => 'Produit',
                'class' => Produit::class,
                'choice_label' => function(Produit $produit) {
                    return $produit->getReference() . ' - ' . $produit->getDesignation();
                },
                'placeholder' => '-- Aucun --',
                'required' => false,
                'attr' => ['class' => 'form-select form-select-sm']
            ])
            ->add('nomenclatureEnfant', EntityType::class, [
                'label' => 'Nomenclature enfant',
                'class' => Nomenclature::class,
                'choice_label' => function(Nomenclature $nomenclature) {
                    return $nomenclature->getCode() . ' - ' . $nomenclature->getLibelle();
                },
                'placeholder' => '-- Aucune --',
                'required' => false,
                'attr' => ['class' => 'form-select form-select-sm']
            ])
            ->add('quantiteBase', NumberType::class, [
                'label' => 'Quantité',
                'attr' => [
                    'placeholder' => '1.0000',
                    'class' => 'form-control form-control-sm',
                    'step' => '0.0001'
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Positive()
                ]
            ])
            ->add('uniteQuantite', EntityType::class, [
                'label' => 'Unité',
                'class' => Unite::class,
                'choice_label' => 'symbole',
                'placeholder' => '-- Aucune --',
                'required' => false,
                'attr' => ['class' => 'form-select form-select-sm']
            ])
            ->add('formuleQuantite', TextareaType::class, [
                'label' => 'Formule de quantité',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: largeur * hauteur / 10000',
                    'class' => 'form-control form-control-sm',
                    'rows' => 2
                ],
                'help' => 'Formule dynamique : largeur, hauteur, surface, perimetre...'
            ])
            ->add('tauxChute', NumberType::class, [
                'label' => 'Chute (%)',
                'attr' => [
                    'placeholder' => '0.00',
                    'class' => 'form-control form-control-sm',
                    'step' => '0.01'
                ]
            ])
            ->add('margeDefaut', NumberType::class, [
                'label' => 'Marge (%)',
                'required' => false,
                'attr' => [
                    'placeholder' => '0.00',
                    'class' => 'form-control form-control-sm',
                    'step' => '0.01'
                ],
                'help' => 'Marge spécifique pour ce composant'
            ])
            ->add('obligatoire', CheckboxType::class, [
                'label' => 'Obligatoire',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('conditionAffichage', TextareaType::class, [
                'label' => 'Condition d\'affichage',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: option_eclairage == "LED"',
                    'class' => 'form-control form-control-sm',
                    'rows' => 2
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NomenclatureLigne::class,
        ]);
    }
}
