# Plan : Implémentation V2 — Topical Authority Engine

## TL;DR

Ajouter un pipeline multi-agent asynchrone (V2) à côté du système V1 existant (qui reste disponible comme "génération rapide"). Le pipeline V2 est orchestré via Symfony Messenger + Redis en **5 étapes** (pas 7) : SERP+Questions → Intent+Entities+Semantic → Brief+Outline → Article → SEO Score. Architecture : un service orchestrateur `ArticleGenerationPipelineService` qui dispatch des messages séquentiels, avec des entités Doctrine dédiées + un `PipelineRunLog` pour traçabilité complète. Chaque étape est relançable individuellement. Le provider SERP est abstrait derrière une interface.

---

## Décisions prises

- **Scope MVP** : 5 étapes de pipeline + SEO Score basique + Publication WP/Shopify existante
- **V1 conservée** : V1 = "génération rapide" (synchrone), V2 = "génération intelligente" (async pipeline)
- **5 étapes** (pas 7) : regroupement pour réduire coût/latence des appels Claude
- **SERP API** : Zenserp pour prototyper, derrière `SerpProviderInterface` pour swap futur
- **Multi-agent** : Orchestrateur Symfony natif (service + Messenger), chaque étape = un appel Claude spécialisé
- **Transport** : Redis (nouveau transport `pipeline` dédié)
- **Stockage** : Entités Doctrine dédiées + `PipelineRunLog` pour chaque appel IA
- **Statut pipeline** : Enum dédié multi-étapes
- **Retry par étape** : chaque étape relançable individuellement depuis l'UI
- **Pas de Mercure MVP** : polling/refresh côté UI suffit
- **Zenserp** : SERP complet (Top 10, PAA, Related Searches, Featured Snippets, Suggest)

---

## Phase A — Infrastructure & Fondations

### A1. Transport Redis pour Messenger
- Installer `symfony/redis-messenger`
- Configurer `config/packages/messenger.yaml` avec transport Redis
- Créer un transport dédié `pipeline` pour les messages V2 (séparé du transport `async` existant)
- Configurer retry policies (3 attempts, exponential backoff)
- Mettre à jour `compose.yaml` avec un service Redis
- Le transport `async` existant (Doctrine) reste pour les messages V1

### A2. Enum PipelineStatus
- Créer `src/Enum/PipelineStatus.php` :
  ```
  NEW, 
  SERP_ANALYZING, SERP_ANALYZED, 
  INTELLIGENCE_ANALYZING, INTELLIGENCE_ANALYZED,
  BRIEF_GENERATING, BRIEF_READY,
  CONTENT_GENERATING, CONTENT_GENERATED,
  SEO_OPTIMIZING, SEO_OPTIMIZED,
  READY_TO_PUBLISH, PUBLISHED, FAILED
  ```

### A3. Nouvelles entités Doctrine

**`TopicResearch`** — Entité racine du pipeline V2
- `id` (UUID), `project_id` (FK), `user_id` (FK qui a initié)
- `primaryKeyword` (string), `status` (PipelineStatus enum)
- `country`, `language`, `sector`, `audience`, `businessObjective`
- `currentStep` (string nullable), `failedStep` (string nullable), `errorMessage` (text nullable)
- `createdAt`, `updatedAt`, `completedAt`
- Relations : `serpAnalysis` (1:1), `intelligenceAnalysis` (1:1), `contentBrief` (1:1), `articleOutline` (1:1), `article` (N:1 nullable)
- Méthode `canRetryStep(string $step): bool`

**`SerpAnalysis`** — Résultats SERP + Questions (étape 1 combinée)
- `id`, `topicResearch_id` (FK)
- `competitors` (JSONB) — [{url, title, h1, h2s, wordCount, structure, faq, tables, media}]
- `serpFeatures` (JSONB) — {featured_snippets, paa, related_searches, images, videos}
- `contentGaps` (JSONB) — [{topic, coverage, opportunity_score}]
- `questions` (JSONB) — [{question, intent, cluster, priority_score, source}]
- `averageWordCount` (int), `totalQuestions` (int)
- `rawSerpResponse` (JSONB) — réponse brute provider SERP
- `analyzedAt`

