<?php

namespace App\Form;

use App\Entity\FormeJuridique;
use App\Entity\Fournisseur;
use App\Entity\ModeReglement;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FournisseurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Code fournisseur',
                'attr' => [
                    'maxlength' => 20,
                    'placeholder' => 'Ex: F-001'
                ],
                'help' => 'Code unique d\'identification du fournisseur'
            ])
            ->add('raisonSociale', TextType::class, [
                'label' => 'Raison sociale',
                'attr' => [
                    'maxlength' => 200,
                    'placeholder' => 'Ex: EUROPLEX Distribution'
                ]
            ])
            ->add('formeJuridique', EntityType::class, [
                'class' => FormeJuridique::class,
                'choice_label' => 'nom',
                'label' => 'Forme juridique',
                'required' => false,
                'placeholder' => '-- Sélectionner --'
            ])
            ->add('siren', TextType::class, [
                'label' => 'SIREN',
                'required' => false,
                'attr' => [
                    'maxlength' => 20,
                    'placeholder' => '123456789'
                ]
            ])
            ->add('siret', TextType::class, [
                'label' => 'SIRET',
                'required' => false,
                'attr' => [
                    'maxlength' => 20,
                    'placeholder' => '12345678900012'
                ]
            ])
            ->add('numeroTVA', TextType::class, [
                'label' => 'Numéro de TVA',
                'required' => false,
                'attr' => [
                    'maxlength' => 50,
                    'placeholder' => 'FR12345678901'
                ]
            ])
            ->add('codeNAF', TextType::class, [
                'label' => 'Code NAF',
                'required' => false,
                'attr' => [
                    'maxlength' => 10,
                    'placeholder' => '1234A'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => [
                    'maxlength' => 100,
                    'placeholder' => 'contact@fournisseur.fr'
                ]
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'maxlength' => 25,
                    'placeholder' => '01 23 45 67 89'
                ]
            ])
            ->add('siteWeb', UrlType::class, [
                'label' => 'Site web',
                'required' => false,
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'https://www.fournisseur.fr'
                ]
            ])
            ->add('conditionsPaiement', TextType::class, [
                'label' => 'Conditions de paiement',
                'required' => false,
                'attr' => [
                    'maxlength' => 100,
                    'placeholder' => 'Ex: 30 jours net, 60 jours fin de mois'
                ]
            ])
            ->add('modeReglement', EntityType::class, [
                'class' => ModeReglement::class,
                'choice_label' => 'nom',
                'label' => 'Mode de règlement par défaut',
                'required' => false,
                'placeholder' => '-- Sélectionner --'
            ])
            ->add('remiseGenerale', NumberType::class, [
                'label' => 'Remise générale (%)',
                'required' => false,
                'scale' => 2,
                'attr' => [
                    'min' => 0,
                    'max' => 100,
                    'step' => 0.01,
                    'placeholder' => '0.00'
                ],
                'help' => 'Remise accordée par le fournisseur sur tous ses produits'
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Actif' => 'actif',
                    'Inactif' => 'inactif',
                    'Bloqué' => 'bloque'
                ],
                'help' => 'Un fournisseur bloqué ne peut plus être utilisé pour de nouvelles commandes'
            ])
            ->add('notesInternes', TextareaType::class, [
                'label' => 'Notes internes',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Notes et remarques internes sur ce fournisseur'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Fournisseur::class,
        ]);
    }
}
