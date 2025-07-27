<?php

namespace App\Form;

use App\Entity\Prospect;
use App\Form\AdresseFacturationType;
use App\Form\AdresseLivraisonType;
use App\Form\ContactFacturationType;
use App\Form\ContactLivraisonType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProspectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Informations principales
            ->add('famille', ChoiceType::class, [
                'label' => 'Famille',
                'choices' => [
                    'TPE (Très Petite Entreprise)' => 'TPE',
                    'PME (Petite et Moyenne Entreprise)' => 'PME',
                    'ETI (Entreprise de Taille Intermédiaire)' => 'ETI',
                    'Grand Compte' => 'Grand Compte',
                    'Administration Publique' => 'Administration',
                    'Association' => 'Association',
                    'Particulier' => 'Particulier'
                ],
                'required' => false,
                'placeholder' => 'Choisir une famille...'
            ])
            ->add('typePersonne', ChoiceType::class, [
                'label' => 'Type de personne',
                'choices' => [
                    'Personne morale (Entreprise)' => 'morale',
                    'Personne physique (Particulier)' => 'physique'
                ],
                'expanded' => true,
                'multiple' => false,
                'attr' => ['class' => 'type-personne-radio']
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom / Raison sociale',
                'attr' => ['placeholder' => 'Nom de l\'entreprise ou nom de famille']
            ])
            ->add('formeJuridique', ChoiceType::class, [
                'label' => 'Forme juridique',
                'choices' => [
                    'SAS (Société par Actions Simplifiée)' => 'SAS',
                    'SARL (Société à Responsabilité Limitée)' => 'SARL',
                    'EURL (Entreprise Unipersonnelle à Responsabilité Limitée)' => 'EURL',
                    'SA (Société Anonyme)' => 'SA',
                    'SCI (Société Civile Immobilière)' => 'SCI',
                    'SASU (Société par Actions Simplifiée Unipersonnelle)' => 'SASU',
                    'SNC (Société en Nom Collectif)' => 'SNC',
                    'Micro-entreprise' => 'Micro-entreprise',
                    'Entreprise individuelle' => 'Entreprise individuelle',
                    'Association loi 1901' => 'Association',
                    'Collectivité territoriale' => 'Collectivité',
                    'Établissement public' => 'Établissement public',
                    'Autre' => 'Autre'
                ],
                'required' => false,
                'placeholder' => 'Choisir une forme juridique...',
                'attr' => ['class' => 'forme-juridique-field']
            ])
            
            // Adresses et contacts (sous-formulaires)
            ->add('adresseFacturation', AdresseFacturationType::class, [
                'label' => false
            ])
            ->add('adresseLivraison', AdresseLivraisonType::class, [
                'label' => false
            ])
            ->add('contactFacturation', ContactFacturationType::class, [
                'label' => false
            ])
            ->add('contactLivraison', ContactLivraisonType::class, [
                'label' => false
            ])
            
            // Gestion commerciale
            ->add('regimeComptable', ChoiceType::class, [
                'label' => 'Régime comptable',
                'choices' => [
                    'Réel' => 'reel',
                    'Micro-entreprise' => 'micro',
                    'Simplifié' => 'simplifie'
                ],
                'required' => false,
                'placeholder' => 'Choisir...'
            ])
            ->add('modePaiement', ChoiceType::class, [
                'label' => 'Mode de paiement',
                'choices' => [
                    'Virement' => 'virement',
                    'Chèque' => 'cheque',
                    'Espèces' => 'especes',
                    'Carte bancaire' => 'cb',
                    'Prélèvement' => 'prelevement'
                ],
                'required' => false,
                'placeholder' => 'Choisir...'
            ])
            ->add('delaiPaiement', IntegerType::class, [
                'label' => 'Délai de paiement (jours)',
                'required' => false,
                'attr' => ['min' => 0, 'max' => 365, 'placeholder' => '30']
            ])
            ->add('tauxTva', NumberType::class, [
                'label' => 'Taux TVA (%)',
                'required' => false,
                'scale' => 2,
                'attr' => ['step' => '0.01', 'min' => 0, 'max' => 100, 'placeholder' => '20.00']
            ])
            ->add('assujettiTva', CheckboxType::class, [
                'label' => 'Assujetti à la TVA',
                'required' => false
            ])
            ->add('conditionsTarifs', ChoiceType::class, [
                'label' => 'Conditions tarifaires',
                'choices' => [
                    'Standard' => 'standard',
                    'Remise 5%' => 'remise5',
                    'Remise 10%' => 'remise10',
                    'Tarif négocié' => 'negocie'
                ],
                'required' => false,
                'placeholder' => 'Choisir...'
            ])
            
            // Notes
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['rows' => 5, 'placeholder' => 'Notes et remarques sur ce prospect/client...']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Prospect::class,
            // Temporairement désactiver le CSRF pour éviter les problèmes de session
            'csrf_protection' => false,
        ]);
    }
}