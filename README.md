# PHP Signature Parser

**Un parseur strict et typé pour les commandes CLI qui extrait la source, les arguments requis, les arguments par défaut, les nullables, les variadiques et les flags avec des Value Objects et des collections typées. Support automatique du formatage des espaces via le caractère `^` et des tokens spéciaux (`?`, `~`).**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Table des matières

1. [Installation](#installation)
2. [Concepts fondamentaux](#concepts-fondamentaux)
3. [Formatage des espaces avec `^`](#formatage-des-espaces-avec-)
4. [Tokens spéciaux](#tokens-spéciaux)
   - [Le token `?` (null explicite)](#le-token--null-explicite)
   - [Le token `~` (skip)](#le-token--skip)
5. [Ordre strict des arguments](#ordre-strict-des-arguments)
6. [Utilisation du parseur](#utilisation-du-parseur)
7. [Manipulation des collections](#manipulation-des-collections)
   - [ArgumentCollection](#argumentcollection)
   - [FlagCollection](#flagcollection)
   - [VariadicArgumentCollection](#variadicargumentcollection)
8. [Value Objects](#value-objects)
   - [SignatureStructureVO](#signaturestructurevo)
   - [SignatureVO](#signaturevo)
9. [QueryBuilder - Construction dynamique](#querybuilder---construction-dynamique)
10. [Tags personnalisés](#tags-personnalisés)
11. [Extraction manuelle des éléments](#extraction-manuelle-des-éléments)
12. [Les parseurs internes](#les-parseurs-internes)
13. [Extensibilité](#extensibilité)
14. [Cas d'usage avancés](#cas-dusage-avancés)
15. [Exemples complets](#exemples-complets)
16. [Licence](#licence)

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
$signature = 'backup {source} {destination} {format=zip} {env=?} {excludes*} {purpose*} {--force} {--verbose}';
```

| Élément | Syntaxe | Description |
|---------|---------|-------------|
| **Source** | `backup` | Nom de la commande (position 0) |
| **Requis** | `{source}` | Argument obligatoire |
| **Par défaut** | `{format=zip}` | Argument avec valeur par défaut |
| **Nullable** | `{env=?}` | Argument pouvant être `null` |
| **Variadique** | `{excludes*}` | Argument qui capture plusieurs valeurs |
| **Flag** | `{--force}` | Flag optionnel (booléen) |
| **Tag personnalisé** | `<key="value">` | Données supplémentaires (non définies dans la signature) |

### La requête

La requête est la commande réelle exécutée par l'utilisateur.

```php
$query = 'backup /var/www /backup tar.gz staging [cache, logs, tmp] [home, data, models] --force <user="admin">';
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
| `admin^user` | `admin user` |
| `PHP^8.4^features` | `PHP 8.4 features` |

### Exemples avec commandes

```bash
# ❌ Mauvaise syntaxe
command John Doe          # Deux arguments séparés
command "John Doe"        # Non supporté

# ✅ Bonne syntaxe
command John^Doe          # Un seul argument avec espace
```

### Exemples de code

```php
// Arguments requis
$signature = 'user:create {name} {email}';
$query = 'user:create John^Doe john@example.com';

$result = $parser->parse($signature, $query);
// $result->required->first()->value = 'John Doe'

// Valeurs par défaut
$signature = 'user:list {format=zip}';
$query = 'user:list tar^gz';
$result = $parser->parse($signature, $query);
// $result->default->first()->value = 'tar gz'

// Variadiques
$signature = 'process {files*}';
$query = 'process [file^1.txt, file^2.txt, my^file^3.txt]';
$result = $parser->parse($signature, $query);
// $result->variadic->first()->values = ['file 1.txt', 'file 2.txt', 'my file 3.txt']
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

### Exemples

```php
// Requis → null
$signature = 'backup {source} {destination}';
$query = 'backup /var/www ~';
// destination = null

// Par défaut → valeur par défaut
$signature = 'backup {source} {format=zip}';
$query = 'backup /var/www ~';
// format = zip

// Nullable → null
$signature = 'deploy {env=?} {--force}';
$query = 'deploy ~ --force';
// env = null
```

### Tokens échappés

| Valeur | Résultat | Description |
|--------|----------|-------------|
| `??` | `?` | Point d'interrogation littéral |
| `~~` | `~` | Tilde littéral |
| `?` | `null` | Valeur null |
| `~` | `null` | Skip |

---

## Ordre strict des arguments

⚠️ **L'ordre des éléments dans la signature est STRICT et IMPÉRATIF.**

| Ordre | Type | Syntaxe | Exemple |
|-------|------|---------|---------|
| **1** | **Source** | `command` | `backup` |
| **2** | **Requis** | `{name}` | `{source}` `{destination}` |
| **3** | **Par défaut** | `{name=value}` | `{format=zip}` `{output=dist}` |
| **4** | **Nullable** | `{name=?}` | `{env=?}` `{port=?}` |
| **5** | **Variadique** | `{name*}` | `{excludes*}` `{purpose*}` |
| **6** | **Flags** | `{--flag}` | `{--force}` `{--verbose}` |
| **7** | **Tags personnalisés** | `<key="value">` | `<user="admin">` |

### Règles strictes

| Règle | Description |
|-------|-------------|
| **Source** | Toujours en première position (position 0) |
| **Requis** | Viennent en premier, avant tous les autres |
| **Par défaut** | Viennent après les requis, avant les nullables et variadiques |
| **Nullable** | `{name=?}` - Viennent après les par défaut, avant les variadiques |
| **Variadiques** | Toujours en dernière position des arguments |
| **Flags** | Peuvent être à n'importe quelle position après la source |
| **Tags personnalisés** | Toujours en dernière position (après les flags) |
| **Ordre de la requête** | Doit respecter l'ordre de la signature |

### Exemples d'ordre valide

```php
// ✅ Ordre correct
$signature = 'backup {source} {destination} {format=zip} {env=?} {excludes*} {--force}';

// ✅ Flags à la fin ou entre
$signature = 'backup {source} {--force} {destination} {format=zip}';

// ✅ Tags personnalisés à la fin
$query = 'backup /var/www /backup --force <user="admin">';
```

### Exemples d'ordre invalide

```php
// ❌ Requis après défaut
$signature = 'backup {format=zip} {source}';

// ❌ Variadique avant défaut
$signature = 'backup {excludes*} {format=zip}';

// ❌ Flag avant arguments
$signature = 'backup {--force} {source}';

// ❌ Tags avant flags
$query = 'backup /var/www <user="admin"> --force';
```

---

## Utilisation du parseur

### Utilisation de base

```php
<?php

use AndyDefer\SignatureParser\SignatureParser;

// Définition de la commande
$signature = 'backup {source} {destination} {format=zip} {output=dist} {env=?} {excludes*} {purpose*} {--force} {--verbose}';

// Commande exécutée
$query = 'backup /var/www /backup tar.gz dist staging [cache, logs, tmp] [home, data, models] --force';

// Parse
$parser = new SignatureParser();
$result = $parser->parse($signature, $query);

// Accès aux données typées
echo $result->source;                                      // 'backup'
echo $result->required->first()->value;                   // '/var/www'
echo $result->default->first()->value;                    // 'tar.gz'
echo $result->variadic->first()->values->first();         // 'cache'
echo $result->flags->first()->value;                      // true
```

### Parcours des collections

```php
// Parcours des arguments requis
foreach ($result->required as $arg) {
    echo "{$arg->name}: {$arg->value}\n";
}
// source: /var/www
// destination: /backup

// Parcours des valeurs par défaut
foreach ($result->default as $arg) {
    echo "{$arg->name}: {$arg->value}\n";
}
// format: tar.gz
// output: dist

// Parcours des variadiques
foreach ($result->variadic as $arg) {
    echo "{$arg->name}: " . implode(', ', $arg->values->toArray()) . "\n";
}
// excludes: cache, logs, tmp
// purpose: home, data, models

// Parcours des flags
foreach ($result->flags as $flag) {
    echo "{$flag->name}: " . ($flag->value ? 'true' : 'false') . "\n";
}
// force: true
// verbose: false

// Accès aux tags personnalisés
$customData = $result->custom_data->toArray();
foreach ($customData as $key => $value) {
    echo "{$key}: {$value}\n";
}
// user: admin
```

### Validation de requête

```php
$result = $parser->validate(
    'backup {source} {destination}',
    'backup /var/www'
);

if (!$result->isValid) {
    foreach ($result->errors as $error) {
        echo "❌ $error\n";
    }
    foreach ($result->suggestions as $suggestion) {
        echo "💡 $suggestion\n";
    }
}
```

### Validation de signature

```php
$result = $parser->validateSignature('backup {source} {format=zip} {excludes*} {--force}');

if ($result->isValid) {
    echo "✅ Signature valide\n";
} else {
    foreach ($result->errors as $error) {
        echo "❌ $error\n";
    }
}
```

---

## Manipulation des collections

Le résultat du parseur (`ParsedSignatureRecord`) contient 4 collections typées plus les données personnalisées.

### ArgumentCollection

Collection d'arguments (`ArgumentRecord`) avec leurs noms et valeurs.

```php
use AndyDefer\SignatureParser\Collections\ArgumentCollection;
use AndyDefer\SignatureParser\Records\ArgumentRecord;

$collection = new ArgumentCollection();
$collection->add(
    new ArgumentRecord('source', '/var/www'),
    new ArgumentRecord('destination', '/backup'),
    new ArgumentRecord('format', 'tar.gz')
);

// Récupérer une valeur par nom
$source = $collection->get('source');        // '/var/www'
$unknown = $collection->get('unknown');      // null

// Vérifier si un argument existe
if ($collection->has('destination')) {
    echo "Destination définie";
}

// Récupérer tous les noms
$names = $collection->getNames();            // ['source', 'destination', 'format']

// Récupérer toutes les valeurs
$values = $collection->getValues();          // ['/var/www', '/backup', 'tar.gz']

// Convertir en tableau associatif
$assoc = $collection->toAssociativeArray();  // ['source' => '/var/www', 'destination' => '/backup', 'format' => 'tar.gz']
```

#### Cas d'usage : Récupération d'arguments dans une commande

```php
$source = $result->required->get('source');
$destination = $result->required->get('destination');

if (!$result->required->has('source')) {
    throw new \Exception("Source argument is required");
}

// Transformer en tableau associatif pour une API
$payload = $result->required->toAssociativeArray();
```

---

### FlagCollection

Collection de flags (`FlagRecord`) avec leurs noms et valeurs booléennes.

```php
use AndyDefer\SignatureParser\Collections\FlagCollection;
use AndyDefer\SignatureParser\Records\FlagRecord;

$collection = new FlagCollection();
$collection->add(
    new FlagRecord('force', true),
    new FlagRecord('verbose', false),
    new FlagRecord('all', true)
);

// Récupérer la valeur d'un flag
$force = $collection->get('force');          // true
$verbose = $collection->get('verbose');      // false
$unknown = $collection->get('unknown');      // false (par défaut)

// Vérifier si un flag existe
if ($collection->has('force')) {
    echo "Flag force présent";
}

// Vérifier si un flag est actif
if ($collection->isActive('force')) {
    echo "Mode force activé";
}

// Récupérer tous les flags actifs
$active = $collection->getActiveNames();     // ['force', 'all']

// Récupérer tous les noms
$names = $collection->getNames();            // ['force', 'verbose', 'all']

// Convertir en tableau associatif
$assoc = $collection->toAssociativeArray();  // ['force' => true, 'verbose' => false, 'all' => true]
```

#### Cas d'usage : Validation des flags

```php
// Vérification des flags requis
if (!$result->flags->isActive('force')) {
    echo "Le flag --force est requis pour cette opération";
}

// Liste des flags actifs
$activeFlags = $result->flags->getActiveNames();
echo "Flags actifs: " . implode(', ', $activeFlags);
```

---

### VariadicArgumentCollection

Collection d'arguments variadiques (`VariadicArgumentRecord`) avec leurs noms et listes de valeurs.

```php
use AndyDefer\SignatureParser\Collections\VariadicArgumentCollection;
use AndyDefer\SignatureParser\Records\VariadicArgumentRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

$collection = new VariadicArgumentCollection();
$collection->add(
    new VariadicArgumentRecord('excludes', StringTypedCollection::from(['cache', 'logs', 'tmp'])),
    new VariadicArgumentRecord('includes', StringTypedCollection::from(['src', 'tests']))
);

// Récupérer les valeurs d'un argument variadique
$excludes = $collection->get('excludes');    // ['cache', 'logs', 'tmp']
$unknown = $collection->get('unknown');      // []

// Vérifier si un argument variadique existe
if ($collection->has('excludes')) {
    echo "Excludes défini";
}

// Récupérer tous les noms
$names = $collection->getNames();            // ['excludes', 'includes']

// Récupérer toutes les valeurs (aplatit tout)
$allValues = $collection->getAllValues();    // ['cache', 'logs', 'tmp', 'src', 'tests']

// Compter le nombre total de valeurs
$total = $collection->countAllValues();      // 5

// Convertir en tableau associatif
$assoc = $collection->toAssociativeArray();  // ['excludes' => ['cache', 'logs', 'tmp'], 'includes' => ['src', 'tests']]
```

#### Cas d'usage : Traitement des fichiers en lot

```php
// Traitement des fichiers
$files = $result->variadic->get('files');
foreach ($files as $file) {
    echo "Processing: $file\n";
}

// Vérification s'il y a des fichiers à traiter
if ($result->variadic->has('files')) {
    $count = $result->variadic->countAllValues();
    echo "Traitement de $count fichiers...";
}
```

---

## Value Objects

### SignatureStructureVO

Analyse UNIQUEMENT la structure d'une signature (sans requête) et la valide.

```php
<?php

use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;

$vo = new SignatureStructureVO('backup {source} {destination} {format=zip} {excludes*} {--force}');

// Accès aux informations
echo $vo->getSource();          // 'backup'

// Vérification de la présence d'arguments
if ($vo->hasRequired('source')) {
    echo "L'argument source est requis\n";
}

// Récupération des listes
$requireds = $vo->getRequireds();    // ['source', 'destination']
$defaults = $vo->getDefaults();      // ['format' => 'zip']
$variadics = $vo->getVariadics();    // ['excludes']
$flags = $vo->getFlags();            // ['force']

// Structure complète typée
$structure = $vo->getValue();
echo $structure->source;             // 'backup'
echo $structure->default->format;    // 'zip'

// Validation de la signature
if ($vo->isValid()) {
    echo "✅ Signature valide";
} else {
    foreach ($vo->getValidationErrors() as $error) {
        echo "❌ $error\n";
    }
}
```

#### Cas d'usage : Génération de documentation

```php
function generateCommandHelp(string $signature): string
{
    $vo = new SignatureStructureVO($signature);
    
    $help = "Usage: " . $vo->getSource() . "\n\n";
    
    // Arguments requis
    if ($vo->hasRequireds()) {
        $help .= "Arguments requis:\n";
        foreach ($vo->getRequireds() as $arg) {
            $help .= "  <$arg>\n";
        }
        $help .= "\n";
    }
    
    // Arguments avec valeurs par défaut
    if ($vo->hasDefaults()) {
        $help .= "Arguments optionnels:\n";
        foreach ($vo->getDefaults() as $name => $value) {
            $help .= "  <$name> (défaut: $value)\n";
        }
        $help .= "\n";
    }
    
    // Flags
    if ($vo->hasFlags()) {
        $help .= "Flags:\n";
        foreach ($vo->getFlags() as $flag) {
            $help .= "  --$flag\n";
        }
    }
    
    // Tags personnalisés
    $help .= "\nTags personnalisés:\n";
    $help .= "  <key=\"value\"> - Données supplémentaires\n";
    
    return $help;
}

// Génère l'aide pour une commande
echo generateCommandHelp('deploy {env=production} {--force} {--verbose}');
// Usage: deploy
// 
// Arguments optionnels:
//   <env> (défaut: production)
// 
// Flags:
//   --force
//   --verbose
// 
// Tags personnalisés:
//   <key="value"> - Données supplémentaires
```

---

### SignatureVO

Analyse complète avec signature ET requête.

```php
<?php

use AndyDefer\SignatureParser\ValueObjects\SignatureVO;

$vo = new SignatureVO(
    'backup {source} {destination} {format=zip} {--force}',
    'backup /var/www /backup tar.gz --force <user="admin">'
);

// Accès direct aux valeurs
echo $vo->getSource();                    // 'backup'
echo $vo->getRequired('source');          // '/var/www'
echo $vo->getDefault('format');           // 'tar.gz'
echo $vo->getFlag('force');               // true
echo $vo->getCustom('user');              // 'admin'

// Vérification de présence
if ($vo->hasFlag('force')) {
    echo "Force mode activé\n";
}

if ($vo->hasCustom('user')) {
    echo "User: " . $vo->getCustom('user') . "\n";
}

// Récupération complète
$requireds = $vo->getRequireds();    // ['source' => '/var/www', 'destination' => '/backup']
$defaults = $vo->getDefaults();      // ['format' => 'tar.gz']
$flags = $vo->getFlags();            // ['force' => true]
$customs = $vo->getCustoms();         // ['user' => 'admin']

// Accès via objet typé
$parsed = $vo->getParsed();
echo $parsed->source;                // 'backup'
echo $parsed->required['source'];    // '/var/www'
echo $parsed->custom_tags['user'];   // 'admin'

// Validation
if (!$vo->isValid()) {
    foreach ($vo->getValidationErrors() as $error) {
        echo "❌ $error\n";
    }
}
```

#### Cas d'usage : Validation de commande

```php
function validateCommand(string $signature, string $query): array
{
    $vo = new SignatureVO($signature, $query);
    $errors = [];

    // Vérification des arguments requis
    foreach ($vo->getRequireds() as $name => $value) {
        if (empty($value) || $value === '~') {
            $errors[] = "L'argument '$name' est requis";
        }
    }

    // Vérification des flags obligatoires
    if ($vo->hasFlag('force') && !$vo->getFlag('force')) {
        $errors[] = "Le flag --force est obligatoire";
    }

    return $errors;
}

// Validation d'une commande
$errors = validateCommand(
    'deploy {env} {--force}',
    'deploy staging'
);

if (empty($errors)) {
    echo "Commande valide\n";
} else {
    echo "Erreurs:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}
// Erreurs:
//   - Le flag --force est obligatoire
```

---

## QueryBuilder - Construction dynamique

Le `QueryBuilder` permet de construire programmatiquement des requêtes CLI à partir d'une signature avec un chaînage fluide.

### Utilisation de base avec chaînage

```php
<?php

use AndyDefer\SignatureParser\QueryBuilder;

$query = QueryBuilder::init('greet {name} {--formal}')
    ->setRequired('name', 'John')
    ->setFlag('--formal', true)
    ->build();

echo $query; // 'greet John --formal'
```

### Arguments avec valeur par défaut

```php
$query = QueryBuilder::init('backup {source} {format=zip} {--force}')
    ->setRequired('source', '/var/www')
    ->setDefault('format', 'tar.gz')
    ->setFlag('--force', true)
    ->build();

echo $query; // 'backup /var/www tar.gz --force'
```

### Arguments variadiques avec tableaux

```php
$query = QueryBuilder::init('process {files*} {--verbose}')
    ->setVariadic('files', ['file1.txt', 'file2.txt', 'file3.txt'])
    ->setFlag('--verbose', true)
    ->build();

echo $query; // 'process [file1.txt, file2.txt, file3.txt] --verbose'

// Avec une chaîne (comportement existant)
$query = QueryBuilder::init('process {files*} {--verbose}')
    ->setVariadic('files', 'file1.txt, file2.txt, file3.txt')
    ->setFlag('--verbose', true)
    ->build();
// Même résultat
```

### Tags personnalisés avec chaînage

```php
$query = QueryBuilder::init('send {recipient} {--verbose}')
    ->setRequired('recipient', 'John')
    ->setFlag('--verbose', true)
    ->setCustom('greeting', 'Hello World')
    ->setCustom('later', 'goodby')
    ->build();

echo $query; // 'send John --verbose <greeting="Hello World"> <later="goodby">'
```

### Multiple tags en une fois

```php
$query = QueryBuilder::init('deploy {environment} {--force}')
    ->setRequired('environment', 'staging')
    ->setFlag('--force', true)
    ->setCustoms([
        'version' => '1.2.3',
        'user' => 'admin',
        'timestamp' => '2026-07-10'
    ])
    ->build();

echo $query; // 'deploy staging --force <version="1.2.3"> <user="admin"> <timestamp="2026-07-10">'
```

### Parsing d'une requête initiale

```php
$builder = QueryBuilder::init(
    'send {recipient} {--verbose}',
    'send John --verbose <greeting="Hello">'
);

// Les valeurs sont déjà chargées
echo $builder->getRequired('recipient'); // 'John'
echo $builder->getCustom('greeting');    // 'Hello'

// Modifier avec chaînage
$query = $builder
    ->setCustom('greeting', 'Hello World')
    ->setCustom('later', 'goodby')
    ->build();

echo $query; // 'send John --verbose <greeting="Hello World"> <later="goodby">'
```

### Validation et erreurs

```php
$builder = QueryBuilder::init('greet {name} {--formal}');

if (!$builder->isValid()) {
    foreach ($builder->getErrors() as $error) {
        echo "❌ $error\n";
    }
}
```

### Reset du builder

```php
$query = QueryBuilder::init('greet {name} {--formal}')
    ->setRequired('name', 'John')
    ->setFlag('--formal', true)
    ->reset()  // Réinitialise tout
    ->build();

echo $query; // 'greet ~' (avec les valeurs par défaut)
```
---

## Tags personnalisés

Les tags personnalisés permettent d'ajouter des données supplémentaires à une commande sans modifier la signature.

### Syntaxe

```php
<key="value">
```

### Utilisation avec le parser

```php
$signature = 'send {recipient} {--verbose}';
$query = 'send John --verbose <greeting="Hello World"> <later="goodby">';

$result = $parser->parse($signature, $query);

// Accès aux tags personnalisés
$customData = $result->custom_data->toArray();
echo $customData['greeting']; // 'Hello World'
echo $customData['later'];    // 'goodby'
```

### Tags avec QueryBuilder

```php
$query = QueryBuilder::init('deploy {environment}')
    ->setRequired('environment', 'staging')
    ->setCustoms([
        'version' => '1.2.3',
        'user' => 'admin',
        'timestamp' => '2026-07-10'
    ])
    ->build();

// 'deploy staging <version="1.2.3"> <user="admin"> <timestamp="2026-07-10">'
```

### Accès dans SignatureVO

```php
$vo = new SignatureVO($signature, $query);

echo $vo->getCustom('greeting');   // 'Hello World'
echo $vo->hasCustom('later');      // true

$allCustoms = $vo->getCustoms();
// ['greeting' => 'Hello World', 'later' => 'goodby']
```

### Validation des tags personnalisés

Le `CustomTagParser` valide automatiquement la syntaxe des tags :

```php
$result = $parser->validate(
    'send {recipient}',
    'send John <greeting="Hello"> <invalid_tag>'
);

if (!$result->isValid) {
    foreach ($result->errors as $error) {
        echo "❌ $error\n";
    }
}
// ❌ Invalid custom tag syntax: <invalid_tag>
```

---

## Extraction manuelle des éléments

```php
$parser = new SignatureParser();

// Extraction des éléments de la signature
$elements = $parser->extractSignatureElements('backup {source} {destination} {--force}');
// StringTypedCollection ['backup', 'source', 'destination', '--force']

// Extraction des éléments de la requête
$elements = $parser->extractQueryElements('backup /var/www /backup [cache, logs] --force');
// StringTypedCollection ['backup', '/var/www', '/backup', '[cache, logs]', '--force']
```

---

## Les parseurs internes

Le package utilise une **chaîne de responsabilité** (Chain of Responsibility) avec 6 parseurs :

| Parser | Rôle | Syntaxe | Priorité |
|--------|------|---------|----------|
| `SourceParser` | Nom de la commande | `command` | 1 |
| `RequiredParser` | Arguments requis | `{name}` | 2 |
| `DefaultParser` | Par défaut et nullables | `{name=value}`, `{name=?}` | 3 |
| `VariadicParser` | Arguments variadiques | `{name*}` | 4 |
| `FlagParser` | Flags | `{--flag}` | 5 |
| `CustomTagParser` | Tags personnalisés | `<key="value">` | 6 |

---

## Extensibilité

### Ajouter un parseur personnalisé

```php
<?php

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

### Parseur de tags personnalisés intégré

Le package inclut un `CustomTagParser` qui extrait automatiquement les tags `<key="value">`.

```php
// Les tags sont automatiquement extraits et placés dans custom_data
$result = $parser->parse(
    'send {recipient}',
    'send John <greeting="Hello"> <later="goodby">'
);

$customData = $result->custom_data->toArray();
// ['greeting' => 'Hello', 'later' => 'goodby']
```

### Supprimer un parseur

```php
// Supprime le parseur de tags personnalisés
$parser->removeParser(CustomTagParser::class);

// Les tags ne seront plus extraits
$result = $parser->parse('send {recipient}', 'send John <greeting="Hello">');
// $result->custom_data est vide
```

---

## Cas d'usage avancés

### Cas 1 : Interface de ligne de commande avec QueryBuilder

```php
<?php

use AndyDefer\SignatureParser\QueryBuilder;

class CliApplication
{
    public function buildDeployCommand(string $env, string $version, array $files): string
    {
        return QueryBuilder::init('deploy {environment} {version=?} {files*} {--force}')
            ->setRequired('environment', $env)
            ->setDefault('version', $version)
            ->setVariadic('files', $files)
            ->setFlag('--force', true)
            ->build();
    }
}

$app = new CliApplication();
$cmd = $app->buildDeployCommand('staging', '1.2.3', ['config.yaml', 'secrets.json']);
// 'deploy staging 1.2.3 [config.yaml, secrets.json] --force'
```

### Cas 2 : Validation avancée avec les collections

```php
<?php

use AndyDefer\SignatureParser\SignatureParser;

function validateBackupCommand(string $query): array
{
    $parser = new SignatureParser();
    $result = $parser->parse(
        'backup {source} {destination} {--force} {--verbose}',
        $query
    );
    
    $errors = [];
    
    if (!$result->required->has('source')) {
        $errors[] = "La source est requise";
    }
    
    if (!$result->required->has('destination')) {
        $errors[] = "La destination est requise";
    }
    
    $activeFlags = $result->flags->getActiveNames();
    if (in_array('verbose', $activeFlags) && !in_array('force', $activeFlags)) {
        $errors[] = "Le flag --verbose nécessite --force";
    }
    
    // Vérification des tags personnalisés
    $customData = $result->custom_data->toArray();
    if (isset($customData['user']) && $customData['user'] === '') {
        $errors[] = "Le tag user ne peut pas être vide";
    }
    
    return $errors;
}
```

### Cas 3 : Génération de documentation avec tags personnalisés

```php
<?php

use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;

function generateHelp(string $signature): string
{
    $vo = new SignatureStructureVO($signature);
    $help = "Usage: " . $vo->getSource() . "\n\n";
    
    if ($vo->hasRequireds()) {
        $help .= "Arguments requis:\n";
        foreach ($vo->getRequireds() as $arg) {
            $help .= "  <$arg>\n";
        }
        $help .= "\n";
    }
    
    if ($vo->hasDefaults()) {
        $help .= "Arguments optionnels:\n";
        foreach ($vo->getDefaults() as $name => $value) {
            $display = $value ?? 'null';
            $help .= "  <$name> (défaut: $display)\n";
        }
        $help .= "\n";
    }
    
    if ($vo->hasFlags()) {
        $help .= "Flags:\n";
        foreach ($vo->getFlags() as $flag) {
            $help .= "  --$flag\n";
        }
        $help .= "\n";
    }
    
    // Note sur les tags personnalisés
    $help .= "Tags personnalisés:\n";
    $help .= "  <key=\"value\"> - Données supplémentaires\n";
    $help .= "  Exemple: <user=\"admin\"> <version=\"1.2.3\">\n";
    
    return $help;
}
```

### Cas 4 : Construction de commandes complexes avec chaînage

```php
<?php

use AndyDefer\SignatureParser\QueryBuilder;

class DeploymentCommandBuilder
{
    public static function build(
        string $environment,
        string $version,
        array $files,
        bool $force = false,
        bool $verbose = false,
        array $tags = []
    ): string {
        return QueryBuilder::init('deploy {environment} {version=?} {files*} {--force} {--verbose}')
            ->setRequired('environment', $environment)
            ->setDefault('version', $version)
            ->setVariadic('files', $files)
            ->setFlag('--force', $force)
            ->setFlag('--verbose', $verbose)
            ->setCustoms($tags)
            ->build();
    }
}

$cmd = DeploymentCommandBuilder::build(
    environment: 'production',
    version: '2.0.0',
    files: ['app.yaml', 'database.sql', 'secrets.json'],
    force: true,
    verbose: false,
    tags: [
        'user' => 'deployer',
        'timestamp' => '2026-07-10',
        'commit' => 'abc123'
    ]
);

echo $cmd;
// 'deploy production 2.0.0 [app.yaml, database.sql, secrets.json] --force <user="deployer"> <timestamp="2026-07-10"> <commit="abc123">'
```

---

## Exemples complets

### Exemple 1 : Commande avec tags personnalisés

```php
<?php

use AndyDefer\SignatureParser\SignatureParser;
use AndyDefer\SignatureParser\QueryBuilder;

$parser = new SignatureParser();

// 1. Parse avec tags
$result = $parser->parse(
    'deploy {environment} {--force}',
    'deploy staging --force <user="admin"> <version="1.2.3">'
);

echo "Environnement: " . $result->required->first()->value . "\n";
echo "Force: " . ($result->flags->first()->value ? 'true' : 'false') . "\n";

$customData = $result->custom_data->toArray();
echo "User: " . $customData['user'] . "\n";
echo "Version: " . $customData['version'] . "\n";

// 2. Construire avec QueryBuilder
$query = QueryBuilder::init('deploy {environment} {--force}')
    ->setRequired('environment', 'production')
    ->setFlag('--force', true)
    ->setCustoms([
        'user' => 'deployer',
        'timestamp' => '2026-07-10'
    ])
    ->build();

echo $query . "\n";
// 'deploy production --force <user="deployer"> <timestamp="2026-07-10">'
```

### Exemple 2 : Application CLI complète

```php
<?php

use AndyDefer\SignatureParser\SignatureParser;
use AndyDefer\SignatureParser\QueryBuilder;
use AndyDefer\SignatureParser\ValueObjects\SignatureVO;

class TaskRunner
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
            'args' => $result->required->toAssociativeArray(),
            'flags' => $result->flags->toAssociativeArray(),
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

// Utilisation
$runner = new TaskRunner();

// Exécution
$result = $runner->run(
    'deploy {environment} {--force}',
    'deploy staging --force <user="admin">'
);

// Construction
$cmd = $runner->build('deploy {environment} {--force}', [
    'args' => ['environment' => 'production'],
    'flags' => ['force' => true],
    'custom' => ['user' => 'deployer']
]);
echo $cmd; // 'deploy production --force <user="deployer">'
```

### Exemple 3 : Pipeline de déploiement

```php
<?php

use AndyDefer\SignatureParser\QueryBuilder;

class DeploymentPipeline
{
    public static function buildDeploySteps(string $env): array
    {
        $validate = QueryBuilder::init('validate {environment}')
            ->setRequired('environment', $env)
            ->build();
        
        $backup = QueryBuilder::init('backup {environment}')
            ->setRequired('environment', $env)
            ->setCustom('timestamp', date('Y-m-d_H-i-s'))
            ->build();
        
        $deploy = QueryBuilder::init('deploy {environment} {--force}')
            ->setRequired('environment', $env)
            ->setFlag('--force', true)
            ->setCustom('user', get_current_user())
            ->build();
        
        $verify = QueryBuilder::init('verify {environment}')
            ->setRequired('environment', $env)
            ->setCustom('checks', 'health,status')
            ->build();
        
        return [$validate, $backup, $deploy, $verify];
    }
}

$steps = DeploymentPipeline::buildDeploySteps('staging');
foreach ($steps as $step) {
    echo $step . "\n";
}

// validate staging
// backup staging <timestamp="2026-07-10_12-34-56">
// deploy staging --force <user="john">
// verify staging <checks="health,status">
```

### Exemple 4 : Génération de rapport avec tags

```php
<?php

use AndyDefer\SignatureParser\ValueObjects\SignatureVO;

function generateReport(string $signature, string $query): string
{
    $vo = new SignatureVO($signature, $query);
    
    $report = "=== Rapport de commande ===\n";
    $report .= "Commande: " . $vo->getSource() . "\n";
    $report .= "Arguments:\n";
    
    foreach ($vo->getRequireds() as $name => $value) {
        $report .= "  - $name: $value\n";
    }
    
    if ($vo->hasDefaults()) {
        $report .= "Valeurs par défaut:\n";
        foreach ($vo->getDefaults() as $name => $value) {
            $display = $value ?? 'null';
            $report .= "  - $name: $display\n";
        }
    }
    
    if ($vo->hasFlags()) {
        $report .= "Flags:\n";
        foreach ($vo->getFlags() as $name => $active) {
            $report .= "  - --$name: " . ($active ? 'actif' : 'inactif') . "\n";
        }
    }
    
    if ($vo->hasCustoms()) {
        $report .= "Tags personnalisés:\n";
        foreach ($vo->getCustoms() as $key => $value) {
            $report .= "  - $key: $value\n";
        }
    }
    
    return $report;
}

$report = generateReport(
    'deploy {environment} {--force}',
    'deploy staging --force <user="admin"> <version="1.2.3">'
);

echo $report;
```

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)