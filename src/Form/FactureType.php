<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Commande;
use App\Entity\Contact;
use App\Entity\Facture;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FactureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numeroFacture')
            ->add('dateFacture')
            ->add('dateEcheance')
            ->add('datePaiement')
            ->add('statut')
            ->add('totalHt')
            ->add('totalTva')
            ->add('totalTtc')
            ->add('montantPaye')
            ->add('montantRestant')
            ->add('modePaiement')
            ->add('notesFacturation')
            ->add('notesComptabilite')
            ->add('createdAt', null, [
                'widget' => 'single_text',
            ])
            ->add('updatedAt', null, [
                'widget' => 'single_text',
            ])
            ->add('commande', EntityType::class, [
                'class' => Commande::class,
                'choice_label' => 'id',
            ])
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'id',
            ])
            ->add('contact', EntityType::class, [
                'class' => Contact::class,
                'choice_label' => 'id',
            ])
            ->add('commercial', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Facture::class,
        ]);
    }
}
