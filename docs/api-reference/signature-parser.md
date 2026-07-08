# SignatureParser - Référence Technique

## Description

Analyseur de signatures et requêtes de commandes CLI. Extrait les arguments requis, les valeurs par défaut, les arguments nullables, les variadiques et les flags booléens d'une commande avec support du formatage des valeurs et validation intégrée.

## Hiérarchie / Implémentations

```
ParserRegistryInterface
    └── SignatureParser
SignatureParserInterface
    └── SignatureParser
```

## Rôle principal

`SignatureParser` est le point d'entrée central pour l'analyse des commandes CLI. Il utilise une **chaîne de responsabilité** (Chain of Responsibility) avec 6 parseurs spécialisés pour extraire chaque type d'élément :

1. **SourceParser** - Nom de la commande
2. **RequiredParser** - Arguments requis `{name}`
3. **NullableParser** - Arguments nullables `{name?}`
4. **DefaultParser** - Arguments avec valeur par défaut `{name=value}`
5. **VariadicParser** - Arguments variadiques `{name*}`
6. **FlagParser** - Flags booléens `{--flag}`

Le parser intègre également un **système de validation** qui vérifie la conformité d'une requête par rapport à sa signature.

### Syntaxes supportées

| Syntaxe | Description | Exemple |
|---------|-------------|---------|
| `{name}` | Argument requis | `{source}` |
| `{name=value}` | Valeur par défaut | `{format=zip}` |
| `{name=}` | Valeur par défaut vide (ignoré) | `{format=}` |
| `{name?}` | Argument nullable | `{format?}` |
| `{name*}` | Argument variadique | `{files*}` |
| `{--flag}` | Flag booléen | `{--force}` |

### Formatage automatique des valeurs

Le parser intègre un **formateur automatique** qui remplace les caractères `^` par des espaces dans toutes les valeurs extraites.

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
| `$signature` | `string` | Définition de la commande |
| `$query` | `string` | Commande exécutée |

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
echo $result->flags->first()->value;       // true
```

---

### `validate(string $signature, string $query): ValidationResultRecord`

Valide une requête contre une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Définition de la commande |
| `$query` | `string` | Commande exécutée |

**Retourne :** `ValidationResultRecord` - Résultat de la validation avec erreurs et suggestions

**Exemple :**
```php
$result = $parser->validate(
    'backup {source} {destination} {--force}',
    'backup /var/www --force'
);

if (!$result->isValid) {
    foreach ($result->errors as $error) {
        echo $error . "\n";
    }
    // Missing required argument: 'destination'
}
```

---

### `isValid(string $signature, string $query): bool`

Vérifie rapidement si une requête est valide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Définition de la commande |
| `$query` | `string` | Commande exécutée |

**Retourne :** `bool` - `true` si la requête est valide

**Exemple :**
```php
if ($parser->isValid('backup {source} {--force}', 'backup /var/www --force')) {
    echo "Commande valide";
}
```

---

### `getValidationErrors(string $signature, string $query): StringTypedCollection`

Retourne uniquement les erreurs de validation.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Définition de la commande |
| `$query` | `string` | Commande exécutée |

**Retourne :** `StringTypedCollection` - Collection des messages d'erreur

**Exemple :**
```php
$errors = $parser->getValidationErrors(
    'backup {source} {destination} {--force}',
    'backup /var/www --force'
);
// StringTypedCollection ['Missing required argument: destination']
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

---

### `removeParser(string $parserClass): self`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$parserClass` | `string` | Nom complet de la classe du parseur à supprimer |

**Retourne :** `self` - Instance pour le chaînage

---

### `getParsers(): array`

**Retourne :** `array<ParserInterface>` - Liste des parseurs enregistrés

---

## Ordre des parseurs

