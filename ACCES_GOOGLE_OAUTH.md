# 🎯 Google OAuth - Configuration Finale

## ✅ Configuration terminée !

Google OAuth est maintenant configuré avec :
- **URL de redirection** : `http://test.decorpub.fr:8080/connect/google/check`
- **Client ID** : `249270781829-1eorf05uiu4n1qr83m3n18n3naai54s1.apps.googleusercontent.com`
- **Domaines autorisés** : decorpub.fr, technograv.fr, pimpanelo.fr, technoburo.fr, pimpanelo.com

## 🌐 Accès pour l'équipe

### URL principale de test
```
http://test.decorpub.fr:8080
```

### Comptes de test existants (connexion classique)
- **Admin** : test.admin@technoprod.com / password
- **Commercial** : test.commercial@technoprod.com / password  
- **Utilisateur** : test.user@technoprod.com / password

### Connexion Google OAuth
- Rendez-vous sur : http://test.decorpub.fr:8080/login
- Cliquez sur **"Se connecter avec Google"**
- Utilisez votre compte Google professionnel (@decorpub.fr, @technograv.fr, etc.)

## 👑 Super Admin automatique
**Nicolas Michel** (nicolas.michel@decorpub.fr) obtient automatiquement tous les rôles lors de la première connexion Google.

## 🎭 Tests des rôles

En mode développement, un menu **"Switch Rôles"** apparaît en haut à droite pour tester :
- **Administrateur** : Tous les droits
- **Commercial** : Gestion prospects/clients/devis
- **Utilisateur** : Consultation uniquement

## 🔍 Tests à effectuer

### 1. Test Google OAuth
- [ ] Connexion avec compte @decorpub.fr
- [ ] Connexion avec compte @technograv.fr
- [ ] Rejet d'un compte non autorisé (ex: @gmail.com)
- [ ] Affichage de l'avatar Google dans la navbar

### 2. Test Switch de rôles
- [ ] Menu visible en mode dev
- [ ] Switch vers Administrateur
- [ ] Switch vers Commercial
- [ ] Switch vers Utilisateur
- [ ] Rechargement automatique après changement

### 3. Test Interface refactorisée
- [ ] Création de devis avec nouvelle interface
- [ ] Section "Informations générales" simplifiée
- [ ] Section "Tiers" avec auto-population
- [ ] Onglets (Détail, Facturation, Livraison, Notes)
- [ ] Panneau Récapitulatif à droite

## 🚨 Important pour les tests

1. **DNS** : Assurez-vous que `test.decorpub.fr` pointe bien vers `172.17.4.210`
2. **Port** : N'oubliez pas le port `:8080` dans l'URL
3. **Domaines** : Seuls les 5 domaines autorisés peuvent se connecter

## 📞 Support technique

Si problèmes lors des tests :
1. Vérifiez que `test.decorpub.fr` est accessible
2. Vérifiez les logs de l'application
3. Testez d'abord avec les comptes de test classiques
4. Contactez l'équipe de développement

---
**🎉 L'application est prête pour les tests complets avec Google OAuth !**