**`IntelligenceAnalysis`** — Intent + Entities + Semantic (étape 2 combinée)
- `id`, `topicResearch_id` (FK)
- `primaryIntent` (string)
- `intentBreakdown` (JSONB) — {informational: 0.6, commercial: 0.3, ...}
- `entities` (JSONB) — [{name, type, relevance_score, relations}]
- `semanticConcepts` (JSONB) — [{concept, cooccurrences, synonyms}]
- `analyzedAt`

**`ContentBrief`** — Brief + Outline combinés (étape 3 combinée)
- `id`, `topicResearch_id` (FK)
- `targetAudience` (text), `objective` (text)
- `intent` (string), `toneRecommendation` (string)
- `targetWordCount` (int)
- `keyEntities` (JSONB) — [{entity, importance, context}]
- `keyQuestions` (JSONB) — [{question, priority}]
- `competitorInsights` (JSONB)
- `cta` (text), `sources` (JSONB)
- `seoTargets` (JSONB) — {primary_keyword, secondary_keywords, lsi_terms}
- `outline` (JSONB) — [{level: 'h2'|'h3', title, key_points, questions_answered, entities_covered}]
- `faqSuggestions` (JSONB), `tableSuggestions` (JSONB)
- `estimatedWordCount` (int)
- `generatedAt`

**`PipelineRunLog`** — Traçabilité complète de chaque appel IA
- `id` (UUID), `topicResearch_id` (FK)
- `step` (string: 'serp_analysis', 'intelligence', 'brief_outline', 'article', 'seo_score')
- `attempt` (int, default 1)
- `promptSent` (TEXT) — prompt complet envoyé
- `rawResponse` (TEXT) — réponse IA brute
- `parsedResponse` (JSONB nullable) — réponse parsée
- `model` (string), `provider` (string)
- `inputTokens` (int), `outputTokens` (int), `totalCredits` (int)
- `durationMs` (int) — durée de l'appel en ms
- `status` (string: 'success', 'failed', 'retried')
- `errorMessage` (text nullable)
- `createdAt`

### A4. Migration Doctrine
- Créer migration pour toutes les nouvelles tables
- FK constraints, indexes sur `topic_research.status`, `topic_research.project_id`
- Index sur `pipeline_run_log.topic_research_id` + `pipeline_run_log.step`

### A5. SerpProviderInterface
- Créer `src/Service/Serp/SerpProviderInterface.php`
  ```php
  interface SerpProviderInterface {
      public function search(string $query, string $country, string $language): SerpResultDto;
      public function suggest(string $query, string $country, string $language): array;
  }
  ```
- Créer `src/Dto/SerpResultDto.php` — structure normalisée (organic, paa, related, features)
- L'implémentation Zenserp implémente cette interface
- Demain : DataForSEO, SerpAPI, etc. sans toucher au reste du code

---

## Phase B — SERP + Questions (Étape 1 du pipeline)

### B1. Client Zenserp
- Créer `src/Service/Serp/ZenserpProvider.php` implements `SerpProviderInterface`
  - Méthode `search()` : organic results, PAA, related searches, featured snippets
  - Méthode `suggest()` : Google Suggest / autocomplete
  - Gestion erreurs, rate limiting, timeout
  - Retry intégré (2 attempts)

### B2. Service SERP + Questions Analyzer (Claude)
- Créer `src/Service/Pipeline/SerpQuestionAnalyzerService.php`
  - Input : SerpResultDto + suggestions + keyword
  - **Un seul appel Claude** = Agent "SERP & Question Analyst"
  - Analyse concurrents + classifie questions + identifie gaps
  - Output : `SerpAnalysis` entity populated
  - Enregistre `PipelineRunLog`

### B3. Message + Handler
- `src/Message/Pipeline/AnalyzeSerpMessage.php` (contient `topicResearchId`)
- `src/MessageHandler/Pipeline/AnalyzeSerpHandler.php`
  - Fetch TopicResearch → set status SERP_ANALYZING
  - Call ZenserpProvider → call SerpQuestionAnalyzerService
  - Persist SerpAnalysis + PipelineRunLog
  - Set status SERP_ANALYZED
  - Dispatch next : `AnalyzeIntelligenceMessage`

---

## Phase C — Intent + Entities + Semantic (Étape 2)

