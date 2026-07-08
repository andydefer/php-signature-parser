# SignatureParser - Référence Technique

## Description

Analyseur de signatures et requêtes de commandes CLI. Extrait la source, les arguments requis, les arguments par défaut, les variadiques et les flags d'une commande. Valide également la structure des signatures.

## Hiérarchie / Implémentations

```
ParserRegistryInterface
    └── SignatureParser
SignatureParserInterface
    └── SignatureParser
```

## Rôle principal

`SignatureParser` est le point d'entrée central pour l'analyse des commandes CLI. Il utilise une **chaîne de responsabilité** (Chain of Responsibility) avec 5 parseurs spécialisés :

1. **SourceParser** - Nom de la commande
2. **RequiredParser** - Arguments requis `{name}`
3. **DefaultParser** - Arguments par défaut `{name=value}` et nullables `{name=?}`
4. **VariadicParser** - Arguments variadiques `{name*}`
5. **FlagParser** - Flags `{--flag}`

## Installation

```bash
composer require andydefer/php-signature-parser
```

### Prérequis

- PHP 8.1 ou supérieur

## API / Méthodes publiques

### `parse(string $signature, string $query): ParsedSignatureRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Définition de la commande |
| `$query` | `string` | Commande exécutée |

**Retourne :** `ParsedSignatureRecord` - Structure typée contenant toutes les données extraites

**Exceptions :** `InvalidArgumentException` - Si l'ordre des arguments est invalide

**Exemple :**
```php
$parser = new SignatureParser();
$result = $parser->parse(
    'backup {source} {destination} {format=zip} {--force}',
    'backup /var/www /backup tar.gz --force'
);

echo $result->source;                   // 'backup'
echo $result->required->first()->value; // '/var/www'
echo $result->default->first()->value;  // 'tar.gz'
echo $result->flags->first()->value;    // true
```

---

### `validate(string $signature, string $query): ValidationResultRecord`

Valide une requête contre une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Définition de la commande |
| `$query` | `string` | Commande exécutée |

**Retourne :** `ValidationResultRecord` - Résultat de validation avec erreurs et suggestions

**Exemple :**
```php
$result = $parser->validate(
    'backup {source} {destination}',
    'backup /var/www'
);

if (!$result->isValid) {
    foreach ($result->errors as $error) {
        echo $error;
    }
}
```

---

### `validateSignature(string $signature): ValidationResultRecord`

Valide la structure d'une signature (sans requête).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature à valider |

**Retourne :** `ValidationResultRecord` - Résultat de validation avec erreurs et suggestions

**Vérifications effectuées :**
- Ordre des arguments (requis → défaut → variadique → flags)
- Syntaxe des tokens
- Absence de doublons
- Source valide

**Exemple :**
```php
$result = $parser->validateSignature('backup {source} {format=zip} {--force}');
if ($result->isValid) {
    echo 'Signature valide';
}
```

---

### `isValid(string $signature, string $query): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Définition de la commande |
| `$query` | `string` | Commande exécutée |

**Retourne :** `bool` - `true` si la commande est valide

---

### `isSignatureValid(string $signature): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature à valider |

**Retourne :** `bool` - `true` si la signature est valide

---

### `getValidationErrors(string $signature, string $query): StringTypedCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Définition de la commande |
| `$query` | `string` | Commande exécutée |

**Retourne :** `StringTypedCollection` - Collection des erreurs de validation

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

### `extractSignatureElements(string $signature): StringTypedCollection`

Extrait les éléments bruts d'une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la commande |

**Retourne :** `StringTypedCollection` - Liste des éléments bruts

---

### `extractQueryElements(string $query): StringTypedCollection`

Extrait les éléments bruts d'une requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Requête de la commande |

**Retourne :** `StringTypedCollection` - Liste des éléments bruts

---

## Ordre strict des arguments

⚠️ **L'ordre des éléments est STRICT et IMPÉRATIF.**

| Ordre | Type | Syntaxe | Exemple |
|-------|------|---------|---------|
| **1** | **Source** | `command` | `backup` |
| **2** | **Requis** | `{name}` | `{source}` `{destination}` |
| **3** | **Par défaut** | `{name=value}` | `{format=zip}` `{output=dist}` |
| **4** | **Nullable** | `{name=?}` | `{env=?}` `{port=?}` |
| **5** | **Variadique** | `{name*}` | `{excludes*}` `{purpose*}` |
| **6** | **Flags** | `{--flag}` | `{--force}` `{--verbose}` |

### Règles strictes

| Règle | Description |
|-------|-------------|
| **Source** | Toujours en première position |
| **Requis** | Viennent en premier, avant tous les autres |
| **Par défaut** | Viennent après les requis, avant les nullables et variadiques |
| **Nullable** | `{name=?}` - Viennent après les par défaut, avant les variadiques |
| **Variadiques** | Toujours en dernière position des arguments |
| **Flags** | Peuvent être à n'importe quelle position après la source |

