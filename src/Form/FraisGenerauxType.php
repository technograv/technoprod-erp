<?php

namespace App\Form;

use App\Entity\FraisGeneraux;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MonthType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FraisGenerauxType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'Ex: Loyer atelier'
                ]
            ])
            ->add('montantMensuel', NumberType::class, [
                'label' => 'Montant mensuel (€)',
                'scale' => 2,
                'attr' => [
                    'min' => 0,
                    'step' => 0.01,
                    'placeholder' => '0.00'
                ]
            ])
            ->add('typeRepartition', ChoiceType::class, [
                'label' => 'Type de répartition',
                'choices' => [
                    'Par volume de devis' => 'volume_devis',
                    'Ligne cachée dans devis' => 'ligne_cachee',
                    'Coefficient global' => 'coefficient_global',
                    'Par heure main d\'œuvre' => 'par_heure_mo'
                ],
                'attr' => [
                    'data-repartition-selector' => 'true'
                ],
                'help' => 'Méthode de calcul pour répartir ces frais sur les devis'
            ])
            ->add('volumeDevisMensuelEstime', IntegerType::class, [
                'label' => 'Volume de devis mensuel estimé',
                'required' => false,
                'attr' => [
                    'min' => 1,
                    'placeholder' => '50',
                    'data-repartition-field' => 'volume_devis'
                ],
                'help' => 'Nombre de devis estimé par mois (pour calcul frais/devis)'
            ])
            ->add('heuresMOMensuelles', IntegerType::class, [
                'label' => 'Heures main d\'œuvre mensuelles',
                'required' => false,
                'attr' => [
                    'min' => 1,
                    'placeholder' => '160',
                    'data-repartition-field' => 'par_heure_mo'
                ],
                'help' => 'Nombre d\'heures MO estimé par mois (pour calcul frais/heure)'
            ])
            ->add('coefficientMajoration', NumberType::class, [
                'label' => 'Coefficient de majoration',
                'required' => false,
                'scale' => 4,
                'attr' => [
                    'min' => 1,
                    'max' => 10,
                    'step' => 0.0001,
                    'placeholder' => '1.1500',
                    'data-repartition-field' => 'coefficient_global'
                ],
                'help' => 'Coefficient multiplicateur (ex: 1.15 pour +15%)'
            ])
            ->add('periode', TextType::class, [
                'label' => 'Période (YYYY-MM)',
                'attr' => [
                    'maxlength' => 7,
                    'placeholder' => date('Y-m'),
                    'pattern' => '\d{4}-\d{2}'
                ],
                'help' => 'Format: 2025-10'
            ])
            ->add('ordre', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'attr' => [
                    'min' => 0,
                    'value' => 0
                ]
            ])
            ->add('actif', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
                'help' => 'Seuls les frais actifs sont pris en compte dans les calculs'
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Description et détails de ces frais généraux'
                ]
            ])
        ;

        // Dynamically show/hide fields based on typeRepartition
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            // This is handled via JavaScript in the template
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FraisGeneraux::class,
        ]);
    }
}
