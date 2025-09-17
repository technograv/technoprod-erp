<?php

namespace App\Service\Admin;

use App\Entity\User;
use App\Entity\Societe;
use App\Entity\ModeReglement;
use App\Entity\ModePaiement;
use App\Entity\Banque;
use App\Entity\Tag;
use App\Entity\TauxTVA;
use App\Entity\Unite;
use App\Entity\Civilite;
use App\Entity\FraisPort;
use App\Entity\Transporteur;
use App\Entity\MethodeExpedition;
use App\Entity\ModeleDocument;
use App\Entity\DivisionAdministrative;
use App\Entity\TypeSecteur;
use App\Entity\AttributionSecteur;
use App\Entity\GroupeUtilisateur;
use App\Entity\Alerte;
use App\Entity\Secteur;
use App\Service\Admin\Interfaces\AdminDashboardServiceInterface;
use App\Service\DashboardService;
use App\Service\TenantService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service spécialisé pour le dashboard d'administration
 * Complète le DashboardService de base avec les statistiques spécifiques à l'admin
 */
class AdminDashboardService implements AdminDashboardServiceInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DashboardService $baseDashboardService,
        private TenantService $tenantService,
        private CacheInterface $dashboardCache
    ) {
    }

    /**
     * Retourne les données complètes du dashboard admin
     */
    public function getAdminDashboardData(): array
    {
        $currentSociete = $this->tenantService->getCurrentSociete();
        $isSocieteMere = $currentSociete ? $currentSociete->isMere() : true;

        // Récupération des statistiques de base via le service existant
        $coreStats = $this->baseDashboardService->getAdminDashboardStats();
        
        // Statistiques supplémentaires spécifiques à l'admin
        $additionalStats = $this->getAdditionalAdminStats();
        
        // Commerciaux avec secteurs ou rôle commercial
        $commerciaux = $this->getCommerciaux();
        
        // Secteurs pour la carte
        $secteurs = $this->getSecteurs();

        return [
            'stats' => array_merge($coreStats, $additionalStats),
            'commerciaux' => $commerciaux,
            'secteurs' => $secteurs,
            'current_societe' => $currentSociete,
            'is_societe_mere' => $isSocieteMere,
            'signature_entreprise' => $currentSociete ? "--\n{$currentSociete->getNom()}\n{$currentSociete->getTelephone()}\n{$currentSociete->getEmail()}" : '',
        ];
    }

    /**
     * Statistiques additionnelles pour l'admin (moins fréquentes)
     */
    private function getAdditionalAdminStats(): array
    {
        return $this->dashboardCache->get('admin_additional_stats', function (ItemInterface $item) {
            $item->expiresAfter(600); // 10 minutes (moins fréquent)

            $sql = '
                SELECT 
                    (SELECT COUNT(*) FROM mode_reglement) as modes_reglement,
                    (SELECT COUNT(*) FROM mode_paiement) as modes_paiement,
                    (SELECT COUNT(*) FROM banque) as banques,
                    (SELECT COUNT(*) FROM tag) as tags,
                    (SELECT COUNT(*) FROM taux_tva) as taux_tva,
                    (SELECT COUNT(*) FROM unite) as unites,
                    (SELECT COUNT(*) FROM civilite) as civilites,
                    (SELECT COUNT(*) FROM frais_port) as frais_port,
                    (SELECT COUNT(*) FROM transporteur) as transporteurs,
                    (SELECT COUNT(*) FROM methode_expedition) as methodes_expedition,
                    (SELECT COUNT(*) FROM modele_document) as modeles_document,
                    (SELECT COUNT(*) FROM division_administrative WHERE actif = true) as divisions_administratives,
                    (SELECT COUNT(*) FROM type_secteur WHERE actif = true) as types_secteur,
                    (SELECT COUNT(*) FROM attribution_secteur) as attributions_secteur,
                    (SELECT COUNT(*) FROM societe WHERE type = \'mere\') as societes_meres,
                    (SELECT COUNT(*) FROM societe WHERE type = \'fille\') as societes_filles,
                    (SELECT COUNT(*) FROM groupe_utilisateur) as groupes_utilisateurs,
                    (SELECT COUNT(*) FROM alerte) as alertes_total,
                    (SELECT COUNT(*) FROM alerte WHERE is_active = true) as alertes_actives,
                    (SELECT COUNT(*) FROM groupe_utilisateur WHERE actif = true) as groupes_actifs
            ';

            $result = $this->entityManager->getConnection()->fetchAssociative($sql);
            
            return [
                'modes_reglement' => (int)$result['modes_reglement'],
                'modes_paiement' => (int)$result['modes_paiement'],
                'banques' => (int)$result['banques'],
                'tags' => (int)$result['tags'],
                'taux_tva' => (int)$result['taux_tva'],
                'unites' => (int)$result['unites'],
                'civilites' => (int)$result['civilites'],
                'frais_port' => (int)$result['frais_port'],
                'transporteurs' => (int)$result['transporteurs'],
                'methodes_expedition' => (int)$result['methodes_expedition'],
                'modeles_document' => (int)$result['modeles_document'],
                'divisions_administratives' => (int)$result['divisions_administratives'],
                'types_secteur' => (int)$result['types_secteur'],
                'attributions_secteur' => (int)$result['attributions_secteur'],
                'societes_meres' => (int)$result['societes_meres'],
                'societes_filles' => (int)$result['societes_filles'],
                'groupes_utilisateurs' => (int)$result['groupes_utilisateurs'],
                'alertes_total' => (int)$result['alertes_total'],
                'alertes_actives' => (int)$result['alertes_actives'],
                'groupes_actifs' => (int)$result['groupes_actifs'],
            ];
        });
    }

    /**
     * Récupère la liste des commerciaux (utilisateurs avec secteurs ou rôle COMMERCIAL)
     */
    private function getCommerciaux(): array
    {
        return $this->dashboardCache->get('admin_commerciaux_list', function (ItemInterface $item) {
            $item->expiresAfter(300); // 5 minutes

            // Approche 1: Récupérer les utilisateurs avec des secteurs
            $commerciauxAvecSecteurs = $this->entityManager->getRepository(User::class)->createQueryBuilder('u')
                ->innerJoin('u.secteurs', 's')
                ->where('u.isActive = true')
                ->getQuery()
                ->getResult();
                
            // Approche 2: Récupérer via SQL natif pour les rôles JSON
            $commerciauxAvecRole = $this->entityManager->getConnection()->executeQuery(
                'SELECT u.* FROM "user" u WHERE u.is_active = true AND CAST(u.roles AS TEXT) LIKE ?',
                ['%ROLE_COMMERCIAL%']
            )->fetchAllAssociative();
            
            // Convertir les résultats SQL en entités User
            $commerciauxEntites = [];
            foreach ($commerciauxAvecRole as $userData) {
                $commerciauxEntites[] = $this->entityManager->getRepository(User::class)->find($userData['id']);
            }
            
            // Fusionner les deux listes et supprimer les doublons
            $commerciaux = [];
            $commerciauxIds = [];
            
            foreach (array_merge($commerciauxAvecSecteurs, $commerciauxEntites) as $commercial) {
                if ($commercial && !in_array($commercial->getId(), $commerciauxIds)) {
                    // Retourner les entités complètes pour que le template ait accès à toutes les propriétés
                    $commerciaux[] = $commercial;
                    $commerciauxIds[] = $commercial->getId();
                }
            }
            
            // Trier par nom
            usort($commerciaux, function($a, $b) {
                return strcasecmp($a->getNom(), $b->getNom());
            });

            return $commerciaux;
        });
    }

    /**
     * Récupère la liste des secteurs pour l'affichage admin
     */
    private function getSecteurs(): array
    {
        return $this->dashboardCache->get('admin_secteurs_list', function (ItemInterface $item) {
            $item->expiresAfter(600); // 10 minutes

            // Retourner les entités complètes pour que le template ait accès à toutes les propriétés
            return $this->entityManager->getRepository(Secteur::class)->findBy([], ['nomSecteur' => 'ASC']);
        });
    }

    /**
     * Invalide le cache admin
     */
    public function invalidateAdminCache(): void
    {
        $this->dashboardCache->delete('admin_additional_stats');
        $this->dashboardCache->delete('admin_commerciaux_list');
        $this->dashboardCache->delete('admin_secteurs_list');
        $this->baseDashboardService->invalidateAdminCache();
    }

    /**
     * Force la mise à jour du cache des secteurs
     */
    public function refreshSecteursCache(): void
    {
        $this->dashboardCache->delete('admin_secteurs_list');
    }

    /**
     * Force la mise à jour du cache des commerciaux
     */
    public function refreshCommerciauxCache(): void
    {
        $this->dashboardCache->delete('admin_commerciaux_list');
    }
}