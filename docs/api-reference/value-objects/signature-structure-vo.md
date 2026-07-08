# SignatureStructureVO - Référence Technique

## Description

Value Object analysant UNIQUEMENT la structure d'une signature CLI (sans requête). Fournit des informations sur la source, les arguments requis, les arguments par défaut, les variadiques et les flags. Intègre également la validation de la structure.

## Hiérarchie / Implémentations

```
AbstractValueObject
    └── SignatureStructureVO
```

## Rôle principal

`SignatureStructureVO` analyse la **structure** d'une signature de commande CLI. Contrairement à `SignatureVO` qui nécessite une requête, ce VO travaille uniquement sur la signature pour fournir une vue structurée des éléments attendus et valider leur syntaxe.

## Installation

```bash
composer require andydefer/php-signature-parser
```

```php
use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;
```

## API / Méthodes publiques

### `__construct(string $signature)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la commande |

**Retourne :** `void`

**Exceptions :** `InvalidArgumentException` - Si la signature est vide

**Exemple :**
```php
$vo = new SignatureStructureVO('backup {source} {destination} {format=zip} {excludes*} {--force}');
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

### `getRequireds(): array`

Retourne la liste des arguments requis.

**Retourne :** `array<string>` - Liste des noms d'arguments requis

**Exemple :**
```php
$requireds = $vo->getRequireds(); // ['source', 'destination']
```

---

### `getDefaults(): array`

Retourne les arguments avec leurs valeurs par défaut.

**Retourne :** `array<string, string>` - Tableau associatif [nom => valeur par défaut]

**Exemple :**
```php
$defaults = $vo->getDefaults(); // ['format' => 'zip', 'output' => 'dist']
```

---

### `getVariadics(): array`

Retourne la liste des arguments variadiques.

**Retourne :** `array<string>` - Liste des noms d'arguments variadiques

**Exemple :**
```php
$variadics = $vo->getVariadics(); // ['excludes']
```

---

### `getFlags(): array`

Retourne la liste des flags.

**Retourne :** `array<string>` - Liste des noms de flags

**Exemple :**
```php
$flags = $vo->getFlags(); // ['force', 'verbose']
```

---

### `hasRequired(string $name): bool`

Vérifie si un argument requis existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument à vérifier |

**Retourne :** `bool` - `true` si l'argument requis existe

---

### `hasDefault(string $name): bool`

Vérifie si un argument par défaut existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument à vérifier |

**Retourne :** `bool` - `true` si l'argument par défaut existe

---

### `hasVariadic(string $name): bool`

Vérifie si un argument variadique existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument à vérifier |

**Retourne :** `bool` - `true` si l'argument variadique existe

---

### `hasFlag(string $name): bool`

Vérifie si un flag existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag à vérifier |

**Retourne :** `bool` - `true` si le flag existe

---

### `getRaw(): string`

Retourne la signature brute.

**Retourne :** `string` - La signature originale

---

### `hasRequireds(): bool`

Vérifie s'il y a des arguments requis.

**Retourne :** `bool` - `true` s'il y a au moins un argument requis

---

### `hasDefaults(): bool`

Vérifie s'il y a des arguments par défaut.

**Retourne :** `bool` - `true` s'il y a au moins un argument par défaut

---

### `hasVariadics(): bool`

Vérifie s'il y a des arguments variadiques.

**Retourne :** `bool` - `true` s'il y a au moins un argument variadique

---

### `hasFlags(): bool`

Vérifie s'il y a des flags.

**Retourne :** `bool` - `true` s'il y a au moins un flag

---

### `isValid(): bool`

Vérifie si la structure de la signature est valide.

**Retourne :** `bool` - `true` si la signature est valide

**Exemple :**
```php
if ($vo->isValid()) {
    echo "Signature valide";
}
```

---

### `getValidationErrors(): array`

Retourne les erreurs de validation de la signature.

**Retourne :** `array<string>` - Liste des messages d'erreur

**Exemple :**
```php
foreach ($vo->getValidationErrors() as $error) {
    echo "❌ $error\n";
}
```

---

### `getValidationSuggestions(): array`

Retourne les suggestions de correction.

**Retourne :** `array<string>` - Liste des suggestions

**Exemple :**
```php
foreach ($vo->getValidationSuggestions() as $suggestion) {
    echo "💡 $suggestion\n";
}
```

---

### `getValidationResult(): ValidationResultRecord`

Retourne le résultat complet de validation.

**Retourne :** `ValidationResultRecord` - Résultat de validation

---

### `getValue(): StrictDataObject`

Retourne toute la structure sous forme d'objet typé.

**Retourne :** `StrictDataObject` - Structure complète

**Exemple :**
```php
$structure = $vo->getValue();
echo $structure->source;        // 'backup'
echo $structure->required[0];   // 'source'
echo $structure->default->format; // 'zip'
echo $structure->variadic[0];   // 'excludes'
echo $structure->flags[0];      // 'force'
```

---

### `equals(AbstractValueObject $other): bool`

Compare deux `SignatureStructureVO` pour l'égalité.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$other` | `AbstractValueObject` | Autre Value Object à comparer |

