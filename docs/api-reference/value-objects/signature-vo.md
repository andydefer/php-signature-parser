# SignatureVO - Référence Technique

## Description

Value Object qui analyse une commande CLI complète (signature + requête) et fournit un accès typé à toutes ses parties : source, arguments requis, arguments par défaut, variadiques et options.

## Hiérarchie

```
AbstractValueObject
    └── SignatureVO
```

## Rôle principal

`SignatureVO` combine une signature et une requête pour fournir une analyse complète d'une commande CLI. Contrairement à `SignatureStructureVO` qui analyse uniquement la structure, ce VO traite les valeurs réelles de la requête.

## Installation

```bash
composer require andydefer/php-signature-parser
```

```php
use AndyDefer\SignatureParser\ValueObjects\SignatureVO;
```

## API / Méthodes publiques

### `__construct(string $signature, string $query)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Définition de la commande |
| `$query` | `string` | Commande exécutée |

**Retourne :** `void`

**Exceptions :** `InvalidArgumentException` - Si la signature ou la requête est vide

**Exemple :**
```php
$vo = new SignatureVO(
    'backup {source} {destination} {format=zip} {--force}',
    'backup /var/www /backup tar.gz --force'
);
```

---

### `getSource(): string`

Retourne le nom de la commande.

**Retourne :** `string` - Nom de la commande

**Exemple :**
```php
$source = $vo->getSource(); // 'backup'
```

---

### `getRequired(string $name): ?string`

Retourne la valeur d'un argument requis.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument requis |

**Retourne :** `?string` - La valeur ou `null` si l'argument n'existe pas

**Exemple :**
```php
$source = $vo->getRequired('source'); // '/var/www'
$unknown = $vo->getRequired('unknown'); // null
```

---

### `getRequireds(): array`

Retourne tous les arguments requis.

**Retourne :** `array<string, string>` - Tableau associatif [nom => valeur]

**Exemple :**
```php
$requireds = $vo->getRequireds(); // ['source' => '/var/www', 'destination' => '/backup']
```

---

### `getDefault(string $name): ?string`

Retourne la valeur d'un argument par défaut.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument par défaut |

**Retourne :** `?string` - La valeur ou `null` si l'argument n'existe pas

**Exemple :**
```php
$format = $vo->getDefault('format'); // 'tar.gz'
$unknown = $vo->getDefault('unknown'); // null
```

---

### `getDefaults(): array`

Retourne tous les arguments par défaut.

**Retourne :** `array<string, string>` - Tableau associatif [nom => valeur]

**Exemple :**
```php
$defaults = $vo->getDefaults(); // ['format' => 'tar.gz', 'output' => 'dist']
```

---

### `getVariadic(string $name): array`

Retourne les valeurs d'un argument variadique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument variadique |

**Retourne :** `array<string>` - Liste des valeurs ou tableau vide

**Exemple :**
```php
$excludes = $vo->getVariadic('excludes'); // ['cache', 'logs', 'tmp']
$unknown = $vo->getVariadic('unknown'); // []
```

---

### `getVariadics(): array`

Retourne tous les arguments variadiques.

**Retourne :** `array<string, array<string>>` - Tableau associatif [nom => liste de valeurs]

**Exemple :**
```php
$variadics = $vo->getVariadics();
// ['excludes' => ['cache', 'logs', 'tmp'], 'purpose' => ['home', 'data']]
```

---

### `getOption(string $name): bool`

Retourne la valeur d'une option.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'option |

**Retourne :** `bool` - `true` si l'option est présente, `false` sinon

**Exemple :**
```php
$force = $vo->getOption('force'); // true
$verbose = $vo->getOption('verbose'); // false
```

---

### `getOptions(): array`

Retourne toutes les options.

**Retourne :** `array<string, bool>` - Tableau associatif [nom => booléen]

**Exemple :**
```php
$options = $vo->getOptions(); // ['force' => true, 'verbose' => false]
```

---

### `getParsed(): StrictDataObject`

Retourne toute la structure sous forme d'objet typé.

**Retourne :** `StrictDataObject` - Structure complète avec accès propriété

