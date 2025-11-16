# Health Check Bundle - Stratégie Technique

## Vue d'ensemble

Bundle Symfony réutilisable pour effectuer des health checks sur les applications, avec support de multiples types de vérifications, timeouts configurables, et statuts détaillés par service.

## Architecture & Design Patterns

### 1. Strategy Pattern
Chaque type de check implémente l'interface `HealthCheckInterface`, permettant d'ajouter facilement de nouveaux checks sans modifier le code existant.

```php
interface HealthCheckInterface
{
    public function check(): HealthCheckResult;
    public function getName(): string;
    public function getTimeout(): int;
}
```

### 2. Value Object Pattern
`HealthCheckResult` et `HealthCheckStatus` sont des value objects immuables représentant l'état d'un check.

### 3. Dependency Injection Pattern
Les checks sont déclarés comme services Symfony avec auto-tagging moderne via l'attribut `#[AutoconfigureTag]` sur l'interface `HealthCheckInterface`. Cela permet l'injection automatique de tous les checks dans le service principal sans configuration manuelle.

**Symfony 6.1+ Feature**: L'attribut `#[AutoconfigureTag]` appliqué directement sur l'interface permet le tagging automatique de toutes les classes implémentant cette interface, sans avoir à tagger chaque service individuellement.

### 4. Composite Pattern
Le `HealthCheckService` agrège tous les checks et retourne un statut global.

## Structure du Bundle

```
src/HealthCheckBundle/
├── DependencyInjection/
│   ├── HealthCheckExtension.php      # Configuration du bundle
│   ├── Configuration.php              # Définition de la config YAML
│   └── Compiler/
│       └── HealthCheckPass.php        # Auto-registration des checks
├── HealthCheck/
│   ├── HealthCheckInterface.php       # Interface commune
│   ├── HealthCheckResult.php          # Value object résultat
│   ├── HealthCheckStatus.php          # Enum des statuts
│   └── Checks/
│       ├── AbstractHealthCheck.php    # Classe de base avec timeout
│       ├── DatabaseHealthCheck.php    # Vérification BDD
│       ├── RedisHealthCheck.php       # Vérification Redis
│       ├── HttpHealthCheck.php        # Vérification HTTP externe
│       └── DiskSpaceHealthCheck.php   # Vérification espace disque
├── Service/
│   └── HealthCheckService.php         # Service principal orchestrateur
├── Controller/
│   └── HealthCheckController.php      # Endpoint /health
└── HealthCheckBundle.php              # Classe principale du bundle

composer.json                           # Package composer
README.md                              # Documentation utilisateur
```

## Composants Principaux

### 1. HealthCheckStatus (Enum)

```php
enum HealthCheckStatus: string
{
    case HEALTHY = 'healthy';
    case DEGRADED = 'degraded';
    case UNHEALTHY = 'unhealthy';
}
```

### 2. HealthCheckResult (Value Object)

```php
final readonly class HealthCheckResult
{
    public function __construct(
        public string $name,
        public HealthCheckStatus $status,
        public ?string $message,
        public float $duration,
        public array $metadata = []
    ) {}
}
```

### 3. HealthCheckInterface

```php
interface HealthCheckInterface
{
    /**
     * Execute the health check
     */
    public function check(): HealthCheckResult;

    /**
     * Get the unique name of this check
     */
    public function getName(): string;

    /**
     * Get the timeout in seconds (default: 5)
     */
    public function getTimeout(): int;

    /**
     * Determine if this check is critical (affects global status)
     */
    public function isCritical(): bool;
}
```

### 4. AbstractHealthCheck (Base Class)

Fournit:
- Gestion automatique du timeout avec `set_time_limit()`
- Mesure du temps d'exécution
- Gestion des exceptions
- Logging des erreurs

### 5. HealthCheckService

Responsabilités:
- Récupération de tous les checks via DI (tagged services)
- Exécution parallèle ou séquentielle des checks
- Agrégation des résultats
- Détermination du statut global
- Gestion des checks critiques vs non-critiques

### 6. HealthCheckController

Route: `GET /health`

