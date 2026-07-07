# SignatureStructureVO - Référence Technique

## Description

Value Object qui analyse la structure d'une signature de commande CLI **sans** nécessiter de requête. Extrait le nom de la commande, les arguments requis, les arguments par défaut, les variadiques et les options.

## Hiérarchie

```
AbstractValueObject
    └── SignatureStructureVO
```

## Rôle principal

`SignatureStructureVO` analyse la **structure** d'une signature de commande CLI. Contrairement à `SignatureVO` qui nécessite une requête, ce VO travaille uniquement sur la signature pour fournir une vue structurée des éléments attendus.

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
| `$signature` | `string` | Signature de la commande (ex: `backup {source} {destination} {format=zip} {--force}`) |

**Retourne :** `void`

**Exceptions :** `InvalidArgumentException` - Si la signature est vide

**Exemple :**
```php
$vo = new SignatureStructureVO('backup {source} {destination} {format=zip} {excludes*} {--force}');
```

---

### `getSource(): string`

Retourne le nom de la commande (premier élément de la signature).

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

### `getOptions(): array`

Retourne la liste des options.

**Retourne :** `array<string>` - Liste des noms d'options

**Exemple :**
```php
$options = $vo->getOptions(); // ['force', 'verbose']
```

---

### `hasRequired(string $name): bool`

Vérifie si un argument requis existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument à vérifier |

**Retourne :** `bool` - `true` si l'argument requis existe

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

**Retourne :** `bool` - `true` si l'argument par défaut existe

**Exemple :**
```php
$vo->hasDefault('format'); // true
```

---

### `hasVariadic(string $name): bool`

Vérifie si un argument variadique existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument à vérifier |

**Retourne :** `bool` - `true` si l'argument variadique existe

**Exemple :**
```php
$vo->hasVariadic('excludes'); // true
```

---

### `hasOption(string $name): bool`

Vérifie si une option existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'option à vérifier |

**Retourne :** `bool` - `true` si l'option existe

**Exemple :**
```php
$vo->hasOption('force'); // true
```

---

### `countArguments(): int`

Compte le nombre total d'arguments (hors source et options).

**Retourne :** `int` - Nombre total d'arguments

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

### `hasOptions(): bool`

Vérifie s'il y a des options.

**Retourne :** `bool` - `true` s'il y a au moins une option

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
echo $structure->options[0];    // 'force'
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
echo "Options: " . implode(', ', $vo->getOptions()) . "\n";
```

### Cas 2 : Validation de signature

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

if ($vo->hasOptions()) {
    echo "Options disponibles: " . implode(', ', $vo->getOptions()) . "\n";
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
    
    if ($vo->hasOptions()) {
        $doc .= "**Options:**\n";
        foreach ($vo->getOptions() as $opt) {
            $doc .= "- `--$opt`\n";
        }
    }
    
    return $doc;
}

echo generateCommandDoc('backup {source} {destination} {format=zip} {--force}');
```

### Cas 4 : Intégration avec SignatureVO

```php
// Analyse de structure sans requête
$structureVo = new SignatureStructureVO('backup {source} {destination} {format=zip} {--force}');

// Analyse complète avec requête
$fullVo = new SignatureVO(
    'backup {source} {destination} {format=zip} {--force}',
    'backup /var/www /backup tar.gz --force'
);

// Comparaison
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
    ├── --flag → options
    ├── * → variadic
    ├── = → default (name => value)
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
// Les éléments sont ensuite analysés par SignatureStructureVO
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
| `getOptions()` | O(1) | Accès direct |
| `hasRequired()` | O(n) | Recherche dans le tableau |
| `hasDefault()` | O(1) | Recherche dans le tableau associatif |
| `hasVariadic()` | O(n) | Recherche dans le tableau |
| `hasOption()` | O(n) | Recherche dans le tableau |

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

// Création d'un VO de structure
$signature = 'backup {source} {destination} {format=zip} {output=dist} {excludes*} {--force} {--verbose}';
$vo = new SignatureStructureVO($signature);

// Accès aux données
echo "Source: " . $vo->getSource() . "\n";
echo "Raw: " . $vo->getRaw() . "\n";
echo "Arguments: " . $vo->countArguments() . "\n";

// Vérifications
echo "Has requireds? " . ($vo->hasRequireds() ? 'Yes' : 'No') . "\n";
echo "Has defaults? " . ($vo->hasDefaults() ? 'Yes' : 'No') . "\n";
echo "Has variadics? " . ($vo->hasVariadics() ? 'Yes' : 'No') . "\n";
echo "Has options? " . ($vo->hasOptions() ? 'Yes' : 'No') . "\n";

// Vérifications spécifiques
echo "Has 'source' required? " . ($vo->hasRequired('source') ? 'Yes' : 'No') . "\n";
echo "Has 'format' default? " . ($vo->hasDefault('format') ? 'Yes' : 'No') . "\n";
echo "Default value for 'format': " . ($vo->hasDefault('format') ? $vo->getDefaults()['format'] : 'N/A') . "\n";
echo "Has 'force' option? " . ($vo->hasOption('force') ? 'Yes' : 'No') . "\n";

// Structure complète
$structure = $vo->getValue();
print_r($structure->toArray());

// Comparaison
$vo2 = new SignatureStructureVO('backup {source} {destination} {format=zip} {--force}');
echo "Equal? " . ($vo->equals($vo2) ? 'Yes' : 'No') . "\n";
```

## Voir aussi

- `SignatureVO` - Value Object avec analyse de la requête
- `SignatureParser` - Parseur principal
- `ParsedSignatureRecord` - Structure de données retournée par le parseur
- `StrictDataObject` - DataObject pour l'accès typé