**Retourne :** `bool` - `true` si les signatures sont identiques

---

## Cas d'utilisation

### Cas 1 : Analyse de structure de commande

```php
$vo = new SignatureStructureVO('backup {source} {destination} {format=zip} {excludes*} {--force}');

echo "Source: " . $vo->getSource() . "\n";
echo "Arguments requis: " . implode(', ', $vo->getRequireds()) . "\n";
echo "Arguments par défaut: " . print_r($vo->getDefaults(), true) . "\n";
echo "Arguments variadiques: " . implode(', ', $vo->getVariadics()) . "\n";
echo "Flags: " . implode(', ', $vo->getFlags()) . "\n";
```

### Cas 2 : Validation de signature

```php
$vo = new SignatureStructureVO('backup {format=zip} {source} {--force}');

if (!$vo->isValid()) {
    foreach ($vo->getValidationErrors() as $error) {
        echo "Erreur: $error\n";
    }
}
```

### Cas 3 : Génération de documentation

```php
function generateCommandDoc(string $signature): string
{
    $vo = new SignatureStructureVO($signature);
    
    $doc = "## " . $vo->getSource() . "\n\n";
    
    if ($vo->hasRequireds()) {
        $doc .= "**Arguments requis:**\n";
        foreach ($vo->getRequireds() as $arg) {
            $doc .= "- `$arg`\n";
        }
        $doc .= "\n";
    }
    
    if ($vo->hasDefaults()) {
        $doc .= "**Arguments par défaut:**\n";
        foreach ($vo->getDefaults() as $name => $value) {
            $doc .= "- `$name`: $value\n";
        }
        $doc .= "\n";
    }
    
    if ($vo->hasFlags()) {
        $doc .= "**Flags:**\n";
        foreach ($vo->getFlags() as $flag) {
            $doc .= "- `--$flag`\n";
        }
    }
    
    return $doc;
}
```

### Cas 4 : Intégration avec SignatureVO

```php
$structureVo = new SignatureStructureVO('backup {source} {destination} {format=zip} {--force}');

$fullVo = new SignatureVO(
    'backup {source} {destination} {format=zip} {--force}',
    'backup /var/www /backup tar.gz --force'
);

$requireds = $structureVo->getRequireds(); // ['source', 'destination']
$requiredValues = $fullVo->getRequireds(); // ['source' => '/var/www', 'destination' => '/backup']
```

---

## Flux d'exécution

```
Signature string
    ↓
extractSignatureElements()
    ↓
validateSignature() → ValidationResultRecord
    ↓
Éléments bruts
    ↓
Parcours des éléments
    ├── Position 0 → source
    ├── --flag → flags
    ├── * → variadic
    ├── = → default (name => value)
    └── other → required
    ↓
Structure interne
    ↓
getValue() / méthodes accesseurs
```