**Exemple :**
```php
$parsed = $vo->getParsed();
echo $parsed->source;              // 'backup'
echo $parsed->required['source'];  // '/var/www'
echo $parsed->default['format'];   // 'tar.gz'
echo $parsed->options['force'];    // true
```

---

### `hasOption(string $name): bool`

Vérifie si une option est présente.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'option |

**Retourne :** `bool` - `true` si l'option est présente

**Exemple :**
```php
if ($vo->hasOption('force')) {
    echo "Force mode enabled";
}
```

---

### `hasRequired(string $name): bool`

Vérifie si un argument requis est présent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument requis |

**Retourne :** `bool` - `true` si l'argument existe

**Exemple :**
```php
if ($vo->hasRequired('source')) {
    echo "Source: " . $vo->getRequired('source');
}
```

---

### `hasDefault(string $name): bool`

Vérifie si un argument par défaut est présent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument par défaut |

**Retourne :** `bool` - `true` si l'argument existe

---

### `hasVariadic(string $name): bool`

Vérifie si un argument variadique est présent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument variadique |

**Retourne :** `bool` - `true` si l'argument existe

---

### `getValue(): StrictDataObject`

Alias de `getParsed()`. Retourne la structure complète.

**Retourne :** `StrictDataObject` - Structure complète

---

### `equals(AbstractValueObject $other): bool`

Compare deux `SignatureVO` pour l'égalité.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$other` | `AbstractValueObject` | Autre Value Object à comparer |

**Retourne :** `bool` - `true` si les signatures ET les requêtes sont identiques

---

## Cas d'utilisation

### Cas 1 : Commande de backup

```php
$vo = new SignatureVO(
    'backup {source} {destination} {format=zip} {excludes*} {--force}',
    'backup /var/www /backup tar.gz [cache, logs, tmp] --force'
);

echo "Source: " . $vo->getSource() . "\n";
echo "Source path: " . $vo->getRequired('source') . "\n";
echo "Destination: " . $vo->getRequired('destination') . "\n";
echo "Format: " . $vo->getDefault('format') . "\n";
echo "Excludes: " . implode(', ', $vo->getVariadic('excludes')) . "\n";
echo "Force: " . ($vo->getOption('force') ? 'Yes' : 'No') . "\n";
```

### Cas 2 : Validation de commande

```php
function validateCommand(string $signature, string $query): array
{
    $vo = new SignatureVO($signature, $query);
    $errors = [];

    // Vérifier les arguments requis
    foreach ($vo->getRequireds() as $name => $value) {
        if (empty($value)) {
            $errors[] = "Missing required argument: $name";
        }
    }

    // Vérifier les options
    if ($vo->hasOption('force') && !$vo->getOption('force')) {
        $errors[] = "Force option required for this operation";
    }

    return $errors;
}

$errors = validateCommand(
    'deploy {env} {--force}',
    'deploy --force'
);
// $errors = ['Missing required argument: env']
```

### Cas 3 : Commande Docker

```php
$vo = new SignatureVO(
    'docker {container} {image} {--detach} {--rm}',
    'docker run nginx --detach'
);

echo "Command: " . $vo->getSource() . "\n";
echo "Container: " . $vo->getRequired('container') . "\n";
echo "Image: " . $vo->getRequired('image') . "\n";
echo "Detach: " . ($vo->getOption('detach') ? 'Yes' : 'No') . "\n";
echo "Remove: " . ($vo->getOption('rm') ? 'Yes' : 'No') . "\n";
```

### Cas 4 : Comparaison avec SignatureStructureVO

```php
// Structure seule
$structureVo = new SignatureStructureVO('backup {source} {destination} {format=zip} {--force}');

// Avec requête
$vo = new SignatureVO(
    'backup {source} {destination} {format=zip} {--force}',
    'backup /var/www /backup tar.gz --force'
);

// La structure VO donne les types attendus
$expected = $structureVo->getRequireds(); // ['source', 'destination']

// Le VO donne les valeurs réelles
$actual = $vo->getRequireds(); // ['source' => '/var/www', 'destination' => '/backup']
```

## Flux d'exécution

