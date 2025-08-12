<?php

namespace App\Form;

use App\Entity\Secteur;
use App\Entity\User;
use App\Entity\TypeSecteur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SecteurModerneType extends AbstractType
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
                'required' => false
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Description du secteur (optionnel)'
                ]
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