## Gestion des erreurs

| Situation | Exception / Comportement | Message |
|-----------|--------------------------|---------|
| Signature vide | `InvalidArgumentException` | `Signature cannot be empty` |
| Ordre invalide | Validation échoue | `Required argument '...' must appear before ...` |
| Token invalide | Validation échoue | `Invalid token syntax: '...'` |
| Doublon d'argument | Validation échoue | `Duplicate argument name: '...'` |
| Source invalide | Validation échoue | `Invalid source name: '...'` |

## Intégration

### Avec SignatureParser

```php
$parser = new SignatureParser();
$elements = $parser->extractSignatureElements($signature);
$validation = $parser->validateSignature($signature);
// Le VO encapsule cette logique
```

### Avec StrictDataObject

```php
$structure = $vo->getValue();
$source = $structure->source;
$requireds = $structure->required->toArray();
$defaults = $structure->default->toArray();
```

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `__construct()` | O(n) | n = nombre d'éléments de la signature |
| `getSource()` | O(1) | Accès direct |
| `getRequireds()` | O(1) | Accès direct |
| `getDefaults()` | O(1) | Accès direct |
| `getVariadics()` | O(1) | Accès direct |
| `getFlags()` | O(1) | Accès direct |
| `isValid()` | O(1) | Accès direct |
| `getValidationErrors()` | O(1) | Accès direct |
| `hasRequired()` | O(n) | Recherche dans le tableau |
| `hasDefault()` | O(1) | Recherche dans le tableau associatif |
| `hasVariadic()` | O(n) | Recherche dans le tableau |
| `hasFlag()` | O(n) | Recherche dans le tableau |

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

use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;

$signature = 'backup {source} {destination} {format=zip} {output=dist} {excludes*} {--force} {--verbose}';
$vo = new SignatureStructureVO($signature);

echo "Source: " . $vo->getSource() . "\n";
echo "Raw: " . $vo->getRaw() . "\n";

echo "✅ Valid: " . ($vo->isValid() ? 'Yes' : 'No') . "\n";

if (!$vo->isValid()) {
    echo "Errors:\n";
    foreach ($vo->getValidationErrors() as $error) {
        echo "  ❌ $error\n";
    }
    echo "Suggestions:\n";
    foreach ($vo->getValidationSuggestions() as $suggestion) {
        echo "  💡 $suggestion\n";
    }
}

echo "Has requireds? " . ($vo->hasRequireds() ? 'Yes' : 'No') . "\n";
echo "Has defaults? " . ($vo->hasDefaults() ? 'Yes' : 'No') . "\n";
echo "Has variadics? " . ($vo->hasVariadics() ? 'Yes' : 'No') . "\n";
echo "Has flags? " . ($vo->hasFlags() ? 'Yes' : 'No') . "\n";

echo "Has 'source' required? " . ($vo->hasRequired('source') ? 'Yes' : 'No') . "\n";
echo "Has 'format' default? " . ($vo->hasDefault('format') ? 'Yes' : 'No') . "\n";
echo "Default value for 'format': " . ($vo->hasDefault('format') ? $vo->getDefaults()['format'] : 'N/A') . "\n";
echo "Has 'force' flag? " . ($vo->hasFlag('force') ? 'Yes' : 'No') . "\n";

$structure = $vo->getValue();
print_r($structure->toArray());

$vo2 = new SignatureStructureVO('backup {source} {destination} {format=zip} {--force}');
echo "Equal? " . ($vo->equals($vo2) ? 'Yes' : 'No') . "\n";
```

## Voir aussi

- `SignatureVO` - Value Object avec analyse de la requête
- `SignatureParser` - Parseur principal
- `ParsedSignatureRecord` - Structure de données retournée
- `ValidationResultRecord` - Résultat de validation
- `StrictDataObject` - DataObject pour l'accès typé
- `TextFormatter` - Formateur des valeurs
---