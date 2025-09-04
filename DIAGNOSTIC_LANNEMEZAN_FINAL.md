# DIAGNOSTIC FINAL - PROBLÈME DE POSITIONNEMENT SECTEUR "PLATEAU DE LANNEMEZAN"

## RÉSUMÉ DU PROBLÈME

La puce du secteur "Plateau de Lannemezan" apparaissait **au milieu d'un autre secteur** au lieu d'être positionnée correctement dans les Hautes-Pyrénées/Haute-Garonne.

## CAUSE IDENTIFIÉE

**Attribution géographique erronée** dans la table `attribution_secteur` :

- **Attribution ID #123** : Code postal `34150` 
- **Problème** : Ce code postal correspond à la région de **Montpellier dans l'Hérault (34)** 
- **Distance** : ~400 km du vrai Plateau de Lannemezan !
- **Coordonnées erronées** : `43.6594000, 3.6472000` (région montpelliéraine)

## ALGORITHME DE POSITIONNEMENT (AdminController.php)

L'algorithme `calculerPositionHierarchique()` :

1. **Collecte toutes les attributions** du secteur par ordre hiérarchique :
   - communes → codes postaux → EPCI → départements → régions
2. **Récupère toutes les communes** concernées par chaque attribution
3. **Calcule le centre géographique** en mélangeant toutes les coordonnées
4. **Positionnement de la puce** basé sur ce centre global

### Problème dans l'algorithme :
Quand une attribution erronée existe, l'algorithme **mélange les coordonnées correctes et incorrectes**, créant un centre géographique aberrant entre les deux zones.

## DONNÉES EXACTES AVANT CORRECTION

### Secteur "Plateau de Lannemezan" (ID: 64)
- **4 attributions** dont 1 erronée :

| ID | Type | Valeur | Division | Département | Coordonnées | Status |
|----|------|--------|----------|-------------|-------------|---------|
| 120 | epci | 200070787 | Sarlabous | Hautes-Pyrénées | 43.0761000, 0.2802000 | ✅ Correct |
| 121 | code_postal | 31160 | Arbas | Haute-Garonne | 42.9925000, 0.9036000 | ✅ Correct |
| 122 | commune | 31255 | Labroquère | Haute-Garonne | 43.0433000, 0.5874000 | ✅ Correct |
| 123 | code_postal | **34150** | **La Boissière** | **Hérault** | **43.6594000, 3.6472000** | ❌ **ERREUR** |

### Résultat du calcul erroné :
- **Centre calculé** : Mélange des coordonnées Pyrénées + Hérault
- **Positionnement** : Quelque part entre Lannemezan et Montpellier
- **Apparence** : Puce au milieu d'un autre secteur

## SOLUTION APPLIQUÉE

**Suppression de l'attribution erronée** (ID #123) :
```sql
DELETE FROM attribution_secteur WHERE id = 123;
```

## ÉTAT APRÈS CORRECTION

### Secteur "Plateau de Lannemezan" (ID: 64)
- **3 attributions** toutes cohérentes :

| ID | Type | Valeur | Division | Département | Coordonnées | Status |
|----|------|--------|----------|-------------|-------------|---------|
| 120 | epci | 200070787 | Sarlabous | Hautes-Pyrénées | 43.0761000, 0.2802000 | ✅ Correct |
| 121 | code_postal | 31160 | Arbas | Haute-Garonne | 42.9925000, 0.9036000 | ✅ Correct |
| 122 | commune | 31255 | Labroquère | Haute-Garonne | 43.0433000, 0.5874000 | ✅ Correct |

### Résultat attendu :
- **Centre calculé** : Zone géographique cohérente Hautes-Pyrénées/Haute-Garonne
- **Positionnement** : Puce correctement placée dans la région du Plateau de Lannemezan
- **Apparence** : Puce dans son territoire géographique logique

## ALGORITHME DE POSITIONNEMENT HIÉRARCHIQUE

### Méthode `calculerPositionHierarchique()` (ligne 876)

```php
private function calculerPositionHierarchique(Secteur $secteur, array $communesAvecGeometries): ?array
{
    // 1. PRIORITÉ ÉPCI: Si le secteur contient des EPCI, utiliser le plus central
    $epcis = [];
    foreach ($attributions as $attribution) {
        if ($attribution->getTypeCritere() === TypeSecteur::TYPE_EPCI) {
            $epcis[] = $attribution->getDivisionAdministrative();
        }
    }
    
    // 2. PRIORITÉ DÉPARTEMENT: Si pas d'EPCI mais des départements
    // 3. FALLBACK: Centre géographique des communes
}
```

### Ordre de traitement hiérarchique :
1. **communes** (priorité 1)
2. **codes_postaux** (priorité 2) 
3. **epci** (priorité 3)
4. **departement** (priorité 4)
5. **region** (priorité 5)

## VÉRIFICATION DE LA CORRECTION

Pour vérifier que la correction fonctionne :

1. **Recharger la carte** des secteurs dans l'interface admin
2. **Localiser la puce** "Plateau de Lannemezan"
3. **Vérifier** qu'elle se trouve maintenant dans la région Hautes-Pyrénées/Haute-Garonne
4. **Confirmer** qu'elle n'apparaît plus "au milieu" d'un autre secteur

## RECOMMANDATIONS PRÉVENTIVES

### 1. Validation des attributions
- **Contrôle de cohérence géographique** lors de l'ajout d'attributions
- **Alerte** si les coordonnées sont à plus de X km de distance du centre actuel du secteur

### 2. Interface de vérification
- **Outil de diagnostic** des secteurs pour identifier les incohérences
- **Visualisation** des attributions sur carte avant validation

### 3. Amélioration de l'algorithme
- **Détection d'outliers** géographiques
- **Exclusion automatique** des coordonnées aberrantes
- **Logging amélioré** pour identifier facilement les problèmes

## FICHIERS CONCERNÉS

- `/src/Controller/AdminController.php` - Algorithme de positionnement
- `/src/Entity/Secteur.php` - Entité secteur
- `/src/Entity/AttributionSecteur.php` - Entité attribution 
- `/src/Entity/DivisionAdministrative.php` - Divisions administratives
- `/src/Command/DiagnosticLannemezanCommand.php` - Outil de diagnostic créé
- `/src/Command/CorrigerAttributionLannemezanCommand.php` - Outil de correction créé

## CONCLUSION

Le problème était causé par **une erreur de saisie humaine** : attribution d'un code postal de l'Hérault (34150) au lieu d'un code postal local au Plateau de Lannemezan.

L'algorithme de positionnement fonctionnait correctement, mais ne pouvait pas deviner qu'une attribution était géographiquement incohérente.

**La correction appliquée devrait résoudre définitivement le problème de positionnement de la puce.**