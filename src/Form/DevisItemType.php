<?php

namespace App\Form;

use App\Entity\DevisItem;
use App\Entity\Produit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DevisItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('produit', EntityType::class, [
                'class' => Produit::class,
                'choice_label' => function(Produit $produit) {
                    return $produit->getReference() . ' - ' . $produit->getDesignation() . ' (' . number_format((float)$produit->getPrixVenteHt(), 2, ',', ' ') . '€ HT)';
                },
                'placeholder' => 'Choisir un produit/service ou saisie libre',
                'label' => 'Produit/Service',
                'required' => false,
                'attr' => [
                    'class' => 'form-select produit-select',
                    'data-prix' => '',
                    'data-tva' => ''
                ]
            ])
            ->add('designation', TextType::class, [
                'label' => 'Désignation',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Description du produit/service'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description détaillée',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 2,
                    'placeholder' => 'Description complémentaire (optionnel)'
                ]
            ])
            ->add('quantite', NumberType::class, [
                'label' => 'Quantité',
                'data' => 1.00,
                'attr' => [
                    'class' => 'form-control quantite-input',
                    'step' => '0.01',
                    'min' => '0.01',
                    'data-calculation' => 'quantite'
                ]
            ])
            ->add('prixUnitaireHt', NumberType::class, [
                'label' => 'Prix unitaire HT',
                'scale' => 2,
                'attr' => [
                    'class' => 'form-control prix-input',
                    'data-calculation' => 'prix',
                    'step' => '0.01',
                    'placeholder' => '0.00'
                ]
            ])
            ->add('remisePercent', NumberType::class, [
                'label' => 'Remise (%)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control remise-percent-input',
                    'step' => '0.01',
                    'min' => '0',
                    'max' => '100',
                    'data-calculation' => 'remise-percent',
                    'placeholder' => '0'
                ]
            ])
            ->add('remiseMontant', NumberType::class, [
                'label' => 'Remise (€)',
                'required' => false,
                'scale' => 2,
                'attr' => [
                    'class' => 'form-control remise-montant-input',
                    'data-calculation' => 'remise-montant',
                    'placeholder' => '0.00',
                    'step' => '0.01'
                ]
            ])
            ->add('tvaPercent', NumberType::class, [
                'label' => 'TVA (%)',
                'data' => 20.00,
                'attr' => [
                    'class' => 'form-control tva-input',
                    'step' => '0.01',
                    'min' => '0',
                    'max' => '100',
                    'data-calculation' => 'tva'
                ]
            ])
            ->add('totalLigneHt', NumberType::class, [
                'label' => 'Total ligne HT',
                'scale' => 2,
                'data' => '0.00',
                'empty_data' => '0.00',
                'attr' => [
                    'class' => 'form-control total-ht-display',
                    'readonly' => true,
                    'data-calculation' => 'total-ht',
                    'step' => '0.01'
                ]
            ])
            ->add('ordreAffichage', HiddenType::class, [
                'attr' => ['class' => 'ordre-affichage']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DevisItem::class,
        ]);
    }
}