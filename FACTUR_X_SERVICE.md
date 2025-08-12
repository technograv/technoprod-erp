# 🧾 Service FacturXService - Conformité Facture Électronique 2026

## Vue d'ensemble

Le service `FacturXService` implémente la génération de factures au format **Factur-X** conforme à la norme **EN 16931** pour préparer TechnoProd à l'obligation de facturation électronique en France à partir de 2026.

## 📋 Fonctionnalités principales

### 1. Génération Factur-X complète
- **PDF/A-3** avec métadonnées de conformité
- **XML CII** (Cross Industry Invoice) intégré
- Support des 4 profils Factur-X
- Signature numérique qualifiée (optionnelle)
- Sécurisation selon NF203

### 2. Profils supportés

| Profil | Description | Usage recommandé |
|--------|-------------|------------------|
| **MINIMUM** | Données minimales requises | Factures simples |
| **BASIC WL** | Basic Without Lines - sans détail des lignes | Factures résumé |
| **BASIC** | Facture complète avec lignes de détail | **Usage standard** |
| **EN 16931** | Profil européen complet | Export international |

### 3. Méthodes publiques

#### `generateFacturX(Facture $facture, string $profile = 'BASIC', bool $signDocument = true): string`
Génère une facture Factur-X complète (PDF/A-3 + XML CII intégré).

#### `generateXMLCII(Facture $facture, string $profile = 'BASIC'): string`
Génère le XML CII seul conforme au standard Factur-X.

#### `embedXMLInPDF(string $pdfContent, string $xmlCII, string $invoiceNumber): string`
Intègre le XML CII dans un PDF comme fichier attaché (norme PDF/A-3).

#### `validateFacturX(string $xmlCII, string $profile = 'BASIC'): bool`
Valide le XML CII selon les schémas XSD et règles métier.

#### `exportFacturXFile(Facture $facture, string $profile = 'BASIC'): BinaryFileResponse`
Export direct en fichier téléchargeable.

## 🚀 Utilisation

### Dans un contrôleur

```php
use App\Service\FacturXService;

public function generateFacturX(Facture $facture, FacturXService $facturXService): Response
{
    try {
        // Génération avec profil BASIC et signature
        return $facturXService->exportFacturXFile($facture, 'BASIC');
        
    } catch (\Exception $e) {
        // Gestion d'erreur
        $this->addFlash('error', 'Erreur Factur-X: ' . $e->getMessage());
        return $this->redirectToRoute('app_facture_show', ['id' => $facture->getId()]);
    }
}
```

### Génération XML CII seul

```php
// Génération XML pour validation ou intégration
$xmlContent = $facturXService->generateXMLCII($facture, 'EN16931');

// Validation
$isValid = $facturXService->validateFacturX($xmlContent, 'EN16931');
```

## 🔧 Configuration

### Paramètres entreprise (config/services.yaml)

```yaml
parameters:
    # Configuration entreprise pour Factur-X
    app.company.name: 'TechnoProd'
    app.company.siret: '12345678901234'
    app.company.address: '123 Avenue de la République, 75011 Paris'
    app.company.phone: '+33 1 23 45 67 89'
    app.company.email: 'contact@technoprod.fr'
    app.company.vat_number: 'FR12345678901'
```

### Certificats de signature (optionnel)

```bash
# Créer le répertoire des certificats
mkdir -p var/crypto

# Placer les certificats pour signature qualifiée
var/crypto/facturx_certificate.pem    # Certificat public
var/crypto/facturx_private_key.pem    # Clé privée
```

## 📊 Interface utilisateur

### Accès depuis la vue facture

Dans la page de détail d'une facture (`/facture/{id}`), une section **"Actions Factur-X - Conformité 2026"** propose :

1. **Génération Factur-X** par profil (MINIMUM, BASIC WL, BASIC, EN 16931)
2. **Génération XML CII** seul pour validation
3. **Informations** sur les profils et la conformité 2026

### URLs disponibles

```
GET /facture/{id}/factur-x?profile=BASIC&sign=true    # Télécharge PDF Factur-X
GET /facture/{id}/xml-cii?profile=BASIC               # Télécharge XML CII
```

