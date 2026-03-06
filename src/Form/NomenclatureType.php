<?php

namespace App\Form;

use App\Entity\Production\Nomenclature;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class NomenclatureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Code',
                'attr' => [
                    'placeholder' => 'Ex: NOM-ENSEIGNE-DRAPEAU',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le code est obligatoire']),
                    new Assert\Length(['max' => 50])
                ]
            ])
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'attr' => [
                    'placeholder' => 'Ex: Enseigne drapeau LED double face',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le libellé est obligatoire']),
                    new Assert\Length(['max' => 255])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Description détaillée de la nomenclature',
                    'class' => 'form-control',
                    'rows' => 3
                ]
            ])
            ->add('version', TextType::class, [
                'label' => 'Version',
                'attr' => [
                    'placeholder' => 'Ex: 1.0',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La version est obligatoire']),
                    new Assert\Length(['max' => 20])
                ]
            ])
            ->add('parent', EntityType::class, [
                'label' => 'Nomenclature parente',
                'class' => Nomenclature::class,
                'choice_label' => function(Nomenclature $nomenclature) {
                    return $nomenclature->getCode() . ' - ' . $nomenclature->getLibelle();
                },
                'placeholder' => 'Aucune (nomenclature racine)',
                'required' => false,
                'attr' => ['class' => 'form-select']
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Brouillon' => Nomenclature::STATUT_BROUILLON,
                    'Validée' => Nomenclature::STATUT_VALIDEE,
                    'Obsolète' => Nomenclature::STATUT_OBSOLETE,
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes techniques',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Notes internes',
                    'class' => 'form-control',
                    'rows' => 4
                ]
            ])
            ->add('actif', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('lignes', CollectionType::class, [
                'entry_type' => NomenclatureLigneType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
                'attr' => ['class' => 'nomenclature-lignes-collection']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Nomenclature::class,
        ]);
    }
}
