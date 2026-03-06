<?php

namespace App\DataFixtures;

use App\Entity\Production\CategoriePoste;
use App\Entity\Production\PosteTravail;
use App\Entity\Production\Nomenclature;
use App\Entity\Production\NomenclatureLigne;
use App\Entity\Production\Gamme;
use App\Entity\Production\GammeOperation;
use App\Entity\Catalogue\ProduitCatalogue;
use App\Entity\Catalogue\OptionProduit;
use App\Entity\Catalogue\ValeurOption;
use App\Entity\Catalogue\RegleCompatibilite;
use App\Entity\Produit;
use App\Entity\Unite;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixtures Phase 2A - Produits catalogue avec nomenclatures et gammes
 *
 * Exemple concret : Enseigne drapeau LED 600x600mm
 * Basé sur les spécifications fournies par l'utilisateur
 */
class Phase2AFixtures extends Fixture
{

    public function load(ObjectManager $manager): void
    {
        echo "Chargement fixtures Phase 2A...\n";

        // 1. Créer les catégories de postes
        $categories = $this->creerCategoriesPostes($manager);

        // 2. Créer les postes de travail (machines)
        $postes = $this->creerPostes($manager, $categories);

        // 3. Créer les produits simples (matières premières)
        [$matieres, $unites] = $this->creerMatieresPremieresEnseignes($manager);
        $manager->flush(); // Flush pour que les unités soient disponibles

        // 4. Créer la nomenclature "Enseigne drapeau"
        $nomenclatureEnseigne = $this->creerNomenclatureEnseigneDrapeau($manager, $matieres, $unites);

        // 5. Créer la gamme "Fabrication enseigne drapeau"
        $gammeEnseigne = $this->creerGammeEnseigneDrapeau($manager, $postes);

        // 6. Créer le produit catalogue "Enseigne drapeau"
        $produitCatalogueEnseigne = $this->creerProduitCatalogueEnseigne(
            $manager,
            $nomenclatureEnseigne,
            $gammeEnseigne
        );

        // 7. Créer les options et règles de compatibilité
        $this->creerOptionsEtRegles($manager, $produitCatalogueEnseigne);

        $manager->flush();

        echo "Fixtures Phase 2A chargées avec succès!\n";
        echo "- Catégories postes : " . count($categories) . "\n";
        echo "- Postes de travail : " . count($postes) . "\n";
        echo "- Matières premières : " . count($matieres) . "\n";
        echo "- Produit catalogue : Enseigne drapeau LED\n";
    }

    private function creerCategoriesPostes(ObjectManager $manager): array
    {
        $categories = [];

        $data = [
            ['IMPRESSION', 'Impression numérique', 'Imprimantes grand format', 'fa-print', '#3498db', 10],
            ['DECOUPE', 'Découpe', 'Machines de découpe', 'fa-cut', '#e74c3c', 20],
            ['MONTAGE', 'Montage', 'Assemblage et montage', 'fa-wrench', '#2ecc71', 30],
            ['FINITION', 'Finition', 'Finitions et détails', 'fa-magic', '#f39c12', 40],
            ['POSE', 'Pose sur site', 'Installation chez le client', 'fa-truck', '#9b59b6', 50],
            ['GRAPHISME', 'Graphisme & PAO', 'Création graphique', 'fa-pen-nib', '#1abc9c', 5]
        ];

        foreach ($data as [$code, $libelle, $description, $icone, $couleur, $ordre]) {
            $categorie = new CategoriePoste();
            $categorie->setCode($code);
            $categorie->setLibelle($libelle);
            $categorie->setDescription($description);
            $categorie->setIcone($icone);
            $categorie->setCouleur($couleur);
            $categorie->setOrdre($ordre);
            $categorie->setActif(true);

            $manager->persist($categorie);
            $categories[$code] = $categorie;
        }

        return $categories;
    }

