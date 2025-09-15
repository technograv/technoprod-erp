# Guide de Test des Emails - TechnoProd

## Configuration Actuelle

L'envoi d'emails est maintenant configuré pour les tests de développement.

### Mode Développement (MAILER_DSN=null://null)
- ✅ Les emails sont sauvegardés dans `var/log/` au lieu d'être envoyés
- ✅ Le système fonctionne sans configuration SMTP
- ✅ Logs détaillés dans `var/log/dev.log`

## Comment Tester l'Envoi de Devis

1. **Créer ou modifier un devis**
2. **Aller sur la page de visualisation du devis**
3. **Cliquer sur "Envoyer" ou "Renvoyer"**
4. **Remplir l'email de destination**
5. **Envoyer**

## Vérifier que ça fonctionne

### 1. Message Flash
Vous verrez : "📧 Devis envoyé avec succès à email@test.com (vérifiez les logs pour les détails en mode développement)"

### 2. Vérifier les Logs
```bash
tail -f var/log/dev.log | grep -i email
```

Vous devriez voir :
- ✅ `SIMULATION ENVOI EMAIL` avec les détails
- ✅ `Email envoyé avec succès via mailer par défaut` OU `Email simulé avec succès`

### 3. Vérifier les Emails Générés
Les emails sont sauvegardés dans `var/spool/` (avec MAILER_DSN=null://null)

## Options pour Tests Réels

### Option 1: MailHog (Recommandé pour développement)
```bash
# Installer MailHog
go install github.com/mailhog/MailHog@latest

# Lancer MailHog
MailHog
```
Puis modifier `.env` :
```
MAILER_DSN=smtp://localhost:1025
```
Interface web : http://localhost:8025

### Option 2: Mailtrap (Service en ligne)
1. Créer un compte sur https://mailtrap.io
2. Créer une boîte de réception de test
3. Copier les identifiants SMTP
4. Modifier `.env` :
```
MAILER_DSN=smtp://api:your-token@sandbox.smtp.mailtrap.io:2525?encryption=tls&auth_mode=login
```

### Option 3: Gmail SMTP (Pour tests proches de la production)
1. Activer l'authentification à 2 facteurs sur votre Gmail
2. Générer un "Mot de passe d'application"
3. Modifier `.env` :
```
MAILER_DSN=smtp://smtp.gmail.com:587?encryption=tls&auth_mode=login&username=votre-email@gmail.com&password=votre-mot-de-passe-app
```

## Status du Devis

Après envoi réussi :
- ✅ Statut passe à "envoyé"
- ✅ Date d'envoi enregistrée
- ✅ Historique/versioning conservé

## Debugging

Si problème, vérifier :
1. `var/log/dev.log` pour les erreurs
2. Configuration SMTP dans `.env`
3. Permissions du dossier `var/log/`
4. Firewall/ports réseau si SMTP externe

## Versioning

Le système de versioning fonctionne automatiquement :
- Modification d'un devis "envoyé" = création d'une version
- Historique visible dans l'onglet "Historique" du devis