# PHP Signature Parser

**Un parseur strict et typé pour les commandes CLI qui extrait la source, les arguments requis, les arguments par défaut, les variadiques et les options avec des Value Objects et des collections typées. Support automatique du formatage des espaces via le caractère `^`.**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Table des matières

1. [Installation](#installation)
2. [Concepts fondamentaux](#concepts-fondamentaux)
3. [Formatage des espaces avec `^`](#formatage-des-espaces-avec-)
4. [Ordre strict des arguments](#ordre-strict-des-arguments)
5. [Utilisation du parseur](#utilisation-du-parseur)
6. [Value Objects](#value-objects)
   - [SignatureStructureVO](#signaturestructurevo)
   - [SignatureVO](#signaturevo)
7. [Extraction manuelle des éléments](#extraction-manuelle-des-éléments)
8. [Les parseurs internes](#les-parseurs-internes)
9. [Extensibilité](#extensibilité)
10. [Cas d'usage avancés](#cas-dusage-avancés)
11. [Exemples complets](#exemples-complets)
12. [Tests](#tests)
13. [Licence](#licence)

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

// Enregistrement d'une commande avec espaces dans les valeurs
$app->register('backup', 'backup {source} {destination} {--force}', function($args, $options) {
    echo "Sauvegarde de {$args['source']} vers {$args['destination']}\n";
    if ($options['force'] ?? false) {
        echo "Mode forcé activé\n";
    }
});

// Exécution avec espaces via ^
$app->run('backup /home/user/My^Project /backup --force');
// Sauvegarde de /home/user/My Project vers /backup
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
            
            // Note sur le formatage des espaces
            $content .= "**Note:** Utilisez `^` pour les espaces dans les valeurs.\n";
            $content .= "Exemple: `$name John^Doe`\n\n";
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

### Exemple 1 : Script de backup avec espaces

```php
<?php

use AndyDefer\SignatureParser\SignatureParser;

// Signature et requête avec espaces dans les valeurs
$signature = 'backup {source} {destination} {format=zip} {excludes*} {--force}';
$query = 'backup /home/user/My^Project /backup tar^gz [cache^folder, logs^folder] --force';

// Parsing
$parser = new SignatureParser();
$result = $parser->parse($signature, $query);

// Affichage structuré
echo "Source: " . $result->source . "\n";  // 'backup'
echo "Source path: " . $result->required->first()->value . "\n";  // '/home/user/My Project'
echo "Destination: " . $result->required->last()->value . "\n";   // '/backup'
echo "Format: " . $result->default->first()->value . "\n";        // 'tar gz'
echo "Excludes: " . implode(', ', $result->variadic->first()->values->toArray()) . "\n";  // 'cache folder, logs folder'
echo "Force: " . ($result->options->first()->value ? 'Oui' : 'Non') . "\n";  // 'Oui'
```

### Exemple 2 : Commande utilisateur avec nom complet

```php
<?php

use AndyDefer\SignatureParser\SignatureParser;

$signature = 'user:create {name} {email} {--role}';
$query = 'user:create John^Doe john@example.com --role';

$parser = new SignatureParser();
$result = $parser->parse($signature, $query);

echo "Source: " . $result->source . "\n";                       // 'user:create'
echo "Nom: " . $result->required->first()->value . "\n";        // 'John Doe'
echo "Email: " . $result->required->last()->value . "\n";       // 'john@example.com'
echo "Role: " . ($result->options->first()->value ? 'Oui' : 'Non') . "\n";  // 'Oui'
```

### Exemple 3 : Commande avec message long

```php
<?php

use AndyDefer\SignatureParser\SignatureParser;

$signature = 'log {level} {message}';
$query = 'log error [ERROR]^Failed^to^connect^to^database:^Connection^timed^out';

$parser = new SignatureParser();
$result = $parser->parse($signature, $query);

echo "Niveau: " . $result->required->first()->value . "\n";     // 'error'
echo "Message: " . $result->required->last()->value . "\n";     // '[ERROR] Failed to connect to database: Connection timed out'
```

### Exemple 4 : Utilisation des Value Objects avec formatage

```php
<?php

use AndyDefer\SignatureParser\ValueObjects\SignatureVO;

// Les espaces sont automatiquement formatés dans SignatureVO
$full = new SignatureVO(
    'deploy {env=production} {--force} {--verbose}',
    'deploy staging^server --force'
);

echo "Environnement: " . $full->getDefault('env') . "\n";  // 'staging server'
echo "Force: " . ($full->getOption('force') ? 'Oui' : 'Non') . "\n";  // 'Oui'
echo "Verbose: " . ($full->getOption('verbose') ? 'Oui' : 'Non') . "\n";  // 'Non'
```

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)