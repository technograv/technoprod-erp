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
     * @param string $prefixe Préfixe du document (DEV, FACT, etc.)
     * @param string $libelle Libellé du type de document
     * @return string Le numéro complet généré
     */
    public function genererProchainNumero(string $prefixe, string $libelle = null): string
    {
        // Récupérer ou créer l'entité de numérotation pour ce préfixe
        $numerotation = $this->entityManager->getRepository(DocumentNumerotation::class)
            ->findOneBy(['prefixe' => $prefixe]);

        if (!$numerotation) {
            $numerotation = new DocumentNumerotation();
            $numerotation->setPrefixe($prefixe);
            $numerotation->setLibelle($libelle ?? $prefixe);
            $numerotation->setCompteur(1);
            $this->entityManager->persist($numerotation);
        }

        // Utiliser la méthode de l'entité pour générer le numéro
        $numeroGenere = $numerotation->genererProchainNumero();

        $this->entityManager->flush();

        return $numeroGenere;
    }

    /**
     * Réinitialise le compteur pour un préfixe de document (utilisé en début d'année)
     * 
     * @param string $prefixe
     * @return bool
     */
    public function reinitialiserCompteur(string $prefixe): bool
    {
        $numerotation = $this->entityManager->getRepository(DocumentNumerotation::class)
            ->findOneBy(['prefixe' => $prefixe]);

        if (!$numerotation) {
            return false;
        }

        $numerotation->setCompteur(1);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Obtient le prochain numéro qui sera généré (sans incrémenter)
     * 
     * @param string $prefixe
     * @return string
     */
    public function previewProchainNumero(string $prefixe): string
    {
        $numerotation = $this->entityManager->getRepository(DocumentNumerotation::class)
            ->findOneBy(['prefixe' => $prefixe]);

        if ($numerotation) {
            return $numerotation->getProchainNumero();
        }

        // Si pas trouvé, retourner le format avec compteur 1
        return $prefixe . str_pad(1, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Définit le compteur pour un préfixe donné
     * 
     * @param string $prefixe
     * @param int $compteur
     * @return bool
     */
    public function setCompteur(string $prefixe, int $compteur): bool
    {
        $numerotation = $this->entityManager->getRepository(DocumentNumerotation::class)
            ->findOneBy(['prefixe' => $prefixe]);

        if (!$numerotation) {
            return false;
        }

        $numerotation->setCompteur($compteur);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Alias pour genererProchainNumero() pour compatibilité
     * 
     * @param string $prefixe Préfixe du document
     * @param string $libelle Libellé du type de document
     * @return string Le numéro complet généré
     */
    public function getProchainNumero(string $prefixe, string $libelle = null): string
    {
        return $this->genererProchainNumero($prefixe, $libelle);
    }
}