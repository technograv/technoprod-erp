<?php

namespace App\Form;

use App\Entity\Secteur;
use App\Entity\User;
use App\Entity\Zone;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SecteurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomSecteur', null, [
                'label' => 'Nom du secteur',
                'attr' => ['placeholder' => 'Ex: Centre-ville, Zone industrielle...']
            ])
            ->add('commercial', EntityType::class, [
                'class' => User::class,
                'choice_label' => function(User $user) {
                    return $user->getFullName() . ' (' . $user->getEmail() . ')';
                },
                'placeholder' => 'Choisir un commercial',
                'label' => 'Commercial responsable'
            ])
            ->add('couleurHex', ColorType::class, [
                'label' => 'Couleur du secteur',
                'required' => false,
                'attr' => ['value' => '#3498db']
            ])
            ->add('zones', EntityType::class, [
                'class' => Zone::class,
                'choice_label' => function(Zone $zone) {
                    return $zone->getCodePostal() . ' - ' . $zone->getVille() . 
                           ($zone->getDepartement() ? ' (' . $zone->getDepartement() . ')' : '');
                },
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'Zones géographiques',
                'attr' => ['class' => 'select2-multiple', 'size' => 8],
                'help' => 'Sélectionnez une ou plusieurs zones pour ce secteur'
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Secteur actif',
                'required' => false,
                'data' => true
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Secteur::class,
        ]);
    }
}
