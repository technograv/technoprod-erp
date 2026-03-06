<?php

namespace App\Form;

use App\Entity\Catalogue\ProduitCatalogue;
use App\Entity\Production\Nomenclature;
use App\Entity\Production\Gamme;
use App\Entity\Produit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ProduitCatalogueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('produit', EntityType::class, [
                'class' => Produit::class,
                'choice_label' => function(Produit $produit) {
                    return $produit->getReference() . ' - ' . $produit->getDesignation();
                },
                'label' => 'Produit de base',
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionner un produit',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le produit est obligatoire'])
                ]
            ])
            ->add('nomenclature', EntityType::class, [
                'class' => Nomenclature::class,
                'choice_label' => function(Nomenclature $nomenclature) {
                    return $nomenclature->getCode() . ' - ' . $nomenclature->getLibelle();
                },
                'label' => 'Nomenclature',
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionner une nomenclature',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La nomenclature est obligatoire'])
                ]
            ])
            ->add('gamme', EntityType::class, [
                'class' => Gamme::class,
                'choice_label' => function(Gamme $gamme) {
                    return $gamme->getCode() . ' - ' . $gamme->getLibelle();
                },
                'label' => 'Gamme de fabrication',
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionner une gamme',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La gamme est obligatoire'])
                ]
            ])
            ->add('margeDefaut', NumberType::class, [
                'label' => 'Marge par défaut (%)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 35',
                    'step' => '0.01'
                ],
                'required' => false,
                'constraints' => [
                    new Assert\PositiveOrZero(['message' => 'La marge doit être positive'])
                ]
            ])
            ->add('personnalisable', CheckboxType::class, [
                'label' => 'Produit personnalisable',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('afficherSurDevis', CheckboxType::class, [
                'label' => 'Afficher sur les devis',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('actif', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('instructionsConfiguration', TextareaType::class, [
                'label' => 'Instructions de configuration',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Instructions affichées lors de la configuration...'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProduitCatalogue::class,
        ]);
    }
}