    private function creerPostes(ObjectManager $manager, array $categories): array
    {
        $postes = [];

        $data = [
            // Code, Libelle, Catégorie, Coût/h, Setup, Nettoyage, Spécifications
            ['IMP-LATEX-1', 'Imprimante HP Latex 360', 'IMPRESSION', 45.00, 15, 10, ['laize' => 1372, 'vitesse_max' => '20m²/h']],
            ['IMP-LATEX-2', 'Imprimante HP Latex 570', 'IMPRESSION', 65.00, 20, 15, ['laize' => 1626, 'vitesse_max' => '30m²/h']],
            ['DEC-CNC-1', 'Découpe CNC Zünd', 'DECOUPE', 55.00, 10, 5, ['surface_max' => '3200x1600', 'epaisseur_max' => 50]],
            ['DEC-LASER-1', 'Découpe Laser CO2', 'DECOUPE', 40.00, 5, 5, ['puissance' => '150W', 'surface' => '1300x900']],
            ['MONT-MAN-1', 'Montage manuel', 'MONTAGE', 35.00, 0, 0, ['equipe' => 1]],
            ['MONT-MAN-2', 'Montage équipe 2 personnes', 'MONTAGE', 70.00, 0, 0, ['equipe' => 2]],
            ['FIN-MAN-1', 'Finition manuelle', 'FINITION', 30.00, 0, 0, []],
            ['GRAPH-PAO-1', 'Poste graphisme PAO', 'GRAPHISME', 40.00, 0, 0, ['logiciels' => 'Adobe CC']]
        ];

        foreach ($data as [$code, $libelle, $catCode, $cout, $setup, $nettoyage, $specs]) {
            $poste = new PosteTravail();
            $poste->setCode($code);
            $poste->setLibelle($libelle);
            $poste->setCategorie($categories[$catCode]);
            $poste->setCoutHoraire($cout);
            $poste->setTempsSetup($setup);
            $poste->setTempsNettoyage($nettoyage);
            $poste->setSpecifications($specs);
            $poste->setNecessiteOperateur(true);
            $poste->setPolyvalent(false);
            $poste->setActif(true);

            $manager->persist($poste);
            $postes[$code] = $poste;
        }

        return $postes;
    }

    private function creerMatieresPremieresEnseignes(ObjectManager $manager): array
    {
        $matieres = [];
        $unites = [];

        // Récupérer ou créer l'unité m²
        $uniteM2 = $manager->getRepository(Unite::class)->findOneBy(['code' => 'm²'])
            ?? $this->creerUnite($manager, 'm²', 'Mètre carré');

        $uniteM = $manager->getRepository(Unite::class)->findOneBy(['code' => 'm'])
            ?? $this->creerUnite($manager, 'm', 'Mètre');

        $unitePce = $manager->getRepository(Unite::class)->findOneBy(['code' => 'pce'])
            ?? $this->creerUnite($manager, 'pce', 'Pièce');

        $unites['m²'] = $uniteM2;
        $unites['m'] = $uniteM;
        $unites['pce'] = $unitePce;

        $data = [
            // Référence, Désignation, Prix achat, Unité
            ['MAT-ALU-5050', 'Profil aluminium 50x50mm', 15.50, $uniteM],
            ['MAT-PMMA-3MM', 'Plaque PMMA opale 3mm', 45.00, $uniteM2],
            ['MAT-LED-5630', 'Bande LED 5630 blanc chaud 24V', 12.50, $uniteM],
            ['MAT-TRANSFO-60W', 'Transformateur LED 60W 24V', 18.00, $unitePce],
            ['MAT-EQUERRE-INOX', 'Équerre de fixation inox', 2.50, $unitePce],
            ['MAT-VIS-INOX', 'Lot visserie inox', 8.50, $unitePce],
            ['MAT-POTENCE-500', 'Potence murale 500mm', 35.00, $unitePce],
            ['MAT-CABLE-2X075', 'Câble électrique 2x0.75mm²', 1.50, $uniteM]
        ];

        foreach ($data as [$reference, $designation, $prix, $unite]) {
            $produit = new Produit();
            $produit->setReference($reference);
            $produit->setDesignation($designation);
            $produit->setType('produit');
            $produit->setPrixAchatHt($prix);
            $produit->setUnite($unite);
            $produit->setActif(true);

            $manager->persist($produit);
            $matieres[$reference] = $produit;
        }

        return [$matieres, $unites];
    }

    private function creerUnite(ObjectManager $manager, string $code, string $nom): Unite
    {
        $unite = new Unite();
        $unite->setCode($code);
        $unite->setNom($nom);
        $unite->setOrdre(99);
        $unite->setActif(true);

        $manager->persist($unite);
        return $unite;
    }

