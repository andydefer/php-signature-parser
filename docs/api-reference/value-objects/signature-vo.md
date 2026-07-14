# SignatureVO - Référence Technique

## Description

Le `SignatureVO` est un Value Object qui représente un couple signature/requête complet. Il analyse et valide la requête par rapport à la signature, et fournit un accès typé à tous les composants extraits.

## Hiérarchie

```
AbstractValueObject
    └── SignatureVO
```

## Rôle principal

Associe une signature à une requête, extrait tous les composants (source, arguments requis, arguments par défaut, nullables, énumérations, variadics, flags, tags personnalisés) et valide la cohérence entre la signature et la requête.

---

## API / Méthodes publiques

### `__construct(string $signature, string $query)`

Construit un nouvel objet SignatureVO en analysant la signature et la requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la commande (ex: `'backup {source} {--force}'`) |
| `$query` | `string` | Requête de la commande (ex: `'backup /var/www --force'`) |

**Exceptions :** `InvalidArgumentException` - Si la signature ou la requête est vide

**Exemple :**
```php
$vo = new SignatureVO('backup {source} {--force}', 'backup /var/www --force');
```

---

### `getSource(): string`

Retourne le nom de la commande (source).

**Retourne :** `string` - Nom de la commande

**Exemple :**
```php
$source = $vo->getSource(); // 'backup'
```

---

### `getRequired(string $name): ?string`

Retourne la valeur d'un argument requis par son nom.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `string|null` - La valeur ou `null` si non trouvé

**Exemple :**
```php
$source = $vo->getRequired('source'); // '/var/www'
```

---

### `getRequireds(): array`

Retourne tous les arguments requis.

**Retourne :** `array<string, string>` - Tableau [nom => valeur]

**Exemple :**
```php
$requireds = $vo->getRequireds(); // ['source' => '/var/www', 'destination' => '/backup']
```

---

### `getDefault(string $name): ?string`

Retourne la valeur d'un argument par défaut par son nom.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `string|null` - La valeur ou `null` si non trouvé

**Exemple :**
```php
$format = $vo->getDefault('format'); // 'zip'
```

---

### `getDefaults(): array`

Retourne tous les arguments par défaut.

**Retourne :** `array<string, string|null>` - Tableau [nom => valeur]

**Exemple :**
```php
$defaults = $vo->getDefaults(); // ['format' => 'zip', 'output' => null]
```

---

### `getVariadic(string $name): array`

Retourne les valeurs d'un argument variadique par son nom.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `array<string>` - Tableau des valeurs ou tableau vide

**Exemple :**
```php
$files = $vo->getVariadic('files'); // ['file1.txt', 'file2.txt']
```

---

### `getVariadics(): array`

Retourne tous les arguments variadiques.

**Retourne :** `array<string, array<string>>` - Tableau [nom => valeurs]

---

### `getFlag(string $name): bool`

Retourne la valeur d'un flag par son nom.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag (sans préfixe `--`) |

**Retourne :** `bool` - `true` si actif, `false` sinon

**Exemple :**
```php
$force = $vo->getFlag('force'); // true
```

---

### `getFlags(): array`

Retourne tous les flags.

**Retourne :** `array<string, bool>` - Tableau [nom => booléen]

---

### `getEnum(string $name): ?string`

Retourne la valeur d'une énumération par son nom.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'énumération |

**Retourne :** `string|null` - La valeur ou `null` si non trouvé

**Exemple :**
```php
$level = $vo->getEnum('level'); // 'high'
```

---

### `getEnums(): array`

Retourne toutes les énumérations.

**Retourne :** `array<string, string>` - Tableau [nom => valeur]

---

### `getCustom(string $key): ?string`

Retourne la valeur d'un tag personnalisé par sa clé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du tag |

**Retourne :** `string|null` - La valeur ou `null` si non trouvé

**Exemple :**
```php
$greeting = $vo->getCustom('greeting'); // 'Hello World'
```

---

### `getCustoms(): array`

Retourne tous les tags personnalisés.

**Retourne :** `array<string, string>` - Tableau [clé => valeur]

---

### `hasCustom(string $key): bool`

Vérifie si un tag personnalisé existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du tag |

**Retourne :** `bool` - `true` s'il existe, `false` sinon

---

### `getParsed(): StrictDataObject`

Retourne la structure parsée complète.

**Retourne :** `StrictDataObject` - Objet contenant toutes les données

---

### `hasFlag(string $name): bool`

Vérifie si un flag est présent et actif.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag |

**Retourne :** `bool` - `true` si actif, `false` sinon

**Exemple :**
```php
if ($vo->hasFlag('force')) {
    echo "Mode force activé";
}
```

---

### `hasRequired(string $name): bool`

Vérifie si un argument requis existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool` - `true` s'il existe, `false` sinon

---

### `hasDefault(string $name): bool`

Vérifie si un argument par défaut existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool` - `true` s'il existe, `false` sinon

---

### `hasVariadic(string $name): bool`

