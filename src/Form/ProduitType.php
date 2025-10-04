<?php

namespace App\Form;

use App\Entity\FamilleProduit;
use App\Entity\Fournisseur;
use App\Entity\Produit;
use App\Entity\Unite;
use App\Entity\ComptePCG;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
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
            ->add('famille', EntityType::class, [
                'class' => FamilleProduit::class,
                'choice_label' => function (FamilleProduit $famille) {
                    return $famille->getCheminComplet();
                },
                'label' => 'Famille de produit',
                'required' => false,
                'placeholder' => '-- Sélectionner une famille --',
                'attr' => ['class' => 'form-select']
            ])
            ->add('fournisseurPrincipal', EntityType::class, [
                'class' => Fournisseur::class,
                'choice_label' => 'raisonSociale',
                'label' => 'Fournisseur principal',
                'required' => false,
                'placeholder' => '-- Sélectionner un fournisseur --',
                'attr' => ['class' => 'form-select']
            ])
            ->add('categorie', TextType::class, [
                'label' => 'Catégorie (ancien champ)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Informatique, Consulting, etc.'
                ],
                'help' => 'À migrer vers "Famille de produit"'
            ])
            ->add('uniteVente', EntityType::class, [
                'class' => Unite::class,
                'choice_label' => function (Unite $unite) {
                    return $unite->getNom() . ' (' . $unite->getSymbole() . ')';
                },
                'label' => 'Unité de vente',
                'required' => false,
                'placeholder' => '-- Sélectionner --',
                'attr' => ['class' => 'form-select']
            ])
            ->add('uniteAchat', EntityType::class, [
                'class' => Unite::class,
                'choice_label' => function (Unite $unite) {
                    return $unite->getNom() . ' (' . $unite->getSymbole() . ')';
                },
                'label' => 'Unité d\'achat',
                'required' => false,
                'placeholder' => '-- Sélectionner --',
                'attr' => ['class' => 'form-select']
            ])
            ->add('unite', ChoiceType::class, [
                'label' => 'Unité (ancien champ)',
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
                'attr' => ['class' => 'form-select'],
                'help' => 'À migrer vers "Unité de vente/achat"'
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
            ->add('fraisPourcentage', NumberType::class, [
                'label' => 'Frais supplémentaires (%)',
                'scale' => 2,
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.01',
                    'min' => '0',
                    'max' => '100',
                    'placeholder' => '0.00'
                ],
                'help' => 'Pourcentage de frais supplémentaires sur prix d\'achat'
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
            ->add('quantiteDefaut', NumberType::class, [
                'label' => 'Quantité par défaut',
                'scale' => 4,
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.0001',
                    'min' => '0',
                    'value' => '1.0000'
                ]
            ])
            ->add('nombreDecimalesPrix', IntegerType::class, [
                'label' => 'Nombre de décimales pour les prix',
                'attr' => [
                    'class' => 'form-control',
                    'min' => '0',
                    'max' => '6',
                    'value' => '2'
                ],
                'help' => 'Précision d\'affichage des prix (généralement 2)'
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
            ->add('typeDestination', ChoiceType::class, [
                'label' => 'Type de destination comptable',
                'required' => false,
                'choices' => [
                    'Marchandise' => 'MARCHANDISE',
                    'Produit fini' => 'PRODUIT_FINI',
                    'Matière première' => 'MATIERE_PREMIERE',
                    'Fourniture' => 'FOURNITURE',
                    'Service' => 'SERVICE'
                ],
                'placeholder' => '-- Sélectionner --',
                'attr' => ['class' => 'form-select']
            ])
            ->add('compteVente', EntityType::class, [
                'class' => ComptePCG::class,
                'choice_label' => 'libelleComplet',
                'label' => 'Compte de vente',
                'required' => false,
                'placeholder' => '-- Sélectionner --',
                'attr' => ['class' => 'form-select'],
                'help' => 'Compte PCG pour les ventes (701xxx, 706xxx, 707xxx)'
            ])
            ->add('compteAchat', EntityType::class, [
                'class' => ComptePCG::class,
                'choice_label' => 'libelleComplet',
                'label' => 'Compte d\'achat',
                'required' => false,
                'placeholder' => '-- Sélectionner --',
                'attr' => ['class' => 'form-select'],
                'help' => 'Compte PCG pour les achats (601xxx, 606xxx, 607xxx)'
            ])
            ->add('compteStock', EntityType::class, [
                'class' => ComptePCG::class,
                'choice_label' => 'libelleComplet',
                'label' => 'Compte de stock',
                'required' => false,
                'placeholder' => '-- Sélectionner --',
                'attr' => ['class' => 'form-select'],
                'help' => 'Compte PCG pour le stock (3xxx)'
            ])
            ->add('compteVariationStock', EntityType::class, [
                'class' => ComptePCG::class,
                'choice_label' => 'libelleComplet',
                'label' => 'Compte variation de stock',
                'required' => false,
                'placeholder' => '-- Sélectionner --',
                'attr' => ['class' => 'form-select'],
                'help' => 'Compte PCG pour variation de stock (603xxx)'
            ])
            ->add('estConcurrent', CheckboxType::class, [
                'label' => 'Produit concurrent (prospection)',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'help' => 'Cocher si ce produit est un produit concurrent (n\'apparaît pas dans les devis)'
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