# SignatureStructureVO - Référence Technique

## Description

Value Object qui analyse et valide la structure d'une signature de commande CLI. Extrait les composants : nom de la commande, arguments requis, arguments par défaut, arguments nullables, arguments variadiques et flags.

## Hiérarchie / Implémentations

```
AbstractValueObject
    └── SignatureStructureVO
```

## Rôle principal

`SignatureStructureVO` est le cœur de l'analyse syntaxique des signatures CLI. Il permet de :

- Valider la syntaxe d'une signature
- Extraire le nom de la commande (source)
- Identifier les arguments requis `{name}`
- Identifier les arguments avec valeur par défaut `{name=value}`
- Identifier les arguments nullables `{name=?}`
- Identifier les arguments variadiques `{name*}`
- Identifier les flags `{--flag}`
- Fournir des erreurs de validation et des suggestions

## Installation

```bash
composer require andydefer/php-signature-parser
```

### Dépendances

- `AbstractValueObject` - Classe de base des Value Objects
- `StrictDataObject` - Structure de données immuable
- `SignatureParser` - Parser de signatures
- `ValidationResultRecord` - Enregistrement des résultats de validation
- PHP 8.1+

## API / Méthodes publiques

### `__construct(string $signature)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature CLI (ex: `'greet {name} {--formal}'`) |

**Retourne :** `void`

**Exceptions :** `InvalidArgumentException` - Si la signature est vide ou invalide

**Exemple :**
```php
$structure = new SignatureStructureVO('backup {source} {destination} {format=zip} {--force}');
```

---

### `getSource(): string`

Retourne le nom de la commande (source).

**Retourne :** `string` - Nom de la commande

**Exemple :**
```php
$structure = new SignatureStructureVO('backup {source}');
echo $structure->getSource(); // 'backup'
```

---

### `getStructure(): StrictDataObject`

Retourne la représentation structurée de la signature.

**Retourne :** `StrictDataObject` - Structure contenant `source`, `required`, `default`, `variadic`, `flags`

**Exemple :**
```php
$structure = $vo->getStructure();
echo $structure->source;          // 'backup'
print_r($structure->required);    // ['source', 'destination']
print_r($structure->default);     // ['format' => 'zip']
```

---

### `getRequireds(): array`

Retourne la liste des noms d'arguments requis.

**Retourne :** `array<string>` - Liste des noms d'arguments requis

**Exemple :**
```php
$requireds = $vo->getRequireds(); // ['source', 'destination']
```

---

### `getDefaults(): array`

Retourne les arguments par défaut avec leurs valeurs.

**Retourne :** `array<string, string|null>` - Tableau associatif [nom => valeur]

**Exemple :**
```php
$defaults = $vo->getDefaults();
// ['format' => 'zip', 'env' => null]
```

---

### `getVariadics(): array`

Retourne la liste des noms d'arguments variadiques.

**Retourne :** `array<string>` - Liste des noms d'arguments variadiques

**Exemple :**
```php
$variadics = $vo->getVariadics(); // ['files', 'excludes']
```

---

### `getFlags(): array`

Retourne la liste des noms des flags.

**Retourne :** `array<string>` - Liste des noms des flags

**Exemple :**
```php
$flags = $vo->getFlags(); // ['force', 'verbose']
```

---

### `hasRequired(string $name): bool`

Vérifie si un argument requis existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool` - `true` si l'argument requis existe

---

### `hasDefault(string $name): bool`

Vérifie si un argument par défaut (incluant nullable) existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool` - `true` si l'argument par défaut existe

---

### `hasVariadic(string $name): bool`

Vérifie si un argument variadique existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool` - `true` si l'argument variadique existe

---

### `hasFlag(string $name): bool`

Vérifie si un flag existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag |

**Retourne :** `bool` - `true` si le flag existe

---

### `hasArgument(string $name): bool`

Vérifie si un argument existe (requis, par défaut ou variadique).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool` - `true` si l'argument existe

---

### `getRaw(): string`

Retourne la signature brute.

**Retourne :** `string` - La signature d'origine

---

### `hasRequireds(): bool`

Vérifie si la signature a des arguments requis.

**Retourne :** `bool` - `true` si des arguments requis existent

---

### `hasDefaults(): bool`

Vérifie si la signature a des arguments par défaut (incluant nullables).

**Retourne :** `bool` - `true` si des arguments par défaut existent

---

### `hasVariadics(): bool`

Vérifie si la signature a des arguments variadiques.

**Retourne :** `bool` - `true` si des arguments variadiques existent

---

### `hasFlags(): bool`

Vérifie si la signature a des flags.

**Retourne :** `bool` - `true` si des flags existent

---

### `isValid(): bool`

