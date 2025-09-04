<?php

namespace App\Service;

use App\Repository\UserRepository;
use App\Repository\SecteurRepository;
use App\Repository\ProduitRepository;
use App\Repository\FormeJuridiqueRepository;
use App\Repository\ClientRepository;
use App\Repository\DevisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class DashboardService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private SecteurRepository $secteurRepository,
        private ProduitRepository $produitRepository,
        private FormeJuridiqueRepository $formeJuridiqueRepository,
        private ClientRepository $clientRepository,
        private DevisRepository $devisRepository,
        private CacheInterface $dashboardCache
    ) {
    }

    public function getAdminDashboardStats(): array
    {
        return $this->dashboardCache->get('admin_dashboard_stats', function (ItemInterface $item) {
            $item->expiresAfter(300); // 5 minutes

            // Requête unique consolidée pour toutes les statistiques admin
            $sql = '
                SELECT 
                    (SELECT COUNT(*) FROM "user") as total_users,
                    (SELECT COUNT(*) FROM "user" WHERE is_active = true) as active_users,
                    (SELECT COUNT(*) FROM "user" WHERE CAST(roles AS TEXT) LIKE \'%ROLE_ADMIN%\') as admin_users,
                    (SELECT COUNT(*) FROM secteur) as total_secteurs,
                    (SELECT COUNT(*) FROM secteur) as total_zones,
                    (SELECT COUNT(*) FROM produit) as total_produits,
                    (SELECT COUNT(*) FROM forme_juridique) as total_formes_juridiques,
                    (SELECT COUNT(*) FROM forme_juridique) as active_formes_juridiques,
                    (SELECT COUNT(*) FROM client WHERE statut = \'CLIENT\') as total_clients,
                    (SELECT COUNT(*) FROM client WHERE statut = \'PROSPECT\') as total_prospects,
                    (SELECT COUNT(*) FROM devis WHERE statut = \'SIGNE\') as devis_signes,
                    (SELECT COUNT(*) FROM devis WHERE statut = \'ENVOYE\') as devis_en_cours
            ';

            $result = $this->entityManager->getConnection()->fetchAssociative($sql);
            
            return [
                // Format compatible avec le template existant
                'users' => (int)$result['total_users'],
                'users_actifs' => (int)$result['active_users'],
                'admins' => (int)$result['admin_users'],
                'secteurs' => (int)$result['total_secteurs'],
                'zones' => (int)$result['total_zones'],
                'produits' => (int)$result['total_produits'],
                'formes_juridiques' => (int)$result['total_formes_juridiques'],
                // Données commerciales
                'clients' => (int)$result['total_clients'],
                'prospects' => (int)$result['total_prospects'],
                'devis_signes' => (int)$result['devis_signes'],
                'devis_en_cours' => (int)$result['devis_en_cours'],
                // Conserve aussi le nouveau format pour compatibilité future
                'utilisateurs' => [
                    'total' => (int)$result['total_users'],
                    'actifs' => (int)$result['active_users'],
                    'administrateurs' => (int)$result['admin_users']
                ],
                'commercial' => [
                    'clients' => (int)$result['total_clients'],
                    'prospects' => (int)$result['total_prospects'],
                    'devis_signes' => (int)$result['devis_signes'],
                    'devis_en_cours' => (int)$result['devis_en_cours']
                ]
            ];
        });
    }

    public function getWorkflowDashboardStats(int $userId): array
    {
        $cacheKey = "workflow_dashboard_stats_user_{$userId}";
        
        return $this->dashboardCache->get($cacheKey, function (ItemInterface $item) use ($userId) {
            $item->expiresAfter(300); // 5 minutes

            // Requête consolidée pour les stats du workflow utilisateur
            $sql = '
                SELECT 
                    (SELECT COUNT(*) FROM devis d 
                     JOIN client c ON d.client_id = c.id 
                     JOIN secteur s ON c.secteur_id = s.id 
                     WHERE s.commercial_id = :userId 
                     AND d.statut = \'BROUILLON\') as devis_brouillons,
                    
                    (SELECT COUNT(*) FROM devis d 
                     JOIN client c ON d.client_id = c.id 
                     JOIN secteur s ON c.secteur_id = s.id 
                     WHERE s.commercial_id = :userId 
                     AND d.statut = \'ENVOYE\' 
                     AND d.date_envoi < NOW() - INTERVAL \'7 days\') as devis_relances,
                     
                    (SELECT COUNT(*) FROM client c 
                     JOIN secteur s ON c.secteur_id = s.id 
                     WHERE s.commercial_id = :userId 
                     AND c.statut = \'PROSPECT\' 
                     AND c.date_conversion_client IS NULL) as prospects_actifs,
                     
                    (SELECT COUNT(*) FROM client c 
                     JOIN secteur s ON c.secteur_id = s.id 
                     WHERE s.commercial_id = :userId 
                     AND c.statut = \'CLIENT\') as clients_total
            ';

            $result = $this->entityManager->getConnection()->fetchAssociative($sql, ['userId' => $userId]);
            
            return [
                'devis_brouillons' => (int)$result['devis_brouillons'],
                'devis_relances' => (int)$result['devis_relances'],
                'prospects_actifs' => (int)$result['prospects_actifs'],
                'clients_total' => (int)$result['clients_total']
            ];
        });
    }

    public function getSecteurPerformanceData(int $userId): array
    {
        $cacheKey = "secteur_performance_user_{$userId}";
        
        return $this->dashboardCache->get($cacheKey, function (ItemInterface $item) use ($userId) {
            $item->expiresAfter(600); // 10 minutes
            
            // Requête optimisée pour les données de performance secteur
            $sql = '
                SELECT 
                    s.id,
                    s.nom_secteur as secteur_nom,
                    s.couleur_hex as couleur,
                    COUNT(DISTINCT c.id) as nombre_clients,
                    COUNT(DISTINCT CASE WHEN c.statut = \'PROSPECT\' THEN c.id END) as nombre_prospects,
                    COUNT(DISTINCT d.id) as nombre_devis,
                    COALESCE(SUM(CASE WHEN d.statut = \'SIGNE\' THEN d.total_ttc END), 0) as ca_signe,
                    1 as nombre_zones,
                    s.nom_secteur as zones_noms
                FROM secteur s
                LEFT JOIN client c ON s.id = c.secteur_id
                LEFT JOIN devis d ON c.id = d.client_id
                WHERE s.commercial_id = :userId
                GROUP BY s.id, s.nom_secteur, s.couleur_hex
                ORDER BY ca_signe DESC
            ';

            $results = $this->entityManager->getConnection()->fetchAllAssociative($sql, ['userId' => $userId]);
            
            return [
                'secteurs' => $results,
                'resume' => [
                    'total_ca' => array_sum(array_column($results, 'ca_signe')),
                    'total_devis' => array_sum(array_column($results, 'nombre_devis')),
                    'total_clients' => array_sum(array_column($results, 'nombre_clients'))
                ]
            ];
        });
    }

    public function invalidateUserCache(int $userId): void
    {
        $this->dashboardCache->delete("workflow_dashboard_stats_user_{$userId}");
        $this->dashboardCache->delete("secteur_performance_user_{$userId}");
    }

    public function invalidateAdminCache(): void
    {
        $this->dashboardCache->delete('admin_dashboard_stats');
    }
}