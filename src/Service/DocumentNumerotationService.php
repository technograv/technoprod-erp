<?php

namespace App\Service;

use App\Entity\DocumentNumerotation;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion de la numérotation automatique des documents
 * 
 * Gère les compteurs pour les différents types de documents :
 * - Devis (DEV)
 * - Factures (FACT) 
 * - Bons de commande (BC)
 * - Etc.
 */
class DocumentNumerotationService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Génère le prochain numéro pour un type de document donné
     * 
     * @param string $typeDocument Type du document (devis, facture, etc.)
     * @param string $prefixe Préfixe à utiliser (DEV, FACT, etc.)
     * @return string Le numéro complet généré
     */
    public function genererProchainNumero(string $typeDocument, string $prefixe = null): string
    {
        // Récupérer ou créer l'entité de numérotation pour ce type
        $numerotation = $this->entityManager->getRepository(DocumentNumerotation::class)
            ->findOneBy(['typeDocument' => $typeDocument]);

        if (!$numerotation) {
            $numerotation = new DocumentNumerotation();
            $numerotation->setTypeDocument($typeDocument);
            $numerotation->setPrefixe($prefixe ?? strtoupper($typeDocument));
            $numerotation->setCompteur(0);
            $this->entityManager->persist($numerotation);
        }

        // Incrémenter le compteur
        $nouveauCompteur = $numerotation->getCompteur() + 1;
        $numerotation->setCompteur($nouveauCompteur);

        // Générer le numéro avec format : PREFIXE-YYYY-NNNN
        $annee = date('Y');
        $numeroFormate = sprintf(
            '%s-%s-%04d',
            $numerotation->getPrefixe(),
            $annee,
            $nouveauCompteur
        );

        $numerotation->setDernierNumero($numeroFormate);
        $numerotation->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $numeroFormate;
    }

    /**
     * Réinitialise le compteur pour un type de document (utilisé en début d'année)
     * 
     * @param string $typeDocument
     * @return bool
     */
    public function reinitialiserCompteur(string $typeDocument): bool
    {
        $numerotation = $this->entityManager->getRepository(DocumentNumerotation::class)
            ->findOneBy(['typeDocument' => $typeDocument]);

        if (!$numerotation) {
            return false;
        }

        $numerotation->setCompteur(0);
        $numerotation->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return true;
    }

    /**
     * Obtient le prochain numéro qui sera généré (sans incrémenter)
     * 
     * @param string $typeDocument
     * @param string $prefixe
     * @return string
     */
    public function previewProchainNumero(string $typeDocument, string $prefixe = null): string
    {
        $numerotation = $this->entityManager->getRepository(DocumentNumerotation::class)
            ->findOneBy(['typeDocument' => $typeDocument]);

        $compteur = $numerotation ? $numerotation->getCompteur() + 1 : 1;
        $prefixeUtilise = $numerotation ? $numerotation->getPrefixe() : ($prefixe ?? strtoupper($typeDocument));
        
        $annee = date('Y');
        return sprintf('%s-%s-%04d', $prefixeUtilise, $annee, $compteur);
    }

    /**
     * Alias pour genererProchainNumero() pour compatibilité
     * 
     * @param string $typeDocument Type du document
     * @param string $prefixe Préfixe à utiliser
     * @return string Le numéro complet généré
     */
    public function getProchainNumero(string $typeDocument, string $prefixe = null): string
    {
        return $this->genererProchainNumero($typeDocument, $prefixe);
    }
}