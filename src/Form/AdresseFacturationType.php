<?php

namespace App\Form;

use App\Entity\AdresseFacturation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdresseFacturationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ligne1', TextType::class, [
                'label' => 'Adresse ligne 1',
                'attr' => ['placeholder' => 'Numéro et nom de rue']
            ])
            ->add('ligne2', TextType::class, [
                'label' => 'Adresse ligne 2',
                'required' => false,
                'attr' => ['placeholder' => 'Complément d\'adresse (optionnel)']
            ])
            ->add('ligne3', TextType::class, [
                'label' => 'Adresse ligne 3',
                'required' => false,
                'attr' => ['placeholder' => 'Bâtiment, étage... (optionnel)']
            ])
            ->add('codePostal', TextType::class, [
                'label' => 'Code postal',
                'attr' => ['placeholder' => '31000', 'maxlength' => 10]
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'attr' => ['placeholder' => 'Toulouse']
            ])
            ->add('pays', TextType::class, [
                'label' => 'Pays',
                'attr' => ['placeholder' => 'France']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AdresseFacturation::class,
        ]);
    }
}