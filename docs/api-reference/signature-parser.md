# SignatureParser - Référence Technique

## Description

Analyseur de signatures et requêtes de commandes CLI. Extrait les arguments requis, les valeurs par défaut, les variadiques et les options d'une commande avec support du formatage des valeurs.

## Hiérarchie / Implémentations

```
ParserRegistryInterface
    └── SignatureParser
SignatureParserInterface
    └── SignatureParser
```

## Rôle principal

`SignatureParser` est le point d'entrée central pour l'analyse des commandes CLI. Il utilise une **chaîne de responsabilité** (Chain of Responsibility) avec 5 parseurs spécialisés pour extraire chaque type d'élément :

1. **SourceParser** - Nom de la commande
2. **RequiredParser** - Arguments requis `{name}`
3. **DefaultParser** - Arguments avec valeur par défaut `{name=value}`
4. **VariadicParser** - Arguments variadiques `{name*}`
5. **OptionsParser** - Options `{--flag}`

### Formatage automatique des valeurs

Le parser intègre un **formateur automatique** qui remplace les caractères `^` par des espaces dans toutes les valeurs extraites. Cela permet aux utilisateurs d'inclure des espaces dans leurs arguments sans utiliser de guillemets complexes.

```bash
# Au lieu de : "John Doe"
# On fait : John^Doe

# Résultat : "John Doe"
```

## Installation

```bash
composer require andydefer/php-signature-parser
```

## API / Méthodes publiques

### `parse(string $signature, string $query): ParsedSignatureRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Définition de la commande (ex: `backup {source} {destination} {--force}`) |
| `$query` | `string` | Commande exécutée (ex: `backup /var/www /backup --force`) |

**Retourne :** `ParsedSignatureRecord` - Structure typée contenant toutes les données extraites

**Exceptions :** Aucune (les parseurs sont tolérants)

**Exemple :**
```php
$parser = new SignatureParser();
$result = $parser->parse(
    'backup {source} {destination} {format=zip} {--force}',
    'backup /var/www /backup tar.gz --force'
);

echo $result->source;                      // 'backup'
echo $result->required->first()->value;    // '/var/www'
echo $result->default->first()->value;     // 'tar.gz'
echo $result->options->first()->value;     // true
```

---

### `extractSignatureElements(string $signature): StringTypedCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la commande |

**Retourne :** `StringTypedCollection` - Liste des éléments bruts de la signature

**Exemple :**
```php
$elements = $parser->extractSignatureElements('backup {source} {destination} {--force}');
// ['backup', 'source', 'destination', '--force']
```

---

### `extractQueryElements(string $query): StringTypedCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Requête de la commande |

**Retourne :** `StringTypedCollection` - Liste des éléments bruts de la requête

**Exemple :**
```php
$elements = $parser->extractQueryElements('backup /var/www /backup [cache, logs] --force');
// ['backup', '/var/www', '/backup', '[cache, logs]', '--force']
```

---

### `addParser(ParserInterface $parser): self`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$parser` | `ParserInterface` | Parseur à ajouter à la chaîne |

**Retourne :** `self` - Instance pour le chaînage

**Exemple :**
```php
$parser = new SignatureParser();
$parser->addParser(new CustomParser());
```

---

### `removeParser(string $parserClass): self`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$parserClass` | `string` | Nom complet de la classe du parseur à supprimer |

**Retourne :** `self` - Instance pour le chaînage

**Exemple :**
```php
$parser->removeParser(OptionsParser::class);
```

---

### `getParsers(): array`

**Retourne :** `array<ParserInterface>` - Liste des parseurs enregistrés

**Exemple :**
```php
$parsers = $parser->getParsers();
foreach ($parsers as $p) {
    echo get_class($p);
}
```

---

## Formatage des valeurs avec `^`

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

## Cas d'utilisation

### Cas 1 : Commande de backup avec espaces dans les chemins

```php
$parser = new SignatureParser();

$signature = 'backup {source} {destination} {format=zip} {excludes*} {--force}';
$query = 'backup /home/user/My^Project /backup tar^gz [cache^folder, logs^folder] --force';

$result = $parser->parse($signature, $query);

$source = $result->required->first()->value;      // '/home/user/My Project'
$format = $result->default->first()->value;       // 'tar gz'
$excludes = $result->variadic->first()->values;   // ['cache folder', 'logs folder']
$force = $result->options->first()->value;        // true
```

### Cas 2 : Commande Docker

```php
$signature = 'docker {container} {image} {--detach} {--rm}';
$query = 'docker run nginx --detach';

$result = $parser->parse($signature, $query);

$container = $result->required->first()->value;      // 'run'
$image = $result->required->last()->value;           // 'nginx'
$detach = $result->options->first()->value;          // true
$rm = $result->options->last()->value;               // false
```

### Cas 3 : Valeurs par défaut avec espaces

```php
$signature = 'deploy {env=production} {--force}';
$query = 'deploy staging^server';

$result = $parser->parse($signature, $query);

$env = $result->default->first()->value;             // 'staging server' (override)
$force = $result->options->first()->value;           // false
```

### Cas 4 : Ajout d'un parseur personnalisé

```php
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;

final class CustomParser implements ParserInterface
{
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        // Logique personnalisée
        return ParsedResultRecord::from([
            'data' => ['custom' => 'value'],
            'signature' => $signature,
            'query' => $query,
        ]);
    }
}

$parser = new SignatureParser();
$parser->addParser(new CustomParser());

$result = $parser->parse($signature, $query);
// $result contient 'custom' en plus des champs standards
```