---

## Le token `~` (skip)

Le token `~` permet de sauter un argument :

| Cas | Comportement |
|-----|--------------|
| **Argument requis** | `~` → `null` |
| **Argument par défaut** | `~` → utilise la valeur par défaut |
| **Argument nullable** | `~` → `null` |

---

## Formatage des valeurs

Le `TextFormatter` applique les transformations suivantes :

| Valeur | Résultat |
|--------|----------|
| `^` | Espace |
| `?` | `null` |
| `~` | `null` |
| `??` | `?` |
| `~~` | `~` |

---

## Cas d'utilisation

### Cas 1 : Commande de backup

```php
$signature = 'backup {source} {destination} {format=zip} {excludes*} {--force}';
$query = 'backup /var/www /backup tar.gz [cache, logs, tmp] --force';

$result = $parser->parse($signature, $query);

$source = $result->required->first()->value;        // '/var/www'
$format = $result->default->first()->value;         // 'tar.gz'
$excludes = $result->variadic->first()->values;     // ['cache', 'logs', 'tmp']
$force = $result->flags->first()->value;            // true
```

### Cas 2 : Validation de commande

```php
$result = $parser->validate(
    'backup {source} {destination}',
    'backup /var/www'
);

if (!$result->isValid) {
    foreach ($result->errors as $error) {
        echo "Error: $error\n";
    }
    foreach ($result->suggestions as $suggestion) {
        echo "Suggestion: $suggestion\n";
    }
}
```

### Cas 3 : Validation de signature

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

## Flux d'exécution

```
Signature + Query
    ↓
extractSignatureElements() / extractQueryElements()
    ↓
validateSignatureOrder() → Vérifie l'ordre
    ↓
SourceParser → extrait 'source'
    ↓
RequiredParser → extrait 'required'
    ↓
DefaultParser → extrait 'default'
    ↓
VariadicParser → extrait 'variadic'
    ↓
FlagParser → extrait 'flags'
    ↓
TextFormatter → formate les valeurs
    ↓
buildRecord() → construit ParsedSignatureRecord
    ↓
Retourne ParsedSignatureRecord
```

## Gestion des erreurs

| Situation | Exception / Comportement | Message |
|-----------|--------------------------|---------|
| Ordre invalide | `InvalidArgumentException` | `Invalid signature order: {error}` |
| Défaut vide `{name=}` | Exception dans DefaultParser | `Default argument 'name' cannot have empty value` |
| Nullable invalide `{name?}` | Exception dans DefaultParser | `Invalid syntax '{name?}'. Use '{name=?}' instead` |
| Signature vide | Validation échoue | `Signature cannot be empty` |
| Token invalide | Validation échoue | `Invalid token syntax: '{token}'` |
| Doublon d'argument | Validation échoue | `Duplicate argument name: '{name}'` |

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `parse()` | O(n) | n = nombre d'éléments de la commande |
| `validate()` | O(n) | Parcours des parseurs |
| `validateSignature()` | O(n) | Parcours des tokens |
| `extractSignatureElements()` | O(n) | Regex + boucle |
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

// 1. Parse
$signature = 'backup {source} {destination} {format=zip} {env=?} {excludes*} {purpose*} {--force} {--verbose}';
$query = 'backup /var/www /backup tar.gz staging [cache, logs, tmp] [home, data, models] --force';

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
echo "Arguments variadiques:\n";
foreach ($result->variadic as $arg) {
    echo "  {$arg->name}: " . implode(', ', $arg->values->toArray()) . "\n";
}
echo "Flags:\n";
foreach ($result->flags as $flag) {
    echo "  {$flag->name}: " . ($flag->value ? 'true' : 'false') . "\n";
}

// 2. Validation de requête
$validation = $parser->validate($signature, $query);
if (!$validation->isValid) {
    echo "Erreurs:\n";
    foreach ($validation->errors as $error) {
        echo "  - $error\n";
    }
}

// 3. Validation de signature
$sigValidation = $parser->validateSignature($signature);
if ($sigValidation->isValid) {
    echo "✅ Signature valide\n";
}

// 4. Extraction
$elements = $parser->extractSignatureElements($signature);
echo "Éléments signature: " . implode(', ', $elements->toArray()) . "\n";
```

## Voir aussi

- `ParsedSignatureRecord` - Structure de données retournée
- `ParserInterface` - Contrat pour les parseurs personnalisés
- `TextFormatter` - Formateur des valeurs
- `SignatureStructureVO` - Value Object pour l'analyse de structure
- `SignatureVO` - Value Object pour l'analyse complète
- `ValidationResultRecord` - Résultat de validation
---