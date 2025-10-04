<?php

namespace App\Form;

use App\Entity\FamilleProduit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FamilleProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Code',
                'attr' => [
                    'maxlength' => 50,
                    'placeholder' => 'Ex: SIGNA-ENSE'
                ],
                'help' => 'Code unique de la famille (max 50 caractères)'
            ])
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'Ex: Enseignes lumineuses'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Description détaillée de la famille de produits'
                ]
            ])
            ->add('parent', EntityType::class, [
                'class' => FamilleProduit::class,
                'choice_label' => function (FamilleProduit $famille) {
                    return $famille->getCheminComplet();
                },
                'label' => 'Famille parente',
                'required' => false,
                'placeholder' => '-- Aucune (famille racine) --',
                'help' => 'Laisser vide pour créer une famille de niveau racine'
            ])
            ->add('ordre', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'attr' => [
                    'min' => 0,
                    'value' => 0
                ],
                'help' => 'Ordre d\'affichage dans les listes (0 = premier)'
            ])
            ->add('actif', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
                'help' => 'Une famille inactive n\'apparaît pas dans les sélecteurs'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FamilleProduit::class,
        ]);
    }
}
