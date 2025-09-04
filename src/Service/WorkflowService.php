<?php

namespace App\Service;

use App\Entity\Devis;
use App\Entity\Commande;
use App\Entity\Facture;
use Doctrine\ORM\EntityManagerInterface;

class WorkflowService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Convertir un devis accepté en commande
     */
    public function convertDevisToCommande(Devis $devis): Commande
    {
        if (!in_array($devis->getStatut(), ['accepte', 'signe', 'acompte_regle'])) {
            throw new \InvalidArgumentException('Le devis doit être accepté, signé ou avoir un acompte réglé pour être converti en commande');
        }

        // Vérifier qu'il n'y a pas déjà une commande pour ce devis
        $existingCommande = $this->entityManager->getRepository(Commande::class)
            ->findOneBy(['devis' => $devis]);

        if ($existingCommande) {
            throw new \InvalidArgumentException('Une commande existe déjà pour ce devis');
        }

        $commande = new Commande();
        $commande->setDevis($devis);
        
        // Générer le numéro de commande
        $this->entityManager->persist($commande);
        $this->entityManager->flush();
        
        $numeroCommande = $commande->generateNumeroCommande();
        $commande->setNumeroCommande($numeroCommande);
        
        // Copier les données du devis
        $commande->copyFromDevis();
        
        // Mettre à jour le statut du devis
        $devis->setStatut('converti');
        $devis->setUpdatedAt(new \DateTimeImmutable());
        
        $this->entityManager->flush();
        
        return $commande;
    }

    /**
     * Convertir une commande livrée en facture
     */
    public function convertCommandeToFacture(Commande $commande): Facture
    {
        if (!in_array($commande->getStatut(), ['expediee', 'livree'])) {
            throw new \InvalidArgumentException('La commande doit être expédiée ou livrée pour être facturée');
        }

        $facture = new Facture();
        $facture->setCommande($commande);
        
        // Générer le numéro de facture
        $this->entityManager->persist($facture);
        $this->entityManager->flush();
        
        $numeroFacture = $facture->generateNumeroFacture();
        $facture->setNumeroFacture($numeroFacture);
        
        // Copier les données de la commande
        $facture->copyFromCommande();
        
        $this->entityManager->flush();
        
        return $facture;
    }

    /**
     * Changer le statut d'un devis
     */
    public function changeDevisStatut(Devis $devis, string $newStatut): void
    {
        $allowedTransitions = $this->getDevisAllowedTransitions($devis->getStatut());
        
        if (!in_array($newStatut, $allowedTransitions)) {
            throw new \InvalidArgumentException("Transition non autorisée de '{$devis->getStatut()}' vers '$newStatut'");
        }
        
        $devis->setStatut($newStatut);
        $devis->setUpdatedAt(new \DateTimeImmutable());
        
        $this->entityManager->flush();
    }

    /**
     * Changer le statut d'une commande
     */
    public function changeCommandeStatut(Commande $commande, string $newStatut): void
    {
        $allowedTransitions = $this->getCommandeAllowedTransitions($commande->getStatut());
        
        if (!in_array($newStatut, $allowedTransitions)) {
            throw new \InvalidArgumentException("Transition non autorisée de '{$commande->getStatut()}' vers '$newStatut'");
        }
        
        $commande->setStatut($newStatut);
        $commande->setUpdatedAt(new \DateTimeImmutable());
        
        // Actions automatiques selon le statut
        switch ($newStatut) {
            case 'expediee':
                if (!$commande->getDateLivraisonPrevue()) {
                    $commande->setDateLivraisonPrevue(new \DateTime('+3 days'));
                }
                break;
            case 'livree':
                $commande->setDateLivraisonReelle(new \DateTime());
                break;
        }
        
        $this->entityManager->flush();
    }

    /**
     * Changer le statut d'une facture
     */
    public function changeFactureStatut(Facture $facture, string $newStatut): void
    {
        $allowedTransitions = $this->getFactureAllowedTransitions($facture->getStatut());
        
        if (!in_array($newStatut, $allowedTransitions)) {
            throw new \InvalidArgumentException("Transition non autorisée de '{$facture->getStatut()}' vers '$newStatut'");
        }
        
        $facture->setStatut($newStatut);
        $facture->setUpdatedAt(new \DateTimeImmutable());
        
        // Actions automatiques selon le statut
        switch ($newStatut) {
            case 'payee':
                $facture->setDatePaiement(new \DateTime());
                $facture->setMontantPaye($facture->getTotalTtc());
                break;
        }
        
        $this->entityManager->flush();
    }

    /**
     * Obtenir les transitions autorisées pour un devis
     */
    public function getDevisAllowedTransitions(string $currentStatut): array
    {
        return match($currentStatut) {
            'brouillon' => ['envoye', 'annule'],
            'envoye' => ['accepte', 'refuse', 'annule'],
            'accepte' => ['converti', 'annule'],
            'refuse' => ['envoye'],
            'converti' => [],
            'annule' => [],
            default => []
        };
    }

    /**
     * Obtenir les transitions autorisées pour une commande
     */
    public function getCommandeAllowedTransitions(string $currentStatut): array
    {
        return match($currentStatut) {
            'en_preparation' => ['confirmee', 'annulee'],
            'confirmee' => ['en_production', 'annulee'],
            'en_production' => ['expediee', 'annulee'],
            'expediee' => ['livree'],
            'livree' => [],
            'annulee' => [],
            default => []
        };
    }

    /**
     * Obtenir les transitions autorisées pour une facture
     */
    public function getFactureAllowedTransitions(string $currentStatut): array
    {
        return match($currentStatut) {
            'brouillon' => ['envoyee', 'annulee'],
            'envoyee' => ['en_relance', 'payee', 'en_litige', 'annulee'],
            'en_relance' => ['payee', 'en_litige', 'annulee'],
            'en_litige' => ['payee', 'annulee'],
            'payee' => ['archivee'],
            'annulee' => [],
            'archivee' => [],
            default => []
        };
    }

    /**
     * Obtenir les actions possibles pour un devis
     */
    public function getDevisActions(Devis $devis): array
    {
        $actions = [];
        $allowedTransitions = $this->getDevisAllowedTransitions($devis->getStatut());
        
        foreach ($allowedTransitions as $statut) {
            $actions[] = [
                'action' => $statut,
                'label' => $this->getActionLabel('devis', $statut),
                'css_class' => $this->getActionCssClass('devis', $statut)
            ];
        }
        
        // Action spéciale pour convertir en commande
        if (in_array($devis->getStatut(), ['accepte', 'signe', 'acompte_regle'])) {
            $actions[] = [
                'action' => 'convert_to_commande',
                'label' => 'Convertir en commande',
                'css_class' => 'btn-success'
            ];
        }
        
        return $actions;
    }

    /**
     * Obtenir les actions possibles pour une commande
     */
    public function getCommandeActions(Commande $commande): array
    {
        $actions = [];
        $allowedTransitions = $this->getCommandeAllowedTransitions($commande->getStatut());
        
        foreach ($allowedTransitions as $statut) {
            $actions[] = [
                'action' => $statut,
                'label' => $this->getActionLabel('commande', $statut),
                'css_class' => $this->getActionCssClass('commande', $statut)
            ];
        }
        
        // Action spéciale pour convertir en facture
        if (in_array($commande->getStatut(), ['expediee', 'livree'])) {
            $actions[] = [
                'action' => 'convert_to_facture',
                'label' => 'Créer une facture',
                'css_class' => 'btn-info'
            ];
        }
        
        return $actions;
    }

    /**
     * Obtenir les actions possibles pour une facture
     */
    public function getFactureActions(Facture $facture): array
    {
        $actions = [];
        $allowedTransitions = $this->getFactureAllowedTransitions($facture->getStatut());
        
        foreach ($allowedTransitions as $statut) {
            $actions[] = [
                'action' => $statut,
                'label' => $this->getActionLabel('facture', $statut),
                'css_class' => $this->getActionCssClass('facture', $statut)
            ];
        }
        
        return $actions;
    }

    private function getActionLabel(string $entity, string $action): string
    {
        return match("$entity.$action") {
            'devis.envoye' => 'Envoyer',
            'devis.accepte' => 'Accepter',
            'devis.refuse' => 'Refuser',
            'devis.annule' => 'Annuler',
            'devis.converti' => 'Marquer comme converti',
            
            'commande.confirmee' => 'Confirmer',
            'commande.en_production' => 'Lancer la production',
            'commande.expediee' => 'Marquer comme expédiée',
            'commande.livree' => 'Marquer comme livrée',
            'commande.annulee' => 'Annuler',
            
            'facture.envoyee' => 'Envoyer',
            'facture.en_relance' => 'Relancer',
            'facture.payee' => 'Marquer comme payée',
            'facture.en_litige' => 'Marquer en litige',
            'facture.annulee' => 'Annuler',
            'facture.archivee' => 'Archiver',
            
            default => ucfirst($action)
        };
    }

    private function getActionCssClass(string $entity, string $action): string
    {
        return match($action) {
            'envoye', 'envoyee', 'confirmee' => 'btn-primary',
            'accepte', 'livree', 'payee' => 'btn-success',
            'en_production', 'expediee', 'en_relance' => 'btn-warning',
            'refuse', 'annule', 'annulee', 'en_litige' => 'btn-danger',
            'archivee' => 'btn-secondary',
            default => 'btn-outline-primary'
        };
    }
}