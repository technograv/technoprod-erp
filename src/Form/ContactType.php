<?php

namespace App\Form;

use App\Entity\Contact;
use App\Form\AdresseType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactType extends AbstractType
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
                'required' => false,
                'attr' => ['placeholder' => 'Nom de famille']
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
                'attr' => ['placeholder' => 'Prénom']
            ])
            ->add('fonction', TextType::class, [
                'label' => 'Fonction',
                'required' => false,
                'attr' => ['placeholder' => 'Fonction ou poste']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => ['placeholder' => 'email@exemple.com']
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['placeholder' => '01 23 45 67 89']
            ])
            ->add('telephoneMobile', TextType::class, [
                'label' => 'Mobile',
                'required' => false,
                'attr' => ['placeholder' => '06 12 34 56 78']
            ])
            ->add('fax', TextType::class, [
                'label' => 'Fax',
                'required' => false,
                'attr' => ['placeholder' => '01 23 45 67 90']
            ])
            
            // Collection d'adresses pour ce contact
            ->add('adresses', CollectionType::class, [
                'label' => 'Adresses',
                'entry_type' => AdresseType::class,
                'entry_options' => [
                    'label' => false,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'attr' => [
                    'class' => 'adresses-collection',
                    'data-prototype-name' => '__adresses_name__'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contact::class,
        ]);
    }
}