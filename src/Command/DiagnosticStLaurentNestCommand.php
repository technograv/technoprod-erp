<?php

namespace App\Command;

use App\Entity\Secteur;
use App\Entity\DivisionAdministrative;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'debug:saint-laurent-neste',
    description: 'Diagnostic du problème Saint-Laurent-de-Neste dans le secteur Plateau de Lannemezan'
)]
class DiagnosticStLaurentNestCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('DIAGNOSTIC PROBLÈME SAINT-LAURENT-DE-NESTE');
        
        // 1. Vérifier Saint-Laurent-de-Neste
        $io->section('1. Information sur Saint-Laurent-de-Neste');
        
        $stLaurent = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(DivisionAdministrative::class, 'd')
            ->where('d.nomCommune = :nom')
            ->setParameter('nom', 'Saint-Laurent-de-Neste')
            ->getQuery()
            ->getOneOrNullResult();
        
        if ($stLaurent) {
            $info = [
                'ID' => $stLaurent->getId(),
                'Code INSEE' => $stLaurent->getCodeInseeCommune(),
                'Code postal' => $stLaurent->getCodePostal(),
                'Département' => $stLaurent->getNomDepartement() . ' (' . $stLaurent->getCodeDepartement() . ')',
                'EPCI' => $stLaurent->getNomEpci() . ' (' . $stLaurent->getCodeEpci() . ')',
                'Latitude' => $stLaurent->getLatitude(),
                'Longitude' => $stLaurent->getLongitude()
            ];
            $io->definitionList($info);
        } else {
            $io->error('Saint-Laurent-de-Neste non trouvé dans la base');
            return Command::FAILURE;
        }
        
        // 2. Analyser le secteur Plateau de Lannemezan
        $io->section('2. Secteur Plateau de Lannemezan');
        
        $secteurPlateau = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Secteur::class, 's')
            ->where('s.nomSecteur LIKE :nom')
            ->setParameter('nom', '%Plateau de Lannemezan%')
            ->getQuery()
            ->getOneOrNullResult();
        
        if (!$secteurPlateau) {
            $io->error('Secteur Plateau de Lannemezan non trouvé');
            return Command::FAILURE;
        }
        
        $io->success('Secteur trouvé: ' . $secteurPlateau->getNomSecteur() . ' (ID: ' . $secteurPlateau->getId() . ')');
        
        // 3. Analyser les attributions
        $io->section('3. Attributions du secteur');
        
        foreach ($secteurPlateau->getAttributions() as $attribution) {
            $division = $attribution->getDivisionAdministrative();
            $io->note('Attribution #' . $attribution->getId());
            
            $attributionInfo = [
                'Type' => $attribution->getTypeCritere(),
                'Valeur' => $attribution->getValeurCritere(),
                'Division' => $division ? $division->getNomCommune() ?: 'N/A' : 'Aucune',
                'EPCI' => $division ? $division->getNomEpci() : 'N/A',
                'Code EPCI' => $division ? $division->getCodeEpci() : 'N/A',
                'Coordonnées' => $division && $division->getLatitude() ? 
                    $division->getLatitude() . ', ' . $division->getLongitude() : 'N/A'
            ];
            $io->definitionList($attributionInfo);
        }
        
        // 4. Analyser l'EPCI CC du Plateau de Lannemezan
        $io->section('4. Communes de l\'EPCI "CC du Plateau de Lannemezan"');
        
        $communesPlateau = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(DivisionAdministrative::class, 'd')
            ->where('d.codeEpci = :epci')
            ->setParameter('epci', '200070787')
            ->orderBy('d.nomCommune')
            ->getQuery()
            ->getResult();
        
        $io->info('Nombre de communes dans cet EPCI: ' . count($communesPlateau));
        
        $communesNames = [];
        $stLaurentTrouve = false;
        foreach ($communesPlateau as $commune) {
            $communesNames[] = $commune->getNomCommune();
            if ($commune->getNomCommune() === 'Saint-Laurent-de-Neste') {
                $stLaurentTrouve = true;
            }
        }
        
        if ($stLaurentTrouve) {
            $io->error('Saint-Laurent-de-Neste EST dans l\'EPCI CC du Plateau de Lannemezan !');
        } else {
            $io->success('Saint-Laurent-de-Neste N\'EST PAS dans l\'EPCI CC du Plateau de Lannemezan');
        }
        
        $io->listing(array_slice($communesNames, 0, 10));
        if (count($communesNames) > 10) {
            $io->info('... et ' . (count($communesNames) - 10) . ' autres communes');
        }
        
        // 5. Analyser l'EPCI CC Neste Barousse
        $io->section('5. Communes de l\'EPCI "CC Neste Barousse"');
        
        $communesNeste = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(DivisionAdministrative::class, 'd')
            ->where('d.codeEpci = :epci')
            ->setParameter('epci', '200070829')
            ->orderBy('d.nomCommune')
            ->getQuery()
            ->getResult();
        
        $io->info('Nombre de communes dans cet EPCI: ' . count($communesNeste));
        
        $communesNamesNeste = [];
        foreach ($communesNeste as $commune) {
            $communesNamesNeste[] = $commune->getNomCommune();
        }
        
        $io->listing($communesNamesNeste);
        
        // 6. Simulation du problème dans getCoordonneesPourEntite
        $io->section('6. Simulation du bug dans getCoordonneesPourEntite');
        
        $io->warning('Bug identifié: dans getCoordonneesPourEntite(), ligne 990:');
        $io->text('$appartientAEntite = true; // Simplifié pour l\'instant');
        $io->error('Cela fait que TOUTES les communes assignées au secteur sont considérées comme appartenant à n\'importe quel EPCI !');
        
        $io->info('Conséquence:');
        $io->text('- Le secteur "Plateau de Lannemezan" a des attributions EPCI');
        $io->text('- L\'algorithme hiérarchique privilégie les EPCI');
        $io->text('- Pour calculer le centre de l\'EPCI, il appelle getCoordonneesPourEntite()');
        $io->text('- Cette fonction retourne TOUTES les communes du secteur au lieu de filtrer par EPCI');
        $io->text('- Si Saint-Laurent-de-Neste est assigné au secteur par une autre logique...');
        $io->text('- ... il sera inclus dans le calcul du centre géographique !');
        
        // 7. Vérifier comment Saint-Laurent-de-Neste pourrait être assigné au secteur
        $io->section('7. Recherche de l\'assignation de Saint-Laurent-de-Neste au secteur');
        
        // Simuler l'algorithme d'assignation des communes
        $this->simulerAssignationCommune($io, $stLaurent, $secteurPlateau);
        
        return Command::SUCCESS;
    }
    
    private function simulerAssignationCommune(SymfonyStyle $io, DivisionAdministrative $commune, Secteur $secteur): void
    {
        $io->info('Simulation de l\'assignation de ' . $commune->getNomCommune() . ' au secteur ' . $secteur->getNomSecteur());
        
        $assignee = false;
        $raisons = [];
        
        foreach ($secteur->getAttributions() as $attribution) {
            $division = $attribution->getDivisionAdministrative();
            if (!$division) continue;
            
            $match = false;
            $type = $attribution->getTypeCritere();
            
            switch ($type) {
                case 'commune':
                    if ($commune->getCodeInseeCommune() === $division->getCodeInseeCommune()) {
                        $match = true;
                        $raisons[] = "Commune directe: " . $commune->getNomCommune();
                    }
                    break;
                    
                case 'code_postal':
                    if ($commune->getCodePostal() === $division->getCodePostal()) {
                        $match = true;
                        $raisons[] = "Code postal: " . $commune->getCodePostal() . " (division: " . $division->getNomCommune() . ")";
                    }
                    break;
                    
                case 'epci':
                    if ($commune->getCodeEpci() === $division->getCodeEpci()) {
                        $match = true;
                        $raisons[] = "EPCI: " . $commune->getNomEpci() . " (division: " . $division->getNomCommune() . ")";
                    }
                    break;
                    
                case 'departement':
                    if ($commune->getCodeDepartement() === $division->getCodeDepartement()) {
                        $match = true;
                        $raisons[] = "Département: " . $commune->getNomDepartement() . " (division: " . $division->getNomCommune() . ")";
                    }
                    break;
            }
            
            if ($match) {
                $assignee = true;
            }
        }
        
        if ($assignee) {
            $io->error($commune->getNomCommune() . ' SERAIT ASSIGNÉ au secteur pour les raisons suivantes:');
            $io->listing($raisons);
        } else {
            $io->success($commune->getNomCommune() . ' ne serait PAS assigné au secteur');
        }
    }
}