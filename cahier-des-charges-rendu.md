# Cahier des Charges — Plateforme SEO + GEO + IA (Symfony SaaS)

---

## 1. Introduction

### 1.1 Contexte

Le projet est une plateforme SaaS inspirée de SEMrush / Ahrefs, enrichie d'une couche native **IA-first** (GEO : Generative Engine Optimization).

Elle permet aux utilisateurs de :

- connecter leur site en 5 minutes
- analyser leur SEO + visibilité IA
- générer automatiquement du contenu optimisé IA
- publier sur leur CMS
- obtenir des backlinks via un réseau interne
- suivre leurs performances SEO + IA

---

## 2. Objectif du produit

Créer une plateforme autonome capable de :

1. Auditer un site web automatiquement
2. Identifier les opportunités SEO + GEO
3. Générer des contenus IA-first (articles + images)
4. Publier automatiquement sur CMS
5. Distribuer des backlinks contextualisés
6. Monitorer la visibilité dans les IA (ChatGPT, Gemini, Perplexity)
7. Fournir un reporting mensuel automatisé

---

## 3. Périmètre fonctionnel

### Inclus

- SEO technique
- SEO sémantique
- GEO tracking (IA visibility)
- Génération d'articles IA
- Génération d'images IA
- Publication CMS
- Backlinks marketplace
- Analytics dashboard
- Reporting automatisé

### Exclu (MVP initial)

- Publicité payante (Google Ads, Meta Ads)
- Gestion social media
- CRM complet
- Scraping illégal de données protégées

---

## 4. Parcours utilisateur principal

### Flow global

1. Inscription
2. Connexion site (WordPress / Shopify / Webflow / API)
3. Audit automatique
4. Détection mots-clés + opportunités
5. Génération contenu IA
6. Publication automatique
7. Acquisition backlinks réseau
8. Monitoring IA visibility
9. Rapport mensuel

---

## 5. Fonctionnalités détaillées

### MODULE 01 — Onboarding & Connexion CMS

**Objectif :** Permettre une intégration en moins de 5 minutes.

**CMS supportés :**
- WordPress
- Shopify
- Webflow
- Wix
- Webhook custom

| Référence | Description |
|-----------|-------------|
| REQ-ONB-001 | Connexion OAuth ou API key |
| REQ-ONB-002 | Validation automatique du domaine |
| REQ-ONB-003 | Test de publication (article dummy) |

---

### MODULE 02 — Audit SEO Technique

**Objectif :** Analyser la santé globale du site.

| Référence | Description |
|-----------|-------------|
| REQ-SEO-001 | Crawl complet du site |
| REQ-SEO-002 | Détection : 404, 500, redirections, duplicate content, pages orphelines |
| REQ-SEO-003 | Analyse performance : Core Web Vitals, vitesse, indexabilité |
| REQ-SEO-004 | Score SEO global (0–100) |

---

### MODULE 03 — GEO Monitoring (IA Visibility)

**Objectif :** Mesurer la présence dans les IA.

| Référence | Description |
|-----------|-------------|
| REQ-GEO-001 | Monitoring de 300 prompts |
| REQ-GEO-002 | Sources : ChatGPT, Gemini, Perplexity, Claude |
| REQ-GEO-003 | Détection : mention marque, citation URL, concurrents cités |
| REQ-GEO-004 | Score GEO (0–100) |
| REQ-GEO-005 | Historique journalier des réponses IA |

---

### MODULE 04 — Keyword Intelligence (SEO + GEO)

**Objectif :** Identifier les opportunités de croissance.

| Référence | Description |
|-----------|-------------|
| REQ-KW-001 | Recherche par domaine / niche |
| REQ-KW-002 | Affichage : volume, difficulté, intention, CPC |
| REQ-KW-003 | Détection Fanout Keywords (IA search expansion) |
| REQ-KW-004 | Clustering automatique des mots-clés |

---

### MODULE 05 — Content Engine IA-First

**Objectif :** Générer du contenu SEO + GEO optimisé.

