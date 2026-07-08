# SignatureStructureVO - Référence Technique

## Description

Value Object qui analyse la structure d'une signature de commande CLI **sans** nécessiter de requête. Extrait le nom de la commande, les arguments requis, les arguments par défaut, les arguments nullables, les variadiques et les flags booléens.

## Hiérarchie

```
AbstractValueObject
    └── SignatureStructureVO
```

## Rôle principal

`SignatureStructureVO` analyse la **structure** d'une signature de commande CLI. Contrairement à `SignatureVO` qui nécessite une requête, ce VO travaille uniquement sur la signature pour fournir une vue structurée des éléments attendus.

### Éléments supportés

| Syntaxe | Description | Exemple |
|---------|-------------|---------|
| `{name}` | Argument requis | `{source}` |
| `{name=value}` | Argument avec valeur par défaut | `{format=zip}` |
| `{name=}` | Argument avec valeur par défaut vide (ignoré) | `{format=}` |
| `{name?}` | Argument nullable | `{format?}` |
| `{name*}` | Argument variadique | `{files*}` |
| `{--flag}` | Flag booléen | `{--force}` |

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

Retourne les arguments avec leurs valeurs par défaut (uniquement ceux qui ont une valeur non-nulle).

**Retourne :** `array<string, string>` - Tableau associatif [nom => valeur par défaut]

**Exemple :**
```php
$defaults = $vo->getDefaults(); // ['format' => 'zip', 'output' => 'dist']
```

**Note :** Les arguments `{name=}` (valeur vide) ou `{name?}` (nullable) ne sont pas inclus.

---

### `getNullables(): array`

Retourne la liste des arguments nullables.

**Retourne :** `array<string>` - Liste des noms d'arguments nullables

**Exemple :**
```php
$nullables = $vo->getNullables(); // ['env', 'port']
```

---

### `getVariadics(): array`

Retourne la liste des arguments variadiques.

**Retourne :** `array<string>` - Liste des noms d'arguments variadiques

**Exemple :**
```php
$variadics = $vo->getVariadics(); // ['excludes']
---

### `getFlags(): array`

Retourne la liste des flags booléens.

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

**Retourne :** `bool`

**Exemple :**
```php
$vo->hasRequired('source'); // true
$vo->hasRequired('unknown'); // false
```

---

### `hasDefault(string $name): bool`

Vérifie si un argument par défaut existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument à vérifier |

**Retourne :** `bool`

**Exemple :**
```php
$vo->hasDefault('format'); // true
```

---

### `hasNullable(string $name): bool`

Vérifie si un argument nullable existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument à vérifier |

**Retourne :** `bool`

**Exemple :**
```php
$vo->hasNullable('env'); // true
$vo->hasNullable('unknown'); // false
```

---

### `hasVariadic(string $name): bool`

Vérifie si un argument variadique existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument à vérifier |

**Retourne :** `bool`

**Exemple :**
```php
$vo->hasVariadic('excludes'); // true
```

---

### `hasFlag(string $name): bool`

Vérifie si un flag existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag à vérifier |

**Retourne :** `bool`

**Exemple :**
```php
$vo->hasFlag('force'); // true
```

---

### `countArguments(): int`

Compte le nombre total d'arguments (hors source et flags).

**Retourne :** `int` - Nombre total d'arguments (requis + defaults + nullables + variadics)

**Exemple :**
```php
$count = $vo->countArguments(); // 4 (source, destination, format, excludes)
```

---

### `getRaw(): string`

Retourne la signature brute.

**Retourne :** `string` - La signature originale

**Exemple :**
```php
$raw = $vo->getRaw(); // 'backup {source} {destination} {format=zip} {excludes*} {--force}'
```

---

### `hasRequireds(): bool`

Vérifie s'il y a des arguments requis.

**Retourne :** `bool`

---

### `hasDefaults(): bool`

Vérifie s'il y a des arguments par défaut.

**Retourne :** `bool`

---

### `hasNullables(): bool`

Vérifie s'il y a des arguments nullables.

**Retourne :** `bool`

---

### `hasVariadics(): bool`

Vérifie s'il y a des arguments variadiques.

**Retourne :** `bool`

---

### `hasFlags(): bool`

Vérifie s'il y a des flags.

**Retourne :** `bool`

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
echo $structure->nullable[0];   // 'env'
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
echo "Arguments nullables: " . implode(', ', $vo->getNullables()) . "\n";
echo "Arguments variadiques: " . implode(', ', $vo->getVariadics()) . "\n";
echo "Flags: " . implode(', ', $vo->getFlags()) . "\n";
```

### Cas 2 : Validation de structure de signature

