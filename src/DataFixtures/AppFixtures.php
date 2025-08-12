<?php

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\Secteur;
use App\Entity\Adresse;
use App\Entity\Contact;
use App\Entity\User;
use App\Entity\Produit;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Créer des utilisateurs commerciaux
        $commercial1 = new User();
        $commercial1->setEmail('commercial1@technoprod.com');
        $commercial1->setNom('Martin');
        $commercial1->setPrenom('Jean');
        $commercial1->setRoles(['ROLE_COMMERCIAL']);
        $commercial1->setActive(true);
        $commercial1->setPassword($this->passwordHasher->hashPassword($commercial1, 'password'));
        $manager->persist($commercial1);

        $commercial2 = new User();
        $commercial2->setEmail('commercial2@technoprod.com');
        $commercial2->setNom('Dupont');
        $commercial2->setPrenom('Marie');
        $commercial2->setRoles(['ROLE_COMMERCIAL']);
        $commercial2->setActive(true);
        $commercial2->setPassword($this->passwordHasher->hashPassword($commercial2, 'password'));
        $manager->persist($commercial2);

        // Créer des secteurs
        $secteur1 = new Secteur();
        $secteur1->setNomSecteur('Centre-ville');
        $secteur1->setCommercial($commercial1);
        $manager->persist($secteur1);

        $secteur2 = new Secteur();
        $secteur2->setNomSecteur('Zone industrielle');
        $secteur2->setCommercial($commercial2);
        $manager->persist($secteur2);

        // Créer des clients
        $client1 = new Client();
        $client1->setNomEntreprise('Entreprise ABC');
        $client1->setSiret('12345678901234');
        $client1->setCodeClient('CLI001');
        $client1->setCommercial($commercial1);
        $client1->setSecteur($secteur1);
        $manager->persist($client1);

        $client2 = new Client();
        $client2->setNomEntreprise('Société XYZ');
        $client2->setSiret('98765432109876');
        $client2->setCodeClient('CLI002');
        $client2->setCommercial($commercial2);
        $client2->setSecteur($secteur2);
        $manager->persist($client2);

        $client3 = new Client();
        $client3->setNomEntreprise('Imprimerie Moderne');
        $client3->setSiret('11223344556677');
        $client3->setCodeClient('CLI003');
        $client3->setCommercial($commercial1);
        $client3->setSecteur($secteur1);
        $manager->persist($client3);

        // Créer des contacts pour les clients
        $contact1 = new Contact();
        $contact1->setClient($client1);
        $contact1->setNom('Dubois');
        $contact1->setPrenom('Pierre');
        $contact1->setFonction('Directeur');
        $contact1->setEmail('pierre.dubois@abc.com');
        $contact1->setTelephone('05 61 23 45 67');
        $contact1->setTelephoneMobile('06 12 34 56 78');
        $contact1->setDefaut(true);
        $manager->persist($contact1);

        $contact2 = new Contact();
        $contact2->setClient($client2);
        $contact2->setNom('Moreau');
        $contact2->setPrenom('Sophie');
        $contact2->setFonction('Responsable communication');
        $contact2->setEmail('sophie.moreau@xyz.com');
        $contact2->setTelephone('05 62 34 56 78');
        $contact2->setTelephoneMobile('06 23 45 67 89');
        $contact2->setDefaut(true);
        $manager->persist($contact2);

        $contact3 = new Contact();
        $contact3->setClient($client3);
        $contact3->setNom('Bernard');
        $contact3->setPrenom('Michel');
        $contact3->setFonction('Chef d\'atelier');
        $contact3->setEmail('michel.bernard@impmoderne.com');
        $contact3->setTelephone('05 63 45 67 89');
        $contact3->setTelephoneMobile('06 34 56 78 90');
        $contact3->setDefaut(true);
        $manager->persist($contact3);

        // Créer des adresses pour les clients
        $adresse1 = new Adresse();
        $adresse1->setClient($client1);
        $adresse1->setTypeAdresse('Siège social');
        $adresse1->setAdresseLigne1('123 Rue de la République');
        $adresse1->setVille('Toulouse');
        $adresse1->setCodePostal('31000');
        $adresse1->setPays('France');
        $adresse1->setDefaut(true);
        $manager->persist($adresse1);

        $adresse2 = new Adresse();
        $adresse2->setClient($client2);
        $adresse2->setTypeAdresse('Siège social');
        $adresse2->setAdresseLigne1('456 Avenue des Entreprises');
        $adresse2->setVille('Blagnac');
        $adresse2->setCodePostal('31700');
        $adresse2->setPays('France');
        $adresse2->setDefaut(true);
        $manager->persist($adresse2);

        $adresse3 = new Adresse();
        $adresse3->setClient($client3);
        $adresse3->setTypeAdresse('Siège social');
        $adresse3->setAdresseLigne1('789 Zone Industrielle');
        $adresse3->setVille('Colomiers');
        $adresse3->setCodePostal('31770');
        $adresse3->setPays('France');
        $adresse3->setDefaut(true);
        $manager->persist($adresse3);

        // Créer un administrateur
        $admin = new User();
        $admin->setEmail('admin@technoprod.com');
        $admin->setNom('Admin');
        $admin->setPrenom('Système');
        $admin->setRoles(['ROLE_ADMIN', 'ROLE_COMMERCIAL']);
        $admin->setActive(true);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        // Créer des produits de test
        $produit1 = new Produit();
        $produit1->setDesignation('Impression flyers A5');
        $produit1->setDescription('Impression flyers couleur format A5 - 350g/m²');
        $produit1->setReference('FLY-A5-350');
        $produit1->setPrixUnitaireHt('150.00');
        $produit1->setTvaPercent('20.00');
        $manager->persist($produit1);

        $produit2 = new Produit();
        $produit2->setDesignation('Brochure 8 pages');
        $produit2->setDescription('Brochure commerciale 8 pages A4 - couché mat 135g/m²');
        $produit2->setReference('BRO-8P-135');
        $produit2->setPrixUnitaireHt('280.00');
        $produit2->setTvaPercent('20.00');
        $manager->persist($produit2);

        $produit3 = new Produit();
        $produit3->setDesignation('Carte de visite');
        $produit3->setDescription('Carte de visite 85x55mm - pelliculage mat');
        $produit3->setReference('CDV-85x55-PELL');
        $produit3->setPrixUnitaireHt('45.00');
        $produit3->setTvaPercent('20.00');
        $manager->persist($produit3);

        $produit4 = new Produit();
        $produit4->setDesignation('Affiche A3');
        $produit4->setDescription('Affiche couleur format A3 - papier couché brillant 170g/m²');
        $produit4->setReference('AFF-A3-170');
        $produit4->setPrixUnitaireHt('25.00');
        $produit4->setTvaPercent('20.00');
        $manager->persist($produit4);

        $produit5 = new Produit();
        $produit5->setDesignation('Catalogue 16 pages');
        $produit5->setDescription('Catalogue produits 16 pages A4 - dos carré collé');
        $produit5->setReference('CAT-16P-DCC');
        $produit5->setPrixUnitaireHt('420.00');
        $produit5->setTvaPercent('20.00');
        $manager->persist($produit5);

        $manager->flush();
    }
}
