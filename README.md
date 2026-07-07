# PHP Signature Parser

**Un parseur strict et typé pour les commandes CLI qui extrait la source, les arguments requis, les arguments par défaut, les variadiques et les options avec des Value Objects et des collections typées.**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Table des matières

1. [Installation](#installation)
2. [Concepts fondamentaux](#concepts-fondamentaux)
3. [Ordre strict des arguments](#ordre-strict-des-arguments)
4. [Utilisation du parseur](#utilisation-du-parseur)
5. [Value Objects](#value-objects)
   - [SignatureStructureVO](#signaturestructurevo)
   - [SignatureVO](#signaturevo)
6. [Extraction manuelle des éléments](#extraction-manuelle-des-éléments)
7. [Les parseurs internes](#les-parseurs-internes)
8. [Extensibilité](#extensibilité)
9. [Cas d'usage avancés](#cas-dusage-avancés)
10. [Exemples complets](#exemples-complets)
11. [Tests](#tests)
12. [Licence](#licence)

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

---

## Utilisation du parseur

### Utilisation de base

```php
<?php

use AndyDefer\SignatureParser\SignatureParser;

// Définition de la commande
$signature = 'backup {source} {destination} {format=zip} {output=dist} {excludes*} {purpose*} {--force} {--verbose}';

// Commande exécutée
$query = 'backup /var/www /backup tar.gz [cache, logs, tmp] [home, data, models] --force';

// Parse
$parser = new SignatureParser();
$result = $parser->parse($signature, $query);

// Accès aux données typées
echo $result->source;                                      // 'backup'
echo $result->required->first()->value;                   // '/var/www'
echo $result->default->first()->value;                    // 'tar.gz'
echo $result->variadic->first()->values->first();         // 'cache'
echo $result->options->first()->value;                    // true
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

// Parcours des options
foreach ($result->options as $opt) {
    echo "{$opt->name}: " . ($opt->value ? 'true' : 'false') . "\n";
}
// force: true
// verbose: false
```

---

## Value Objects

### SignatureStructureVO

Analyse UNIQUEMENT la structure d'une signature (sans requête).

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
$options = $vo->getOptions();        // ['force']

// Structure complète typée
$structure = $vo->getValue();
echo $structure->source;             // 'backup'
echo $structure->default->format;    // 'zip'
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
    
    // Options
    if ($vo->hasOptions()) {
        $help .= "Options:\n";
        foreach ($vo->getOptions() as $opt) {
            $help .= "  --$opt\n";
        }
    }
    
    return $help;
}

// Génère l'aide pour une commande
echo generateCommandHelp('deploy {env=production} {--force} {--verbose}');
// Usage: deploy
// 
// Arguments optionnels:
//   <env> (défaut: production)
// 
// Options:
//   --force
//   --verbose
```

---

### SignatureVO

Analyse complète avec signature ET requête.

```php
<?php

use AndyDefer\SignatureParser\ValueObjects\SignatureVO;

$vo = new SignatureVO(
    'backup {source} {destination} {format=zip} {--force}',
    'backup /var/www /backup tar.gz --force'
);

// Accès direct aux valeurs
echo $vo->getSource();                    // 'backup'
echo $vo->getRequired('source');          // '/var/www'
echo $vo->getDefault('format');           // 'tar.gz'
echo $vo->getOption('force');             // true

// Vérification de présence
if ($vo->hasOption('force')) {
    echo "Force mode activé\n";
}

// Récupération complète
$requireds = $vo->getRequireds();    // ['source' => '/var/www', 'destination' => '/backup']
$defaults = $vo->getDefaults();      // ['format' => 'tar.gz']
$options = $vo->getOptions();        // ['force' => true]

// Accès via objet typé
$parsed = $vo->getParsed();
echo $parsed->source;                // 'backup'
echo $parsed->required['source'];    // '/var/www'
```

#### Cas d'usage : Validation de commande

```php
function validateCommand(string $signature, string $query): array
{
    $vo = new SignatureVO($signature, $query);
    $errors = [];

    // Vérification des arguments requis
    foreach ($vo->getRequireds() as $name => $value) {
        if (empty($value)) {
            $errors[] = "L'argument '$name' est requis";
        }
    }

    // Vérification des options obligatoires
    if ($vo->hasOption('force') && !$vo->getOption('force')) {
        $errors[] = "L'option --force est obligatoire";
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
//   - L'option --force est obligatoire
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

Le package utilise une **chaîne de responsabilité** (Chain of Responsibility) avec 5 parseurs :

| Parser | Rôle | Syntaxe | Priorité |
|--------|------|---------|----------|
| `SourceParser` | Nom de la commande | `command` | 1 |
| `RequiredParser` | Arguments requis | `{name}` | 2 |
| `DefaultParser` | Valeurs par défaut | `{name=value}` | 3 |
| `VariadicParser` | Arguments variadiques | `{name*}` | 4 |
| `OptionsParser` | Options | `{--flag}` | 5 |

---

## Extensibilité

### Ajouter un parseur personnalisé

```php
<?php

use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;
use AndyDefer\SignatureParser\SignatureParser;

final class CustomParser implements ParserInterface
{
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        // Votre logique personnalisée
        // Ici on ajoute un champ 'custom' au résultat
        return ParsedResultRecord::from([
            'data' => ['custom' => 'valeur_personnalisee'],
            'signature' => $signature,
            'query' => $query,
        ]);
    }
}

$parser = new SignatureParser();
$parser->addParser(new CustomParser());  // S'ajoute après les parseurs par défaut

$result = $parser->parse($signature, $query);
echo $result->custom;  // 'valeur_personnalisee'
```

### Supprimer un parseur

```php
// Supprime le parseur d'options
$parser->removeParser(OptionsParser::class);

// Les options ne seront plus extraites
$result = $parser->parse($signature, $query);
```

---

## Cas d'usage avancés

### Cas 1 : Interface de ligne de commande simple

```php
<?php

use AndyDefer\SignatureParser\ValueObjects\SignatureVO;

class SimpleCli
{
    private array $commands = [];
    
    public function register(string $name, string $signature, callable $handler): void
    {
        $this->commands[$name] = [
            'signature' => $signature,
            'handler' => $handler,
        ];
    }
    
    public function run(string $query): void
    {
        $parts = explode(' ', $query);
        $commandName = $parts[0];
        
        if (!isset($this->commands[$commandName])) {
            echo "Commande inconnue: $commandName\n";
            return;
        }
        
        $command = $this->commands[$commandName];
        $vo = new SignatureVO($command['signature'], $query);
        
        // Récupère les arguments et options
        $args = $vo->getRequireds();
        $options = $vo->getOptions();
        
        // Exécute le handler
        $command['handler']($args, $options);
    }
}

// Création de l'application
$app = new SimpleCli();

// Enregistrement d'une commande
$app->register('backup', 'backup {source} {destination} {--force}', function($args, $options) {
    echo "Sauvegarde de {$args['source']} vers {$args['destination']}\n";
    if ($options['force'] ?? false) {
        echo "Mode forcé activé\n";
    }
});

// Exécution
$app->run('backup /var/www /backup --force');
// Sauvegarde de /var/www vers /backup
// Mode forcé activé
```

### Cas 2 : Générateur de documentation

```php
<?php

use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;

class DocumentationGenerator
{
    private array $commands = [];
    
    public function addCommand(string $name, string $signature, string $description): void
    {
        $this->commands[$name] = [
            'signature' => $signature,
            'description' => $description,
        ];
    }
    
    public function generate(): string
    {
        $content = "# Commandes disponibles\n\n";
        
        foreach ($this->commands as $name => $data) {
            $vo = new SignatureStructureVO($data['signature']);
            
            $content .= "## $name\n\n";
            $content .= $data['description'] . "\n\n";
            
            // Construction de l'usage
            $content .= "Usage: `$name`";
            
            if ($vo->hasRequireds()) {
                foreach ($vo->getRequireds() as $arg) {
                    $content .= " <$arg>";
                }
            }
            
            if ($vo->hasDefaults()) {
                foreach ($vo->getDefaults() as $name => $default) {
                    $content .= " [<$name>]";
                }
            }
            
            $content .= "\n\n";
            
            // Options
            if ($vo->hasOptions()) {
                $content .= "**Options:**\n";
                foreach ($vo->getOptions() as $opt) {
                    $content .= "- `--$opt`\n";
                }
                $content .= "\n";
            }
        }
        
        return $content;
    }
}

$docs = new DocumentationGenerator();
$docs->addCommand('backup', 'backup {source} {destination} {format=zip} {--force}', 'Sauvegarde des fichiers');
$docs->addCommand('deploy', 'deploy {env=production} {--force} {--verbose}', 'Déploiement de l\'application');

echo $docs->generate();
```

---

## Exemples complets

### Exemple 1 : Script de backup

```php
<?php

use AndyDefer\SignatureParser\SignatureParser;

// Signature et requête
$signature = 'backup {source} {destination} {format=zip} {excludes*} {--force}';
$query = 'backup /var/www /backup tar.gz [cache, logs, tmp] --force';

// Parsing
$parser = new SignatureParser();
$result = $parser->parse($signature, $query);

// Affichage structuré
echo "Source: " . $result->source . "\n";
echo "Source path: " . $result->required->first()->value . "\n";
echo "Destination: " . $result->required->last()->value . "\n";
echo "Format: " . $result->default->first()->value . "\n";
echo "Excludes: " . implode(', ', $result->variadic->first()->values->toArray()) . "\n";
echo "Force: " . ($result->options->first()->value ? 'Oui' : 'Non') . "\n";

// Source: backup
// Source path: /var/www
// Destination: /backup
// Format: tar.gz
// Excludes: cache, logs, tmp
// Force: Oui
```

### Exemple 2 : Commande Git

```php
<?php

use AndyDefer\SignatureParser\SignatureParser;

$signature = 'git {command} {--all} {--force}';
$query = 'git add --all';

$parser = new SignatureParser();
$result = $parser->parse($signature, $query);

echo "Source: " . $result->source . "\n";                       // 'git'
echo "Commande: " . $result->required->first()->value . "\n";   // 'add'
echo "All: " . ($result->options->first()->value ? 'Oui' : 'Non') . "\n";   // 'Oui'
echo "Force: " . ($result->options->last()->value ? 'Oui' : 'Non') . "\n";  // 'Non'
```

### Exemple 3 : Commande Docker

```php
<?php

use AndyDefer\SignatureParser\SignatureParser;

$signature = 'docker {container} {image} {--detach} {--rm}';
$query = 'docker run nginx --detach';

$parser = new SignatureParser();
$result = $parser->parse($signature, $query);

echo "Source: " . $result->source . "\n";                      // 'docker'
echo "Container: " . $result->required->first()->value . "\n"; // 'run'
echo "Image: " . $result->required->last()->value . "\n";      // 'nginx'
echo "Detach: " . ($result->options->first()->value ? 'Oui' : 'Non') . "\n"; // 'Oui'
echo "Remove: " . ($result->options->last()->value ? 'Oui' : 'Non') . "\n";  // 'Non'
```

### Exemple 4 : Utilisation des Value Objects

```php
<?php

use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;
use AndyDefer\SignatureParser\ValueObjects\SignatureVO;

// 1. Analyser la structure (sans requête)
$structure = new SignatureStructureVO('deploy {env=production} {--force} {--verbose}');

echo "Commande: " . $structure->getSource() . "\n";  // 'deploy'
echo "Arguments: " . $structure->countArguments() . "\n";  // 1 (seulement 'env')

if ($structure->hasDefault('env')) {
    $defaults = $structure->getDefaults();
    echo "Environnement par défaut: " . $defaults['env'] . "\n";  // 'production'
}

// 2. Analyser avec la requête
$full = new SignatureVO(
    'deploy {env=production} {--force} {--verbose}',
    'deploy staging --force'
);

echo "Environnement: " . $full->getDefault('env') . "\n";  // 'staging' (override)
echo "Force: " . ($full->getOption('force') ? 'Oui' : 'Non') . "\n";  // 'Oui'
echo "Verbose: " . ($full->getOption('verbose') ? 'Oui' : 'Non') . "\n";  // 'Non'

// 3. Vérification des arguments
if ($full->hasRequired('env')) {
    echo "L'environnement est fourni: " . $full->getRequired('env') . "\n";
}
```

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)