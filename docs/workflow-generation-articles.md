# Workflow Complet : Génération d'Articles de Blog

## Vue d'ensemble

La génération d'articles suit un pipeline en 7 étapes, du mot-clé jusqu'à la publication sur un CMS externe (WordPress / Shopify).

```
Mot-clé → Création article → Génération IA (Claude) → Sanitisation → Revue → Publication CMS
```

---

## 1. Création de l'article (2 chemins)

### Chemin A — Création manuelle

- **Contrôleur** : `ArticleController::new()`
- L'utilisateur remplit un formulaire (`ArticleType`) avec :
  - Titre, SEO title, meta description, slug
  - Sélection du **mot-clé primaire** et des **mots-clés cibles** (filtrés par projet)
- **Statut initial** : `DRAFT`

### Chemin B — Depuis un audit complété

- **Contrôleur** : `ArticleController::createFromAudit()`
- **Service** : `AuditArticleDraftFactory`
- Le service extrait les données de l'analyse IA de l'audit :
  - Titre suggéré depuis `ai_analysis.suggested_title`
  - HTML starter construit à partir du résumé + FAQ de l'audit
  - **Auto-détection de keywords** : jusqu'à 12 mots-clés extraits de `keyword_analysis.detected_target_keywords`, créés ou retrouvés en base
- **Statut initial** : `GENERATED`

---

## 2. Sélection des mots-clés

Les mots-clés sont liés à l'article via :

| Relation | Type | Description |
|----------|------|-------------|
| `primaryKeyword` | FK → Keyword | Mot-clé principal ciblé |
| `targetKeywords` | ManyToMany → Keyword | Mots-clés secondaires |

Chaque `Keyword` contient :
- `term` — le mot-clé
- `searchVolume` — volume mensuel
- `difficulty` — score 0-100
- `cpc` — coût par clic
- `intent` — informational, transactional, commercial, navigational

---

## 3. Déclenchement de la génération IA

L'utilisateur soumet le formulaire `ArticleGenerationType` avec :

| Paramètre | Description | Contraintes |
|-----------|-------------|-------------|
| `brief` | Instructions de rédaction | 20-4000 caractères, obligatoire |
| `tone` | Ton de l'article | `expert_clear`, `friendly_practical`, `professional_concise`, `educational_beginner` |
| `targetWordCount` | Nombre de mots cible | 500-4000 (défaut : 1400) |
| `includeFaq` | Inclure une section FAQ | Booléen (défaut : true) |

---

## 4. Appel à Claude (SYNCHRONE)

Le service `ClaudeArticleWriterService` effectue un appel **synchrone** (pas de queue Messenger) :

1. **Assemblage du contexte** :
   - Nom du projet, langue, pays cible
   - Titre courant de l'article, SEO title, meta description
   - Mot-clé primaire + mots-clés cibles
   - Brief et paramètres de génération
   - Dernière `ai_analysis` d'un audit complété (si disponible)

2. **Appel API Claude** :
   - Modèle : `claude-haiku-4-5-20251001`
   - Max tokens : 12 000
   - Température : 0.35 (faible pour la cohérence)
   - Timeout : 180 secondes

3. **System prompt** :
   - Rédiger du HTML sémantique SEO/GEO-ready
   - Ne pas inventer de statistiques, citations ou études
   - Utiliser le mot-clé primaire naturellement dans le titre, intro, au moins un H2, et conclusion
   - Retourner uniquement du JSON valide

---

## 5. Réponse Claude → Mise à jour de l'article

### Structure JSON retournée par Claude

```json
{
  "title": "titre visible de l'article",
  "seo_title": "max 60 caractères",
  "meta_description": "140-160 caractères",
  "excerpt": "résumé court",
  "slug": "slug-url-en-minuscules",
  "content_html": "HTML sémantique complet (h2, h3, p, ul, table...)",
  "faq": [
    { "question": "...", "answer": "..." }
  ],
  "image_suggestions": [
    { "prompt": "prompt de génération", "alt_text": "texte alt SEO", "placement": "après quelle section" }
  ],
  "internal_link_suggestions": [
    { "anchor": "texte d'ancre", "target_topic": "sujet cible" }
  ],
  "external_source_suggestions": [
    { "claim": "affirmation à sourcer", "source_type": "official documentation|research|statistics" }
  ]
}
```

