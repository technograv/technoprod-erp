<?php

namespace App\Command;

use App\Entity\Zone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'import:zones',
    description: 'Import postal codes from CSV file',
)]
class ImportZonesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('csv-file', InputArgument::REQUIRED, 'Path to CSV file')
            ->addOption('clear', 'c', InputOption::VALUE_NONE, 'Clear existing zones before import')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of rows to import', 0)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $csvFile = $input->getArgument('csv-file');
        $clear = $input->getOption('clear');
        $limit = (int) $input->getOption('limit');

        if (!file_exists($csvFile)) {
            $io->error("File not found: $csvFile");
            return Command::FAILURE;
        }

        if ($clear) {
            $io->note('Clearing existing zones...');
            $this->entityManager->createQuery('DELETE FROM App\Entity\Zone')->execute();
        }

        $io->note("Importing zones from: $csvFile");
        
        $handle = fopen($csvFile, 'r');
        $header = fgetcsv($handle); // Skip header
        $count = 0;
        $imported = 0;

        while (($data = fgetcsv($handle)) !== false) {
            $count++;
            
            if ($limit > 0 && $count > $limit) {
                break;
            }

            // CSV format: code_postal,ville,departement,region,latitude,longitude
            if (count($data) >= 2) {
                $codePostal = trim($data[0]);
                $ville = trim($data[1]);
                $departement = isset($data[2]) ? trim($data[2]) : null;
                $region = isset($data[3]) ? trim($data[3]) : null;
                $latitude = isset($data[4]) && $data[4] !== '' ? (float) $data[4] : null;
                $longitude = isset($data[5]) && $data[5] !== '' ? (float) $data[5] : null;

                // Check if zone already exists
                $existingZone = $this->entityManager->getRepository(Zone::class)
                    ->findOneBy(['codePostal' => $codePostal, 'ville' => $ville]);

                if (!$existingZone) {
                    $zone = new Zone();
                    $zone->setCodePostal($codePostal);
                    $zone->setVille($ville);
                    $zone->setDepartement($departement);
                    $zone->setRegion($region);
                    $zone->setLatitude($latitude);
                    $zone->setLongitude($longitude);

                    $this->entityManager->persist($zone);
                    $imported++;

                    if ($imported % 100 === 0) {
                        $this->entityManager->flush();
                        $io->progressAdvance($imported);
                    }
                }
            }
        }

        fclose($handle);
        $this->entityManager->flush();

        $io->success("Successfully imported $imported zones from $count rows.");
        return Command::SUCCESS;
    }
}