    private function creerNomenclatureEnseigneDrapeau(ObjectManager $manager, array $matieres, array $unites): Nomenclature
    {
        $nomenclature = new Nomenclature();
        $nomenclature->setCode('NOM-ENS-DRAPEAU');
        $nomenclature->setLibelle('Enseigne drapeau LED sur mesure');
        $nomenclature->setDescription('Enseigne double face éclairée LED avec structure aluminium');
        $nomenclature->setVersion('1.0');
        $nomenclature->setStatut(Nomenclature::STATUT_VALIDEE);
        $nomenclature->setActif(true);

        $uniteM2 = $unites['m²'];
        $uniteM = $unites['m'];
        $unitePce = $unites['pce'];

        // Ligne 1: Structure aluminium (formule dynamique)
        $ligne1 = new NomenclatureLigne();
        $ligne1->setNomenclature($nomenclature);
        $ligne1->setOrdre(10);
        $ligne1->setType(NomenclatureLigne::TYPE_MATIERE_PREMIERE);
        $ligne1->setProduitSimple($matieres['MAT-ALU-5050']);
        $ligne1->setDesignation('Cadre aluminium périmètre');
        $ligne1->setQuantiteBase(1.0);
        $ligne1->setFormuleQuantite('(largeur + hauteur) * 2 / 1000'); // Périmètre en mètres
        $ligne1->setTauxChute(10.00); // 10% de chute
        $ligne1->setUniteQuantite($uniteM);
        $ligne1->setObligatoire(true);
        $nomenclature->addLigne($ligne1);

        // Ligne 2: PMMA face avant (formule surface)
        $ligne2 = new NomenclatureLigne();
        $ligne2->setNomenclature($nomenclature);
        $ligne2->setOrdre(20);
        $ligne2->setType(NomenclatureLigne::TYPE_MATIERE_PREMIERE);
        $ligne2->setProduitSimple($matieres['MAT-PMMA-3MM']);
        $ligne2->setDesignation('Face avant PMMA opale');
        $ligne2->setQuantiteBase(1.0);
        $ligne2->setFormuleQuantite('largeur * hauteur / 1000000'); // Surface en m²
        $ligne2->setTauxChute(15.00); // 15% de chute
        $ligne2->setUniteQuantite($uniteM2);
        $ligne2->setObligatoire(true);
        $nomenclature->addLigne($ligne2);

        // Ligne 3: PMMA face arrière (même formule)
        $ligne3 = new NomenclatureLigne();
        $ligne3->setNomenclature($nomenclature);
        $ligne3->setOrdre(30);
        $ligne3->setType(NomenclatureLigne::TYPE_MATIERE_PREMIERE);
        $ligne3->setProduitSimple($matieres['MAT-PMMA-3MM']);
        $ligne3->setDesignation('Face arrière PMMA opale');
        $ligne3->setQuantiteBase(1.0);
        $ligne3->setFormuleQuantite('largeur * hauteur / 1000000');
        $ligne3->setTauxChute(15.00);
        $ligne3->setUniteQuantite($uniteM2);
        $ligne3->setObligatoire(true);
        $nomenclature->addLigne($ligne3);

        // Ligne 4: Bande LED (périmètre interne)
        $ligne4 = new NomenclatureLigne();
        $ligne4->setNomenclature($nomenclature);
        $ligne4->setOrdre(40);
        $ligne4->setType(NomenclatureLigne::TYPE_MATIERE_PREMIERE);
        $ligne4->setProduitSimple($matieres['MAT-LED-5630']);
        $ligne4->setDesignation('Bande LED éclairage interne');
        $ligne4->setQuantiteBase(1.0);
        $ligne4->setFormuleQuantite('(largeur + hauteur) * 2 / 1000 * 1.1'); // Périmètre + 10%
        $ligne4->setTauxChute(5.00);
        $ligne4->setUniteQuantite($uniteM);
        $ligne4->setObligatoire(true);
        $ligne4->setConditionAffichage('option_eclairage != "aucun"');
        $nomenclature->addLigne($ligne4);

        // Ligne 5: Transformateur (quantité fixe)
        $ligne5 = new NomenclatureLigne();
        $ligne5->setNomenclature($nomenclature);
        $ligne5->setOrdre(50);
        $ligne5->setType(NomenclatureLigne::TYPE_MATIERE_PREMIERE);
        $ligne5->setProduitSimple($matieres['MAT-TRANSFO-60W']);
        $ligne5->setDesignation('Transformateur LED 60W');
        $ligne5->setQuantiteBase(1.0);
        $ligne5->setUniteQuantite($unitePce);
        $ligne5->setObligatoire(true);
        $ligne5->setConditionAffichage('option_eclairage != "aucun"');
        $nomenclature->addLigne($ligne5);

        // Ligne 6: Équerres de fixation (quantité fixe)
        $ligne6 = new NomenclatureLigne();
        $ligne6->setNomenclature($nomenclature);
        $ligne6->setOrdre(60);
        $ligne6->setType(NomenclatureLigne::TYPE_MATIERE_PREMIERE);
        $ligne6->setProduitSimple($matieres['MAT-EQUERRE-INOX']);
        $ligne6->setDesignation('Équerres fixation structure');
        $ligne6->setQuantiteBase(8.0);
        $ligne6->setUniteQuantite($unitePce);
        $ligne6->setObligatoire(true);
        $nomenclature->addLigne($ligne6);

        // Ligne 7: Visserie (quantité fixe)
        $ligne7 = new NomenclatureLigne();
        $ligne7->setNomenclature($nomenclature);
        $ligne7->setOrdre(70);
        $ligne7->setType(NomenclatureLigne::TYPE_FOURNITURE);
        $ligne7->setProduitSimple($matieres['MAT-VIS-INOX']);
        $ligne7->setDesignation('Kit visserie complet');
        $ligne7->setQuantiteBase(1.0);
        $ligne7->setUniteQuantite($unitePce);
        $ligne7->setObligatoire(true);
        $nomenclature->addLigne($ligne7);

        // Ligne 8: Potences murales (conditionnelle)
        $ligne8 = new NomenclatureLigne();
        $ligne8->setNomenclature($nomenclature);
        $ligne8->setOrdre(80);
        $ligne8->setType(NomenclatureLigne::TYPE_MATIERE_PREMIERE);
        $ligne8->setProduitSimple($matieres['MAT-POTENCE-500']);
        $ligne8->setDesignation('Potences murales 500mm');
        $ligne8->setQuantiteBase(2.0);
        $ligne8->setUniteQuantite($unitePce);
        $ligne8->setObligatoire(false);
        $ligne8->setConditionAffichage('option_fixation == "murale"');
        $nomenclature->addLigne($ligne8);

        // Ligne 9: Câble électrique
        $ligne9 = new NomenclatureLigne();
        $ligne9->setNomenclature($nomenclature);
        $ligne9->setOrdre(90);
        $ligne9->setType(NomenclatureLigne::TYPE_MATIERE_PREMIERE);
        $ligne9->setProduitSimple($matieres['MAT-CABLE-2X075']);
        $ligne9->setDesignation('Câble alimentation électrique');
        $ligne9->setQuantiteBase(3.0); // 3 mètres par défaut
        $ligne9->setUniteQuantite($uniteM);
        $ligne9->setObligatoire(true);
        $ligne9->setConditionAffichage('option_eclairage != "aucun"');
        $nomenclature->addLigne($ligne9);

        $manager->persist($nomenclature);
        return $nomenclature;
    }