### Traitement du résultat

- Le HTML est **sanitisé** par `ArticleHtmlSanitizer` :
  - Suppression des tags dangereux (script, style, iframe, form...)
  - Seuls les tags sémantiques autorisés : `p, h2, h3, h4, ul, ol, li, strong, em, blockquote, a, table, thead, tbody, tr, th, td, code, pre, hr, br`
  - Validation des URLs (http, https, mailto, relatifs uniquement)
  - Nettoyage des attributs non autorisés
- L'article est mis à jour avec tous les champs générés
- Le `wordCount` est calculé automatiquement
- **Statut** : passe à `GENERATED`
- L'usage IA est enregistré via `AiUsageRecorder` (tokens input/output, modèle, opération)

---

## 6. Publication vers un CMS

L'utilisateur choisit une `CmsConnection` et le mode (publié ou brouillon) via le formulaire `CmsPublishType`.

### WordPress (REST API v2)

1. Upload des images vers la Media Library (`/wp-json/wp/v2/media`)
2. Création ou mise à jour du post (`/wp-json/wp/v2/posts`)
3. Mapping : titre → `title`, HTML → `content`, slug → `slug`, excerpt → `excerpt`, image → `featured_media`
4. **Auth** : HTTP Basic + Application Password (chiffré en base)

### Shopify (GraphQL Admin API)

1. Mutation `articleCreate` ou `articleUpdate`
2. Mapping similaire avec les champs Shopify
3. **Auth** : Custom App Access Token

### Entité `CmsPublication`

Chaque tentative de publication est tracée :

| Champ | Description |
|-------|-------------|
| `article_id` | Article publié |
| `cms_connection_id` | Connexion CMS utilisée |
| `externalPostId` | ID du post sur le CMS (WordPress post ID, Shopify article ID) |
| `externalUrl` | URL publique sur le CMS |
| `status` | DRAFT, PUBLISHED, FAILED |
| `publishedAt` | Date de publication |
| `errorMessage` | Détail de l'erreur si échec |

---

## 7. Cycle de vie complet

```
DRAFT ──(génération IA)──→ GENERATED ──(publish CMS)──→ PUBLISHED
                                      ──(erreur CMS)──→ FAILED
                                      ──(planifié)────→ SCHEDULED
```

---

## Points techniques importants

- **Pas de traitement asynchrone** pour la génération d'articles — tout est synchrone pendant la requête HTTP (contrairement aux audits qui utilisent Symfony Messenger)
- **Sécurité** : sanitisation HTML stricte contre le XSS, validation des URLs
- **Billing** : chaque génération est enregistrée dans `AiUsage` pour le suivi de consommation
- **Mots-clés auto-détectés** : lors de la création depuis un audit, jusqu'à 12 keywords sont automatiquement extraits et créés/liés

## Fichiers clés

| Fichier | Rôle |
|---------|------|
| `src/Entity/Article.php` | Entité article (champs, relations, statuts) |
| `src/Controller/ArticleController.php` | Actions web (new, createFromAudit, generate, publish) |
| `src/Form/ArticleType.php` | Formulaire de création/édition |
| `src/Form/ArticleGenerationType.php` | Formulaire de paramètres de génération IA |
| `src/Form/CmsPublishType.php` | Formulaire de publication CMS |
| `src/Service/Content/ClaudeArticleWriterService.php` | Appel API Claude + parsing réponse |
| `src/Service/Content/AuditArticleDraftFactory.php` | Création d'article depuis un audit |
| `src/Service/Content/ArticleHtmlSanitizer.php` | Sanitisation HTML (sécurité XSS) |
| `src/Service/Cms/CmsPublishingService.php` | Orchestration publication CMS |
| `src/Service/Cms/WordPressCmsClient.php` | Client WordPress REST API |
| `src/Service/Cms/ShopifyCmsClient.php` | Client Shopify GraphQL |
| `src/Entity/CmsPublication.php` | Suivi des publications CMS |
| `src/Service/Ai/AiUsageRecorder.php` | Enregistrement consommation tokens IA |