```php
$vo = new SignatureStructureVO('deploy {env=production} {--force}');

if ($vo->hasRequireds()) {
    echo "Cette commande a des arguments requis\n";
}

if ($vo->hasDefaults()) {
    echo "Arguments par défaut disponibles:\n";
    foreach ($vo->getDefaults() as $name => $value) {
        echo "  - $name: $value\n";
    }
}

if ($vo->hasNullables()) {
    echo "Arguments nullables: " . implode(', ', $vo->getNullables()) . "\n";
}

if ($vo->hasFlags()) {
    echo "Flags disponibles: " . implode(', ', $vo->getFlags()) . "\n";
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
    
    if ($vo->hasNullables()) {
        $doc .= "**Arguments nullables:**\n";
        foreach ($vo->getNullables() as $arg) {
            $doc .= "- `$arg`\n";
        }
        $doc .= "\n";
    }
    
    if ($vo->hasVariadics()) {
        $doc .= "**Arguments variadiques:**\n";
        foreach ($vo->getVariadics() as $arg) {
            $doc .= "- `$arg`\n";
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

echo generateCommandDoc('backup {source} {destination} {format=zip} {excludes*} {--force}');
```

### Cas 4 : Intégration avec SignatureVO

```php
$structureVo = new SignatureStructureVO('backup {source} {destination} {format=zip} {--force}');

$fullVo = new SignatureVO(
    'backup {source} {destination} {format=zip} {--force}',
    'backup /var/www /backup tar.gz --force'
);

$requireds = $structureVo->getRequireds(); // ['source', 'destination']
$requiredValues = $fullVo->getRequired('source'); // '/var/www'
```

## Flux d'exécution

```
Signature string
    ↓
extractSignatureElements()
    ↓
Éléments bruts
    ↓
Parcours des éléments
    ├── Position 0 → source
    ├── --flag → flags
    ├── * → variadic
    ├── = → default (name => value)
    ├── ? → nullable
    └── other → required
    ↓
Structure interne
    ↓
getValue() / méthodes accesseurs
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Signature vide | `InvalidArgumentException` | `Signature cannot be empty` |

## Intégration

### Avec SignatureParser

```php
$parser = new SignatureParser();
$elements = $parser->extractSignatureElements($signature);
```

### Avec StrictDataObject

```php
$structure = $vo->getValue();
$source = $structure->source;
$requireds = $structure->required->toArray();
$defaults = $structure->default->toArray();
$nullables = $structure->nullable->toArray();
$variadics = $structure->variadic->toArray();
$flags = $structure->flags->toArray();
```

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `__construct()` | O(n) | n = nombre d'éléments de la signature |
| `getSource()` | O(1) | Accès direct |
| `getRequireds()` | O(1) | Accès direct |
| `getDefaults()` | O(1) | Accès direct |
| `getNullables()` | O(1) | Accès direct |
| `getVariadics()` | O(1) | Accès direct |
| `getFlags()` | O(1) | Accès direct |
| `hasRequired()` | O(n) | Recherche dans le tableau |
| `hasDefault()` | O(1) | Recherche dans le tableau associatif |
| `hasNullable()` | O(n) | Recherche dans le tableau |
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

$signature = 'backup {source} {destination} {format=zip} {output=dist} {env?} {excludes*} {--force} {--verbose}';
$vo = new SignatureStructureVO($signature);

echo "Source: " . $vo->getSource() . "\n";
echo "Raw: " . $vo->getRaw() . "\n";
echo "Total arguments: " . $vo->countArguments() . "\n";

echo "Requireds: " . implode(', ', $vo->getRequireds()) . "\n";
echo "Defaults: " . print_r($vo->getDefaults(), true) . "\n";
echo "Nullables: " . implode(', ', $vo->getNullables()) . "\n";
echo "Variadics: " . implode(', ', $vo->getVariadics()) . "\n";
echo "Flags: " . implode(', ', $vo->getFlags()) . "\n";

echo "Has requireds? " . ($vo->hasRequireds() ? 'Yes' : 'No') . "\n";
echo "Has defaults? " . ($vo->hasDefaults() ? 'Yes' : 'No') . "\n";
echo "Has nullables? " . ($vo->hasNullables() ? 'Yes' : 'No') . "\n";
echo "Has variadics? " . ($vo->hasVariadics() ? 'Yes' : 'No') . "\n";
echo "Has flags? " . ($vo->hasFlags() ? 'Yes' : 'No') . "\n";

echo "Has 'source' required? " . ($vo->hasRequired('source') ? 'Yes' : 'No') . "\n";
echo "Has 'format' default? " . ($vo->hasDefault('format') ? 'Yes' : 'No') . "\n";
echo "Default value for 'format': " . ($vo->hasDefault('format') ? $vo->getDefaults()['format'] : 'N/A') . "\n";
echo "Has 'env' nullable? " . ($vo->hasNullable('env') ? 'Yes' : 'No') . "\n";
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
- `StrictDataObject` - DataObject pour l'accès typé
---