Retourne si la structure de la signature est valide.

**Retourne :** `bool` - `true` si la signature est valide

---

### `getValidationErrors(): array`

Retourne les erreurs de validation si la signature est invalide.

**Retourne :** `array<string>` - Liste des messages d'erreur

---

### `getValidationSuggestions(): array`

Retourne des suggestions pour corriger les erreurs.

**Retourne :** `array<string>` - Liste des suggestions

---

### `getValidationResult(): ValidationResultRecord`

Retourne le résultat complet de la validation.

**Retourne :** `ValidationResultRecord` - Résultat de la validation

---

### `getValue(): StrictDataObject`

Retourne la structure sous forme de `StrictDataObject`.

**Retourne :** `StrictDataObject` - Structure complète

---

### `equals(AbstractValueObject $other): bool`

Vérifie l'égalité avec un autre Value Object.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$other` | `AbstractValueObject` | Autre Value Object à comparer |

**Retourne :** `bool` - `true` si les objets sont égaux

---

## Cas d'utilisation

### Cas 1 : Analyse d'une signature simple

```php
<?php

use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;

$structure = new SignatureStructureVO('greet {name} {--formal}');

echo "Commande: " . $structure->getSource() . "\n";
echo "Arguments requis: " . implode(', ', $structure->getRequireds()) . "\n";
echo "Flags: " . implode(', ', $structure->getFlags()) . "\n";

// Résultat:
// Commande: greet
// Arguments requis: name
// Flags: formal
```

### Cas 2 : Validation d'une signature complexe

```php
<?php

$signature = 'backup {source} {destination} {format=zip} {env=?} {excludes*} {--force} {--verbose}';
$structure = new SignatureStructureVO($signature);

if ($structure->isValid()) {
    echo "✅ Signature valide\n";
    echo "Arguments requis: " . implode(', ', $structure->getRequireds()) . "\n";
    echo "Valeurs par défaut:\n";
    foreach ($structure->getDefaults() as $name => $value) {
        echo "  - {$name}: " . ($value ?? 'null') . "\n";
    }
    echo "Variadiques: " . implode(', ', $structure->getVariadics()) . "\n";
    echo "Flags: " . implode(', ', $structure->getFlags()) . "\n";
} else {
    echo "❌ Signature invalide\n";
    foreach ($structure->getValidationErrors() as $error) {
        echo "  - {$error}\n";
    }
}
```

### Cas 3 : Vérification de l'existence d'arguments

```php
<?php

$structure = new SignatureStructureVO('deploy {environment} {--force}');

if ($structure->hasArgument('environment')) {
    echo "L'argument 'environment' existe\n";
}

if ($structure->hasFlag('force')) {
    echo "Le flag '--force' existe\n";
}

if (!$structure->hasArgument('version')) {
    echo "L'argument 'version' n'existe pas\n";
}
```

### Cas 4 : Génération de documentation

```php
<?php

function generateHelp(string $signature): string
{
    $structure = new SignatureStructureVO($signature);
    $help = "Usage: " . $structure->getSource() . "\n\n";

    if ($structure->hasRequireds()) {
        $help .= "Arguments requis:\n";
        foreach ($structure->getRequireds() as $arg) {
            $help .= "  <{$arg}>\n";
        }
        $help .= "\n";
    }

    if ($structure->hasDefaults()) {
        $help .= "Arguments optionnels:\n";
        foreach ($structure->getDefaults() as $name => $value) {
            $display = $value ?? 'null';
            $help .= "  <{$name}> (défaut: {$display})\n";
        }
        $help .= "\n";
    }

    if ($structure->hasFlags()) {
        $help .= "Flags:\n";
        foreach ($structure->getFlags() as $flag) {
            $help .= "  --{$flag}\n";
        }
    }

    return $help;
}

echo generateHelp('backup {source} {destination} {format=zip} {--force}');
```

---

## Flux d'exécution

```
new SignatureStructureVO($signature)
    ↓
Valider que la signature n'est pas vide
    ↓
SignatureParser::validateSignature($signature)
    ↓
SignatureParser::extractSignatureElements($signature)
    ↓
Pour chaque élément
    ├── --nom → flag
    ├── nom* → variadic
    ├── nom=valeur → default (valeur)
    ├── nom=? → default (null)
    └── nom → required
    ↓
Construction de la structure
    ↓
Retourner SignatureStructureVO
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Signature vide | `InvalidArgumentException` | `Signature cannot be empty` |
| Token invalide | Validation via `isValid()` | `Invalid token syntax: '{token}'` |
| Ordre invalide | Validation via `isValid()` | `Required argument '{name}' must appear before default...` |
| Doublon d'argument | Validation via `isValid()` | `Duplicate argument name: '{name}'` |
| Nom de source invalide | Validation via `isValid()` | `Invalid source name: '{name}'` |

---

## Intégration

### Avec SignatureParser

```php
$parser = new SignatureParser();
$elements = $parser->extractSignatureElements('greet {name} {--formal}');
$structure = new SignatureStructureVO('greet {name} {--formal}');
```

### Avec QueryBuilder

```php
$builder = QueryBuilder::init('greet {name} {--formal}');
$structure = $builder->getStructure(); // SignatureStructureVO
```

### Avec ValidationResultRecord

```php
$structure = new SignatureStructureVO('greet {name} {--formal}');
$result = $structure->getValidationResult();