    private function creerGammeEnseigneDrapeau(ObjectManager $manager, array $postes): Gamme
    {
        $gamme = new Gamme();
        $gamme->setCode('GAM-ENS-DRAPEAU');
        $gamme->setLibelle('Fabrication enseigne drapeau LED');
        $gamme->setDescription('Gamme complète de fabrication d\'une enseigne drapeau double face LED');
        $gamme->setVersion('1.0');
        $gamme->setStatut(Gamme::STATUT_VALIDEE);
        $gamme->setActif(true);

        // Opération 1: Création graphique
        $op1 = new GammeOperation();
        $op1->setGamme($gamme);
        $op1->setOrdre(10);
        $op1->setCode('GRAPH-01');
        $op1->setLibelle('Création fichier PAO');
        $op1->setTypeTemps(GammeOperation::TYPE_TEMPS_FIXE);
        $op1->setTempsFixe(45); // 45 minutes
        $op1->setPosteTravail($postes['GRAPH-PAO-1']);
        $op1->setInstructions('Créer le fichier vectoriel aux dimensions exactes, export PDF haute qualité');
        $op1->setControleQualite(true);
        $op1->setDescriptionControle('Vérifier dimensions et qualité du fichier');
        $gamme->addOperation($op1);

        // Opération 2: Impression face avant (temps dynamique)
        $op2 = new GammeOperation();
        $op2->setGamme($gamme);
        $op2->setOrdre(20);
        $op2->setCode('IMP-01');
        $op2->setLibelle('Impression face avant sur PMMA');
        $op2->setTypeTemps(GammeOperation::TYPE_TEMPS_FORMULE);
        $op2->setFormuleTemps('surface * 0.8'); // 0.8 min par m²
        $op2->setPosteTravail($postes['IMP-LATEX-1']);
        $op2->setInstructions('Impression qualité haute définition, vérifier calibrage couleurs');
        $op2->setParametresMachine(['qualite' => 'HD', 'nb_passes' => 6]);
        $op2->setControleQualite(true);
        $op2->setDescriptionControle('Vérifier rendu couleurs et absence de défauts');
        $gamme->addOperation($op2);

        // Opération 3: Impression face arrière (parallèle possible)
        $op3 = new GammeOperation();
        $op3->setGamme($gamme);
        $op3->setOrdre(30);
        $op3->setCode('IMP-02');
        $op3->setLibelle('Impression face arrière sur PMMA');
        $op3->setTypeTemps(GammeOperation::TYPE_TEMPS_FORMULE);
        $op3->setFormuleTemps('surface * 0.8');
        $op3->setPosteTravail($postes['IMP-LATEX-1']);
        $op3->setInstructions('Impression qualité haute définition, vérifier calibrage couleurs');
        $op3->setParametresMachine(['qualite' => 'HD', 'nb_passes' => 6]);
        $op3->setTempsParallele(true); // Peut se faire en même temps que découpe
        $gamme->addOperation($op3);

        // Opération 4: Découpe aluminium
        $op4 = new GammeOperation();
        $op4->setGamme($gamme);
        $op4->setOrdre(40);
        $op4->setCode('DEC-01');
        $op4->setLibelle('Découpe profilés aluminium');
        $op4->setTypeTemps(GammeOperation::TYPE_TEMPS_FORMULE);
        $op4->setFormuleTemps('perimetre * 0.5 + 15'); // 0.5min/m + 15min setup
        $op4->setPosteTravail($postes['DEC-CNC-1']);
        $op4->setInstructions('Découpe angles 45° pour assemblage cadre');
        $gamme->addOperation($op4);

        // Opération 5: Découpe PMMA
        $op5 = new GammeOperation();
        $op5->setGamme($gamme);
        $op5->setOrdre(50);
        $op5->setCode('DEC-02');
        $op5->setLibelle('Découpe faces PMMA imprimées');
        $op5->setTypeTemps(GammeOperation::TYPE_TEMPS_FORMULE);
        $op5->setFormuleTemps('perimetre * 0.3 + 10');
        $op5->setPosteTravail($postes['DEC-CNC-1']);
        $op5->setInstructions('Découpe précise avec finition bords');
        $op5->setControleQualite(true);
        $op5->setDescriptionControle('Vérifier dimensions et qualité des bords');
        $gamme->addOperation($op5);

        // Opération 6: Montage structure
        $op6 = new GammeOperation();
        $op6->setGamme($gamme);
        $op6->setOrdre(60);
        $op6->setCode('MONT-01');
        $op6->setLibelle('Assemblage structure aluminium');
        $op6->setTypeTemps(GammeOperation::TYPE_TEMPS_FIXE);
        $op6->setTempsFixe(30);
        $op6->setPosteTravail($postes['MONT-MAN-1']);
        $op6->setInstructions('Assemblage cadre avec équerres, vérifier équerrage');
        $gamme->addOperation($op6);

        // Opération 7: Installation LEDs (conditionnelle)
        $op7 = new GammeOperation();
        $op7->setGamme($gamme);
        $op7->setOrdre(70);
        $op7->setCode('MONT-02');
        $op7->setLibelle('Installation bande LED');
        $op7->setTypeTemps(GammeOperation::TYPE_TEMPS_FORMULE);
        $op7->setFormuleTemps('perimetre * 0.4 + 20'); // 0.4min/m + 20min câblage
        $op7->setPosteTravail($postes['MONT-MAN-1']);
        $op7->setInstructions('Pose bande LED, câblage transformateur, test éclairage');
        $op7->setConditionExecution('option_eclairage != "aucun"');
        $op7->setControleQualite(true);
        $op7->setDescriptionControle('Test fonctionnel éclairage complet');
        $gamme->addOperation($op7);

        // Opération 8: Montage final
        $op8 = new GammeOperation();
        $op8->setGamme($gamme);
        $op8->setOrdre(80);
        $op8->setCode('MONT-03');
        $op8->setLibelle('Montage final faces + fixations');
        $op8->setTypeTemps(GammeOperation::TYPE_TEMPS_FIXE);
        $op8->setTempsFixe(45);
        $op8->setPosteTravail($postes['MONT-MAN-1']);
        $op8->setInstructions('Assemblage faces PMMA sur structure, pose fixations');
        $gamme->addOperation($op8);

        // Opération 9: Contrôle qualité final
        $op9 = new GammeOperation();
        $op9->setGamme($gamme);
        $op9->setOrdre(90);
        $op9->setCode('FIN-01');
        $op9->setLibelle('Contrôle qualité et emballage');
        $op9->setTypeTemps(GammeOperation::TYPE_TEMPS_FIXE);
        $op9->setTempsFixe(15);
        $op9->setPosteTravail($postes['FIN-MAN-1']);
        $op9->setInstructions('Vérification complète, nettoyage, emballage protection');
        $op9->setControleQualite(true);
        $op9->setDescriptionControle('Check-list complète qualité produit fini');
        $gamme->addOperation($op9);

        $manager->persist($gamme);
        return $gamme;
    }