Vérifie si un argument variadique existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool` - `true` s'il existe, `false` sinon

---

### `hasEnum(string $name): bool`

Vérifie si une énumération existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'énumération |

**Retourne :** `bool` - `true` si elle existe, `false` sinon

---

### `hasCustoms(): bool`

Vérifie si des tags personnalisés existent.

**Retourne :** `bool` - `true` s'il y en a, `false` sinon

---

### `isValid(): bool`

Vérifie si la requête est valide par rapport à la signature.

**Retourne :** `bool` - `true` si valide, `false` sinon

**Exemple :**
```php
if ($vo->isValid()) {
    echo "Requête valide";
}
```

---

### `getValidationErrors(): StringTypedCollection`

Retourne les erreurs de validation.

**Retourne :** `StringTypedCollection` - Collection des messages d'erreur

---

### `getValidationSuggestions(): StringTypedCollection`

Retourne les suggestions pour corriger les erreurs.

**Retourne :** `StringTypedCollection` - Collection des suggestions

---

### `getValidationResult(): ValidationResultRecord`

Retourne le résultat complet de la validation.

**Retourne :** `ValidationResultRecord` - Résultat de la validation

---

### `getValue(): StrictDataObject`

Méthode de `AbstractValueObject`. Retourne la structure parsée.

**Retourne :** `StrictDataObject`

---

### `equals(AbstractValueObject $other): bool`

Compare deux objets `SignatureVO`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$other` | `AbstractValueObject` | Autre objet à comparer |

**Retourne :** `bool` - `true` s'ils sont égaux, `false` sinon

---

## Cas d'utilisation

### Cas 1 : Analyse d'une commande simple

```php
$vo = new SignatureVO(
    'send {recipient} {--verbose}',
    'send John --verbose'
);

echo $vo->getSource();              // 'send'
echo $vo->getRequired('recipient'); // 'John'
echo $vo->getFlag('verbose');       // true
```

### Cas 2 : Commande avec tags personnalisés

```php
$vo = new SignatureVO(
    'deploy {environment} {--force}',
    'deploy staging --force <user="admin"> <version="1.2.3">'
);

echo $vo->getRequired('environment'); // 'staging'
echo $vo->getCustom('user');          // 'admin'
echo $vo->getCustom('version');       // '1.2.3'
```

### Cas 3 : Validation d'une requête invalide

```php
$vo = new SignatureVO(
    'backup {source} {destination} {--force}',
    'backup /var/www --force'
);

if (!$vo->isValid()) {
    $errors = $vo->getValidationErrors();
    foreach ($errors as $error) {
        echo "Erreur: $error\n";
    }
}
// Erreur: Missing required argument: 'destination'
```

### Cas 4 : Commande avec énumérations

```php
$vo = new SignatureVO(
    'set-level ::level->[beginner,middle,master]=middle',
    'set-level master'
);

echo $vo->getEnum('level'); // 'master'
```

---

## Flux d'exécution

```
Signature + Query
        ↓
SignatureParser::parse()
        ↓
├── Source
├── Requireds
├── Defaults
├── Variadics
├── Flags
├── Enums
└── Custom tags
        ↓
StrictDataObject (parsed)
        ↓
SignatureParser::validate()
        ↓
ValidationResultRecord (validation_result)
```

## Gestion des erreurs

| Situation | Exception/Erreur | Message |
|-----------|------------------|---------|
| Signature vide | `InvalidArgumentException` | `Signature cannot be empty` |
| Requête vide | `InvalidArgumentException` | `Query cannot be empty` |
| Argument requis manquant | Erreur de validation | `Missing required argument: '{$name}'` |
| Flag inconnu | Erreur de validation | `Unknown flag: '{$flag}'` |
| Valeur d'énumération invalide | Erreur de validation | `Invalid value '{$value}' for enum '{$name}'` |

## Performance

- O(n) pour le parsing et la validation, où n est le nombre de tokens
- Parsing et validation effectués une seule fois à la construction
- Structure immuable après construction

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\SignatureParser\ValueObjects\SignatureVO;

$vo = new SignatureVO(
    'backup {source} {destination} {format=zip} {output=?} ::level->[low,high]=medium {excludes*} {--force} {--verbose}',
    'backup /var/www /backup tar.gz _ high [cache,logs] --force'
);

echo "Commande: " . $vo->getSource() . "\n";
echo "Source: " . $vo->getRequired('source') . "\n";
echo "Destination: " . $vo->getRequired('destination') . "\n";
echo "Format: " . $vo->getDefault('format') . "\n";
echo "Output: " . ($vo->getDefault('output') ?? 'null') . "\n";
echo "Niveau: " . $vo->getEnum('level') . "\n";
echo "Exclus: " . implode(', ', $vo->getVariadic('excludes')) . "\n";
echo "Force: " . ($vo->hasFlag('force') ? 'Oui' : 'Non') . "\n";
echo "Verbose: " . ($vo->hasFlag('verbose') ? 'Oui' : 'Non') . "\n";

if ($vo->isValid()) {
    echo "\n✅ Commande valide\n";
} else {
    echo "\n❌ Commande invalide:\n";
    foreach ($vo->getValidationErrors() as $error) {
        echo "  - $error\n";
    }
}
```

## Voir aussi

- `SignatureParser` - Parser principal
- `SignatureStructureVO` - Structure de signature
- `ValidationResultRecord` - Résultat de validation
- `ParsedSignatureRecord` - Résultat de parsing