```
Signature + Query
    ↓
SignatureParser::parse()
    ↓
ParsedSignatureRecord
    ↓
Extraction des données
    ├── source → $this->source
    ├── required → $this->required [name => value]
    ├── default → $this->default [name => value]
    ├── variadic → $this->variadic [name => array]
    └── options → $this->options [name => bool]
    ↓
StrictDataObject construit
    ↓
Accès via méthodes / getValue()
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Signature vide | `InvalidArgumentException` | `Signature cannot be empty` |
| Query vide | `InvalidArgumentException` | `Query cannot be empty` |

## Intégration

### Avec SignatureParser

```php
$parser = new SignatureParser();
$result = $parser->parse($signature, $query);
// Le VO utilise le parser pour obtenir la structure
```

### Avec SignatureStructureVO

```php
// Analyse de structure sans requête
$structure = new SignatureStructureVO($signature);

// Analyse complète avec requête
$full = new SignatureVO($signature, $query);

// Vérification de la présence d'arguments
if ($structure->hasRequired('source')) {
    $value = $full->getRequired('source');
}
```

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `__construct()` | O(n) | n = nombre d'éléments de la commande |
| `getSource()` | O(1) | Accès direct |
| `getRequired()` | O(1) | Accès tableau associatif |
| `getRequireds()` | O(1) | Accès direct |
| `getDefault()` | O(1) | Accès tableau associatif |
| `getDefaults()` | O(1) | Accès direct |
| `getVariadic()` | O(1) | Accès tableau associatif |
| `getOption()` | O(1) | Accès tableau associatif |
| `hasOption()` | O(1) | Accès tableau associatif |
| `hasRequired()` | O(1) | Accès tableau associatif |

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

use AndyDefer\SignatureParser\ValueObjects\SignatureVO;
use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;

// Commande complexe
$signature = 'backup {source} {destination} {format=zip} {output=dist} {excludes*} {purpose*} {--force} {--verbose}';
$query = 'backup /var/www /backup tar.gz [cache, logs, tmp] [home, data, models] --force';

$vo = new SignatureVO($signature, $query);

// Source
echo "Source: " . $vo->getSource() . "\n";

// Arguments requis
echo "\nArguments requis:\n";
foreach ($vo->getRequireds() as $name => $value) {
    echo "  $name: $value\n";
}

// Arguments par défaut
echo "\nArguments par défaut:\n";
foreach ($vo->getDefaults() as $name => $value) {
    echo "  $name: $value\n";
}

// Arguments variadiques
echo "\nArguments variadiques:\n";
foreach ($vo->getVariadics() as $name => $values) {
    echo "  $name: " . implode(', ', $values) . "\n";
}

// Options
echo "\nOptions:\n";
foreach ($vo->getOptions() as $name => $value) {
    echo "  $name: " . ($value ? 'true' : 'false') . "\n";
}

// Vérifications
echo "\nVérifications:\n";
echo "Has force option? " . ($vo->hasOption('force') ? 'Yes' : 'No') . "\n";
echo "Has source required? " . ($vo->hasRequired('source') ? 'Yes' : 'No') . "\n";
echo "Has format default? " . ($vo->hasDefault('format') ? 'Yes' : 'No') . "\n";
echo "Has excludes variadic? " . ($vo->hasVariadic('excludes') ? 'Yes' : 'No') . "\n";

// Accès via StrictDataObject
$parsed = $vo->getParsed();
echo "\nStructure complète:\n";
print_r($parsed->toArray());

// Comparaison avec SignatureStructureVO
$structure = new SignatureStructureVO($signature);
echo "\nStructure VO requireds: " . implode(', ', $structure->getRequireds()) . "\n";
echo "Full VO requireds: " . implode(', ', array_keys($vo->getRequireds())) . "\n";

// Égalité
$vo2 = new SignatureVO($signature, $query);
echo "Equal? " . ($vo->equals($vo2) ? 'Yes' : 'No') . "\n";
```

## Voir aussi

- `SignatureStructureVO` - Analyse de structure sans requête
- `SignatureParser` - Parseur principal
- `ParsedSignatureRecord` - Structure de données retournée
- `StrictDataObject` - DataObject pour l'accès typé