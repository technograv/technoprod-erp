<?php

namespace App\Form;

use App\Entity\Adresse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdresseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'adresse',
                'required' => true,
                'attr' => ['placeholder' => 'Ex: Siège social, Entrepôt, Livraison...']
            ])
            ->add('ligne1', TextType::class, [
                'label' => 'Adresse ligne 1',
                'required' => false,
                'attr' => ['placeholder' => 'Numéro et rue']
            ])
            ->add('ligne2', TextType::class, [
                'label' => 'Adresse ligne 2',
                'required' => false,
                'attr' => ['placeholder' => 'Complément d\'adresse (optionnel)']
            ])
            ->add('ligne3', TextType::class, [
                'label' => 'Adresse ligne 3',
                'required' => false,
                'attr' => ['placeholder' => 'Lieu-dit, résidence (optionnel)']
            ])
            ->add('codePostal', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
                'attr' => ['placeholder' => '00000']
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'attr' => ['placeholder' => 'Ville']
            ])
            ->add('pays', TextType::class, [
                'label' => 'Pays',
                'required' => false,
                'attr' => ['placeholder' => 'France']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Adresse::class,
        ]);
    }
}