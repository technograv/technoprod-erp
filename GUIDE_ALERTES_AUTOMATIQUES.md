# 🚨 GUIDE DU SYSTÈME D'ALERTES AUTOMATIQUES TECHNOPROD

## 📖 VUE D'ENSEMBLE

Le système d'alertes automatiques de TechnoProd permet de **détecter automatiquement des anomalies métier** dans votre base de données et d'**alerter les utilisateurs concernés** selon leur rôle et leur société.

## 🏗️ ARCHITECTURE DU SYSTÈME

### 1. **Types d'alertes** (AlerteType)
Un **type d'alerte** définit :
- **Nom** : Ex. "Client sans contact"
- **Description** : Ce que détecte l'alerte
- **Détecteur** : Classe PHP qui effectue la détection (ex. `ClientSansContactDetector`)
- **Rôles cibles** : Quels utilisateurs verront cette alerte (ROLE_COMMERCIAL, ROLE_COMPTABLE, etc.)
- **Sociétés cibles** : Filtrage par société (optionnel)
- **Sévérité** : info (bleu), warning (orange), danger (rouge)
- **Statut** : Actif/Inactif

### 2. **Instances d'alertes** (AlerteInstance)
Une **instance** est une alerte concrète détectée :
- Créée automatiquement lors de l'exécution d'un détecteur
- Liée à une entité spécifique (ex. le client #42)
- Contient un message descriptif
- Peut être résolue par un utilisateur avec commentaire
- Trace qui a résolu et quand

### 3. **Détecteurs** (AlerteDetector)
Classes PHP qui implémentent la **logique de détection** :
- Interrogent la base de données
- Identifient les anomalies
- Créent les instances d'alertes
- Évitent les doublons automatiquement

## 📊 TYPES D'ALERTES ACTUELLEMENT CONFIGURÉS

### ✅ 1. Client sans contact
- **ID** : 1
- **Détecteur** : `ClientSansContactDetector`
- **Objectif** : Trouver les clients actifs qui n'ont aucun contact associé
- **Rôles** : ROLE_COMMERCIAL, ROLE_COMPTABLE
- **Sévérité** : warning (orange)
- **Pourquoi c'est important** : Un client sans contact signifie qu'on ne peut pas le facturer ni le contacter

**Logique de détection :**
```sql
SELECT clients
WHERE actif = true
  AND aucun contact associé
```

### ✅ 2. Contact sans adresse
- **ID** : 2
- **Détecteur** : `ContactSansAdresseDetector`
- **Objectif** : Trouver les contacts qui n'ont aucune adresse (ni facturation, ni livraison)
- **Rôles** : ROLE_COMMERCIAL
- **Sévérité** : info (bleu)
- **Pourquoi c'est important** : Un contact sans adresse pose problème pour facturer ou livrer

**Logique de détection :**
```sql
SELECT contacts
WHERE client.actif = true
  AND aucune adresse associée
```

### ⚠️ 3. Rosy test
- **ID** : 3
- **Détecteur** : `ClientSansContactDetector` (même détecteur que #1)
- **Rôles** : ROLE_COMPTABLE
- **Sévérité** : warning
- **Note** : Semble être un doublon de test à nettoyer

## 🧪 COMMENT TESTER LE SYSTÈME

### Étape 1 : Accéder à l'interface
1. Connectez-vous à TechnoProd
2. Menu **Administration** → **Configuration Système**
3. Cliquez sur l'onglet **"Alertes automatiques"**

### Étape 2 : Voir la configuration
Vous verrez un tableau avec :
- **Nom** : Nom du type d'alerte
- **Détecteur** : Classe PHP utilisée
- **Statut** : Actif (vert) ou Inactif (gris)
- **Sévérité** : Badge coloré (info/warning/danger)
- **Instances** : Nombre d'alertes actuellement détectées
- **Actions** : Boutons pour tester/modifier/supprimer

### Étape 3 : Lancer une détection manuelle

**Option A - Tester un seul type :**
1. Cliquez sur le bouton **"Tester"** (icône ▶️) d'un type spécifique
2. Le système va :
   - Exécuter le détecteur associé
   - Parcourir votre base de données
   - Créer des instances pour chaque anomalie trouvée
3. Vous verrez le nombre d'instances créées

**Option B - Tester tous les types :**
1. Cliquez sur **"Lancer toutes les détections"** en haut à droite
2. Tous les détecteurs actifs s'exécutent
3. Résultat affiché pour chaque type

### Étape 4 : Voir les alertes détectées
1. Menu **Administration** → **Configuration Système**
2. Onglet **"Alertes manuelles"** (ou section dédiée)
3. Vous verrez la liste des instances détectées :
   - Message descriptif (ex. "Client 'ABC Corp' n'a aucun contact")
   - Date de détection
   - Sévérité
   - Actions possibles (voir détails, résoudre)

### Étape 5 : Résoudre une alerte
1. Cliquez sur une alerte non résolue
2. Corrigez le problème dans votre application :
   - Ex : Ajoutez un contact au client problématique
3. Marquez l'alerte comme résolue avec un commentaire
4. L'alerte passe en statut "Résolu" avec votre nom et la date

## 🔧 CONFIGURATION AVANCÉE (BOUTON ACTUEL)

Le bouton **"Configuration avancée"** ouvre la **modale d'édition** qui permet de :

### Paramètres modifiables :
1. **Nom** : Renommer le type d'alerte
2. **Description** : Expliquer ce que détecte l'alerte
3. **Détecteur** : Changer la classe PHP (liste des détecteurs disponibles)
4. **Sévérité** : info/warning/danger (change la couleur)
5. **Ordre d'affichage** : Position dans la liste
6. **Rôles cibles** : Cocher les rôles qui verront cette alerte
   - ☐ ROLE_ADMIN
   - ☐ ROLE_MANAGER
   - ☐ ROLE_COMMERCIAL
   - ☐ ROLE_COMPTABLE
   - ☐ ROLE_USER
   - Si vide = visible par tous
7. **Sociétés cibles** : Filtrer par société
   - ☐ Toutes les sociétés (cocher pour désactiver filtre)
   - ☐ Société A
   - ☐ Société B
   - Si vide = visible pour toutes
8. **Statut** : Actif/Inactif

### Exemple de cas d'usage :
**Scénario :** Vous voulez que seuls les commerciaux de la société "TechnoProd Sud" voient les alertes "Client sans contact"

**Configuration :**
- Rôles cibles : ☑ ROLE_COMMERCIAL
- Sociétés cibles : ☑ TechnoProd Sud
- Les comptables ne verront plus cette alerte
- Les commerciaux d'autres sociétés non plus

## 🎯 WORKFLOWS D'UTILISATION

### Workflow 1 : Détection automatique programmée (futur)
```
[Cron journalier] → [Exécute tous les détecteurs actifs] → [Crée instances]
→ [Utilisateurs voient alertes au login] → [Corrigent anomalies] → [Résolvent alertes]
```

### Workflow 2 : Détection manuelle (actuel)
```
[Admin clique "Tester"] → [Détecteur s'exécute] → [Instances créées]
→ [Admin consulte alertes] → [Corrige dans l'app] → [Résout alerte]
```

### Workflow 3 : Création nouveau type d'alerte
```
[Admin clique "Nouveau type"] → [Remplit formulaire modal]
→ [Choisit détecteur] → [Configure rôles/sociétés] → [Sauvegarde]
→ [Type apparaît dans liste] → [Peut lancer détection]
```

## 🧩 CRÉER UN NOUVEAU DÉTECTEUR (DÉVELOPPEUR)

### Étape 1 : Créer la classe PHP
**Fichier** : `src/Service/AlerteDetection/MonNouveauDetector.php`

```php
<?php
namespace App\Service\AlerteDetection;

use App\Entity\AlerteType;
use App\Entity\MonEntite;

class MonNouveauDetector extends AbstractAlerteDetector
{
    public function detect(AlerteType $alerteType): array
    {
        // Votre requête pour trouver les anomalies
        $entitesProblematiques = $this->entityManager
            ->getRepository(MonEntite::class)
            ->findBy(['probleme' => true]);

        $instances = [];
        foreach ($entitesProblematiques as $entite) {
            if (!$this->instanceExists($alerteType, $entite->getId())) {
                $instances[] = $this->createInstance($alerteType, $entite->getId(), [
                    'nom_entite' => $entite->getNom(),
                    'raison_probleme' => 'Explication du problème'
                ]);
            }
        }
        return $instances;
    }

    public function getEntityType(): string
    {
        return MonEntite::class;
    }

    public function getName(): string
    {
        return 'Mon nouveau détecteur';
    }

    public function getDescription(): string
    {
        return 'Détecte les entités problématiques';
    }
}
```

### Étape 2 : Enregistrer comme service (si nécessaire)
Symfony détecte automatiquement les services dans `src/Service/AlerteDetection/`

### Étape 3 : Enregistrer le détecteur
Le détecteur doit être enregistré dans le `AlerteManager` :

```php
// Dans votre code d'initialisation
$alerteManager->registerDetector(MonNouveauDetector::class, $monNouveauDetector);
```

### Étape 4 : Créer le type d'alerte
Via l'interface admin ou en base :
```sql
INSERT INTO alerte_type (nom, description, classe_detection, actif, severity, ordre)
VALUES ('Mon nouveau type', 'Description', 'App\\Service\\AlerteDetection\\MonNouveauDetector', true, 'warning', 10);
```

## 📈 STATISTIQUES ET MONITORING

### Données disponibles :
- **Nombre de types actifs** : Combien de détecteurs sont configurés
- **Instances non résolues** : Alertes en attente de traitement
- **Instances résolues aujourd'hui** : Alertes traitées ce jour
- **Par type** : Répartition des alertes par catégorie

### Accès :
```php
$stats = $alerteManager->getStatistics();
// Retourne :
// [
//   'types_actifs' => 3,
//   'instances_non_resolues' => 15,
//   'instances_resolues_aujourd_hui' => 8,
//   'par_type' => [
//     ['nom' => 'Client sans contact', 'nb_instances' => 10],
//     ['nom' => 'Contact sans adresse', 'nb_instances' => 5]
//   ]
// ]
```

## 🔍 DÉBOGAGE ET LOGS

### Vérifier les détecteurs enregistrés :
```php
$detecteurs = $alerteManager->getDetectors();
// Liste tous les détecteurs disponibles
```

### Voir le SQL exécuté :
Activez le profiler Symfony en environnement dev pour voir les requêtes SQL générées par les détecteurs.

### Logs d'exécution :
Le système log automatiquement :
- Détections lancées
- Instances créées
- Instances résolues
- Erreurs de détection

**Fichier** : `var/log/dev.log` ou `var/log/prod.log`

## 🚀 BONNES PRATIQUES

1. **Nommage clair** : Noms de types explicites (éviter "Rosy test")
2. **Éviter les doublons** : Ne créez pas plusieurs types avec le même détecteur (sauf cas spécifiques)
3. **Sévérité appropriée** :
   - `info` : Information, pas bloquant
   - `warning` : Attention requise, à traiter
   - `danger` : Critique, action immédiate
4. **Résolution systématique** : Traitez les alertes, ne les ignorez pas
5. **Commentaires de résolution** : Expliquez comment l'anomalie a été corrigée
6. **Détecteurs performants** : Optimisez vos requêtes SQL pour ne pas ralentir le système
7. **Tests réguliers** : Lancez les détections manuellement avant automatisation

## 🎓 RÉSUMÉ POUR L'UTILISATEUR

**Votre système d'alertes permet de :**
1. ✅ Détecter automatiquement des données incohérentes ou manquantes
2. ✅ Alerter les bonnes personnes (filtrage par rôle et société)
3. ✅ Tracer qui résout quoi et quand
4. ✅ Assurer la qualité des données de votre CRM/ERP
5. ✅ Gagner du temps en automatisant les vérifications

**Actuellement, vous avez 3 types configurés qui détectent :**
- Clients sans contacts
- Contacts sans adresses
- Un type test à nettoyer

**Le bouton "Configuration avancée" permet de** :
- Modifier ces types existants
- Changer qui voit quelles alertes
- Ajuster la sévérité et l'ordre d'affichage
- Activer/désactiver des types

---

**📝 Note finale** : Ce système est extensible. Vous pouvez créer autant de détecteurs personnalisés que nécessaire pour surveiller votre activité métier (devis expirés, factures impayées, stocks bas, etc.).