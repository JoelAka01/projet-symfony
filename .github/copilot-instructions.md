# SEO GEO AI — Contexte Projet

## Vue d'ensemble

Plateforme SaaS de gestion de visibilité SEO, GEO et IA. Permet l'audit de sites web, le suivi de mots-clés, la génération de contenu IA, le geo-targeting, et la gestion de backlinks pour les équipes marketing digital.

## Stack Technique

| Composant | Technologie |
|-----------|-------------|
| Framework | Symfony 7.4 |
| PHP | 8.3 (Alpine) |
| Base de données | PostgreSQL 16 |
| API | API Platform 4.x (JSON-LD) |
| Frontend | Twig + Asset Mapper + Stimulus |
| Queue | Symfony Messenger (transport Doctrine) |
| Temps réel | Mercure |
| IA | Claude (Anthropic API) |
| PDF | DomPDF |
| Monitoring | Sentry |
| Tests | PHPUnit + Faker + Infection (mutation) |
| Qualité | PHPStan + PHP CS Fixer |
| Conteneurisation | Docker (compose.yaml) |
| Email (dev) | Mailpit |

## Architecture & Conventions

### Structure des dossiers

```
src/
├── ApiResource/       # Ressources API Platform
├── Command/           # Commandes CLI Symfony
├── Controller/        # Contrôleurs web (Twig)
├── DataFixtures/      # Fixtures Faker pour tests/dev
├── Dto/               # Data Transfer Objects
├── Entity/            # Entités Doctrine (30+ entités)
├── Enum/              # Backed enums PHP 8.1+
├── EventSubscriber/   # Event subscribers Symfony
├── Exception/         # Exceptions métier custom
├── Form/              # Form types Symfony
├── Message/           # Messages async (Messenger)
├── MessageHandler/    # Handlers pour les messages
├── Repository/        # Repositories Doctrine
├── Security/          # Authenticator, Voters, Checkers
├── Service/           # Services métier (organisés par domaine)
└── Twig/              # Extensions Twig
```

### Conventions de code

