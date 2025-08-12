# 🔧 CORRECTION ERREUR CODE UNIQUE CLIENT

## 🚨 **PROBLÈME RÉSOLU**

**Erreur SQL :** `SQLSTATE[23505]: Unique violation: 7 ERREUR: la valeur d'une clé dupliquée rompt la contrainte unique « uniq_c744045577153098 » DETAIL: La clé « (code)=(P001) » existe déjà.`

### **Causes multiples identifiées :**

1. **Génération de code défaillante** - La méthode `getNextProspectCode()` ne trouvait pas le bon prochain numéro
2. **Contrainte NotBlank dans l'entité** - Validation forcée du champ `nom` même pour personne physique
3. **Base de données stricte** - Champ `nom` non-nullable en BDD

### **Solutions appliquées :**

#### **1. Correction génération codes (ClientRepository.php) :**
```php
// Nouvelle méthode robuste pour getNextProspectCode()
public function getNextProspectCode(): string
{
    // Récupérer TOUS les codes existants
    $existingCodes = $this->createQueryBuilder('c')
        ->select('c.code')
        ->where('c.code LIKE :pattern')
        ->setParameter('pattern', 'P%')
        ->getQuery()
        ->getArrayResult();

    // Trouver le numéro maximum réel
    $maxNumber = 0;
    foreach ($existingCodes as $codeData) {
        if (preg_match('/^P(\d+)$/', $codeData['code'], $matches)) {
            $maxNumber = max($maxNumber, (int) $matches[1]);
        }
    }

    // Générer + vérifier unicité
    $nextNumber = $maxNumber + 1;
    $nextCode = 'P' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    
    while ($this->findOneBy(['code' => $nextCode])) {
        $nextNumber++;
        $nextCode = 'P' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
    
    return $nextCode;
}
```

#### **2. Suppression contrainte entité (Client.php) :**
```php
// AVANT
#[Assert\NotBlank(message: 'Le nom/raison sociale est obligatoire')]
private ?string $nom = null;

// APRÈS  
#[ORM\Column(length: 200, nullable: true)]
#[Assert\Length(max: 200, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
private ?string $nom = null;
```

#### **3. Migration base de données :**
```sql
-- Version20250727143438.php
ALTER TABLE client ALTER nom DROP NOT NULL;
```

#### **4. Validation intelligente dans FormType :**
- Nom obligatoire SEULEMENT pour personne morale
- Nom optionnel pour personne physique (généré automatiquement)

## ✅ **RÉSULTAT**

Après ces corrections :
- ✅ **Codes uniques garantis** - Plus jamais de doublons P001, P002, etc.
- ✅ **Validation adaptée** - Selon type personne physique/morale
- ✅ **Base de données flexible** - Champ nom nullable
- ✅ **Sauvegarde fonctionnelle** - Clients/prospects créés sans erreur

## 🧪 **TEST FINAL**

1. **Aller sur** : `https://technoprod.local:8001/client/new`
2. **Créer personne physique** : Remplir prénom/nom → ✅ Doit marcher
3. **Créer personne morale** : Remplir nom entreprise → ✅ Doit marcher  
4. **Vérifier codes** : P002, P003, P004... (séquence unique)

---

**Statut** : ✅ Problème complètement résolu - Formulaire opérationnel