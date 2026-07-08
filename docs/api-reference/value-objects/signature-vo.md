# SignatureVO - Référence Technique

## Description

Value Object représentant une paire signature/requête de commande CLI complète. Fournit un accès typé à tous les composants parsés et intègre la validation.

## Hiérarchie / Implémentations

```
AbstractValueObject
    └── SignatureVO
```

## Rôle principal

`SignatureVO` combine une signature et une requête pour fournir une analyse complète d'une commande CLI. Contrairement à `SignatureStructureVO` qui analyse uniquement la structure, ce VO traite les valeurs réelles de la requête et intègre la validation.

## Installation

```bash
composer require andydefer/php-signature-parser
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

**Retourne :** `?string` - La valeur ou `null`

**Exemple :**
```php
$source = $vo->getRequired('source'); // '/var/www'
```

---

### `getRequireds(): array`

Retourne tous les arguments requis.

**Retourne :** `array<string, string>` - Tableau associatif

**Exemple :**
```php
$requireds = $vo->getRequireds(); // ['source' => '/var/www', 'destination' => '/backup']
```

---

### `getDefault(string $name): ?string`

Retourne la valeur d'un argument par défaut.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `?string` - La valeur ou `null`

**Exemple :**
```php
$format = $vo->getDefault('format'); // 'tar.gz'
```

---

### `getDefaults(): array`

Retourne tous les arguments par défaut.

**Retourne :** `array<string, string|null>` - Tableau associatif

**Exemple :**
```php
$defaults = $vo->getDefaults(); // ['format' => 'tar.gz']
```

---

### `getVariadic(string $name): array`

Retourne les valeurs d'un argument variadique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `array<string>` - Liste des valeurs

**Exemple :**
```php
$excludes = $vo->getVariadic('excludes'); // ['cache', 'logs', 'tmp']
```

---

### `getVariadics(): array`

Retourne tous les arguments variadiques.

**Retourne :** `array<string, array<string>>` - Tableau associatif

**Exemple :**
```php
$variadics = $vo->getVariadics();
// ['excludes' => ['cache', 'logs'], 'includes' => ['src']]
```

---

### `getFlag(string $name): bool`

Retourne la valeur d'un flag.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag |

**Retourne :** `bool` - `true` si présent

**Exemple :**
```php
$force = $vo->getFlag('force'); // true
```

---

### `getFlags(): array`

Retourne tous les flags.

**Retourne :** `array<string, bool>` - Tableau associatif

**Exemple :**
```php
$flags = $vo->getFlags(); // ['force' => true, 'verbose' => false]
```

---

### `getParsed(): StrictDataObject`

Retourne la structure complète parsée.

**Retourne :** `StrictDataObject`

**Exemple :**
```php
$parsed = $vo->getParsed();
echo $parsed->source;
echo $parsed->required['source'];
```

---

### `getValue(): StrictDataObject`

Alias de `getParsed()`.

**Retourne :** `StrictDataObject`

---

### `hasFlag(string $name): bool`

Vérifie si un flag est présent et actif.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag |

**Retourne :** `bool`

---

### `hasRequired(string $name): bool`

Vérifie si un argument requis existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool`

---

### `hasDefault(string $name): bool`

Vérifie si un argument par défaut existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool`

---

### `hasVariadic(string $name): bool`

Vérifie si un argument variadique existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool`

---

### `isValid(): bool`

Vérifie si la requête est valide.

**Retourne :** `bool`

**Exemple :**
```php
if ($vo->isValid()) {
    echo "Commande valide";
}
```

---

### `getValidationErrors(): StringTypedCollection`

Retourne les erreurs de validation.

**Retourne :** `StringTypedCollection`

**Exemple :**
```php
foreach ($vo->getValidationErrors() as $error) {
    echo $error;
}
```

---

### `getValidationSuggestions(): StringTypedCollection`

Retourne les suggestions de correction.

**Retourne :** `StringTypedCollection`

---

### `getValidationResult(): ValidationResultRecord`

Retourne le résultat complet de validation.

**Retourne :** `ValidationResultRecord`

---

### `equals(AbstractValueObject $other): bool`

Compare deux `SignatureVO` pour l'égalité.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$other` | `AbstractValueObject` | Autre Value Object |

**Retourne :** `bool` - `true` si les signatures ET les requêtes sont identiques

---

## Cas d'utilisation

### Cas 1 : Validation de commande

```php
$vo = new SignatureVO(
    'deploy {env} {--force}',
    'deploy staging'
);

if (!$vo->isValid()) {
    foreach ($vo->getValidationErrors() as $error) {
        echo "Erreur: $error\n";
    }
    foreach ($vo->getValidationSuggestions() as $suggestion) {
        echo "Suggestion: $suggestion\n";
    }
}
```