- **Identifiants** : UUID v7 via `UuidPrimaryKeyTrait` sur toutes les entités
- **Timestamps** : `TimestampableTrait` (createdAt/updatedAt automatiques)
- **Enums** : Backed enums PHP pour tous les statuts (`AuditStatus`, `ArticleStatus`, `PaymentStatus`, etc.)
- **Services** : Organisés par domaine dans `src/Service/` (Ai/, Audit/, Billing/, Cms/, Content/, Crawler/, Project/, Report/)
- **Tests** : Unit + Functional, base de données séparée `app_test`
- **Nommage** : PascalCase entités, camelCase méthodes, snake_case colonnes DB
- **Doctrine** : Attributs PHP 8 (#[ORM\Entity], #[ORM\Column], etc.)
- **API Platform** : Attributs PHP 8 (#[ApiResource])
- **Validation** : Constraints Symfony via attributs

### Patterns clés

- **Multi-tenancy** : Organization → OrganizationUser → User (relation N:N)
- **Polymorphisme** : ContentItem (abstract) → Article, Report (héritage single-table ou joined)
- **Async processing** : Messages Messenger pour audits et analyses IA (RunWebsiteAuditMessage, RunClaudeAnalysisMessage)
- **Quotas & Rate limiting** : AnalysisQuotaManager avec tracking par user/org/IP
- **Abonnements** : Plans STARTER/PRO/ENTERPRISE avec limites de fonctionnalités
- **Voters** : ProjectVoter pour le contrôle d'accès aux projets (owner, team, guests)

## Entités principales et relations

### Utilisateurs & Organisations
- `User` : email/password, rôles (ROLE_USER, ROLE_MANAGER, ROLE_ADMIN), 2FA, vérification email
- `Organization` : multi-tenant, white-label
- `OrganizationUser` : table pivot org↔user avec rôle (UserRole: OWNER/ADMIN/EDITOR/VIEWER)
- `ProjectInvitation` : invitations par email avec token

### Projets & Audits
- `Project` : entité centrale, owner + organization, langue/pays cible, scores SEO/GEO
- `Domain` : domaines web avec méthodes de vérification
- `Audit` : audits de site (QUEUED→RUNNING→COMPLETED→FAILED), crawl + analyse IA
- `AuditPage` : pages crawlées individuelles avec métriques Core Web Vitals
- `AuditIssue` : problèmes SEO (sévérité: critical/high/medium/low/info)

### SEO & Contenu
- `Keyword` : mots-clés avec volume, difficulté, CPC, intention
- `KeywordCluster` : groupes de mots-clés
- `KeywordRanking` : historique de positionnement
- `Article` : contenu optimisé SEO (extends ContentItem), statuts DRAFT→PUBLISHED→ARCHIVED
- `ArticleImage` : médias associés aux articles
- `Report` : rapports PDF/CSV

### Backlinks
- `Backlink` : échanges de liens (PROPOSED→ACTIVE→REMOVED)
- `BacklinkExchange` : négociations de partenariats
- `BacklinkSite` : sites partenaires

### GEO & Analytics
- `GeoPrompt` : prompts géo-ciblés
- `GeoResult` : résultats régionaux
- `GeoDailySnapshot` / `AnalyticsDailySnapshot` : séries temporelles

### CMS & Intégrations
- `CmsConnection` : connexions WordPress/Shopify (credentials chiffrés)
- `CmsPublication` : sync de contenu publié
- `ApiKey` : tokens d'authentification API

### Billing
- `Subscription` : abonnements (SubscriptionPlan: STARTER/PRO/ENTERPRISE/CUSTOM)
- `Payment` : paiements en EUR (PaymentStatus: PENDING/PAID/FAILED/REFUNDED)
- `AiUsage` : ledger de tokens Claude (provider/model/operation)
- `AnalysisQuotaUsage` : quotas de rate limiting

### Audit trail
- `AuditLog` : événements système (actions utilisateur, changements ressources)
- `RateLimitEvent` : violations de rate limit

## Services métier

| Domaine | Services clés | Rôle |
|---------|---------------|------|
| Audit | `WebsiteAuditRunner`, `AuditProgressStatusBuilder`, `AuditInsightsBuilder`, `AuditProgressNotifier` | Orchestration crawl + analyse, suivi progression, notifications Mercure |
| AI | `ClaudeSeoAnalysisService`, `ClaudeSeoAnalysisSchema`, `ClaudeSeoAnalysisResponseParser`, `AiUsageRecorder` | Appels API Claude, parsing réponses structurées, tracking tokens |
| Content | `ClaudeArticleWriterService`, `AuditArticleDraftFactory`, `ArticleHtmlSanitizer` | Génération articles IA, sanitisation HTML |
| Billing | `AnalysisQuotaManager`, `SubscriptionManager`, `PlanCatalog`, `BillingEmailService`, `ClientIpHasher` | Quotas, plans, facturation |
| Project | `ProjectManager`, `ProjectWebsiteUrlNormalizer` | Gestion projets, normalisation URLs |
| Crawler | Services de crawl web | Crawl configurable (profondeur/limite pages) |
| Report | `AuditPdfGenerator` | Génération rapports PDF |

## Sécurité

- **Authentification** : `LoginFormAuthenticator` (formulaire custom)
- **Vérification email** : `VerifiedUserChecker` bloque les comptes non vérifiés
- **Hiérarchie rôles** : ROLE_USER < ROLE_MANAGER < ROLE_ADMIN
- **Voters** : `ProjectVoter` (accès projets par owner/team/guests)
- **Tokens** : `AccountTokenService` (génération + hash pour reset/vérification)
- **Accès public** : login, register, verify-email, password-reset, invitations projets
- **Admin** : `/admin` requiert ROLE_ADMIN

## Commandes Make

| Commande | Action |
|----------|--------|
| `make install` | Build + up Docker |
| `make sh` | Shell dans le conteneur PHP |
| `make migrate` | Exécuter les migrations |
| `make fixtures` | Charger les fixtures |
| `make test` | Lancer PHPUnit (Docker) |
| `make phpstan` | Analyse statique |
| `make lint` / `make lint-fix` | PHP CS Fixer (check/fix) |
| `make mutation` | Tests de mutation (Infection) |
| `make ci-local` | lint + phpstan + tests (local) |
| `make logs` | Logs Docker |

Variantes `-local` disponibles pour exécution sans Docker.

## Infrastructure Docker

- **php** : App Symfony (PHP 8.3 Alpine, extensions: intl, pdo_pgsql, opcache, zip)
- **database** : PostgreSQL 16
- **mailpit** : UI emails de test (port 8025)
- **mercure** : Hub temps réel

**Ports** : App → localhost:8080, Mailpit → localhost:8025

## Tests

- **Unit** (`tests/Unit/`) : Services isolés (AI, Audit, Billing, Cms, Content, Form, Project, Report, Security)
- **Functional** (`tests/Functional/`) : Pages web, flux complets (Admin, Auth, Billing, Projects, Settings, Invitations)
- **DB test** : PostgreSQL `app_test` séparée
- **Fixtures** : Faker pour données réalistes
- **Qualité** : PHPStan niveau strict, PHP CS Fixer, Infection pour mutation testing

## Enums (src/Enum/)

- `UserRole` : OWNER, ADMIN, EDITOR, VIEWER
- `ProjectStatus` : ACTIVE, ARCHIVED
- `AuditStatus` : QUEUED, RUNNING, COMPLETED, FAILED
- `ArticleStatus` : DRAFT, PUBLISHED, ARCHIVED
- `PaymentStatus` : PENDING, PAID, FAILED, REFUNDED
- `SubscriptionPlan` : STARTER, PRO, ENTERPRISE, CUSTOM
- `SubscriptionStatus` : statuts abonnement
- `CmsProvider` : WORDPRESS, SHOPIFY
- `GeoProvider` : fournisseurs GEO
- `BacklinkStatus` : PROPOSED, ACTIVE, REMOVED
- `AnalysisQuotaStatus` : ACTIVE, EXCEEDED, RESET
- `ReportStatus` : QUEUED, GENERATED, FAILED, SENT
