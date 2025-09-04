<?php

namespace App\Command;

use App\Entity\Secteur;
use App\Entity\AttributionSecteur;
use App\Entity\DivisionAdministrative;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'debug:lannemezan',
    description: 'Diagnostic du secteur Plateau de Lannemezan et de son positionnement'
)]
class DiagnosticLannemezanCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('DIAGNOSTIC SECTEUR PLATEAU DE LANNEMEZAN');
        
        // 1. Rechercher les secteurs contenant "Lannemezan" ou "Plateau"
        $io->section('1. Recherche des secteurs');
        
        $secteurs = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Secteur::class, 's')
            ->where('s.nomSecteur LIKE :lannemezan OR s.nomSecteur LIKE :plateau')
            ->setParameter('lannemezan', '%Lannemezan%')
            ->setParameter('plateau', '%Plateau%')
            ->getQuery()
            ->getResult();
        
        if (empty($secteurs)) {
            $io->error('Aucun secteur trouvé avec "Lannemezan" ou "Plateau" dans le nom.');
            
            // Recherche plus large
            $io->info('Recherche plus large dans tous les secteurs actifs:');
            $allSecteurs = $this->entityManager->getRepository(Secteur::class)
                ->findBy(['isActive' => true], ['nomSecteur' => 'ASC']);
            
            $secteurNames = [];
            foreach ($allSecteurs as $secteur) {
                $secteurNames[] = $secteur->getNomSecteur() . ' (ID: ' . $secteur->getId() . ')';
            }
            
            $io->listing($secteurNames);
            $io->info('Total secteurs actifs: ' . count($allSecteurs));
        } else {
            foreach ($secteurs as $secteur) {
                $io->success('Secteur trouvé: ' . $secteur->getNomSecteur());
                
                $info = [
                    'ID' => $secteur->getId(),
                    'Commercial' => $secteur->getCommercial() ? 
                        $secteur->getCommercial()->getPrenom() . ' ' . $secteur->getCommercial()->getNom() : 
                        'Aucun',
                    'Couleur' => $secteur->getCouleurHex() ?: 'Aucune',
                    'Actif' => $secteur->isActive() ? 'Oui' : 'Non',
                    'Nombre d\'attributions' => $secteur->getAttributions()->count()
                ];
                
                $io->definitionList($info);
                
                // 2. Analyser les attributions du secteur
                $io->section('2. Attributions du secteur "' . $secteur->getNomSecteur() . '"');
                
                if ($secteur->getAttributions()->isEmpty()) {
                    $io->error('Aucune attribution trouvée pour ce secteur.');
                } else {
                    foreach ($secteur->getAttributions() as $attribution) {
                        $io->note('Attribution #' . $attribution->getId());
                        
                        $attributionInfo = [
                            'Type' => $attribution->getTypeCritere(),
                            'Valeur' => $attribution->getValeurCritere()
                        ];
                        
                        $division = $attribution->getDivisionAdministrative();
                        if ($division) {
                            $attributionInfo = array_merge($attributionInfo, [
                                'Code INSEE' => $division->getCodeInseeCommune() ?: 'N/A',
                                'Nom commune' => $division->getNomCommune() ?: 'N/A',
                                'Code postal' => $division->getCodePostal() ?: 'N/A',
                                'Département' => ($division->getNomDepartement() ?: 'N/A') . ' (' . ($division->getCodeDepartement() ?: 'N/A') . ')',
                                'Région' => $division->getNomRegion() ?: 'N/A',
                                'EPCI' => ($division->getNomEpci() ?: 'N/A') . ' (' . ($division->getCodeEpci() ?: 'N/A') . ')',
                                'Latitude' => $division->getLatitude() ?: 'N/A',
                                'Longitude' => $division->getLongitude() ?: 'N/A'
                            ]);
                        } else {
                            $attributionInfo['Division Administrative'] = 'Aucune division administrative associée';
                        }
                        
                        $io->definitionList($attributionInfo);
                    }
                }
                
                // 3. Simulation du calcul de position hiérarchique
                $io->section('3. Simulation calcul position hiérarchique');
                
                // Analyser les types d'attributions par ordre hiérarchique
                $attributionsParType = [];
                foreach ($secteur->getAttributions() as $attribution) {
                    $type = $attribution->getTypeCritere();
                    if (!isset($attributionsParType[$type])) {
                        $attributionsParType[$type] = [];
                    }
                    $attributionsParType[$type][] = $attribution;
                }
                
                $ordreHierarchique = ['commune', 'code_postal', 'epci', 'departement', 'region'];
                
                $io->info('Analyse hiérarchique des attributions:');
                
                foreach ($ordreHierarchique as $type) {
                    if (isset($attributionsParType[$type])) {
                        $io->writeln(" ✅ Type '$type': " . count($attributionsParType[$type]) . " attribution(s)");
                        foreach ($attributionsParType[$type] as $attribution) {
                            $division = $attribution->getDivisionAdministrative();
                            if ($division && $division->getLatitude() && $division->getLongitude()) {
                                $io->writeln("   - " . $attribution->getValeurCritere() . 
                                     " → Coordonnées: " . $division->getLatitude() . ", " . $division->getLongitude());
                            } else {
                                $io->writeln("   - " . $attribution->getValeurCritere() . " → ❌ Pas de coordonnées");
                            }
                        }
                    } else {
                        $io->writeln(" ❌ Type '$type': aucune attribution");
                    }
                }
                
                // Types non hiérarchiques
                foreach ($attributionsParType as $type => $attributions) {
                    if (!in_array($type, $ordreHierarchique)) {
                        $io->writeln(" 🔍 Type '$type' (non-hiérarchique): " . count($attributions) . " attribution(s)");
                    }
                }
            }
        }
        
        // 4. Rechercher dans les divisions administratives toute référence à "Lannemezan"
        $io->section('4. Recherche dans les divisions administratives');
        
        $divisions = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(DivisionAdministrative::class, 'd')
            ->where('d.nomCommune LIKE :lannemezan OR d.nomEpci LIKE :lannemezan OR d.nomCanton LIKE :lannemezan')
            ->setParameter('lannemezan', '%Lannemezan%')
            ->getQuery()
            ->getResult();
        
        if (empty($divisions)) {
            $io->error('Aucune division administrative trouvée avec "Lannemezan".');
            
            // Recherche de "Plateau"
            $divisionsPlateaux = $this->entityManager->createQueryBuilder()
                ->select('d')
                ->from(DivisionAdministrative::class, 'd')
                ->where('d.nomCommune LIKE :plateau OR d.nomEpci LIKE :plateau OR d.nomCanton LIKE :plateau')
                ->setParameter('plateau', '%Plateau%')
                ->getQuery()
                ->getResult();
            
            if (!empty($divisionsPlateaux)) {
                $io->info('Divisions avec "Plateau" trouvées:');
                $plateauList = [];
                foreach ($divisionsPlateaux as $division) {
                    $plateauList[] = $division->getNomCommune() . ' (' . $division->getCodePostal() . ')';
                }
                $io->listing($plateauList);
            }
        } else {
            foreach ($divisions as $division) {
                $io->success('Division trouvée:');
                
                $divisionInfo = [
                    'Commune' => $division->getNomCommune(),
                    'Code postal' => $division->getCodePostal(),
                    'Code INSEE' => $division->getCodeInseeCommune(),
                    'Département' => $division->getNomDepartement(),
                    'EPCI' => $division->getNomEpci(),
                    'Latitude' => $division->getLatitude() ?: 'N/A',
                    'Longitude' => $division->getLongitude() ?: 'N/A',
                    'Attributions secteurs' => $division->getAttributionsSecteur()->count()
                ];
                
                $io->definitionList($divisionInfo);
                
                if ($division->getAttributionsSecteur()->count() > 0) {
                    $io->info('Secteurs associés:');
                    $secteurList = [];
                    foreach ($division->getAttributionsSecteur() as $attribution) {
                        $secteurList[] = $attribution->getSecteur()->getNomSecteur() . 
                                       ' (Type: ' . $attribution->getTypeCritere() . ')';
                    }
                    $io->listing($secteurList);
                }
            }
        }
        
        // 5. Diagnostic final
        $io->section('5. Diagnostic et recommandations');
        
        if (empty($secteurs)) {
            $io->error('PROBLÈME IDENTIFIÉ: Le secteur "Plateau de Lannemezan" n\'existe pas dans la base.');
            $io->info('SOLUTIONS POSSIBLES:');
            $io->listing([
                'Vérifier l\'orthographe exacte du nom du secteur',
                'Le secteur pourrait être inactif (is_active = false)',
                'Le secteur pourrait avoir un nom différent'
            ]);
        } else {
            foreach ($secteurs as $secteur) {
                if ($secteur->getAttributions()->isEmpty()) {
                    $io->error('PROBLÈME: Le secteur "' . $secteur->getNomSecteur() . '" n\'a aucune attribution.');
                    $io->info('SOLUTION: Ajouter des attributions géographiques au secteur.');
                } else {
                    $hasCoordinates = false;
                    foreach ($secteur->getAttributions() as $attribution) {
                        $division = $attribution->getDivisionAdministrative();
                        if ($division && $division->getLatitude() && $division->getLongitude()) {
                            $hasCoordinates = true;
                            break;
                        }
                    }
                    
                    if (!$hasCoordinates) {
                        $io->error('PROBLÈME: Aucune attribution du secteur n\'a de coordonnées GPS.');
                        $io->info('SOLUTION: Vérifier les coordonnées des divisions administratives.');
                    } else {
                        $io->success('Le secteur semble correctement configuré.');
                        $io->info('VÉRIFICATION RECOMMANDÉE: Analyser l\'algorithme de positionnement dans AdminController.');
                    }
                }
            }
        }
        
        return Command::SUCCESS;
    }
}