Réponse JSON:
```json
{
    "status": "healthy",
    "timestamp": "2025-11-05T10:00:00+00:00",
    "duration": 0.245,
    "checks": [
        {
            "name": "database",
            "status": "healthy",
            "message": "Connection successful",
            "duration": 0.012,
            "metadata": {
                "connection": "default",
                "driver": "pdo_mysql"
            }
        },
        {
            "name": "redis",
            "status": "healthy",
            "message": "Connected to Redis",
            "duration": 0.008,
            "metadata": {
                "host": "localhost:6379"
            }
        }
    ]
}
```

Codes HTTP:
- `200 OK` - Tous les checks critiques sont healthy
- `503 Service Unavailable` - Au moins un check critique est unhealthy

## Configuration YAML

```yaml
# config/packages/health_check.yaml
health_check:
    enabled: true
    route:
        path: /health
        name: health_check

    checks:
        database:
            enabled: true
            critical: true
            timeout: 5

        redis:
            enabled: true
            critical: false
            timeout: 3

        http:
            enabled: true
            critical: false
            timeout: 10
            urls:
                - https://api.external.com/status
```

## Implémentation des Checks

### DatabaseHealthCheck

Vérifie:
- Connexion à la base de données
- Exécution d'une requête simple (`SELECT 1`)
- Temps de réponse

### RedisHealthCheck

Vérifie:
- Connexion au serveur Redis
- Commande PING
- Temps de réponse

### HttpHealthCheck

Vérifie:
- Connectivité vers des services externes
- Codes HTTP de réponse
- Temps de réponse

### DiskSpaceHealthCheck

Vérifie:
- Espace disque disponible
- Seuils configurables (warning à 80%, critical à 90%)

### MemoryHealthCheck

Vérifie:
- Utilisation mémoire PHP
- Memory limit
- Seuils configurables

## Gestion des Timeouts

```php
abstract class AbstractHealthCheck implements HealthCheckInterface
{
    public function check(): HealthCheckResult
    {
        $startTime = microtime(true);
        $timeout = $this->getTimeout();

        try {
            // Set PHP timeout
            set_time_limit($timeout);

            // Execute check with internal timeout monitoring
            $result = $this->doCheck();

            $duration = microtime(true) - $startTime;

            if ($duration > $timeout) {
                return new HealthCheckResult(
                    name: $this->getName(),
                    status: HealthCheckStatus::UNHEALTHY,
                    message: "Check exceeded timeout of {$timeout}s",
                    duration: $duration
                );
            }

            return $result;

        } catch (\Throwable $e) {
            return new HealthCheckResult(
                name: $this->getName(),
                status: HealthCheckStatus::UNHEALTHY,
                message: $e->getMessage(),
                duration: microtime(true) - $startTime
            );
        }
    }

    abstract protected function doCheck(): HealthCheckResult;
}
```

## Auto-Registration avec Auto-Tagging Moderne

### Méthode 1: Attribut sur l'interface (Recommandé - Symfony 6.1+)

L'interface `HealthCheckInterface` utilise l'attribut `#[AutoconfigureTag]`:

```php
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('health_check.checker')]
interface HealthCheckInterface
{
    // ...
}
```

**Activation dans le Bundle**:
```php
class HealthCheckBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Enregistre l'autoconfiguration pour l'interface
        $container->registerForAutoconfiguration(HealthCheckInterface::class)
            ->addTag('health_check.checker');
    }
}
```

**Résultat**: Toutes les classes implémentant `HealthCheckInterface` sont automatiquement taggées, sans configuration manuelle !

### Méthode 2: Compiler Pass (pour configuration avancée)

Le `HealthCheckPass` gère les checks activés/désactivés via configuration:

```php
class HealthCheckPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(HealthCheckService::class)) {
            return;
        }

        $config = $container->getParameter('health_check.checks');
        $definition = $container->findDefinition(HealthCheckService::class);
        $taggedServices = $container->findTaggedServiceIds('health_check.checker');

        $references = [];
        foreach (array_keys($taggedServices) as $id) {
            // Filtre les checks désactivés
            if ($this->shouldInclude($id, $config)) {
                $references[] = new Reference($id);
            }
        }

        $definition->setArgument('$healthChecks', $references);
    }
}
```

## Installation & Usage

### Installation

```bash
composer require kiora/health-check-bundle
```

