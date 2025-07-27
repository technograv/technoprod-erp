<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Commande;
use App\Entity\Contact;
use App\Entity\Devis;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numeroCommande')
            ->add('dateCommande')
            ->add('dateLivraisonPrevue')
            ->add('dateLivraisonReelle')
            ->add('statut')
            ->add('totalHt')
            ->add('totalTva')
            ->add('totalTtc')
            ->add('notesProduction')
            ->add('notesLivraison')
            ->add('createdAt', null, [
                'widget' => 'single_text',
            ])
            ->add('updatedAt', null, [
                'widget' => 'single_text',
            ])
            ->add('devis', EntityType::class, [
                'class' => Devis::class,
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
            'data_class' => Commande::class,
        ]);
    }
}