if (!$result->isValid) {
    foreach ($result->errors as $error) {
        echo $error . "\n";
    }
}
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `__construct()` | O(n) | n = nombre d'éléments dans la signature |
| `getSource()` | O(1) | Accès direct |
| `getRequireds()` | O(1) | Accès direct |
| `getDefaults()` | O(1) | Accès direct |
| `getVariadics()` | O(1) | Accès direct |
| `getFlags()` | O(1) | Accès direct |
| `hasRequired()` | O(n) | Recherche dans le tableau |
| `isValid()` | O(1) | Accès à la propriété |

**Optimisations :**
- Validation effectuée une seule fois à la construction
- Résultats mis en cache dans les propriétés privées
- Pas de recalcul des composants

---

## Compatibilité

| Version | Support | Notes |
|---------|---------|-------|
| PHP 8.4 | ✅ Complet | Support total |
| PHP 8.3 | ✅ Complet | Support total |
| PHP 8.2 | ✅ Complet | Support total |
| PHP 8.1 | ✅ Complet | Support total |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;

// 1. Création et analyse
$signature = 'backup {source} {destination} {format=zip} {env=?} {excludes*} {--force} {--verbose}';
$structure = new SignatureStructureVO($signature);

echo "=== Analyse de la signature ===\n";
echo "Signature: {$signature}\n\n";

// 2. Validation
if ($structure->isValid()) {
    echo "✅ Signature valide\n\n";
} else {
    echo "❌ Signature invalide:\n";
    foreach ($structure->getValidationErrors() as $error) {
        echo "  - {$error}\n";
    }
    echo "\nSuggestions:\n";
    foreach ($structure->getValidationSuggestions() as $suggestion) {
        echo "  - {$suggestion}\n";
    }
    exit(1);
}

// 3. Affichage des composants
echo "Commande: " . $structure->getSource() . "\n\n";

echo "Arguments requis:\n";
foreach ($structure->getRequireds() as $arg) {
    echo "  - {$arg}\n";
}
echo "\n";

echo "Arguments par défaut:\n";
foreach ($structure->getDefaults() as $name => $value) {
    $display = $value ?? 'null';
    echo "  - {$name}: {$display}\n";
}
echo "\n";

echo "Arguments variadiques:\n";
foreach ($structure->getVariadics() as $arg) {
    echo "  - {$arg}\n";
}
echo "\n";

echo "Flags:\n";
foreach ($structure->getFlags() as $flag) {
    echo "  --{$flag}\n";
}
echo "\n";

// 4. Vérifications
echo "=== Vérifications ===\n";
echo "Has requireds: " . ($structure->hasRequireds() ? 'Yes' : 'No') . "\n";
echo "Has defaults: " . ($structure->hasDefaults() ? 'Yes' : 'No') . "\n";
echo "Has variadics: " . ($structure->hasVariadics() ? 'Yes' : 'No') . "\n";
echo "Has flags: " . ($structure->hasFlags() ? 'Yes' : 'No') . "\n";
echo "\n";

// 5. Vérification d'arguments spécifiques
echo "=== Vérifications spécifiques ===\n";
$checks = ['source', 'destination', 'format', 'env', 'excludes', 'force', 'unknown'];

foreach ($checks as $name) {
    $exists = $structure->hasArgument($name);
    echo "Argument '{$name}': " . ($exists ? '✅' : '❌') . "\n";
}
echo "\n";

// 6. Structure brute
echo "=== Structure brute ===\n";
$value = $structure->getValue();
print_r($value->toArray());
echo "\n";

// 7. Égalité
$structure2 = new SignatureStructureVO($signature);
echo "Égalité: " . ($structure->equals($structure2) ? '✅' : '❌') . "\n";

// 8. Signature brute
echo "Signature brute: " . $structure->getRaw() . "\n";
```

## Voir aussi

- `SignatureParser` - Parser principal des signatures
- `QueryBuilder` - Construction dynamique de requêtes
- `ValidationResultRecord` - Résultat de validation
- `StrictDataObject` - Structure de données immuable