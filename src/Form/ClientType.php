<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Contact;
use App\Form\ContactType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientType extends AbstractType
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
            
            // Collection de contacts multiples
            ->add('contacts', CollectionType::class, [
                'label' => 'Contacts',
                'entry_type' => ContactType::class,
                'entry_options' => [
                    'label' => false,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'attr' => [
                    'class' => 'contacts-collection',
                    'data-prototype-name' => '__contacts_name__'
                ]
            ])
            
            // Sélection des contacts par défaut
            ->add('contactFacturationDefault', EntityType::class, [
                'label' => 'Contact facturation par défaut',
                'class' => Contact::class,
                'choice_label' => function(Contact $contact) {
                    return $contact->getPrenom() . ' ' . $contact->getNom() . ' (' . $contact->getFonction() . ')';
                },
                'choices' => $options['data'] ? $options['data']->getContacts() : [],
                'required' => false,
                'placeholder' => 'Sélectionner un contact...',
                'attr' => ['class' => 'contact-facturation-select']
            ])
            ->add('contactLivraisonDefault', EntityType::class, [
                'label' => 'Contact livraison par défaut',
                'class' => Contact::class,
                'choice_label' => function(Contact $contact) {
                    return $contact->getPrenom() . ' ' . $contact->getNom() . ' (' . $contact->getFonction() . ')';
                },
                'choices' => $options['data'] ? $options['data']->getContacts() : [],
                'required' => false,
                'placeholder' => 'Sélectionner un contact...',
                'attr' => ['class' => 'contact-livraison-select']
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
            'data_class' => Client::class,
            // Temporairement désactiver le CSRF pour éviter les problèmes de session
            'csrf_protection' => false,
        ]);
    }
}