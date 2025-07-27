# Guide de Test TechnoProd - Système de Devis

## 🌐 Accès au Système

**URL principale :** http://172.17.4.210:8080

## 🔐 Authentification

Le système nécessite une authentification. Contactez l'administrateur pour obtenir vos identifiants.

## 📋 Fonctionnalités à Tester

### 1. Dashboard Devis
- **URL :** http://172.17.4.210:8080/devis
- **Tests :**
  - ✅ Affichage des statistiques (total, brouillons, envoyés, etc.)
  - ✅ Filtrage par statut
  - ✅ Liste des devis existants
  - ✅ Actions sur chaque devis (voir, modifier, PDF)

### 2. Création de Devis
- **URL :** http://172.17.4.210:8080/devis/new
- **Tests :**
  - ✅ Sélection prospect/client
  - ✅ Ajout de lignes de devis
  - ✅ Sélection produits/services du catalogue
  - ✅ Calculs automatiques (totaux, TVA, acomptes)
  - ✅ Conditions commerciales
  - ✅ Enregistrement du devis

### 3. Consultation de Devis
- **URL :** http://172.17.4.210:8080/devis/{id}
- **Tests :**
  - ✅ Affichage détaillé avec onglets
  - ✅ Informations client complètes
  - ✅ Timeline du workflow
  - ✅ Actions disponibles selon statut

### 4. Génération PDF
- **URL :** http://172.17.4.210:8080/devis/{id}/pdf
- **Tests :**
  - ✅ Design professionnel
  - ✅ Données complètes et correctes
  - ✅ Sections de signature
  - ✅ Conditions générales

### 5. Interface Client (Signature Électronique)
- **URL :** http://172.17.4.210:8080/devis/{id}/client/{token}
- **Tests :**
  - ✅ Affichage côté client
  - ✅ Canvas de signature fonctionnel
  - ✅ Validation des données (nom, email)
  - ✅ Acceptation/Refus du devis
  - ✅ Sauvegarde de la signature

### 6. Workflow Complet
- **Test End-to-End :**
  1. Créer un devis → Statut "Brouillon"
  2. Envoyer le devis → Statut "Envoyé" + Email
  3. Signature client → Statut "Signé"
  4. Vérifier historique et timeline

## 🎯 Points d'Attention Particuliers

### Performance
- [ ] Temps de chargement des pages
- [ ] Fluidité des calculs automatiques
- [ ] Réactivité de l'interface

### Interface Utilisateur
- [ ] Design responsive (mobile/tablette)
- [ ] Ergonomie des formulaires
- [ ] Clarté des informations affichées

### Fonctionnalités Métier
- [ ] Exactitude des calculs (HT, TVA, TTC)
- [ ] Gestion des remises et acomptes
- [ ] Catalogue produits intégré
- [ ] Workflow statuts cohérent

### Intégrations
- [ ] Génération PDF propre
- [ ] Signature électronique fluide
- [ ] Envoi d'emails (si configuré)

## 🐛 Signalement de Bugs

**Format de signalement :**
1. **Page concernée :** URL exacte
2. **Action effectuée :** Étapes pour reproduire
3. **Résultat attendu :** Ce qui devrait se passer
4. **Résultat obtenu :** Ce qui se passe réellement
5. **Navigateur :** Chrome, Firefox, Safari, etc.
6. **Capture d'écran :** Si possible

## ✅ Validation Finale

- [ ] Tous les modules fonctionnent
- [ ] Interface intuitive et ergonomique
- [ ] Calculs exacts et fiables
- [ ] Workflow complet opérationnel
- [ ] Performance acceptable
- [ ] Design professionnel

## 📞 Support

Contactez l'équipe de développement pour :
- Comptes utilisateur
- Problèmes techniques
- Questions fonctionnelles
- Suggestions d'amélioration

---
*Guide de test - Version 1.0 - Système Devis TechnoProd*