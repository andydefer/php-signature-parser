# PHP Signature Parser

**Un parseur strict et typé pour les commandes CLI qui extrait la source, les arguments requis, les arguments par défaut, les nullables, les variadiques, les énumérations et les flags avec des Value Objects et des collections typées. Support automatique du formatage des espaces via le caractère `^`, des commentaires inline, des tokens spéciaux (`?`, `~`) et des tags personnalisés.**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Table des matières

1. [Installation](#installation)
2. [Concepts fondamentaux](#concepts-fondamentaux)
3. [Commentaires inline](#commentaires-inline)
4. [Formatage des espaces avec `^`](#formatage-des-espaces-avec-)
5. [Tokens spéciaux](#tokens-spéciaux)
   - [Le token `?` (null explicite)](#le-token--null-explicite)
   - [Le token `~` (skip)](#le-token--skip)
6. [Ordre strict des arguments](#ordre-strict-des-arguments)
7. [Énumérations (Enum)](#énumérations-enum)
8. [Tags personnalisés](#tags-personnalisés)
9. [Utilisation du parseur](#utilisation-du-parseur)
10. [Manipulation des collections](#manipulation-des-collections)
    - [ArgumentCollection](#argumentcollection)
    - [FlagCollection](#flagcollection)
    - [EnumCollection](#enumcollection)
    - [VariadicArgumentCollection](#variadicargumentcollection)
11. [Value Objects](#value-objects)
    - [SignatureStructureVO](#signaturestructurevo)
    - [SignatureVO](#signaturevo)
12. [SignatureDocumentor - Génération de documentation](#signaturedocumentor---génération-de-documentation)
13. [QueryBuilder - Construction dynamique](#querybuilder---construction-dynamique)
14. [Les parseurs internes](#les-parseurs-internes)
15. [Extensibilité](#extensibilité)
16. [Cas d'usage avancés](#cas-dusage-avancés)
17. [Exemples complets](#exemples-complets)
18. [Licence](#licence)

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
$signature = 'backup {source} {destination} {format=zip} {env=?} ::level->[low,medium,high]=medium {excludes*} {purpose*} {--force} {--verbose}';
```

| Élément | Syntaxe | Description |
|---------|---------|-------------|
| **Source** | `backup` | Nom de la commande (position 0) |
| **Requis** | `{source}` | Argument obligatoire |
| **Par défaut** | `{format=zip}` | Argument avec valeur par défaut |
| **Nullable** | `{env=?}` | Argument pouvant être `null` |
| **Enum** | `::level->[low,medium,high]=medium` | Énumération avec valeurs autorisées |
| **Variadique** | `{excludes*}` | Argument qui capture plusieurs valeurs |
| **Flag** | `{--force}` | Flag optionnel (booléen) |
| **Tag personnalisé** | `<key="value">` | Données supplémentaires (non définies dans la signature) |
| **Commentaire** | `# "comment"` | Documentation inline |

### La requête

La requête est la commande réelle exécutée par l'utilisateur.

```php
$query = 'backup /var/www /backup tar.gz staging high [cache, logs, tmp] [home, data, models] --force <user="admin">';
```

---

## Commentaires inline

Les commentaires permettent de documenter chaque argument directement dans la signature.

### Syntaxe

```php
{name}#'comment'
{name=value}#'comment'
{name*>[values]}#'comment'
::name->[values]#'comment'
{--flag}#'comment'
```

### Exemples

```php
$signature = 'backup {source}#"Source directory" {destination}#"Destination" {format=zip}#"Archive format" {--force}#"Force overwrite"';
```

### Utilisation avec les records

Les commentaires sont automatiquement extraits et disponibles dans les records :

```php
$result = $parser->parse($signature, $query);

echo $result->requireds->first()->comment;  // 'Source directory'
echo $result->defaults->first()->comment;   // 'Archive format'
echo $result->flags->first()->comment;      // 'Force overwrite'
```

### Formats supportés

```php
// Guillemets doubles
{name}#"The user name"

// Guillemets simples
{name}#'The user name'
```

---

## Formatage des espaces avec `^`

Le parser remplace automatiquement les caractères `^` par des espaces dans toutes les valeurs extraites.

### Règle simple

> **Pour inclure un espace dans une valeur, utilisez `^` à la place.**

| Saisie utilisateur | Valeur réelle |
|-------------------|---------------|
| `John^Doe` | `John Doe` |
| `Hello^World!` | `Hello World!` |
| `C:/Program^Files` | `C:/Program Files` |

### Exemples

```php
// Arguments requis
$signature = 'user:create {name} {email}';
$query = 'user:create John^Doe john@example.com';

$result = $parser->parse($signature, $query);
// $result->requireds->first()->value = 'John Doe'

// Valeurs par défaut
$signature = 'user:list {format=zip}';
$query = 'user:list tar^gz';
$result = $parser->parse($signature, $query);
// $result->defaults->first()->value = 'tar gz'
```

---

## Tokens spéciaux

### Le token `?` (null explicite)

Le token `?` permet de passer explicitement `null` comme valeur.

| Cas | Exemple | Résultat |
|-----|---------|----------|
| Argument requis | `backup /var/www ?` | `destination = null` |
| Argument par défaut | `deploy staging ?` | `env = null` (override) |

### Le token `~` (skip)

Le token `~` permet de sauter un argument et d'utiliser la valeur par défaut ou `null` :

| Cas | Comportement |
|-----|--------------|
| **Argument requis** | `~` → `null` |
| **Argument par défaut** | `~` → utilise la valeur par défaut |
| **Argument nullable** | `~` → `null` |
| **Enum avec défaut** | `~` → utilise la valeur par défaut |
| **Enum optionnel** | `~` → `null` |

### Exemples

```php
// Par défaut → valeur par défaut
$signature = 'backup {source} {format=zip}';
$query = 'backup /var/www ~';
// format = zip

// Nullable → null
$signature = 'deploy {env=?} {--force}';
$query = 'deploy ~ --force';
// env = null

// Enum avec défaut
$signature = 'set-level ::level->[low,high]=medium';
$query = 'set-level ~';
// level = medium
```

---

## Ordre strict des arguments

⚠️ **L'ordre des éléments dans la signature est STRICT et IMPÉRATIF.**

| Ordre | Type | Syntaxe | Exemple |
|-------|------|---------|---------|
| **1** | **Source** | `command` | `backup` |
| **2** | **Requis** | `{name}` | `{source}` `{destination}` |
| **3** | **Par défaut** | `{name=value}` | `{format=zip}` `{output=dist}` |
| **4** | **Nullable** | `{name=?}` | `{env=?}` `{port=?}` |
| **5** | **Enum** | `::name->[values]=state` | `::level->[low,high]=medium` |
| **6** | **Variadique** | `{name*}` | `{excludes*}` `{purpose*}` |
| **7** | **Flags** | `{--flag}` | `{--force}` `{--verbose}` |
| **8** | **Tags personnalisés** | `<key="value">` | `<user="admin">` |

### Exemples d'ordre valide

```php
// ✅ Ordre correct avec tous les types
$signature = 'backup {source} {destination} {format=zip} {env=?} ::level->[low,high]=medium {excludes*} {--force}';

// ✅ Commentaires à n'importe quelle position
$signature = 'backup {source}#"Source" {destination} {--force}#"Force"';
```

### Exemples d'ordre invalide

```php
// ❌ Enum après variadic
$signature = 'backup {source} {excludes*} ::level->[low,high]=medium';

// ❌ Required après default
$signature = 'backup {format=zip} {source}';
```

---

## Énumérations (Enum)

Les énumérations permettent de restreindre les valeurs autorisées pour un argument.

### Syntaxe

```php
::name->[value1,value2,value3]=state
```

### États possibles

| État | Syntaxe | Description |
|------|---------|-------------|
| **Requis** | `=*` | Doit être fourni |
| **Optionnel** | `=?` | Peut être `~` |
| **Défaut** | `=default` | Valeur par défaut |

### Exemples

```php
// Avec valeur par défaut
$signature = 'set-level ::level->[beginner,middle,master]=middle';
$query = 'set-level master';
// level = 'master'

// Requis
$signature = 'set-level ::level->[beginner,middle,master]=*';
$query = 'set-level beginner';
// level = 'beginner'
// set-level seul échouerait

// Optionnel
$signature = 'set-level ::level->[beginner,middle,master]=?';
$query = 'set-level ~';
// level = null

// Avec commentaire
$signature = 'set-level ::level->[beginner,middle,master]=medium#"The skill level"';
```

### Accès aux énumérations

```php
$result = $parser->parse($signature, $query);

// Valeur
$level = $result->enums->get('level'); // 'master'

// Valeurs autorisées
$allowed = $result->enums->getAllowedValues('level'); // ['beginner', 'middle', 'master']

// Vérifications
if ($result->enums->isRequired('level')) {
    echo "Level est requis";
}

if ($result->enums->isAllowed('level', 'master')) {
    echo "'master' est autorisé";
}
```

---

## Tags personnalisés

Les tags personnalisés permettent d'ajouter des données supplémentaires à une commande sans modifier la signature.

### Syntaxe

```php
<key="value">
<key='value'>
```

### Utilisation

```php
$signature = 'send {recipient} {--verbose}';
$query = 'send John --verbose <greeting="Hello World"> <later="goodby">';

$result = $parser->parse($signature, $query);

$customData = $result->custom_data->toArray();
echo $customData['greeting']; // 'Hello World'
echo $customData['later'];    // 'goodby'
```

### Avec QueryBuilder

```php
$query = QueryBuilder::init('deploy {environment}')
    ->setRequired('environment', 'staging')
    ->setCustoms([
        'version' => '1.2.3',
        'user' => 'admin'
    ])
    ->build();

// 'deploy staging <version="1.2.3"> <user="admin">'
```

---

## Utilisation du parseur

### Utilisation de base

```php
<?php

use AndyDefer\SignatureParser\SignatureParser;

$signature = 'backup {source} {destination} {format=zip} {output=dist} {env=?} ::level->[low,high]=medium {excludes*} {purpose*} {--force} {--verbose}';
$query = 'backup /var/www /backup tar.gz dist staging high [cache, logs, tmp] [home, data, models] --force';

$parser = new SignatureParser();
$result = $parser->parse($signature, $query);

echo $result->source;                                      // 'backup'
echo $result->requireds->first()->value;                   // '/var/www'
echo $result->defaults->first()->value;                    // 'tar.gz'
echo $result->variadics->first()->values->first();         // 'cache'
echo $result->flags->first()->value;                       // true
echo $result->enums->get('level');                         // 'high'
```

### Validation

```php
// Validation de requête
$result = $parser->validate(
    'backup {source} {destination}',
    'backup /var/www'
);

if (!$result->isValid) {
    foreach ($result->errors as $error) {
        echo "❌ $error\n";
    }
}

// Validation de signature
$result = $parser->validateSignature('backup {source} {format=zip} {--force}');

if ($result->isValid) {
    echo "✅ Signature valide\n";
}
```

---

## Manipulation des collections

### ArgumentCollection

Collection d'arguments (`ArgumentRecord`).

```php
$collection = $result->requireds;

// Récupérer une valeur par nom
$source = $collection->get('source');        // '/var/www'

// Vérifier si un argument existe
if ($collection->has('destination')) {
    echo "Destination définie";
}

// Récupérer tous les noms
$names = $collection->getNames();            // ['source', 'destination']

// Convertir en tableau associatif
$assoc = $collection->toAssociativeArray();  // ['source' => '/var/www', 'destination' => '/backup']
```

### FlagCollection

Collection de flags (`FlagRecord`).

```php
$collection = $result->flags;

// Récupérer la valeur d'un flag
$force = $collection->get('force');          // true

// Vérifier si un flag est actif
if ($collection->isActive('force')) {
    echo "Mode force activé";
}

// Récupérer tous les flags actifs
$active = $collection->getActiveNames();     // ['force']
```

### EnumCollection

Collection d'énumérations (`EnumRecord`).

```php
$collection = $result->enums;

// Récupérer une valeur
$level = $collection->get('level');          // 'high'

// Valeurs autorisées
$allowed = $collection->getAllowedValues('level'); // ['low', 'medium', 'high']

// Vérifications
if ($collection->isRequired('level')) {
    echo "Level est requis";
}

if ($collection->isAllowed('level', 'master')) {
    echo "'master' est autorisé";
}

// Tableau associatif [nom => valeur]
$assoc = $collection->toAssociativeArray();  // ['level' => 'high']

// Tableau complet avec toutes les données
$full = $collection->toFullArray();
// [
//     [
//         'name' => 'level',
//         'value' => 'high',
//         'allowed_values' => ['low', 'medium', 'high'],
//         'default_value' => 'medium',
//         'value_state' => 'DEFAULTED'
//     ]
// ]
```

### VariadicArgumentCollection

Collection d'arguments variadiques (`VariadicArgumentRecord`).

```php
$collection = $result->variadics;

// Récupérer les valeurs
$excludes = $collection->get('excludes');    // ['cache', 'logs', 'tmp']

// Compter le nombre total de valeurs
$total = $collection->countAllValues();      // 5

// Convertir en tableau associatif
$assoc = $collection->toAssociativeArray();  // ['excludes' => ['cache', 'logs', 'tmp']]
```

---

## Value Objects

### SignatureStructureVO

Analyse UNIQUEMENT la structure d'une signature (sans requête).

```php
use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;

$vo = new SignatureStructureVO('backup {source} {destination} {format=zip} ::level->[low,high]=medium {excludes*} {--force}');

// Accès aux informations
echo $vo->getSource();          // 'backup'
$requireds = $vo->getRequireds();    // ['source', 'destination']
$defaults = $vo->getDefaults();      // ['format' => 'zip']
$enums = $vo->getEnums();            // ['level' => [...]]
$variadics = $vo->getVariadics();    // ['excludes']
$flags = $vo->getFlags();            // ['force']

// Vérifications
if ($vo->hasEnum('level')) {
    $allowed = $vo->getEnumAllowedValues('level'); // ['low', 'medium', 'high']
}

// Validation
if ($vo->isValid()) {
    echo "✅ Signature valide";
} else {
    foreach ($vo->getValidationErrors() as $error) {
        echo "❌ $error\n";
    }
}

// Documentation
$markdown = $vo->documentInMarkdown();
$json = $vo->documentInJson();
$array = $vo->documentInArray();
```

### SignatureVO

Analyse complète avec signature ET requête.

```php
use AndyDefer\SignatureParser\ValueObjects\SignatureVO;

$vo = new SignatureVO(
    'backup {source} {destination} {format=zip} {--force} ::level->[low,high]=medium',
    'backup /var/www /backup tar.gz --force high'
);

// Accès direct aux valeurs
echo $vo->getSource();                    // 'backup'
echo $vo->getRequired('source');          // '/var/www'
echo $vo->getDefault('format');           // 'tar.gz'
echo $vo->getFlag('force');               // true
echo $vo->getEnum('level');               // 'high'

// Vérifications
if ($vo->hasFlag('force')) {
    echo "Force mode activé";
}

if ($vo->hasEnum('level')) {
    echo $vo->getEnum('level');           // 'high'
}

// Récupération complète
$requireds = $vo->getRequireds();    // ['source' => '/var/www', 'destination' => '/backup']
$defaults = $vo->getDefaults();      // ['format' => 'tar.gz']
$flags = $vo->getFlags();            // ['force' => true]
$enums = $vo->getEnums();            // ['level' => 'high']

// Validation
if (!$vo->isValid()) {
    foreach ($vo->getValidationErrors() as $error) {
        echo "❌ $error\n";
    }
}
```

---

## SignatureDocumentor - Génération de documentation

Le `SignatureDocumentor` génère automatiquement une documentation complète pour une signature.

### Formats supportés

| Format | Méthode | Description |
|--------|---------|-------------|
| Markdown | `documentInMarkdown()` | Documentation structurée avec tables |
| Texte | `documentInText()` | Documentation en texte brut |
| JSON | `documentInJson()` | Export structuré |
| Array | `documentInArray()` | Tableau PHP |

### Utilisation

```php
use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;

$vo = new SignatureStructureVO('backup {source}#"Source" {destination} {format=zip}#"Format" {--force}#"Force"');

// Markdown
$markdown = $vo->documentInMarkdown();
echo $markdown;

// JSON
$json = $vo->documentInJson();
file_put_contents('doc.json', $json);

// Array
$data = $vo->documentInArray();
print_r($data);
```

### Exemple de sortie Markdown

```markdown
# Commande : backup

## Description

```bash
backup <source> <destination> [format=zip] [--force]
```

## Arguments requis

| Nom | Description |
|-----|-------------|
| `source` | Source |
| `destination` | — |

## Arguments par défaut

| Nom | Défaut | Description |
|-----|--------|-------------|
| `format` | `zip` | Format |

## Flags

| Nom | Description |
|-----|-------------|
| `--force` | Force |
```

---

## QueryBuilder - Construction dynamique

Le `QueryBuilder` permet de construire programmatiquement des requêtes CLI.

### Utilisation de base

```php
use AndyDefer\SignatureParser\QueryBuilder;

$query = QueryBuilder::init('greet {name} {--formal}')
    ->setRequired('name', 'John')
    ->setFlag('--formal', true)
    ->build();

echo $query; // 'greet John --formal'
```

### Avec énumérations

```php
$query = QueryBuilder::init('set-level ::level->[beginner,middle,master]=middle {--verbose}')
    ->setEnum('level', 'master')
    ->setFlag('--verbose', true)
    ->build();

echo $query; // 'set-level master --verbose'
```

### Avec tags personnalisés

```php
$query = QueryBuilder::init('send {recipient} {--verbose}')
    ->setRequired('recipient', 'John')
    ->setFlag('--verbose', true)
    ->setCustom('greeting', 'Hello World')
    ->setCustoms([
        'later' => 'goodby',
        'user' => 'admin'
    ])
    ->build();

echo $query;
// 'send John --verbose <greeting="Hello World"> <later="goodby"> <user="admin">'
```

### Parsing d'une requête initiale

```php
$builder = QueryBuilder::init(
    'send {recipient} {--verbose}',
    'send John --verbose <greeting="Hello">'
);

echo $builder->getRequired('recipient'); // 'John'
echo $builder->getCustom('greeting');    // 'Hello'

$query = $builder->setCustom('greeting', 'Hello World')->build();
// 'send John --verbose <greeting="Hello World">'
```

### Validation

```php
$builder = QueryBuilder::init('greet {name} {--formal}');

if (!$builder->isValid()) {
    foreach ($builder->getErrors() as $error) {
        echo "❌ $error\n";
    }
}

try {
    $query = $builder->build();
} catch (InvalidArgumentException $e) {
    echo "Erreur: " . $e->getMessage();
}
```

---

## Les parseurs internes

| Parser | Rôle | Syntaxe | Priorité |
|--------|------|---------|----------|
| `SourceParser` | Nom de la commande | `command` | 1 |
| `RequiredParser` | Arguments requis | `{name}` | 2 |
| `DefaultParser` | Par défaut et nullables | `{name=value}`, `{name=?}` | 3 |
| `EnumParser` | Énumérations | `::name->[values]=state` | 4 |
| `VariadicParser` | Arguments variadiques | `{name*}` | 5 |
| `FlagParser` | Flags | `{--flag}` | 6 |
| `CustomTagParser` | Tags personnalisés | `<key="value">` | 7 |

---

## Extensibilité

### Ajouter un parseur personnalisé

```php
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;
use AndyDefer\SignatureParser\SignatureParser;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class CustomParser implements ParserInterface
{
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        // Votre logique personnalisée
        return ParsedResultRecord::from([
            'data' => ['custom' => 'valeur_personnalisee'],
            'signature' => $signature,
            'query' => $query,
        ]);
    }

    public function validate(array $signature, array $query): ValidationResultRecord
    {
        return new ValidationResultRecord(
            isValid: true,
            errors: new StringTypedCollection,
            suggestions: new StringTypedCollection
        );
    }

    public function getTokenPattern(): string
    {
        return '/^@[a-zA-Z_][a-zA-Z0-9_]*$/';
    }
}

$parser = new SignatureParser();
$parser->addParser(new CustomParser());

$result = $parser->parse($signature, $query);
echo $result->data->get('custom'); // 'valeur_personnalisee'
```

### Supprimer un parseur

```php
// Supprime le parser de tags personnalisés
$parser->removeParser(CustomTagParser::class);
```

---

## Cas d'usage avancés

### Cas 1 : Application CLI complète

```php
<?php

use AndyDefer\SignatureParser\SignatureParser;
use AndyDefer\SignatureParser\QueryBuilder;
use AndyDefer\SignatureParser\ValueObjects\SignatureVO;

class CliApplication
{
    private SignatureParser $parser;
    
    public function __construct()
    {
        $this->parser = new SignatureParser();
    }
    
    public function run(string $signature, string $query): array
    {
        $result = $this->parser->parse($signature, $query);
        
        return [
            'source' => $result->source,
            'args' => $result->requireds->toAssociativeArray(),
            'flags' => $result->flags->toAssociativeArray(),
            'enums' => $result->enums->toAssociativeArray(),
            'custom' => $result->custom_data->toArray(),
        ];
    }
    
    public function build(string $signature, array $params): string
    {
        $builder = QueryBuilder::init($signature);
        
        if (isset($params['args'])) {
            foreach ($params['args'] as $name => $value) {
                $builder->setRequired($name, $value);
            }
        }
        
        if (isset($params['enums'])) {
            foreach ($params['enums'] as $name => $value) {
                $builder->setEnum($name, $value);
            }
        }
        
        if (isset($params['flags'])) {
            foreach ($params['flags'] as $name => $active) {
                $builder->setFlag('--' . $name, $active);
            }
        }
        
        if (isset($params['custom'])) {
            $builder->setCustoms($params['custom']);
        }
        
        return $builder->build();
    }
}
```

### Cas 2 : Documentation automatique

```php
use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;

function generateHelp(string $signature): string
{
    $vo = new SignatureStructureVO($signature);
    return $vo->documentInMarkdown();
}

// Génération de documentation pour toutes les commandes
$commands = [
    'deploy' => 'deploy {environment} {version=?} ::level->[low,high]=medium {--force}',
    'backup' => 'backup {source} {destination} {format=zip} {--force}',
    'restore' => 'restore {source} {destination} {--force}',
];

foreach ($commands as $name => $signature) {
    file_put_contents("docs/{$name}.md", generateHelp($signature));
}
```

---

## Exemples complets

### Exemple 1 : Commande avec tous les types

```php
<?php

use AndyDefer\SignatureParser\SignatureParser;
use AndyDefer\SignatureParser\QueryBuilder;

$parser = new SignatureParser();

$signature = 'deploy {environment} {version=?} ::level->[low,medium,high]=medium {excludes*} {--force} {--verbose}';
$query = 'deploy staging 1.2.3 high [cache,logs,tmp] --force';

$result = $parser->parse($signature, $query);

echo "Commande: " . $result->source . "\n";
echo "Environnement: " . $result->requireds->get('environment') . "\n";
echo "Version: " . ($result->defaults->get('version') ?? 'null') . "\n";
echo "Niveau: " . $result->enums->get('level') . "\n";
echo "Exclus: " . implode(', ', $result->variadics->get('excludes')) . "\n";
echo "Force: " . ($result->flags->get('force') ? 'true' : 'false') . "\n";
echo "Verbose: " . ($result->flags->get('verbose') ? 'true' : 'false') . "\n";

// Construction avec QueryBuilder
$cmd = QueryBuilder::init($signature)
    ->setRequired('environment', 'production')
    ->setDefault('version', '2.0.0')
    ->setEnum('level', 'low')
    ->setVariadic('excludes', ['temp', 'cache'])
    ->setFlag('--force', true)
    ->setFlag('--verbose', false)
    ->setCustom('user', 'deployer')
    ->build();

echo $cmd;
// 'deploy production 2.0.0 low [temp, cache] --force <user="deployer">'
```

### Exemple 2 : Pipeline de déploiement

```php
<?php

use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;

class DeploymentPipeline
{
    public static function generateDocs(): string
    {
        $signatures = [
            'validate' => 'validate {environment} {--strict}',
            'backup' => 'backup {environment} {--force}',
            'deploy' => 'deploy {environment} {version=?} ::level->[low,medium,high]=medium {--force}',
            'verify' => 'verify {environment} {--health-check}'
        ];
        
        $docs = "# Commandes de déploiement\n\n";
        
        foreach ($signatures as $name => $signature) {
            $vo = new SignatureStructureVO($signature);
            $docs .= $vo->documentInMarkdown() . "\n---\n\n";
        }
        
        return $docs;
    }
}

echo DeploymentPipeline::generateDocs();
```

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)