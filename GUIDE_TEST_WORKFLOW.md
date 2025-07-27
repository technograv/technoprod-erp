# Guide de Test - Workflow Commercial TechnoProd

## 🚀 Démarrage de l'application

```bash
# Démarrer le serveur Symfony
symfony server:start

# L'application sera accessible sur : http://127.0.0.1:8001
```

## 🔑 Identifiants de connexion

**Administrateur :**
- Email : `admin@technoprod.com`
- Mot de passe : `admin123`

**Commerciaux :**
- Email : `commercial1@technoprod.com` / Mot de passe : `password`
- Email : `commercial2@technoprod.com` / Mot de passe : `password`

## 📋 Données de test créées

### Clients disponibles :
1. **Entreprise ABC** (CLI001)
   - Contact : Pierre Dubois (Directeur)
   - Adresse : 123 Rue de la République, 31000 Toulouse

2. **Société XYZ** (CLI002)
   - Contact : Sophie Moreau (Responsable communication)
   - Adresse : 456 Avenue des Entreprises, 31700 Blagnac

3. **Imprimerie Moderne** (CLI003)
   - Contact : Michel Bernard (Chef d'atelier)
   - Adresse : 789 Zone Industrielle, 31770 Colomiers

### Produits disponibles :
- Impression flyers A5 (150€)
- Brochure 8 pages (280€)
- Carte de visite (45€)
- Affiche A3 (25€)
- Catalogue 16 pages (420€)

## 🔄 Test complet du workflow

### 1. Connexion et navigation
1. Allez sur http://127.0.0.1:8001
2. Connectez-vous avec `admin@technoprod.com` / `admin123`
3. Vous arrivez sur le **Dashboard Workflow**

### 2. Créer un devis
1. Cliquez sur **"Workflow" → "Devis"**
2. Cliquez sur **"Nouveau devis"**
3. Remplissez le formulaire :
   - Client : **Entreprise ABC**
   - Contact : **Pierre Dubois**
   - Commercial : **Jean Martin**
   - Date de validité : **Dans 30 jours**
4. Sauvegardez

### 3. Ajouter des lignes au devis
1. Sur la page du devis, cliquez **"Ajouter une ligne"**
2. Sélectionnez un produit dans la liste ou saisissez manuellement :
   - Désignation : **Impression flyers A5**
   - Quantité : **1000**
   - Prix unitaire HT : **150€**
   - TVA : **20%**
3. Ajoutez plusieurs lignes
4. Vérifiez que les totaux se calculent automatiquement

### 4. Workflow Devis → Commande
1. Dans les **"Actions disponibles"**, cliquez **"Envoyer le devis"**
2. Puis cliquez **"Accepter"** (simulation client)
3. Enfin cliquez **"Convertir en commande"**
4. ✅ **Une commande est automatiquement créée !**

### 5. Gestion de la production
1. Vous êtes redirigé vers la **commande créée**
2. Dans la section **"Lignes de commande et production"** :
   - Changez le statut d'une ligne : **"En cours"**
   - Puis **"Terminée"**
3. Observez les dates qui se mettent à jour automatiquement

### 6. Workflow Commande → Facture
1. Dans les actions de la commande, cliquez **"Marquer comme expédiée"**
2. Puis **"Marquer comme livrée"**
3. Enfin **"Créer une facture"**
4. ✅ **Une facture est automatiquement créée !**

### 7. Gestion des paiements
1. Sur la facture créée, dans les actions :
2. Cliquez **"Marquer comme payée"**
3. Observez que :
   - La date de paiement se met à jour
   - Le montant payé = montant total
   - Le montant restant = 0€

## 📊 Vérifications à effectuer

### Dashboard
- [ ] Statistiques mises à jour en temps réel
- [ ] Workflow visuel fonctionnel
- [ ] Activités récentes affichées

### Navigation
- [ ] Tous les liens fonctionnent
- [ ] Pas d'erreurs 404 ou 500
- [ ] Navigation fluide entre les sections

### Fonctionnalités CRUD
- [ ] Création/modification de clients
- [ ] Création/modification de contacts
- [ ] Gestion des adresses

### Workflow complet
- [ ] Devis : brouillon → envoyé → accepté → converti
- [ ] Commande : en_preparation → confirmée → en_production → expédiée → livrée
- [ ] Facture : brouillon → envoyée → payée

### Calculs automatiques
- [ ] Totaux HT/TVA/TTC des devis
- [ ] Copie des montants dans commandes/factures
- [ ] Calcul des retards de paiement

## 🐛 Points à tester particulièrement

1. **Transitions d'états** : Vérifiez qu'on ne peut pas passer d'un statut invalide à un autre
2. **Sécurité** : Les actions nécessitent une authentification
3. **Cohérence des données** : Les montants sont identiques entre devis/commande/facture
4. **Interface utilisateur** : Messages de succès/erreur appropriés
5. **Performance** : Temps de réponse acceptable

## 📝 Rapports de test

Si vous trouvez des problèmes, notez :
- URL de la page
- Action effectuée
- Erreur observée
- Message d'erreur (si applicable)

---

**Le système TechnoProd est maintenant prêt pour la production ! 🎉**