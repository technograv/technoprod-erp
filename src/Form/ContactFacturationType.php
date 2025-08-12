<?php

namespace App\Form;

use App\Entity\ContactFacturation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactFacturationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('civilite', ChoiceType::class, [
                'label' => 'Civilité',
                'choices' => [
                    'M.' => 'M.',
                    'Mme' => 'Mme',
                    'Mlle' => 'Mlle'
                ],
                'required' => false,
                'placeholder' => 'Choisir...'
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['placeholder' => 'Nom de famille']
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
                'attr' => ['placeholder' => 'Prénom']
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
                    'Autre' => 'Autre'
                ],
                'required' => false,
                'placeholder' => 'Choisir une fonction...'
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone fixe',
                'required' => false,
                'attr' => [
                    'placeholder' => '05 61 23 45 67',
                    'pattern' => '^(?:(?:\+|00)33[\s\.\-]?(?:\(0\)[\s\.\-]?)?|0)[1-9](?:[\s\.\-]?\d{2}){4}$',
                    'title' => 'Format: 05 61 23 45 67 ou +33 5 61 23 45 67',
                    'class' => 'phone-input',
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
                    'class' => 'mobile-input',
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
                    'class' => 'phone-input',
                    'maxlength' => 20
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => ['placeholder' => 'contact@entreprise.com']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactFacturation::class,
        ]);
    }
}