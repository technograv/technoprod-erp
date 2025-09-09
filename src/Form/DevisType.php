<?php

namespace App\Form;

use App\Entity\Adresse;
use App\Entity\Contact;
use App\Entity\Devis;
use App\Entity\Client;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DevisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Informations générales simplifiées
            ->add('numeroDevis', TextType::class, [
                'label' => 'Numéro du devis',
                'attr' => ['placeholder' => 'Généré automatiquement', 'readonly' => true],
                'required' => false
            ])
            ->add('dateCreation', DateType::class, [
                'label' => 'Date de création',
                'widget' => 'single_text',
                'data' => new \DateTime()
            ])
            ->add('dateValidite', DateType::class, [
                'label' => 'Date de validité',
                'widget' => 'single_text',
                'required' => false
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Brouillon' => 'brouillon',
                    'Envoyé' => 'envoye',
                    'Relancé' => 'relance',
                    'Signé' => 'signe',
                    'Acompte réglé' => 'acompte_regle',
                    'Accepté' => 'accepte',
                    'Refusé' => 'refuse',
                    'Expiré' => 'expire'
                ],
                'data' => 'brouillon',
                'attr' => ['class' => 'form-select']
            ])
            
            // Section Tiers
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'nomComplet',
                'placeholder' => 'Choisir un prospect/client',
                'label' => 'Prospect / Client',
                'attr' => ['class' => 'form-select', 'data-populate-tiers' => 'true']
            ])
            ->add('commercial', EntityType::class, [
                'class' => User::class,
                'choice_label' => function(User $user) {
                    return $user->getPrenom() . ' ' . $user->getNom();
                },
                'placeholder' => 'Choisir un commercial',
                'label' => 'Commercial',
                'required' => false,
                'attr' => ['class' => 'form-select']
            ])
            ->add('tiersCivilite', ChoiceType::class, [
                'label' => 'Civilité',
                'choices' => [
                    'M.' => 'M.',
                    'Mme' => 'Mme',
                    'Mlle' => 'Mlle'
                ],
                'placeholder' => 'Civilité',
                'required' => false,
                'attr' => ['class' => 'form-select']
            ])
            ->add('tiersNom', TextType::class, [
                'label' => 'Nom',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('tiersPrenom', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('tiersAdresse', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('tiersCodePostal', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('tiersVille', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('tiersModeReglement', ChoiceType::class, [
                'label' => 'Mode de règlement',
                'choices' => [
                    'Virement bancaire' => 'virement',
                    'Carte bancaire' => 'carte',
                    'Chèque' => 'cheque',
                    'Espèces' => 'especes',
                    'PayPal' => 'paypal',
                    'Stripe' => 'stripe'
                ],
                'placeholder' => 'Mode de règlement',
                'required' => false,
                'attr' => ['class' => 'form-select']
            ])
            // Onglet Détail - Collection de lignes de devis
            ->add('devisItems', CollectionType::class, [
                'entry_type' => DevisItemType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
                'attr' => ['class' => 'devis-items-collection']
            ])
            
            // Onglet Facturation
            ->add('totalHt', MoneyType::class, [
                'label' => 'Total HT',
                'required' => false,
                'currency' => 'EUR'
            ])
            ->add('totalTva', MoneyType::class, [
                'label' => 'Total TVA',
                'required' => false,
                'currency' => 'EUR'
            ])
            ->add('totalTtc', MoneyType::class, [
                'label' => 'Total TTC',
                'required' => false,
                'currency' => 'EUR'
            ])
            ->add('remiseGlobalePercent', NumberType::class, [
                'label' => 'Remise globale (%)',
                'required' => false,
                'attr' => ['step' => '0.01', 'min' => '0', 'max' => '100']
            ])
            ->add('remiseGlobaleMontant', MoneyType::class, [
                'label' => 'Remise globale (€)',
                'required' => false,
                'currency' => 'EUR'
            ])
            ->add('acomptePercent', NumberType::class, [
                'label' => 'Acompte (%)',
                'required' => false,
                'attr' => [
                    'step' => '0.01',
                    'min' => '0',
                    'max' => '100',
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 30'
                ]
            ])
            ->add('acompteMontant', MoneyType::class, [
                'label' => 'Acompte (€)',
                'required' => false,
                'currency' => 'EUR',
                'attr' => ['class' => 'form-control']
            ])
            ->add('modePaiement', ChoiceType::class, [
                'label' => 'Mode de paiement',
                'choices' => [
                    'Virement bancaire' => 'virement',
                    'Carte bancaire' => 'carte',
                    'Chèque' => 'cheque',
                    'Espèces' => 'especes',
                    'PayPal' => 'paypal',
                    'Stripe' => 'stripe'
                ],
                'required' => false,
                'attr' => ['class' => 'form-select']
            ])
            ->add('signatureNom', TextType::class, [
                'label' => 'Nom du signataire',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('signatureEmail', EmailType::class, [
                'label' => 'Email du signataire',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            
            // Onglet Livraison
            ->add('contactFacturation', EntityType::class, [
                'class' => Contact::class,
                'choice_label' => function(Contact $contact) {
                    $label = trim(($contact->getCivilite() ?? '') . ' ' . ($contact->getPrenom() ?? '') . ' ' . ($contact->getNom() ?? ''));
                    return $label ?: 'Contact sans nom';
                },
                'query_builder' => function($repository) use ($options) {
                    $qb = $repository->createQueryBuilder('c');
                    // Ne charger que les contacts du client actuel
                    if (isset($options['data']) && $options['data'] instanceof Devis && $options['data']->getClient()) {
                        $qb->andWhere('c.client = :client')
                           ->setParameter('client', $options['data']->getClient());
                    } else {
                        // Si pas de client, ne retourner aucun résultat
                        $qb->andWhere('1 = 0');
                    }
                    return $qb;
                },
                'choice_attr' => function(Contact $contact) {
                    return ['data-adresse-id' => $contact->getAdresse() ? $contact->getAdresse()->getId() : ''];
                },
                'placeholder' => 'Choisir un contact de facturation',
                'label' => 'Contact facturation',
                'required' => false,
                'attr' => ['class' => 'form-select contact-select']
            ])
            ->add('adresseFacturation', EntityType::class, [
                'class' => Adresse::class,
                'choice_label' => function(Adresse $adresse) {
                    return ($adresse->getNom() ?? 'Adresse') . ' - ' . $adresse->getLigne1() . ' - ' . $adresse->getVille();
                },
                'query_builder' => function($repository) use ($options) {
                    $qb = $repository->createQueryBuilder('a');
                    // Ne charger que les adresses du client actuel
                    if (isset($options['data']) && $options['data'] instanceof Devis && $options['data']->getClient()) {
                        $qb->andWhere('a.client = :client')
                           ->setParameter('client', $options['data']->getClient());
                    } else {
                        // Si pas de client, ne retourner aucun résultat
                        $qb->andWhere('1 = 0');
                    }
                    return $qb;
                },
                'placeholder' => 'Choisir une adresse de facturation',
                'label' => 'Adresse de facturation',
                'required' => false,
                'attr' => ['class' => 'form-select address-select']
            ])
            ->add('contactLivraison', EntityType::class, [
                'class' => Contact::class,
                'choice_label' => function(Contact $contact) {
                    $label = trim(($contact->getCivilite() ?? '') . ' ' . ($contact->getPrenom() ?? '') . ' ' . ($contact->getNom() ?? ''));
                    return $label ?: 'Contact sans nom';
                },
                'query_builder' => function($repository) use ($options) {
                    $qb = $repository->createQueryBuilder('c');
                    // Ne charger que les contacts du client actuel
                    if (isset($options['data']) && $options['data'] instanceof Devis && $options['data']->getClient()) {
                        $qb->andWhere('c.client = :client')
                           ->setParameter('client', $options['data']->getClient());
                    } else {
                        // Si pas de client, ne retourner aucun résultat
                        $qb->andWhere('1 = 0');
                    }
                    return $qb;
                },
                'choice_attr' => function(Contact $contact) {
                    return ['data-adresse-id' => $contact->getAdresse() ? $contact->getAdresse()->getId() : ''];
                },
                'placeholder' => 'Choisir un contact de livraison',
                'label' => 'Contact livraison',
                'required' => false,
                'attr' => ['class' => 'form-select contact-select']
            ])
            ->add('adresseLivraison', EntityType::class, [
                'class' => Adresse::class,
                'choice_label' => function(Adresse $adresse) {
                    return ($adresse->getNom() ?? 'Adresse') . ' - ' . $adresse->getLigne1() . ' - ' . $adresse->getVille();
                },
                'query_builder' => function($repository) use ($options) {
                    $qb = $repository->createQueryBuilder('a');
                    // Ne charger que les adresses du client actuel
                    if (isset($options['data']) && $options['data'] instanceof Devis && $options['data']->getClient()) {
                        $qb->andWhere('a.client = :client')
                           ->setParameter('client', $options['data']->getClient());
                    } else {
                        // Si pas de client, ne retourner aucun résultat
                        $qb->andWhere('1 = 0');
                    }
                    return $qb;
                },
                'placeholder' => 'Choisir une adresse de livraison',
                'label' => 'Adresse de livraison',
                'required' => false,
                'attr' => ['class' => 'form-select address-select']
            ])
            ->add('delaiLivraison', TextType::class, [
                'label' => 'Délai de livraison',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 2-3 semaines'
                ]
            ])
            
            // Onglet Notes
            ->add('notesClient', TextareaType::class, [
                'label' => 'Notes visibles par le client',
                'required' => false,
                'attr' => ['rows' => 3, 'class' => 'form-control']
            ])
            ->add('notesInternes', TextareaType::class, [
                'label' => 'Notes internes',
                'required' => false,
                'attr' => ['rows' => 3, 'class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Devis::class,
        ]);
    }
}