## 🛡️ Sécurité et conformité

### Intégrité des documents (NF203)
- **Hash SHA-256** de chaque facture générée
- **Signature RSA** avec chaînage cryptographique
- **Horodatage sécurisé** 
- **Traçabilité complète** via `DocumentIntegrityService`

### Validation XML
- **Contrôle syntaxique** XML bien formé
- **Validation éléments obligatoires** selon profil
- **Cohérence des montants** (HT + TVA = TTC)
- **Format des dates** (YYYYMMDD)

### Génération PDF/A-3
- **Métadonnées Factur-X** intégrées
- **XML CII attaché** selon norme PDF/A-3
- **Template Twig responsive** avec données structurées
- **Signature numérique** qualifiée (si certificats présents)

## 📄 Structure XML CII générée

Le XML généré respecte la structure UN/CEFACT Cross Industry Invoice :

```xml
<rsm:CrossIndustryInvoice>
    <rsm:ExchangedDocumentContext>
        <!-- Profil Factur-X -->
    </rsm:ExchangedDocumentContext>
    
    <rsm:ExchangedDocument>
        <!-- En-tête facture -->
    </rsm:ExchangedDocument>
    
    <rsm:SupplyChainTradeTransaction>
        <ram:ApplicableHeaderTradeAgreement>
            <!-- Vendeur et Acheteur -->
        </ram:ApplicableHeaderTradeAgreement>
        
        <ram:ApplicableHeaderTradeDelivery>
            <!-- Informations livraison -->
        </ram:ApplicableHeaderTradeDelivery>
        
        <ram:ApplicableHeaderTradeSettlement>
            <!-- Conditions paiement et totaux -->
        </ram:ApplicableHeaderTradeSettlement>
        
        <!-- Lignes de facture (selon profil) -->
    </rsm:SupplyChainTradeTransaction>
</rsm:CrossIndustryInvoice>
```

## 🚨 Gestion d'erreurs

### Erreurs courantes et solutions

| Erreur | Cause | Solution |
|--------|-------|----------|
| `Profil invalide` | Profil non supporté | Utiliser : MINIMUM, BASIC_WL, BASIC, EN16931 |
| `Document doit avoir un ID` | Facture non persistée | Sauvegarder la facture avant génération |
| `XML mal formé` | Structure XML invalide | Vérifier les données de la facture |
| `Éléments obligatoires manquants` | Profil incomplet | Compléter les données client/facture |
| `Certificats non disponibles` | Fichiers .pem absents | Signature désactivée automatiquement |

### Logs détaillés

Le service génère des logs détaillés pour le debugging :

```php
// Logs de génération
'Démarrage génération Factur-X' (INFO)
'XML CII validé avec succès' (INFO)
'Factur-X généré avec succès' (INFO)

// Logs d'erreur  
'Erreur génération Factur-X' (ERROR)
'XML CII mal formé' (ERROR)
```

## 🔮 Évolutions futures

### Implémentations à compléter

1. **Génération PDF/A-3 native** avec DomPDF ou TCPDF
2. **Validation XSD complète** avec schémas officiels
3. **Signature numérique qualifiée** avec certificats eIDAS
4. **Intégration EDI** pour envoi automatique
5. **API REST** pour génération à distance

### Conformité 2026

Le service est conçu pour évoluer vers la conformité complète :

- ✅ **Structure XML CII** conforme EN 16931
- ✅ **Profils Factur-X** supportés  
- ✅ **Métadonnées PDF/A-3** intégrées
- ⚠️ **Signature qualifiée** (implémentation basique)
- ⚠️ **Validation XSD** (contrôles métier)
- 📋 **Transmission EDI** (à développer)

## 📞 Support

Pour toute question sur l'utilisation du service FacturXService :

1. Consulter les logs dans `var/log/dev.log`
2. Vérifier la configuration dans `config/services.yaml`
3. Tester avec une facture simple et profil MINIMUM
4. Valider le XML généré avec un outil externe

---

**Conformité Factur-X v1.0.07 | EN 16931 | NF203 | Préparation obligatoire 2026 🇫🇷**