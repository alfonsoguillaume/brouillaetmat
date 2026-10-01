# ♟️ Brouilla & Mat

Site web du club d'échecs de Brouilla (66), ouvert en 2024 à tous à partir de 7 ans. Avant ce site, tout se gérait sur
papier et via un groupe WhatsApp (inscriptions, tournois, prêts de livres) — avec des informations qui se perdaient
régulièrement.

Projet réalisé dans le cadre d'un stage / titre professionnel **Développeur Web et Web Mobile (DWWM)**.

## Fonctionnalités

- **Authentification** : inscription avec validation manuelle par un admin, connexion par token JWT, mot de passe
  oublié, changement d'email avec confirmation par lien
- **Gestion des membres** (admin) : validation/refus des inscriptions, modification, blocage, suppression
- **Articles** : actualités du club, publication réservée aux gestionnaires/admins
- **Bibliothèque** : catalogue d'ouvrages, emprunts, retours, archive des prêts
- **Documents administratifs** : upload et téléchargement protégés par rôle (fichiers stockés hors du dossier public,
  jamais accessibles par URL directe)
- **Tournois** : création, inscriptions, lancement, annulation, archive, classement
- **Jeu en ligne** : parties d'échecs en temps réel (proposer/accepter une partie), plateau interactif, chronomètre par
  joueur, détection de déconnexion, calcul du classement Elo (K=32)
- **Rôles** : membre / gestionnaire / admin, avec hiérarchie de droits et protection par rate limiting (anti brute-force
  sur la connexion, anti-spam sur l'inscription)

## Stack technique

**Backend** — API Symfony 8.1 (PHP 8.4)

- Doctrine ORM / Migrations (base MySQL/MariaDB)
- `lexik/jwt-authentication-bundle` pour l'authentification par token
- `nelmio/cors-bundle` pour autoriser le frontend Angular à consulter l'API
- `symfony/rate-limiter` pour le anti brute-force et l'anti-spam
- `symfony/mailer` (via Brevo) pour les emails transactionnels

**Frontend** — Angular 22 (composants standalone)

- RxJS pour la gestion des flux asynchrones
- [Chessground](https://github.com/lichess-org/chessground) pour l'affichage du plateau
- [chess.js](https://github.com/jhlywa/chess.js) pour les règles du jeu

## Structure du projet

```
brouillaetmat/
├── backend/     # API Symfony (src/Controller, Entity, Repository, Service...)
├── frontend/    # Application Angular (src/app)
├── LICENSE
└── README.md
```

## Prérequis

- PHP >= 8.4 (extensions `ctype`, `iconv`)
- [Composer](https://getcomposer.org/)
- Node.js + npm
- MySQL ou MariaDB
- [Symfony CLI](https://symfony.com/download) (recommandé)
- OpenSSL (pour générer les clés JWT)

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/alfonsoguillaume/brouillaetmat.git
cd brouillaetmat
```

### 2. Backend

```bash
cd backend
composer install
```

Créer un fichier `.env.local` (non suivi par Git) pour surcharger les variables sensibles — voir la
section [Variables d'environnement](#variables-denvironnement) ci-dessous.

Générer la paire de clés JWT (signature des tokens) :

```bash
php bin/console lexik:jwt:generate-keypair
```

Créer la base de données et appliquer les migrations :

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

Lancer le serveur :

```bash
symfony serve --no-tls
```

L'API est alors disponible sur `http://localhost:8000`.

### 3. Frontend

```bash
cd ../frontend
npm install
ng serve
```

Le site est alors disponible sur `http://localhost:4200`.

## Variables d'environnement

À définir dans `backend/.env.local` (jamais commité) :

| Variable         | Rôle                                                                                                                         |
|------------------|------------------------------------------------------------------------------------------------------------------------------|
| `APP_SECRET`     | Clé secrète interne de Symfony (chaîne aléatoire)                                                                            |
| `DATABASE_URL`   | Connexion à la base, ex : `mysql://utilisateur:motdepasse@127.0.0.1:3306/brouillaetmat?serverVersion=8.4.15&charset=utf8mb4` |
| `MAILER_DSN`     | Connexion au service d'envoi d'emails (Brevo)                                                                                |
| `JWT_PASSPHRASE` | Phrase de passe de la clé privée JWT (si définie à la génération)                                                            |

## Sécurité

- Mots de passe hashés, jamais stockés ni renvoyés en clair
- Règle de complexité renforcée (12 caractères minimum + majuscule/minuscule/chiffre/caractère spécial) — justifiée par
  la présence de données de mineurs
- Documents administratifs stockés hors du dossier public, servis uniquement via une route authentifiée qui vérifie le
  rôle
- Rôle et statut d'inscription toujours fixés côté serveur, jamais laissés au choix du client
- Chaque règle de validation du frontend est dupliquée côté backend (le frontend peut toujours être contourné)

## Licence

Distribué sous licence MIT — voir le fichier [LICENSE](LICENSE).

## Auteur

Guillaume Alfonso