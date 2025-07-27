<?php

namespace App\Form;

use App\Entity\Secteur;
use App\Entity\SecteurZone;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SecteurZoneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('codePostal')
            ->add('ville')
            ->add('createdAt', null, [
                'widget' => 'single_text',
            ])
            ->add('secteur', EntityType::class, [
                'class' => Secteur::class,
                'choice_label' => 'nomSecteur',
                'placeholder' => 'Choisir un secteur',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SecteurZone::class,
        ]);
    }
}