| Référence | Description |
|-----------|-------------|
| REQ-CONT-001 | Génération articles automatisée (20/mois minimum) |
| REQ-CONT-002 | Chaque article contient : 1500+ mots, structure SEO H1-H2-H3, FAQ, liens internes, sources externes |
| REQ-CONT-003 | Génération de 3 images IA réalistes/article |
| REQ-CONT-004 | Optimisation GEO (réponses IA-friendly) |
| REQ-CONT-005 | Détection cannibalisation contenu |

---

### MODULE 06 — CMS Publishing Engine

| Référence | Description |
|-----------|-------------|
| REQ-CMS-001 | Publication automatique ou planifiée |
| REQ-CMS-002 | Support : WordPress REST API, Shopify API, Webflow CMS API |
| REQ-CMS-003 | Gestion médias (images IA incluses) |
| REQ-CMS-004 | Gestion catégories + tags |

---

### MODULE 07 — Backlink Network *(Core Feature différenciante)*

**Objectif :** Créer un réseau de 2000+ utilisateurs pour échange de backlinks.

| Référence | Description |
|-----------|-------------|
| REQ-BL-001 | Marketplace interne de backlinks |
| REQ-BL-002 | Matching automatique basé sur : thématique, autorité domaine, pertinence SEO |
| REQ-BL-003 | Score qualité backlink (0–100) |
| REQ-BL-004 | Détection : lien supprimé, lien cassé |
| REQ-BL-005 | Historique des échanges |

---

### MODULE 08 — Analytics Dashboard

| Référence | Description |
|-----------|-------------|
| REQ-ANA-001 | Vue globale : SEO score, GEO score, trafic, backlinks, contenus |
| REQ-ANA-002 | Suivi performance mots-clés |
| REQ-ANA-003 | Suivi visibilité IA |
| REQ-ANA-004 | ROI estimé SEO/GEO |

---

### MODULE 09 — Reporting Automatisé

| Référence | Description |
|-----------|-------------|
| REQ-RPT-001 | Rapport mensuel PDF |
| REQ-RPT-002 | Envoi email automatique |
| REQ-RPT-003 | Mode white-label (agences) |
| REQ-RPT-004 | Export CSV + API |

---

## 6. Architecture technique (Symfony)

### Backend

- Symfony 7 LTS
- API Platform
- Doctrine ORM
- Symfony Messenger (queues)
- Symfony Scheduler (jobs)

### Modules Symfony

```
src/
├── Auth
├── User
├── Project
├── SeoAudit
├── GeoMonitoring
├── KeywordEngine
├── ContentEngine
├── CmsIntegration
├── BacklinkNetwork
├── Analytics
├── Reporting
└── Shared
```

### Infrastructure

- PostgreSQL
- Redis (cache + queue)
- S3 / Cloud storage
- Elasticsearch (recherche)
- Docker

### IA Providers

- OpenAI
- Anthropic
- Gemini

---

## 7. Modèle de données

**Entités principales :**

- User
- Project
- Domain
- Audit
- Keyword
- KeywordCluster
- Article
- ArticleImage
- GeoPrompt
- GeoResult
- Backlink
- BacklinkExchange
- Report

---

## 8. Règles de performance

| Référence | Règle |
|-----------|-------|
| PERF-001 | Audit site 10 000 pages < 30 min |
| PERF-002 | Génération article < 90 sec |
| PERF-003 | Publication CMS < 10 sec |
| PERF-004 | Dashboard < 2 sec load time |
| PERF-005 | Uptime ≥ 99.9% |

---

## 9. Sécurité

- JWT authentication
- OAuth (Google)
- Chiffrement API keys
- Rate limiting
- Audit logs
- 2FA optionnel

---

## 10. MVP recommandé

### Phase 1 — Core

- Auth
- CMS connect
- SEO audit
- Dashboard

### Phase 2

- Content AI engine
- Auto publishing

### Phase 3

- GEO tracking

### Phase 4

- Backlinks network

---

## 11. Différenciation produit

Ce produit ne doit pas être un simple clone de SEMrush.

**Différences clés :**

- GEO tracking (IA visibility)
- Génération automatique de contenu
- Publication autonome
- Backlinks réseau interne
- Boucle complète SEO → contenu → distribution → backlinks

---

## 12. Résumé produit

> **C'est une machine autonome de croissance organique :**
>
> SEO + IA + contenu + backlinks + analytics = **croissance automatisée continue sans intervention humaine.**