### Configuration

```php
// config/bundles.php
return [
    // ...
    Kiora\HealthCheckBundle\HealthCheckBundle::class => ['all' => true],
];
```

### Créer un Check Personnalisé

```php
namespace App\HealthCheck;

use Kiora\HealthCheckBundle\HealthCheck\AbstractHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('health_check.checker')]
class CustomApiHealthCheck extends AbstractHealthCheck
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {}

    public function getName(): string
    {
        return 'custom_api';
    }

    public function getTimeout(): int
    {
        return 10;
    }

    public function isCritical(): bool
    {
        return false;
    }

    protected function doCheck(): HealthCheckResult
    {
        $response = $this->httpClient->request('GET', 'https://api.custom.com/status');

        if ($response->getStatusCode() === 200) {
            return new HealthCheckResult(
                name: $this->getName(),
                status: HealthCheckStatus::HEALTHY,
                message: 'API is responding',
                duration: 0,
                metadata: ['status_code' => 200]
            );
        }

        return new HealthCheckResult(
            name: $this->getName(),
            status: HealthCheckStatus::UNHEALTHY,
            message: 'API returned ' . $response->getStatusCode(),
            duration: 0
        );
    }
}
```

## Avantages de cette Architecture

### ✅ Extensibilité
- Ajout de nouveaux checks sans modification du code existant
- Interface claire et simple à implémenter

### ✅ Réutilisabilité
- Bundle Composer installable sur n'importe quelle application Symfony
- Configuration flexible via YAML

### ✅ Testabilité
- Chaque check est une classe isolée facilement testable
- Mocks simples grâce à l'interface

### ✅ Performance
- Timeouts configurables par check
- Exécution optimisée avec gestion des erreurs
- Possibilité d'exécution parallèle (future enhancement)

### ✅ Observabilité
- Statut détaillé par service
- Métadonnées personnalisables
- Temps d'exécution mesuré

### ✅ Standards Symfony
- Utilisation de l'injection de dépendances
- Configuration YAML standard
- Auto-configuration avec tags
- Compatible avec Symfony 6.4+ et 7.x

## Roadmap

### Phase 1 (MVP)
- [x] Architecture de base
- [ ] HealthCheckInterface et classes de base
- [ ] DatabaseHealthCheck
- [ ] RedisHealthCheck
- [ ] HealthCheckService
- [ ] HealthCheckController
- [ ] Configuration YAML

### Phase 2
- [ ] HttpHealthCheck
- [ ] DiskSpaceHealthCheck
- [ ] MemoryHealthCheck
- [ ] Métriques Prometheus (endpoint /metrics)
- [ ] Cache des résultats

### Phase 3
- [ ] Exécution parallèle des checks
- [ ] Dashboard HTML (/health/dashboard)
- [ ] Notifications (email, Slack)
- [ ] Historique des checks
- [ ] API GraphQL

## Sécurité

### Authentification
Recommandation: protéger la route `/health` avec:
- IP whitelisting
- Token d'authentification
- Firewall Symfony

```yaml
# config/packages/security.yaml
security:
    firewalls:
        health_check:
            pattern: ^/health
            stateless: true
            custom_authenticators:
                - App\Security\HealthCheckAuthenticator
```

### Informations Sensibles
Ne pas exposer:
- Mots de passe ou tokens
- Chemins système complets
- Versions détaillées des dépendances

## Monitoring & Alerting

### Intégration Prometheus

```yaml
# /metrics endpoint
health_check_status{name="database"} 1
health_check_duration_seconds{name="database"} 0.012
health_check_status{name="redis"} 1
health_check_duration_seconds{name="redis"} 0.008
```

### Alerting Rules

```yaml
groups:
  - name: health_checks
    rules:
      - alert: ServiceUnhealthy
        expr: health_check_status == 0
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "Service {{ $labels.name }} is unhealthy"
```

## Références

- [Health Check Response Format for HTTP APIs (RFC)](https://datatracker.ietf.org/doc/html/draft-inadarei-api-health-check)
- [Symfony Best Practices](https://symfony.com/doc/current/best_practices.html)
- [Design Patterns: Strategy](https://refactoring.guru/design-patterns/strategy)
