<?php

namespace App\Form;

use App\Entity\ContactLivraison;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactLivraisonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('identiqueFacturation', CheckboxType::class, [
                'label' => 'Identique au contact de facturation',
                'required' => false,
                'attr' => ['class' => 'identique-facturation-contact-checkbox']
            ])
            ->add('civilite', ChoiceType::class, [
                'label' => 'Civilité',
                'choices' => [
                    'M.' => 'M.',
                    'Mme' => 'Mme',
                    'Mlle' => 'Mlle'
                ],
                'required' => false,
                'placeholder' => 'Choisir...',
                'attr' => ['class' => 'contact-livraison-field']
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'required' => false,
                'attr' => ['placeholder' => 'Nom de famille', 'class' => 'contact-livraison-field']
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
                'attr' => ['placeholder' => 'Prénom', 'class' => 'contact-livraison-field']
            ])
            ->add('fonction', ChoiceType::class, [
                'label' => 'Fonction',
                'choices' => [
                    'Directeur Général' => 'Directeur Général',
                    'Directeur Commercial' => 'Directeur Commercial',
                    'Directeur Financier' => 'Directeur Financier',
                    'Directeur Technique' => 'Directeur Technique',
                    'Responsable Achats' => 'Responsable Achats',
                    'Responsable Marketing' => 'Responsable Marketing',
                    'Chef de Projet' => 'Chef de Projet',
                    'Commercial' => 'Commercial',
                    'Comptable' => 'Comptable',
                    'Assistant(e)' => 'Assistant(e)',
                    'Secrétaire' => 'Secrétaire',
                    'Technicien' => 'Technicien',
                    'Responsable Logistique' => 'Responsable Logistique',
                    'Responsable Réception' => 'Responsable Réception',
                    'Autre' => 'Autre'
                ],
                'required' => false,
                'placeholder' => 'Choisir une fonction...',
                'attr' => ['class' => 'contact-livraison-field']
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone fixe',
                'required' => false,
                'attr' => [
                    'placeholder' => '05 61 23 45 67',
                    'pattern' => '^(?:(?:\+|00)33[\s\.\-]?(?:\(0\)[\s\.\-]?)?|0)[1-9](?:[\s\.\-]?\d{2}){4}$',
                    'title' => 'Format: 05 61 23 45 67 ou +33 5 61 23 45 67',
                    'class' => 'contact-livraison-field phone-input',
                    'maxlength' => 20
                ]
            ])
            ->add('telephoneMobile', TelType::class, [
                'label' => 'Téléphone mobile',
                'required' => false,
                'attr' => [
                    'placeholder' => '06 12 34 56 78',
                    'pattern' => '^(?:(?:\+|00)33[\s\.\-]?(?:\(0\)[\s\.\-]?)?|0)[6-7](?:[\s\.\-]?\d{2}){4}$',
                    'title' => 'Format: 06 12 34 56 78 ou +33 6 12 34 56 78',
                    'class' => 'contact-livraison-field mobile-input',
                    'maxlength' => 20
                ]
            ])
            ->add('fax', TelType::class, [
                'label' => 'Fax',
                'required' => false,
                'attr' => [
                    'placeholder' => '05 61 23 45 68',
                    'pattern' => '^(?:(?:\+|00)33[\s\.\-]?(?:\(0\)[\s\.\-]?)?|0)[1-9](?:[\s\.\-]?\d{2}){4}$',
                    'title' => 'Format: 05 61 23 45 68 ou +33 5 61 23 45 68',
                    'class' => 'contact-livraison-field phone-input',
                    'maxlength' => 20
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => ['placeholder' => 'contact@entreprise.com', 'class' => 'contact-livraison-field']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactLivraison::class,
        ]);
    }
}