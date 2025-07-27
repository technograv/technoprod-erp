<?php

namespace App\Form;

use App\Entity\Produit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reference', TextType::class, [
                'label' => 'Référence',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: PROD-001'
                ]
            ])
            ->add('designation', TextType::class, [
                'label' => 'Désignation',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom du produit/service'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Description détaillée du produit/service'
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Produit' => 'produit',
                    'Service' => 'service',
                    'Forfait' => 'forfait'
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('categorie', TextType::class, [
                'label' => 'Catégorie',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Informatique, Consulting, etc.'
                ]
            ])
            ->add('unite', ChoiceType::class, [
                'label' => 'Unité',
                'choices' => [
                    'Unité' => 'unité',
                    'Heure' => 'heure',
                    'Jour' => 'jour',
                    'Mois' => 'mois',
                    'Kilogramme' => 'kg',
                    'Mètre' => 'm',
                    'Mètre carré' => 'm²',
                    'Litre' => 'L',
                    'Forfait' => 'forfait'
                ],
                'data' => 'unité',
                'attr' => ['class' => 'form-select']
            ])
            ->add('prixAchatHt', MoneyType::class, [
                'label' => 'Prix d\'achat HT',
                'currency' => 'EUR',
                'attr' => [
                    'class' => 'form-control prix-achat-input',
                    'data-calculation' => 'achat'
                ]
            ])
            ->add('prixVenteHt', MoneyType::class, [
                'label' => 'Prix de vente HT',
                'currency' => 'EUR',
                'attr' => [
                    'class' => 'form-control prix-vente-input',
                    'data-calculation' => 'vente'
                ]
            ])
            ->add('tvaPercent', NumberType::class, [
                'label' => 'Taux TVA (%)',
                'data' => 20.00,
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.01',
                    'min' => '0',
                    'max' => '100'
                ]
            ])
            ->add('margePercent', NumberType::class, [
                'label' => 'Marge (%)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control marge-display',
                    'readonly' => true,
                    'data-calculation' => 'marge'
                ]
            ])
            ->add('gestionStock', CheckboxType::class, [
                'label' => 'Gestion du stock',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input gestion-stock-toggle'
                ]
            ])
            ->add('stockQuantite', NumberType::class, [
                'label' => 'Quantité en stock',
                'required' => false,
                'attr' => [
                    'class' => 'form-control stock-field',
                    'min' => '0'
                ]
            ])
            ->add('stockMinimum', NumberType::class, [
                'label' => 'Stock minimum',
                'required' => false,
                'attr' => [
                    'class' => 'form-control stock-field',
                    'min' => '0'
                ]
            ])
            ->add('actif', CheckboxType::class, [
                'label' => 'Produit actif',
                'required' => false,
                'data' => true,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('image', FileType::class, [
                'label' => 'Image du produit',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp'
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPEG, PNG, GIF, WebP)',
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*'
                ]
            ])
            ->add('notesInternes', TextareaType::class, [
                'label' => 'Notes internes',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Notes pour usage interne uniquement'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Produit::class,
        ]);
    }
}