| Ordre | Parser | Type extrait | Syntaxe |
|-------|--------|--------------|---------|
| 1 | SourceParser | Nom de la commande | `command` |
| 2 | RequiredParser | Arguments requis | `{name}` |
| 3 | NullableParser | Arguments nullables | `{name?}` |
| 4 | DefaultParser | Valeurs par défaut | `{name=value}` |
| 5 | VariadicParser | Arguments variadiques | `{name*}` |
| 6 | FlagParser | Flags booléens | `{--flag}` |

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
NullableParser → extrait 'nullable'
    ↓
DefaultParser → extrait 'default'
    ↓
VariadicParser → extrait 'variadic'
    ↓
FlagParser → extrait 'flags'
    ↓
buildRecord() → construit les collections
    ↓
NormalizerChain::normalize() → normalise les données
    ↓
TextFormatter::format() → remplace ^ par espaces
    ↓
ParsedSignatureRecord::from() → retourne le record typé
```

## Gestion des erreurs

Aucune exception n'est levée par le parser principal. Les valeurs manquantes sont remplacées par :

| Situation | Valeur retournée |
|-----------|------------------|
| Source manquante | `''` (chaîne vide) |
| Argument requis manquant | `''` (chaîne vide) |
| Valeur par défaut manquante | La valeur par défaut définie dans la signature |
| Valeur nullable non fournie | `null` |
| Variadique manquant | `[]` (tableau vide) |
| Flag manquant | `false` |

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
$force = $vo->getFlag('force');          // true
```

### Avec un framework Symfony Console

```php
use Symfony\Component\Console\Command\Command;
use AndyDefer\SignatureParser\SignatureParser;

class BackupCommand extends Command
{
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
| `validate()` | O(n) | Parcours des parseurs |
| `extractSignatureElements()` | O(n) | Regex + boucle de construction |
| `extractQueryElements()` | O(n) | Parcours des tokens |
| `addParser()` | O(1) | Ajout en fin de tableau |
| `removeParser()` | O(n) | Recherche et suppression |

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

$signature = 'backup {source} {destination} {format=zip} {output=dist} {env?} {excludes*} {purpose*} {--force} {--verbose}';
$query = 'backup /home/user/My^Project /backup tar^gz [cache^folder, logs^folder] [home^data, models] --force';

$result = $parser->parse($signature, $query);

echo "Source: " . $result->source . "\n";

echo "Arguments requis:\n";
foreach ($result->required as $arg) {
    echo "  {$arg->name}: {$arg->value}\n";
}

echo "Valeurs par défaut:\n";
foreach ($result->default as $arg) {
    echo "  {$arg->name}: {$arg->value}\n";
}

echo "Arguments nullables:\n";
foreach ($result->nullable as $arg) {
    echo "  {$arg->name}: " . ($arg->value ?? 'null') . "\n";
}

echo "Arguments variadiques:\n";
foreach ($result->variadic as $arg) {
    echo "  {$arg->name}: " . implode(', ', $arg->values->toArray()) . "\n";
}

echo "Flags:\n";
foreach ($result->flags as $flag) {
    echo "  {$flag->name}: " . ($flag->value ? 'true' : 'false') . "\n";
}

// Validation
$validation = $parser->validate($signature, $query);
if (!$validation->isValid) {
    echo "\nErreurs de validation:\n";
    foreach ($validation->errors as $error) {
        echo "  - $error\n";
    }
}

$signatureElements = $parser->extractSignatureElements($signature);
$queryElements = $parser->extractQueryElements($query);

echo "\nÉléments signature: " . implode(', ', $signatureElements->toArray()) . "\n";
echo "Éléments query: " . implode(', ', $queryElements->toArray()) . "\n";
```

## Voir aussi

- `ParsedSignatureRecord` - Structure de données retournée
- `ValidationResultRecord` - Résultat de validation
- `ParserInterface` - Contrat pour les parseurs personnalisés
- `SignatureVO` - Value Object pour l'accès simplifié
- `SignatureStructureVO` - Value Object pour l'analyse de structure
- `StringTypedCollection` - Collection typée pour les chaînes
- `TextFormatter` - Formateur pour le remplacement des caractères
- `ArgumentCollection` - Collection d'arguments
- `FlagCollection` - Collection de flags
---