---

## Flux d'exécution

```
Signature et Query
    ↓
extractSignatureElements() / extractQueryElements()
    ↓
StringTypedCollection (éléments bruts)
    ↓
SourceParser → extrait 'source'
    ↓
RequiredParser → extrait 'required'
    ↓
DefaultParser → extrait 'default'
    ↓
VariadicParser → extrait 'variadic'
    ↓
OptionsParser → extrait 'options'
    ↓
buildRecord() → construit les collections
    ↓
NormalizerChain::normalize() → normalise les données
    ↓
TextFormatter::format() → remplace ^ par espaces
    ↓
ParsedSignatureRecord::from() → retourne le record typé
```

## Ordre des parseurs

| Ordre | Parser | Type extrait | Syntaxe |
|-------|--------|--------------|---------|
| 1 | SourceParser | Nom de la commande | `command` |
| 2 | RequiredParser | Arguments requis | `{name}` |
| 3 | DefaultParser | Arguments par défaut | `{name=value}` |
| 4 | VariadicParser | Arguments variadiques | `{name*}` |
| 5 | OptionsParser | Options | `{--flag}` |

## Gestion des erreurs

Aucune exception n'est levée par le parser principal. Les valeurs manquantes sont remplacées par :

| Situation | Valeur retournée |
|-----------|------------------|
| Source manquante | `''` (chaîne vide) |
| Argument requis manquant | `''` (chaîne vide) |
| Valeur par défaut manquante | La valeur par défaut définie dans la signature |
| Variadique manquant | `[]` (tableau vide) |
| Option manquante | `false` |

## Intégration

### Avec SignatureVO

```php
use AndyDefer\SignatureParser\ValueObjects\SignatureVO;

$vo = new SignatureVO(
    'backup {source} {destination} {--force}',
    'backup /var/www /backup --force'
);

$source = $vo->getSource();              // 'backup'
$destination = $vo->getRequired('destination'); // '/backup'
$force = $vo->getOption('force');        // true
```

### Avec un framework Symfony Console

```php
use Symfony\Component\Console\Command\Command;
use AndyDefer\SignatureParser\SignatureParser;

class BackupCommand extends Command
{
    protected function configure(): void
    {
        // Définition Symfony
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $parser = new SignatureParser();
        $result = $parser->parse(
            'backup {source} {destination} {format=zip} {--force}',
            $input->getArgument('source') . ' ' . $input->getArgument('destination')
        );

        // ...
    }
}
```

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `parse()` | O(n) | n = nombre d'éléments de la commande |
| `extractSignatureElements()` | O(n) | Regex + boucle de construction |
| `extractQueryElements()` | O(n) | Parcours des tokens |
| `addParser()` | O(1) | Ajout en fin de tableau |
| `removeParser()` | O(n) | Recherche et suppression |

**Optimisations :**
- Les parseurs ne s'exécutent que sur les éléments restants
- Aucune allocation mémoire inutile

## Compatibilité

| Version PHP | Support | Notes |
|-------------|---------|-------|
| PHP 8.4 | ✅ Complet | Support total |
| PHP 8.3 | ✅ Complet | Support total |
| PHP 8.2 | ✅ Complet | Support total |
| PHP 8.1 | ✅ Complet | Support total |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\SignatureParser\SignatureParser;

$parser = new SignatureParser();

$signature = 'backup {source} {destination} {format=zip} {output=dist} {excludes*} {purpose*} {--force} {--verbose}';
$query = 'backup /home/user/My^Project /backup tar^gz [cache^folder, logs^folder] [home^data, models] --force';

$result = $parser->parse($signature, $query);

// Accès structuré
echo "Source: " . $result->source . "\n"; // 'backup'

echo "Arguments requis:\n";
foreach ($result->required as $arg) {
    echo "  {$arg->name}: {$arg->value}\n";
}
// source: /home/user/My Project
// destination: /backup

echo "Valeurs par défaut:\n";
foreach ($result->default as $arg) {
    echo "  {$arg->name}: {$arg->value}\n";
}
// format: tar gz
// output: dist

echo "Arguments variadiques:\n";
foreach ($result->variadic as $arg) {
    echo "  {$arg->name}: " . implode(', ', $arg->values->toArray()) . "\n";
}
// excludes: cache folder, logs folder
// purpose: home data, models

echo "Options:\n";
foreach ($result->options as $opt) {
    echo "  {$opt->name}: " . ($opt->value ? 'true' : 'false') . "\n";
}
// force: true
// verbose: false

// Extraction des éléments bruts
$signatureElements = $parser->extractSignatureElements($signature);
$queryElements = $parser->extractQueryElements($query);

echo "Éléments signature: " . implode(', ', $signatureElements->toArray()) . "\n";
echo "Éléments query: " . implode(', ', $queryElements->toArray()) . "\n";
```

## Voir aussi

- `ParsedSignatureRecord` - Structure de données retournée
- `ParserInterface` - Contrat pour les parseurs personnalisés
- `SignatureVO` - Value Object pour l'accès simplifié
- `StringTypedCollection` - Collection typée pour les chaînes
- `TextFormatter` - Formateur pour le remplacement des caractères
- `ArgumentCollection` - Collection d'arguments