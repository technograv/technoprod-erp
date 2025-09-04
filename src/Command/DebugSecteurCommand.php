<?php

namespace App\Command;

use App\Entity\Secteur;
use App\Entity\AttributionSecteur;
use App\Entity\DivisionAdministrative;
use App\Service\CommuneGeometryCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-secteur',
    description: 'Debug des attributions et positionnement d\'un secteur spécifique'
)]
class DebugSecteurCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommuneGeometryCacheService $cacheService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('nom_secteur', InputArgument::REQUIRED, 'Nom du secteur à analyser');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $nomSecteur = $input->getArgument('nom_secteur');

        $io->title("🔍 Analyse du secteur : $nomSecteur");

        // 1. Récupérer le secteur
        $secteur = $this->entityManager->getRepository(Secteur::class)
            ->createQueryBuilder('s')
            ->where('s.nomSecteur LIKE :nom')
            ->setParameter('nom', '%' . $nomSecteur . '%')
            ->getQuery()
            ->getOneOrNullResult();

        if (!$secteur) {
            $io->error("Secteur '$nomSecteur' non trouvé");
            return Command::FAILURE;
        }

        $io->section("📋 Informations du secteur");
        $io->table(
            ['Propriété', 'Valeur'],
            [
                ['ID', $secteur->getId()],
                ['Nom', $secteur->getNomSecteur()],
                ['Actif', $secteur->getIsActive() ? '✅' : '❌'],
                ['Couleur', $secteur->getCouleurHex()],
                ['Commercial', $secteur->getCommercial() ? $secteur->getCommercial()->getPrenom() . ' ' . $secteur->getCommercial()->getNom() : 'Aucun'],
            ]
        );

        // 2. Analyser les attributions
        $io->section("🎯 Attributions du secteur");
        $attributions = $secteur->getAttributions();
        
        if ($attributions->isEmpty()) {
            $io->warning("Aucune attribution trouvée pour ce secteur");
            return Command::SUCCESS;
        }

        $attributionsData = [];
        $communesSecteur = [];
        
        // Traitement hiérarchique comme dans le code original
        $ordreTraitement = ['commune', 'code_postal', 'epci', 'departement', 'region'];
        $attributionsParType = [];
        
        foreach ($attributions as $attribution) {
            $type = $attribution->getTypeCritere();
            if (!isset($attributionsParType[$type])) {
                $attributionsParType[$type] = [];
            }
            $attributionsParType[$type][] = $attribution;
            
            $division = $attribution->getDivisionAdministrative();
            
            // Déterminer le nom selon le type
            $nomDivision = 'N/A';
            if ($division) {
                switch ($type) {
                    case 'commune':
                        $nomDivision = $division->getNomCommune() ?: 'N/A';
                        break;
                    case 'epci':
                        $nomDivision = $division->getNomEpci() ?: 'N/A';
                        break;
                    case 'departement':
                        $nomDivision = $division->getNomDepartement() ?: 'N/A';
                        break;
                    case 'region':
                        $nomDivision = $division->getNomRegion() ?: 'N/A';
                        break;
                    default:
                        $nomDivision = $division->getNomCommune() ?: $division->getNomEpci() ?: $division->getNomDepartement() ?: 'N/A';
                }
            }
            
            $attributionsData[] = [
                $attribution->getId(),
                $type,
                $attribution->getValeurCritere(),
                $nomDivision,
                $division ? ($division->getCodeInseeCommune() ?? $division->getCodeEpci() ?? $division->getCodeDepartement()) : 'N/A'
            ];
        }
        
        $io->table(
            ['ID', 'Type', 'Valeur', 'Nom Division', 'Code'],
            $attributionsData
        );

        // 3. Simuler l'assignation hiérarchique
        $io->section("🔄 Simulation assignation hiérarchique");
        $communeVsSecteur = [];
        
        foreach ($ordreTraitement as $typeActuel) {
            if (!isset($attributionsParType[$typeActuel])) continue;
            
            $io->writeln("📍 Phase $typeActuel: " . count($attributionsParType[$typeActuel]) . " attribution(s)");
            
            foreach ($attributionsParType[$typeActuel] as $attribution) {
                $division = $attribution->getDivisionAdministrative();
                if (!$division) continue;
                
                // Récupérer toutes les communes de ce type
                $communesDuType = $this->getCommunesPourType($typeActuel, $division);
                
                $nouvellesCommunes = 0;
                foreach ($communesDuType as $commune) {
                    $codeInsee = $commune['codeInseeCommune'];
                    if (!isset($communeVsSecteur[$codeInsee])) {
                        $communeVsSecteur[$codeInsee] = [
                            'secteur_id' => $secteur->getId(),
                            'nom_commune' => $commune['nomCommune'],
                            'type_attribution' => $typeActuel,
                            'valeur_critere' => $attribution->getValeurCritere()
                        ];
                        $nouvellesCommunes++;
                    }
                }
                
                $io->writeln("   → $typeActuel '{$attribution->getValeurCritere()}': $nouvellesCommunes nouvelles communes");
            }
        }

        // 4. Afficher toutes les communes assignées
        $io->section("🗺️ Communes assignées au secteur (" . count($communeVsSecteur) . " total)");
        
        if (count($communeVsSecteur) > 0) {
            // Limiter l'affichage aux 20 premières communes
            $communesAffichage = array_slice($communeVsSecteur, 0, 20);
            $communesData = [];
            
            foreach ($communesAffichage as $codeInsee => $data) {
                $communesData[] = [
                    $codeInsee,
                    $data['nom_commune'],
                    $data['type_attribution'],
                    $data['valeur_critere']
                ];
            }
            
            $io->table(
                ['Code INSEE', 'Commune', 'Type Attribution', 'Valeur Critère'],
                $communesData
            );
            
            if (count($communeVsSecteur) > 20) {
                $io->note("... et " . (count($communeVsSecteur) - 20) . " autres communes");
            }
        }

        // 5. Vérifier Saint Laurent de Neste spécifiquement
        $io->section("🔍 Vérification Saint Laurent de Neste");
        
        $saintLaurent = $this->entityManager->getRepository(DivisionAdministrative::class)
            ->createQueryBuilder('d')
            ->where('d.nomCommune LIKE :nom')
            ->setParameter('nom', '%Saint-Laurent%Neste%')
            ->getQuery()
            ->getOneOrNullResult();
            
        if ($saintLaurent) {
            $io->table(
                ['Propriété', 'Valeur'],
                [
                    ['Code INSEE', $saintLaurent->getCodeInseeCommune()],
                    ['Nom', $saintLaurent->getNomCommune()],
                    ['EPCI', $saintLaurent->getCodeEpci() . ' - ' . $saintLaurent->getNomEpci()],
                    ['Département', $saintLaurent->getCodeDepartement()],
                    ['Assignée au secteur?', isset($communeVsSecteur[$saintLaurent->getCodeInseeCommune()]) ? '✅ OUI' : '❌ NON']
                ]
            );
            
            if (isset($communeVsSecteur[$saintLaurent->getCodeInseeCommune()])) {
                $assignation = $communeVsSecteur[$saintLaurent->getCodeInseeCommune()];
                $io->warning("Saint Laurent de Neste est assignée via: {$assignation['type_attribution']} = {$assignation['valeur_critere']}");
            }
        } else {
            $io->error("Saint Laurent de Neste non trouvée en base de données");
        }

        // 6. Test du cache géométries
        $io->section("💾 Test cache géométries");
        $stats = $this->cacheService->getCacheStats();
        
        $io->table(
            ['Métrique', 'Valeur'],
            [
                ['Total entrées', $stats['total']],
                ['Valides', $stats['valid']],
                ['Invalides', $stats['invalid']],
                ['Expirées', $stats['expired']],
                ['Taux couverture', $stats['coverage_rate'] . '%']
            ]
        );

        $io->success("Analyse terminée");
        return Command::SUCCESS;
    }

    private function getCommunesPourType(string $type, $division): array
    {
        switch ($type) {
            case 'commune':
                return [[
                    'codeInseeCommune' => $division->getCodeInseeCommune(),
                    'nomCommune' => $division->getNomCommune()
                ]];
                
            case 'code_postal':
                return $this->entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codePostal = :codePostal 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codePostal', $division->getCodePostal())
                ->getResult();
                
            case 'epci':
                return $this->entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codeEpci = :codeEpci 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codeEpci', $division->getCodeEpci())
                ->getResult();
                
            case 'departement':
                return $this->entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codeDepartement = :codeDepartement 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codeDepartement', $division->getCodeDepartement())
                ->getResult();
                
            case 'region':
                return $this->entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codeRegion = :codeRegion 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codeRegion', $division->getCodeRegion())
                ->getResult();
                
            default:
                return [];
        }
    }
}