### C1. Service Intelligence Analyzer
- Créer `src/Service/Pipeline/IntelligenceAnalyzerService.php`
  - Input : keyword + SerpAnalysis (competitors + questions)
  - **Un seul appel Claude** = Agent "Intelligence Analyst"
  - Détecte intent, extrait entities, identifie concepts sémantiques
  - Output : `IntelligenceAnalysis` entity
  - Enregistre `PipelineRunLog`

### C2. Message + Handler
- `src/Message/Pipeline/AnalyzeIntelligenceMessage.php`
- `src/MessageHandler/Pipeline/AnalyzeIntelligenceHandler.php`
  - Set status INTELLIGENCE_ANALYZING
  - Call IntelligenceAnalyzerService
  - Persist + set status INTELLIGENCE_ANALYZED
  - Dispatch : `GenerateBriefMessage`

---

## Phase D — Brief + Outline (Étape 3)

### D1. Service Brief & Outline Generator
- Créer `src/Service/Pipeline/BriefOutlineGeneratorService.php`
  - Input : TopicResearch + SerpAnalysis + IntelligenceAnalysis
  - **Un seul appel Claude** = Agent "Content Strategist & Architect"
  - Génère brief complet + outline structuré en une seule passe
  - Output : `ContentBrief` entity (contient brief + outline)
  - Enregistre `PipelineRunLog`

### D2. Message + Handler
- `src/Message/Pipeline/GenerateBriefMessage.php`
- `src/MessageHandler/Pipeline/GenerateBriefHandler.php`
  - Set status BRIEF_GENERATING
  - Assemble tout le contexte accumulé
  - Call BriefOutlineGeneratorService
  - Persist + set status BRIEF_READY
  - Dispatch : `GenerateArticleMessage`

---

## Phase E — Article Writer enrichi (Étape 4)

### E1. Service Article Writer V2
- Créer `src/Service/Pipeline/PipelineArticleWriterService.php`
  - Input : ContentBrief (brief + outline) + IntelligenceAnalysis (entities) + SerpAnalysis (questions)
  - **Un seul appel Claude** = Agent "Expert SEO Writer"
  - Token budget : 16000 (articles longs et complets)
  - Doit suivre l'outline section par section
  - Doit couvrir les questions identifiées
  - Doit intégrer les entités
  - Output : Article entity (content_html, faq, internal_links, external_sources, image_suggestions)
  - Enregistre `PipelineRunLog`

### E2. Message + Handler
- `src/Message/Pipeline/GenerateArticleMessage.php`
- `src/MessageHandler/Pipeline/GenerateArticleHandler.php`
  - Set status CONTENT_GENERATING
  - Crée ou met à jour l'entité Article liée au TopicResearch
  - Lie les keywords détectés
  - Sanitize HTML via ArticleHtmlSanitizer existant
  - Record AiUsage (existant)
  - Set status CONTENT_GENERATED
  - Dispatch : `OptimizeSeoMessage`

---

## Phase F — SEO Score (Étape 5)

### F1. Service SEO Scorer
- Créer `src/Service/Pipeline/SeoScorerService.php`
  - Input : Article HTML + ContentBrief (targets)
  - **Un seul appel Claude** = Agent "SEO Quality Reviewer"
  - Vérifie : keyword density, Hn structure, meta lengths, FAQ, alt suggestions
  - Scoring (0-100) + liste de recommandations textuelles
  - Peut appliquer micro-corrections (meta title trop long, etc.)
  - Output : score + recommendations array
  - Enregistre `PipelineRunLog`

