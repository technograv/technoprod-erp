# 🎯 GUIDE D'AUTONOMIE GIT/GITHUB

## 🚀 **DÉMARRAGE RAPIDE**

### **1. Premier Push (Une seule fois)**
```bash
# Exécuter le script d'automatisation
./git-setup.sh technoprod-erp

# OU avec un nom personnalisé
./git-setup.sh mon-projet-erp
```

Ce script fait TOUT automatiquement :
- ✅ Installe GitHub CLI si nécessaire
- ✅ Configure Git avec vos informations
- ✅ Initialise le repository local
- ✅ Crée le commit initial avec message professionnel
- ✅ S'authentifie sur GitHub
- ✅ Crée le repository sur GitHub
- ✅ Pousse le code

### **2. Commits Quotidiens (Usage fréquent)**
```bash
# Pour les modifications courantes
./quick-commit.sh "Correction bug autocomplétion"
./quick-commit.sh "Ajout nouvelle fonctionnalité facturation"
./quick-commit.sh "Mise à jour interface client"
```

## 📋 **COMMANDES ESSENTIELLES**

### **Vérifier l'état**
```bash
git status              # Voir les fichiers modifiés
git log --oneline -10   # Voir les 10 derniers commits
git remote -v           # Voir les remotes configurés
```

### **Annuler des modifications**
```bash
git checkout -- fichier.php     # Annuler modifs d'un fichier
git reset HEAD~1                 # Annuler dernier commit (garde les modifs)
git reset --hard HEAD~1          # Annuler dernier commit (supprime tout)
```

### **Branches et collaboration**
```bash
# Créer une nouvelle branche
git checkout -b feature/nouvelle-fonction
./quick-commit.sh "Travail en cours sur nouvelle fonction"

# Revenir à main
git checkout main

# Fusionner une branche
git merge feature/nouvelle-fonction
```

### **Récupérer les mises à jour**
```bash
git pull origin main     # Récupérer les dernières modifs
```

## ⚡ **WORKFLOWS COURANTS**

### **🔄 Workflow Quotidien Simple**
```bash
# 1. Vérifier l'état
git status

# 2. Commiter et pousser
./quick-commit.sh "Description de ce que j'ai fait"

# C'est tout ! 🎉
```

### **🔧 Workflow avec Branch**
```bash
# 1. Créer une branche pour la fonctionnalité
git checkout -b feature/gestion-factures

# 2. Travailler et commiter régulièrement
./quick-commit.sh "Ajout entité Facture"
./quick-commit.sh "Interface création facture"
./quick-commit.sh "Tests validation facture"

# 3. Fusionner dans main
git checkout main
git merge feature/gestion-factures
git push
```

### **🚨 Workflow Urgence**
```bash
# Pour les corrections urgentes
git checkout -b hotfix/correction-bug-critique
./quick-commit.sh "🚨 HOTFIX: Correction bug critique signature"

# Fusionner immédiatement
git checkout main
git merge hotfix/correction-bug-critique
git push
```

## 🛠️ **PERSONNALISATION AVANCÉE**

### **Modifier les Scripts**

**Pour changer le message de commit par défaut :**
```bash
# Éditer quick-commit.sh ligne 45
FULL_MESSAGE="$COMMIT_MESSAGE

✨ Votre signature personnalisée
Développé par: Votre Nom"
```

**Pour ajouter des vérifications :**
```bash
# Ajouter dans quick-commit.sh avant le commit
print_step "Exécution des tests..."
php bin/console app:test-compliance
if [ $? -ne 0 ]; then
    print_error "Tests échoués, commit annulé"
    exit 1
fi
```

### **Aliases Git Utiles**
```bash
# Ajouter dans ~/.gitconfig
git config --global alias.st status
git config --global alias.co checkout
git config --global alias.br branch
git config --global alias.cm commit
git config --global alias.lg "log --oneline --graph --decorate"
```

## 🔐 **SÉCURITÉ ET BONNES PRATIQUES**

### **⚠️ Fichiers à NE JAMAIS commiter**
- ❌ `.env.local` (mots de passe, clés API)
- ❌ `/var/crypto/` (clés de chiffrement)
- ❌ `*.key`, `*.pem` (certificats)
- ❌ Base de données (`.sql`, `.db`)

### **✅ Toujours vérifier avant de commiter**
```bash
git status              # Voir ce qui va être commité
git diff --cached       # Voir les modifications exactes
```

### **🔄 Sauvegardes automatiques**
```bash
# Créer un cron job pour backup automatique
crontab -e

# Ajouter cette ligne pour backup quotidien à 2h du matin
0 2 * * * cd /home/decorpub/TechnoProd/technoprod && ./quick-commit.sh "🤖 Backup automatique $(date)"
```

## 📚 **AIDE ET RESSOURCES**

### **En cas de problème**
```bash
# Voir l'aide Git
git help

# Voir l'aide sur une commande spécifique
git help commit
git help push

# Voir l'état du repository
git status
git log --oneline -5
```

### **Commandes de récupération**
```bash
# J'ai fait une erreur dans le dernier commit
git commit --amend -m "Nouveau message corrigé"

# J'ai oublié d'ajouter un fichier au dernier commit
git add fichier-oublie.php
git commit --amend --no-edit

# Je veux revenir à l'état d'hier
git log --oneline           # Trouver le commit d'hier
git reset --hard COMMIT_ID  # Remplacer par l'ID trouvé
```

## 🎯 **OBJECTIF : AUTONOMIE COMPLÈTE**

Avec ce setup, vous pouvez :
- ✅ **Créer** un nouveau repository en 1 commande
- ✅ **Commiter** vos modifications quotidiennes rapidement
- ✅ **Collaborer** avec d'autres développeurs
- ✅ **Sauvegarder** automatiquement votre travail
- ✅ **Récupérer** en cas de problème

## 🚀 **PROCHAINES ÉTAPES RECOMMANDÉES**

1. **Testez le workflow** avec quelques commits de test
2. **Configurez GitHub Actions** pour CI/CD automatique
3. **Ajoutez des collaborateurs** au repository
4. **Créez des templates** d'issues et de PR
5. **Configurez branch protection** sur main

---

**Vous êtes maintenant 100% autonome sur Git/GitHub ! 🎉**

En cas de doute, consultez ce guide ou demandez de l'aide sur les forums Git/GitHub.