    private function creerProduitCatalogueEnseigne(
        ObjectManager $manager,
        Nomenclature $nomenclature,
        Gamme $gamme
    ): ProduitCatalogue {
        // Créer le produit simple de base
        $produitBase = new Produit();
        $produitBase->setReference('ENS-DRAPEAU-LED');
        $produitBase->setDesignation('Enseigne drapeau LED double face');
        $produitBase->setType('service');
        $produitBase->setActif(true);

        $manager->persist($produitBase);

        // Créer le produit catalogue
        $produitCatalogue = new ProduitCatalogue();
        $produitCatalogue->setProduit($produitBase);
        $produitCatalogue->setNomenclature($nomenclature);
        $produitCatalogue->setGamme($gamme);
        $produitCatalogue->setPersonnalisable(true);
        $produitCatalogue->setAfficherSurDevis(true);
        $produitCatalogue->setMargeDefaut(35.00); // 35% de marge
        $produitCatalogue->setActif(true);

        // Paramètres par défaut
        $produitCatalogue->setParametresDefaut([
            'largeur' => 600,
            'hauteur' => 600,
            'option_eclairage' => 'led',
            'option_fixation' => 'murale'
        ]);

        // Variables calculées
        $produitCatalogue->setVariablesCalculees([
            'surface' => 'largeur * hauteur / 1000000',
            'perimetre' => '(largeur + hauteur) * 2 / 1000'
        ]);

        $produitCatalogue->setInstructionsConfiguration(
            'Renseigner les dimensions en millimètres. Surface maximum 6m².'
        );

        $manager->persist($produitCatalogue);
        return $produitCatalogue;
    }

