# PHP Signature Parser

**Un parseur strict et ordonné pour les commandes CLI qui extrait la source, les arguments requis, les arguments par défaut, les variadiques et les options.**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Table des matières

- [Installation](#installation)
- [Concepts fondamentaux](#concepts-fondamentaux)
- [Ordre strict des arguments](#ordre-strict-des-arguments)
- [Utilisation de base](#utilisation-de-base)
- [Structure des résultats](#structure-des-résultats)
- [Les parseurs](#les-parseurs)
- [Extensibilité](#extensibilité)
- [Exemples](#exemples)
- [Tests](#tests)
- [Licence](#licence)

---

## Installation

```bash
composer require andydefer/php-signature-parser
```

### Prérequis

- PHP 8.1 ou supérieur

---

## Concepts fondamentaux

### La signature

La signature est une chaîne qui décrit la structure de la commande.

```php
$signature = 'backup {source} {destination} {format=zip} {output=dist} {excludes*} {purpose*} {--force} {--verbose}';
```

| Élément | Syntaxe | Description |
|---------|---------|-------------|
| **Source** | `backup` | Nom de la commande (position 0) |
| **Requis** | `{source}` | Argument obligatoire |
| **Par défaut** | `{format=zip}` | Argument avec valeur par défaut |
| **Variadique** | `{excludes*}` | Argument qui capture plusieurs valeurs |
| **Option** | `{--force}` | Flag optionnel (booléen) |

### La requête

La requête est la commande réelle exécutée par l'utilisateur.

```php
$query = 'backup /var/www /backup tar.gz [cache, logs, tmp] [home, data, models] --force';
```

---

## Ordre strict des arguments

⚠️ **L'ordre des éléments dans la signature est STRICT et IMPÉRATIF.**

| Ordre | Type | Syntaxe | Exemple |
|-------|------|---------|---------|
| **1** | **Source** | `command` | `backup` |
| **2** | **Requis** | `{name}` | `{source}` `{destination}` |
| **3** | **Par défaut** | `{name=value}` | `{format=zip}` `{output=dist}` |
| **4** | **Variadique** | `{name*}` | `{excludes*}` `{purpose*}` |
| **5** | **Options** | `{--flag}` | `{--force}` `{--verbose}` |

### Règles strictes

| Règle | Description |
|-------|-------------|
| **Source** | Toujours en première position (position 0) |
| **Requis** | Viennent en premier, avant tous les autres |
| **Par défaut** | Viennent après les requis, avant les variadiques |
| **Variadiques** | Toujours en dernière position des arguments |
| **Options** | Peuvent être à n'importe quelle position après la source |
| **Ordre de la requête** | Doit respecter l'ordre de la signature |

### Exemple d'ordre correct

```php
// ✅ ORDRE CORRECT
$signature = 'backup {source} {destination} {format=zip} {output=dist} {excludes*} {--force} {--verbose}';

// ❌ ORDRE INCORRECT
$signature = 'backup {format=zip} {source} {destination}'; // Default avant Requis
$signature = 'backup {source} {excludes*} {destination}'; // Variadique avant Requis
```

### Correspondance requête ↔ signature

La requête doit respecter l'ordre de la signature :

```php
$signature = 'backup {source} {destination} {format=zip} {excludes*} {--force}';

// ✅ CORRECT
$query = 'backup /var/www /backup tar.gz [cache, logs] --force';

// ❌ INCORRECT - ordre différent
$query = 'backup /backup /var/www tar.gz [cache, logs] --force';
```

---

## Utilisation de base

```php
<?php

use AndyDefer\SignatureParser\SignatureParser;

$signature = 'backup {source} {destination} {format=zip} {output=dist} {excludes*} {purpose*} {--force} {--verbose}';
$query = 'backup /var/www /backup tar.gz [cache, logs, tmp] [home, data, models] --force';

$parser = new SignatureParser();
$result = $parser->parse($signature, $query);

print_r($result);
```

**Résultat :**

```php
[
    'source' => 'backup',
    'required' => [
        'source' => '/var/www',
        'destination' => '/backup',
    ],
    'default' => [
        'format' => 'tar.gz',
        'output' => 'dist',
    ],
    'variadic' => [
        'excludes' => ['cache', 'logs', 'tmp'],
        'purpose' => ['home', 'data', 'models'],
    ],
    'options' => [
        'force' => true,
        'verbose' => false,
    ],
]
```

---

## Structure des résultats

```php
[
    // Source - nom de la commande
    'source' => 'backup',

    // Arguments requis (nom => valeur)
    'required' => [
        'source' => '/var/www',
        'destination' => '/backup',
    ],

    // Arguments par défaut (nom => valeur)
    'default' => [
        'format' => 'tar.gz',
        'output' => 'dist',
    ],

    // Arguments variadiques (nom => tableau de valeurs)
    'variadic' => [
        'excludes' => ['cache', 'logs', 'tmp'],
        'purpose' => ['home', 'data', 'models'],
    ],

    // Options (nom => booléen)
    'options' => [
        'force' => true,
        'verbose' => false,
    ],
]
```

---

## Les parseurs

Le package utilise une **chaîne de responsabilité** (Chain of Responsibility) avec 5 parseurs :

| Parser | Rôle | Priorité |
|--------|------|----------|
| `SourceParser` | Extrait le nom de la commande (position 0) | 1 |
| `RequiredParser` | Extrait les arguments requis (sans `=`, `*`, `--`) | 2 |
| `DefaultParser` | Extrait les arguments avec valeur par défaut (`=`) | 3 |
| `VariadicParser` | Extrait les arguments variadiques (`*`) | 4 |
| `OptionsParser` | Extrait les options (`--`) | 5 |

### Ordre d'exécution strict

```
1. SourceParser  → Extrait la source
2. RequiredParser → Extrait les arguments requis
3. DefaultParser  → Extrait les arguments par défaut
4. VariadicParser → Extrait les arguments variadiques
5. OptionsParser  → Extrait les options
```

Chaque parseur prend ce qui le concerne et **passe le reste** au parseur suivant.

---

## Extensibilité

### Ajouter un parseur personnalisé

```php
<?php

use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\SignatureParser;

final class CustomParser implements ParserInterface
{
    public function parse(array $signature, array $query): array
    {
        // Votre logique personnalisée
        $custom = 'valeur personnalisée';

        return [
            'result' => ['custom' => $custom],
            'signature' => $signature,
            'query' => $query,
        ];
    }
}

$parser = new SignatureParser();
$parser->addParser(new CustomParser()); // ← S'ajoute APRÈS les parseurs par défaut

$result = $parser->parse($signature, $query);
// $result contient maintenant 'custom' en plus des autres champs
```

### Supprimer un parseur

```php
$parser = new SignatureParser();

// Supprimer le parseur d'options
$parser->removeParser(OptionsParser::class);

// Les options ne seront plus extraites
$result = $parser->parse($signature, $query);
```

### Récupérer la liste des parseurs

```php
$parser = new SignatureParser();
$parsers = $parser->getParsers();

foreach ($parsers as $parser) {
    echo get_class($parser) . "\n";
}
```

---

## Exemples

### Commande Git

```php
$signature = 'git {command} {--all} {--force}';
$query = 'git add --all';

$result = $parser->parse($signature, $query);

// $result['source'] = 'git'
// $result['required']['command'] = 'add'
// $result['options']['all'] = true
// $result['options']['force'] = false
```

### Commande Docker

```php
$signature = 'docker {container} {image} {--detach} {--rm}';
$query = 'docker run nginx --detach';

$result = $parser->parse($signature, $query);

// $result['source'] = 'docker'
// $result['required']['container'] = 'run'
// $result['required']['image'] = 'nginx'
// $result['options']['detach'] = true
// $result['options']['rm'] = false
```

### Commande avec valeurs par défaut

```php
$signature = 'deploy {env=production} {--force}';
$query = 'deploy staging --force';

$result = $parser->parse($signature, $query);

// $result['source'] = 'deploy'
// $result['default']['env'] = 'staging'  // ← override la valeur par défaut
// $result['options']['force'] = true
```

---

## Value Object

Le package fournit également un `SignatureVO` pour un accès typé :

```php
<?php

use AndyDefer\SignatureParser\ValueObjects\SignatureVO;

$vo = new SignatureVO(
    'backup {source} {destination} {format=zip} {excludes*} {--force}',
    'backup /var/www /backup tar.gz [cache, logs] --force'
);

echo $vo->getSource();                      // 'backup'
echo $vo->getRequired('source');           // '/var/www'
echo $vo->getDefault('format');            // 'tar.gz'
print_r($vo->getVariadic('excludes'));     // ['cache', 'logs']
var_dump($vo->getOption('force'));         // true
var_dump($vo->hasOption('verbose'));       // false
```

---

## Tests

```bash
# Exécuter tous les tests
./vendor/bin/phpunit

# Un test spécifique
./vendor/bin/phpunit --filter test_parses_signature

# Avec couverture de code
./vendor/bin/phpunit --coverage-html coverage/
```

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)