# E-Wallet - Gestion Factures, Services et Produits

Application Symfony 6.4 pour la gestion complète des Factures, Services et Produits.

## 🚀 Fonctionnalités

### CRUD Complet
- ✅ **Factures**: Create, Read, Update, Delete avec relations Service/Produit
- ✅ **Services**: Create, Read, Update, Delete avec calcul de durée
- ✅ **Produits**: Create, Read, Update, Delete avec gestion des stocks

### Validation Avancée
- ✅ Validation HTML5 désactivée au niveau des formulaires
- ✅ Validation serveur au niveau des Entités (Symfony Validator)
- ✅ Validateurs personnalisés:
  - `ValidDateRange`: Vérifie que la date d'échéance > date de facture
  - `RequireServiceOrProduit`: Vérifie qu'au moins un Service ou Produit est lié
- ✅ Validation métier au niveau des Contrôleurs

### Services Métier Avancés
- **ProduitService**: Filtres par type/statut, compteurs, calcul montants
- **ServiceService**: Filtres par type/statut, calculs, moyenne tarif
- **FactureService**: Filtres avancés, détection factures expirées, taux recouvrement

### Statistiques Back-Office
- 📊 **Chiffre d'affaires total** et par statut
- 📊 **Factures impayées** avec montant total
- 📊 **Factures expirées** avec détection automatique
- 📊 **Taux de recouvrement** et d'impayé
- 📦 Statistiques produits (vendus, disponibles, expirés)
- ⚙️ Statistiques services (actifs, suspendus, expirés)

## 📋 Installation

### Prérequis
- PHP 8.2+
- Composer
- MariaDB 10.4.32+
- Symfony CLI (optionnel)

### Étapes d'Installation

1. **Cloner le repository**
```bash
git clone https://github.com/younsiahmed9/Pidevsymfony.git
cd Pidevsymfony
```

2. **Installer les dépendances**
```bash
composer install
```

3. **Configurer la base de données**
Éditer le fichier `.env`:
```
DATABASE_URL="mysql://root:@127.0.0.1:3306/service_et_produit?serverVersion=10.4.32-MariaDB&charset=utf8mb4"
```

4. **Créer la base de données et exécuter les migrations**
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
```

5. **Démarrer le serveur de développement**
```bash
symfony serve
# ou
php -S 127.0.0.1:8000 -t public
```

6. **Accéder à l'application**
Ouvrir `http://localhost:8000` dans le navigateur

## 📚 Routes Principales

| Route | Description |
|-------|-------------|
| `/` | Page d'accueil (redirection vers dashboard) |
| `/stats/dashboard` | Dashboard avec statistiques |
| `/facture` | Liste des factures |
| `/facture/new` | Créer une facture |
| `/facture/{id}` | Voir une facture |
| `/facture/{id}/edit` | Modifier une facture |
| `/service` | Liste des services |
| `/service/new` | Créer un service |
| `/produit` | Liste des produits |
| `/produit/new` | Créer un produit |

## 🏗️ Architecture

### Structure du Projet
```
src/
├── Controller/          # Contrôleurs
│   ├── FactureController.php
│   ├── ServiceController.php
│   ├── ProduitController.php
│   ├── StatsController.php
│   └── HomeController.php
├── Entity/             # Entités Doctrine
│   ├── Facture.php
│   ├── Service.php
│   └── Produit.php
├── Form/               # Formulaires Symfony
│   ├── FactureType.php
│   ├── ServiceType.php
│   └── ProduitType.php
├── Service/            # Services métier
│   ├── FactureService.php
│   ├── ServiceService.php
│   └── ProduitService.php
├── Repository/         # Repositories Doctrine
│   ├── FactureRepository.php
│   ├── ServiceRepository.php
│   └── ProduitRepository.php
└── Validator/          # Validateurs personnalisés
    ├── ValidDateRange.php
    ├── RequireServiceOrProduit.php
    └── leurs Validators
```

## 📊 Entités et Relations

### Facture
- `id_facture` (PK)
- `numeroFacture` (UNIQUE)
- `montant` (DECIMAL 10,2)
- `dateFacture` (DATE)
- `dateEcheance` (DATE) - Contrainte: > dateFacture
- `statut` (ENUM: en_attente, payee, impayee)
- `id_service` (FK) - Nullable
- `id_produit` (FK) - Nullable

### Service
- `id_service` (PK)
- `nomService` (VARCHAR 100)
- `typeService` (ENUM: abonnement, facture)
- `tarif` (DECIMAL 10,2)
- `frequence` (ENUM: mensuel, annuel)
- `dateDebut`, `dateFin` (DATE)
- `statut` (ENUM: actif, suspendu, expire)

### Produit
- `id_produit` (PK)
- `nomProduit` (VARCHAR 100)
- `typeProduit` (ENUM: carte_cadeau, carte_abonnement, carte_prepayee)
- `montant` (DECIMAL 10,2)
- `codeUnique` (VARCHAR 100, UNIQUE)
- `statut` (ENUM: disponible, vendu, expire)
- `dateCreation` (DATE)

## 🔍 Validations

### Au Niveau de l'Entité
- **Facture**: ValidDateRange, RequireServiceOrProduit
- **Produit**: NotBlank, Length, Positive, Unique(codeUnique)
- **Service**: NotBlank, Length, Positive

### Au Niveau du Contrôleur (Validation Métier)
- Au moins un Service ou Produit doit être lié à une Facture
- dateEcheance > dateFacture
- Montants positifs

### Formulaires
- Validation HTML5 désactivée: `novalidate`
- Affichage des erreurs au niveau de chaque champ
- Messages Flash pour les succès/erreurs

## 🌿 Branche Déploiement

Tous les commits sont poussés sur la branche `service`:
```bash
git checkout service
```

### Historique des Commits

1. **Commit 1**: CRUD Produit avec validation et formulaires
2. **Commit 2**: CRUD Service avec validation et formulaires
3. **Commit 3**: CRUD Facture avec relations FK et validation
4. **Commit 4**: Services métier avancés (filtres, calculs)
5. **Commit 5**: Validateurs personnalisés
6. **Commit 6**: Dashboard statistiques basiques
7. **Commit 7**: Polish, navigation, et documentation

## 🧪 Tests Recommandés

### Tests Manuels
- [ ] Créer un Produit
- [ ] Créer un Service
- [ ] Créer une Facture avec relations (Service ET/OU Produit)
- [ ] Vérifier validation: date d'échéance > date facture
- [ ] Vérifier validation: au moins un Service ou Produit
- [ ] Modifier une Facture
- [ ] Supprimer une Facture
- [ ] Consulter le Dashboard
- [ ] Vérifier les statistiques (CA, factures impayées, expirées)

### Tests Formulaires
- [ ] Soumettre formulaire vide
- [ ] Soumettre montant négatif
- [ ] Soumettre montant zéro
- [ ] Soumettre sans Service ET sans Produit
- [ ] Soumettre date d'échéance avant date de facture
- [ ] Vérifier les messages d'erreur affichés

## 📝 Améliorations Futures

1. **Authentification**: Ajouter User + gestion de rôles
2. **Export PDF/Excel**: Factures exportables
3. **Graphiques**: Charts.js pour visualiser les tendances
4. **Pagination**: Lister avec pagination
5. **Recherche**: Recherche par critères multiples
6. **Audit**: Logger les modifications

## 📄 Licence

MIT - Voir LICENSE pour plus de détails

## 👨‍💻 Auteur

Younsi Ahmed - [GitHub](https://github.com/younsiahmed9)

---

**Version**: 1.0.0  
**Dernière mise à jour**: Avril 2026