### Cas 2 : Extraction des valeurs

```php
$vo = new SignatureVO(
    'backup {source} {destination} {format=zip} {excludes*} {--force}',
    'backup /var/www /backup tar.gz [cache, logs] --force'
);

$source = $vo->getRequired('source');        // '/var/www'
$format = $vo->getDefault('format');         // 'tar.gz'
$excludes = $vo->getVariadic('excludes');    // ['cache', 'logs']
$force = $vo->getFlag('force');              // true
```

### Cas 3 : Validation avec suggestions

```php
$vo = new SignatureVO(
    'backup {source} {destination}',
    'backup /var/www'
);

if (!$vo->isValid()) {
    $errors = $vo->getValidationErrors();
    $suggestions = $vo->getValidationSuggestions();
    
    foreach ($errors as $error) {
        echo "❌ $error\n";
    }
    foreach ($suggestions as $suggestion) {
        echo "💡 $suggestion\n";
    }
}
```

---

## Flux d'exécution

```
Constructeur
    ↓
Vérification des paramètres
    ↓
parse() → SignatureParser::parse()
    ↓
Extraction des données
    ├── source → $this->source
    ├── required → $this->required
    ├── default → $this->default
    ├── variadic → $this->variadic
    └── flags → $this->flags
    ↓
validate() → SignatureParser::validate()
    ↓
ValidationResultRecord
    ↓
StrictDataObject
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Signature vide | `InvalidArgumentException` | `Signature cannot be empty` |
| Query vide | `InvalidArgumentException` | `Query cannot be empty` |
| Ordre invalide | `InvalidArgumentException` | `Invalid signature order: {error}` |

## Intégration

### Avec SignatureStructureVO

```php
// Structure seule
$structure = new SignatureStructureVO($signature);

// Structure + valeurs
$vo = new SignatureVO($signature, $query);

// Vérification de présence
if ($structure->hasRequired('source')) {
    $value = $vo->getRequired('source');
}
```

### Avec SignatureParser

```php
$parser = new SignatureParser();
$parsed = $parser->parse($signature, $query);
$validation = $parser->validate($signature, $query);

// Le VO encapsule cette logique
$vo = new SignatureVO($signature, $query);
```

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `__construct()` | O(n) | Parsing + validation |
| `getSource()` | O(1) | Accès direct |
| `getRequired()` | O(1) | Accès tableau |
| `getDefaults()` | O(1) | Accès direct |
| `getVariadic()` | O(1) | Accès tableau |
| `getFlag()` | O(1) | Accès tableau |
| `isValid()` | O(1) | Accès direct |
| `getValidationErrors()` | O(1) | Accès direct |

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

$signature = 'backup {source} {destination} {format=zip} {output=dist} {excludes*} {purpose*} {--force} {--verbose}';
$query = 'backup /var/www /backup tar.gz dist [cache, logs, tmp] [home, data, models] --force';

$vo = new SignatureVO($signature, $query);

echo "Source: " . $vo->getSource() . "\n";

echo "Arguments requis:\n";
foreach ($vo->getRequireds() as $name => $value) {
    echo "  $name: $value\n";
}

echo "Valeurs par défaut:\n";
foreach ($vo->getDefaults() as $name => $value) {
    echo "  $name: $value\n";
}

echo "Arguments variadiques:\n";
foreach ($vo->getVariadics() as $name => $values) {
    echo "  $name: " . implode(', ', $values) . "\n";
}

echo "Flags:\n";
foreach ($vo->getFlags() as $name => $value) {
    echo "  $name: " . ($value ? 'true' : 'false') . "\n";
}

echo "Validation: " . ($vo->isValid() ? '✅ Valide' : '❌ Invalide') . "\n";

if (!$vo->isValid()) {
    echo "Erreurs:\n";
    foreach ($vo->getValidationErrors() as $error) {
        echo "  - $error\n";
    }
    echo "Suggestions:\n";
    foreach ($vo->getValidationSuggestions() as $suggestion) {
        echo "  - $suggestion\n";
    }
}

$parsed = $vo->getParsed();
echo "\nParsed data:\n";
print_r($parsed->toArray());
```

## Voir aussi

- `SignatureStructureVO` - Analyse de structure sans requête
- `SignatureParser` - Parseur principal
- `ParsedSignatureRecord` - Structure de données
- `ValidationResultRecord` - Résultat de validation
- `TextFormatter` - Formateur des valeurs
---