    private function creerOptionsEtRegles(ObjectManager $manager, ProduitCatalogue $produit): void
    {
        // Option 1: Largeur
        $optLargeur = new OptionProduit();
        $optLargeur->setProduitCatalogue($produit);
        $optLargeur->setCode('largeur');
        $optLargeur->setLibelle('Largeur (mm)');
        $optLargeur->setTypeChamp(OptionProduit::TYPE_NUMERIC);
        $optLargeur->setObligatoire(true);
        $optLargeur->setOrdre(10);
        $optLargeur->setParametres(['min' => 200, 'max' => 3000, 'step' => 10, 'unite' => 'mm']);
        $manager->persist($optLargeur);

        // Option 2: Hauteur
        $optHauteur = new OptionProduit();
        $optHauteur->setProduitCatalogue($produit);
        $optHauteur->setCode('hauteur');
        $optHauteur->setLibelle('Hauteur (mm)');
        $optHauteur->setTypeChamp(OptionProduit::TYPE_NUMERIC);
        $optHauteur->setObligatoire(true);
        $optHauteur->setOrdre(20);
        $optHauteur->setParametres(['min' => 200, 'max' => 3000, 'step' => 10, 'unite' => 'mm']);
        $manager->persist($optHauteur);

        // Option 3: Éclairage
        $optEclairage = new OptionProduit();
        $optEclairage->setProduitCatalogue($produit);
        $optEclairage->setCode('option_eclairage');
        $optEclairage->setLibelle('Type d\'éclairage');
        $optEclairage->setTypeChamp(OptionProduit::TYPE_SELECT);
        $optEclairage->setObligatoire(true);
        $optEclairage->setOrdre(30);
        $manager->persist($optEclairage);

        // Valeurs éclairage
        $valLed = new ValeurOption();
        $valLed->setOption($optEclairage);
        $valLed->setCode('led');
        $valLed->setLibelle('LED blanc chaud');
        $valLed->setSupplementPrix(0);
        $valLed->setOrdre(10);
        $valLed->setParDefaut(true);
        $valLed->setDisponible(true);
        $manager->persist($valLed);

        $valAucun = new ValeurOption();
        $valAucun->setOption($optEclairage);
        $valAucun->setCode('aucun');
        $valAucun->setLibelle('Sans éclairage');
        $valAucun->setSupplementPrix(-50);
        $valAucun->setOrdre(20);
        $valAucun->setDisponible(true);
        $manager->persist($valAucun);

        // Option 4: Fixation
        $optFixation = new OptionProduit();
        $optFixation->setProduitCatalogue($produit);
        $optFixation->setCode('option_fixation');
        $optFixation->setLibelle('Type de fixation');
        $optFixation->setTypeChamp(OptionProduit::TYPE_SELECT);
        $optFixation->setObligatoire(true);
        $optFixation->setOrdre(40);
        $manager->persist($optFixation);

        // Valeurs fixation
        $valMurale = new ValeurOption();
        $valMurale->setOption($optFixation);
        $valMurale->setCode('murale');
        $valMurale->setLibelle('Fixation murale avec potences');
        $valMurale->setSupplementPrix(0);
        $valMurale->setOrdre(10);
        $valMurale->setParDefaut(true);
        $valMurale->setDisponible(true);
        $manager->persist($valMurale);

        $valSuspendue = new ValeurOption();
        $valSuspendue->setOption($optFixation);
        $valSuspendue->setCode('suspendue');
        $valSuspendue->setLibelle('Suspension par câbles');
        $valSuspendue->setSupplementPrix(25);
        $valSuspendue->setOrdre(20);
        $valSuspendue->setDisponible(true);
        $manager->persist($valSuspendue);

        // Règle 1: Surface maximum
        $regle1 = new RegleCompatibilite();
        $regle1->setProduitCatalogue($produit);
        $regle1->setCode('SURF_MAX');
        $regle1->setNom('Surface maximum 6m²');
        $regle1->setTypeRegle(RegleCompatibilite::TYPE_FORMULA);
        $regle1->setExpression('largeur * hauteur <= 6000000');
        $regle1->setMessageErreur('La surface ne doit pas dépasser 6m² (6 000 000 mm²)');
        $regle1->setPriorite(100);
        $regle1->setSeverite(RegleCompatibilite::SEVERITE_ERREUR);
        $regle1->setActif(true);
        $manager->persist($regle1);

        // Règle 2: Dimensions minimum
        $regle2 = new RegleCompatibilite();
        $regle2->setProduitCatalogue($produit);
        $regle2->setCode('DIM_MIN');
        $regle2->setNom('Dimensions minimum 200x200mm');
        $regle2->setTypeRegle(RegleCompatibilite::TYPE_FORMULA);
        $regle2->setExpression('largeur >= 200 and hauteur >= 200');
        $regle2->setMessageErreur('Les dimensions ne doivent pas être inférieures à 200mm');
        $regle2->setPriorite(90);
        $regle2->setSeverite(RegleCompatibilite::SEVERITE_ERREUR);
        $regle2->setActif(true);
        $manager->persist($regle2);
    }
}
