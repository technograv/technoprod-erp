<?php

namespace App\Form;

use App\Entity\AdresseLivraison;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdresseLivraisonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('identiqueFacturation', CheckboxType::class, [
                'label' => 'Identique à l\'adresse de facturation',
                'required' => false,
                'attr' => ['class' => 'identique-facturation-checkbox']
            ])
            ->add('ligne1', TextType::class, [
                'label' => 'Adresse ligne 1',
                'required' => false,
                'attr' => ['placeholder' => 'Numéro et nom de rue', 'class' => 'adresse-livraison-field']
            ])
            ->add('ligne2', TextType::class, [
                'label' => 'Adresse ligne 2',
                'required' => false,
                'attr' => ['placeholder' => 'Complément d\'adresse (optionnel)', 'class' => 'adresse-livraison-field']
            ])
            ->add('ligne3', TextType::class, [
                'label' => 'Adresse ligne 3',
                'required' => false,
                'attr' => ['placeholder' => 'Bâtiment, étage... (optionnel)', 'class' => 'adresse-livraison-field']
            ])
            ->add('codePostal', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
                'attr' => ['placeholder' => '31000', 'maxlength' => 10, 'class' => 'adresse-livraison-field']
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'attr' => ['placeholder' => 'Toulouse', 'class' => 'adresse-livraison-field']
            ])
            ->add('pays', TextType::class, [
                'label' => 'Pays',
                'required' => false,
                'attr' => ['placeholder' => 'France', 'class' => 'adresse-livraison-field']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AdresseLivraison::class,
        ]);
    }
}