### F2. Message + Handler
- `src/Message/Pipeline/OptimizeSeoMessage.php`
- `src/MessageHandler/Pipeline/OptimizeSeoHandler.php`
  - Set status SEO_OPTIMIZING
  - Call SeoScorerService
  - Set article.seoScore + article.status = GENERATED
  - Set TopicResearch status = READY_TO_PUBLISH
  - (Pas de Mercure MVP — l'UI poll le statut)

---

## Phase G — Retry par étape

### G1. Mécanisme de retry
- Chaque handler : try/catch → si erreur :
  - Set TopicResearch.status = FAILED
  - Set TopicResearch.failedStep = nom de l'étape
  - Set TopicResearch.errorMessage = message
  - Log dans PipelineRunLog (status = 'failed')

### G2. Service de retry
- Ajouter méthode dans `ArticleGenerationPipelineService` :
  ```php
  public function retryStep(TopicResearch $topicResearch): void
  ```
  - Vérifie que status = FAILED et failedStep est set
  - Re-dispatch le message correspondant à failedStep
  - Reset errorMessage, increment attempt dans le futur PipelineRunLog

### G3. UI retry
- Bouton "Relancer cette étape" dans la page show si status = FAILED
- Route POST `topic_research_retry` dans le controller

---

## Phase H — Orchestrateur, Controller & UI

### H1. Pipeline Orchestrator Service
- Créer `src/Service/Pipeline/ArticleGenerationPipelineService.php`
  - `start(Project $project, User $user, string $keyword, array $options): TopicResearch`
    - Crée TopicResearch avec status NEW
    - Dispatch `AnalyzeSerpMessage`
  - `retryStep(TopicResearch $topicResearch): void`
    - Re-dispatch le message de l'étape échouée

### H2. Controller
- Créer `src/Controller/TopicResearchController.php`
  - `index(Project $project)` : liste des pipelines du projet (avec statut)
  - `new(Project $project)` : formulaire de lancement
  - `show(Project $project, TopicResearch $topicResearch)` : détail + progression + résultats
  - `retry(Project $project, TopicResearch $topicResearch)` : relance étape échouée (POST + CSRF)

### H3. Form
- `src/Form/TopicResearchType.php`
  - `primaryKeyword` (string, obligatoire, 3-200 chars)
  - `country` (ChoiceType, défaut = project.targetCountry)
  - `language` (ChoiceType, défaut = project.defaultLanguage)
  - `sector` (TextType, optionnel)
  - `audience` (TextareaType, optionnel)
  - `businessObjective` (TextareaType, optionnel)

### H4. Templates (polling, pas Mercure)
- `templates/topic_research/index.html.twig` — liste avec badges de statut
- `templates/topic_research/new.html.twig` — formulaire de lancement
- `templates/topic_research/show.html.twig` — progression par étapes :
  - Barre de progression visuelle (5 étapes)
  - Résultats intermédiaires expandables (SERP, questions, entities, brief, outline)
  - Section article preview quand CONTENT_GENERATED
  - Bouton "Relancer" si FAILED
  - Bouton "Publier" si READY_TO_PUBLISH (renvoie vers le publish existant)
  - Auto-refresh JS toutes les 5s si pipeline en cours (Stimulus controller simple)

### H5. V1 conservée
- `ArticleController::generate()` reste inchangé
- Ajouter un choix dans l'UI article :
  - "Génération rapide (V1)" → ancien formulaire ArticleGenerationType
  - "Génération intelligente (V2)" → redirige vers TopicResearch/new

---

## Phase I — Infrastructure Docker & Config

### I1. Redis
- Ajouter service Redis dans `compose.yaml` :
  ```yaml
  redis:
    image: redis:7-alpine
    ports: ["6379:6379"]
  ```
- Variable `.env` : `REDIS_DSN=redis://redis:6379`

### I2. Zenserp
- Variable `.env` : `ZENSERP_API_KEY=`
- Injection via `services.yaml` : `$zenserpApiKey: '%env(ZENSERP_API_KEY)%'`

### I3. Messenger config
- `config/packages/messenger.yaml` :
  ```yaml
  transports:
    async: # existant - Doctrine
      dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
    pipeline: # nouveau - Redis
      dsn: '%env(REDIS_DSN)%/messages_pipeline'
      retry_strategy:
        max_retries: 3
        delay: 5000
        multiplier: 3
  routing:
    App\Message\Pipeline\AnalyzeSerpMessage: pipeline
    App\Message\Pipeline\AnalyzeIntelligenceMessage: pipeline
    App\Message\Pipeline\GenerateBriefMessage: pipeline
    App\Message\Pipeline\GenerateArticleMessage: pipeline
    App\Message\Pipeline\OptimizeSeoMessage: pipeline
  ```

### I4. Worker
- Adapter `docker/worker-entrypoint.sh` : consommer les 2 transports (`async` + `pipeline`)
- Ou worker dédié pour pipeline (recommandé pour isolation)

---

## Fichiers à créer (29 fichiers)

### Entités (5)
- `src/Entity/TopicResearch.php`
- `src/Entity/SerpAnalysis.php`
- `src/Entity/IntelligenceAnalysis.php`
- `src/Entity/ContentBrief.php`
- `src/Entity/PipelineRunLog.php`

### Enum (1)
- `src/Enum/PipelineStatus.php`

### Interface + DTO (2)
- `src/Service/Serp/SerpProviderInterface.php`
- `src/Dto/SerpResultDto.php`

### Messages (5)
- `src/Message/Pipeline/AnalyzeSerpMessage.php`
- `src/Message/Pipeline/AnalyzeIntelligenceMessage.php`
- `src/Message/Pipeline/GenerateBriefMessage.php`
- `src/Message/Pipeline/GenerateArticleMessage.php`
- `src/Message/Pipeline/OptimizeSeoMessage.php`

### Handlers (5)
- `src/MessageHandler/Pipeline/AnalyzeSerpHandler.php`
- `src/MessageHandler/Pipeline/AnalyzeIntelligenceHandler.php`
- `src/MessageHandler/Pipeline/GenerateBriefHandler.php`
- `src/MessageHandler/Pipeline/GenerateArticleHandler.php`
- `src/MessageHandler/Pipeline/OptimizeSeoHandler.php`

### Services (7)
- `src/Service/Pipeline/ArticleGenerationPipelineService.php`
- `src/Service/Serp/ZenserpProvider.php`
- `src/Service/Pipeline/SerpQuestionAnalyzerService.php`
- `src/Service/Pipeline/IntelligenceAnalyzerService.php`
- `src/Service/Pipeline/BriefOutlineGeneratorService.php`
- `src/Service/Pipeline/PipelineArticleWriterService.php`
- `src/Service/Pipeline/SeoScorerService.php`

### Controller + Form (2)
- `src/Controller/TopicResearchController.php`
- `src/Form/TopicResearchType.php`

### Templates (3)
- `templates/topic_research/index.html.twig`
- `templates/topic_research/new.html.twig`
- `templates/topic_research/show.html.twig`

---

## Fichiers à modifier (6)

- `compose.yaml` — ajouter service Redis
- `config/packages/messenger.yaml` — ajouter transport `pipeline` + routing
- `.env` — ajouter `REDIS_DSN`, `ZENSERP_API_KEY`
- `docker/worker-entrypoint.sh` — consommer transport pipeline
- `src/Entity/Article.php` — ajouter relation `topicResearch` (ManyToOne nullable)
- `templates/article/show.html.twig` — ajouter choix V1/V2

---

## Vérification

1. `make phpstan` — tous les nouveaux fichiers au niveau strict
2. Tests unitaires pour chaque service pipeline (mocks des réponses Claude + Zenserp)
3. Test unitaire du retry mechanism
4. Test fonctionnel pipeline complet (réponses mockées, vérifie les transitions de statut)
5. Test Redis transport (messages routés correctement)
6. Test PipelineRunLog (chaque étape loggée avec prompt/response/tokens/duration)
7. Test manuel : lancer "CRM PME France" et observer chaque étape
8. `make test && make lint`

---

## Hors scope MVP (itérations futures)

- Mercure temps réel (remplacé par polling JS pour le MVP)
- Knowledge Graph avancé
- Cluster Builder automatique
- Authority Graph + Internal Link Optimizer
- Topical Authority Score
- Content Calendar
- Refresh Engine automatique
- Rank Tracking
- Image Generation IA
- EEAT/GEO Optimizers avancés
- Schema Generator complet
- Support Ghost/Webflow
- DataForSEO migration (swap derrière SerpProviderInterface)

---

## Ordre d'implémentation recommandé

```
1. Phase A (Infrastructure) — bloque tout le reste
2. Phase I (Docker/Config) — en parallèle avec A
3. Phase B (SERP+Questions) — première étape fonctionnelle
4. Phase C (Intelligence) — dépend de B
5. Phase D (Brief+Outline) — dépend de C
6. Phase E (Article Writer) — dépend de D
7. Phase F (SEO Score) — dépend de E
8. Phase G (Retry) — peut être fait en parallèle dès que B fonctionne
9. Phase H (Controller/UI) — peut commencer dès que A est prêt (mock les résultats)
```
