# Collections - Référence Technique

## Description

Les collections sont des conteneurs typés qui stockent et manipulent les différents enregistrements résultant du parsing des signatures de commandes. Chaque collection correspond à un type spécifique d'argument : arguments simples, flags, énumérations et variadics.

## Hiérarchie / Implémentations

```
TypedCollection (DomainStructures)
    ├── ArgumentCollection
    ├── FlagCollection
    ├── EnumCollection
    └── VariadicArgumentCollection
```

## Rôle principal

Chaque collection offre des méthodes spécialisées pour interagir avec son type d'argument spécifique, facilitant l'accès aux données et leur manipulation.

---

# ArgumentCollection

## Description

Collection typée pour les enregistrements `ArgumentRecord`. Utilisée pour les arguments requis et par défaut.

## Hiérarchie

```
TypedCollection
    └── ArgumentCollection
```

## Rôle principal

Stocke et permet l'accès aux arguments simples (requis et par défaut) avec leurs valeurs.

## API

### `__construct()`

Initialise une nouvelle collection d'arguments.

```php
$collection = new ArgumentCollection();
```

### `get(string $name): ?string`

Récupère la valeur d'un argument par son nom.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `string|null` - La valeur de l'argument ou `null` s'il n'existe pas

**Exemple :**
```php
$collection = new ArgumentCollection();
$collection->add(ArgumentRecord::from(['name' => 'John', 'value' => 'Doe']));
$value = $collection->get('name'); // 'John'
```

### `has(string $name): bool`

Vérifie si un argument existe par son nom.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool` - `true` si l'argument existe, `false` sinon

**Exemple :**
```php
if ($collection->has('name')) {
    echo "L'argument 'name' existe";
}
```

### `getNames(): array`

Récupère tous les noms des arguments.

**Retourne :** `array<string>` - Liste des noms

**Exemple :**
```php
$names = $collection->getNames(); // ['name', 'email', 'age']
```

### `getValues(): array`

Récupère toutes les valeurs des arguments.

**Retourne :** `array<string>` - Liste des valeurs

**Exemple :**
```php
$values = $collection->getValues(); // ['John', 'john@example.com', '30']
```

### `toAssociativeArray(): array`

Convertit la collection en tableau associatif.

**Retourne :** `array<string, string>` - Tableau [nom => valeur]

**Exemple :**
```php
$array = $collection->toAssociativeArray();
// ['name' => 'John', 'email' => 'john@example.com']
```

## Cas d'utilisation

### Cas 1 : Accès aux arguments requis

```php
$requireds = new ArgumentCollection();
$requireds->add(ArgumentRecord::from(['name' => 'source', 'value' => '/var/www']));
$requireds->add(ArgumentRecord::from(['name' => 'destination', 'value' => '/backup']));

$source = $requireds->get('source'); // '/var/www'
```

### Cas 2 : Vérification d'existence

```php
if (!$requireds->has('source')) {
    throw new \RuntimeException('Argument source manquant');
}
```

---

# FlagCollection

## Description

Collection typée pour les enregistrements `FlagRecord`. Gère les flags booléens.

## Hiérarchie

```
TypedCollection
    └── FlagCollection
```

## Rôle principal

Stocke et permet l'accès aux flags avec leurs valeurs booléennes.

## API

### `__construct()`

Initialise une nouvelle collection de flags.

```php
$collection = new FlagCollection();
```

### `get(string $name): bool`

Récupère la valeur d'un flag par son nom.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag (sans préfixe `--`) |

**Retourne :** `bool` - `true` si le flag est actif, `false` sinon

**Exemple :**
```php
$collection = new FlagCollection();
$collection->add(FlagRecord::from(['name' => 'force', 'value' => true]));
$isForce = $collection->get('force'); // true
```

### `has(string $name): bool`

Vérifie si un flag existe par son nom.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag |

**Retourne :** `bool` - `true` si le flag existe, `false` sinon

### `isActive(string $name): bool`

Vérifie si un flag est actif (valeur = `true`).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag |

**Retourne :** `bool` - `true` si le flag est actif, `false` sinon

**Exemple :**
```php
if ($flags->isActive('force')) {
    echo "Mode force activé";
}
```

### `getNames(): array`

Récupère tous les noms des flags.

**Retourne :** `array<string>` - Liste des noms

### `getActiveNames(): array`

Récupère tous les flags actifs.

**Retourne :** `array<string>` - Liste des noms des flags actifs

**Exemple :**
```php
$active = $flags->getActiveNames(); // ['force', 'verbose']
```

### `toAssociativeArray(): array`

Convertit la collection en tableau associatif.

**Retourne :** `array<string, bool>` - Tableau [nom => booléen]

**Exemple :**
```php
$array = $flags->toAssociativeArray();
// ['force' => true, 'verbose' => false]
```

## Cas d'utilisation

### Cas 1 : Vérification de flags

```php
$flags = new FlagCollection();
$flags->add(FlagRecord::from(['name' => 'force', 'value' => true]));
$flags->add(FlagRecord::from(['name' => 'verbose', 'value' => false]));

if ($flags->isActive('force')) {
    echo "Exécution forcée";
}
```

### Cas 2 : Liste des flags actifs

```php
$activeFlags = $flags->getActiveNames();
foreach ($activeFlags as $flag) {
    echo "--$flag ";
}
// Résultat : --force
```

---

# EnumCollection

## Description

Collection typée pour les enregistrements `EnumRecord`. Gère les énumérations avec leurs valeurs autorisées.

## Hiérarchie

```
TypedCollection
    └── EnumCollection
```

## Rôle principal

Stocke et permet l'accès aux énumérations avec validation des valeurs et gestion des états (requis, optionnel, défaut).

## API

### `__construct()`

Initialise une nouvelle collection d'énumérations.

```php
$collection = new EnumCollection();
```

### `get(string $name): mixed`

Récupère la valeur d'une énumération par son nom.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'énumération |

**Retourne :** `mixed` - La valeur ou `null` si elle n'existe pas

**Exemple :**
```php
$enums = new EnumCollection();
$enums->add(EnumRecord::from([
    'name' => 'level',
    'value' => 'high',
    'allowed_values' => ['low', 'high']
]));
$value = $enums->get('level'); // 'high'
```

### `has(string $name): bool`

Vérifie si une énumération existe par son nom.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'énumération |

**Retourne :** `bool` - `true` si elle existe, `false` sinon

### `getNames(): array`

Récupère tous les noms des énumérations.

**Retourne :** `array<string>` - Liste des noms

### `getValues(): array`

Récupère toutes les valeurs des énumérations.

**Retourne :** `array<mixed>` - Liste des valeurs

### `getAllowedValues(string $name): ?array`

Récupère les valeurs autorisées pour une énumération.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'énumération |

**Retourne :** `array<string>|null` - Liste des valeurs autorisées ou `null`

**Exemple :**
```php
$allowed = $enums->getAllowedValues('level'); // ['low', 'medium', 'high']
```

### `isAllowed(string $name, string $value): bool`

Vérifie si une valeur est autorisée pour une énumération.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'énumération |
| `$value` | `string` | Valeur à vérifier |

**Retourne :** `bool` - `true` si la valeur est autorisée, `false` sinon

**Exemple :**
```php
if ($enums->isAllowed('level', 'medium')) {
    echo "Valeur valide";
}
```

### `isRequired(string $name): bool`

Vérifie si une énumération est requise.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'énumération |

**Retourne :** `bool` - `true` si requise, `false` sinon

### `isOptional(string $name): bool`

Vérifie si une énumération est optionnelle.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'énumération |

**Retourne :** `bool` - `true` si optionnelle, `false` sinon

### `hasDefault(string $name): bool`

Vérifie si une énumération a une valeur par défaut.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'énumération |

**Retourne :** `bool` - `true` si elle a une valeur par défaut, `false` sinon

### `getDefault(string $name): mixed`

Récupère la valeur par défaut d'une énumération.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'énumération |

**Retourne :** `mixed` - La valeur par défaut ou `null`

**Exemple :**
```php
$default = $enums->getDefault('level'); // 'medium'
```

### `toAssociativeArray(): array`

Convertit la collection en tableau associatif [nom => valeur].

**Retourne :** `array<string, mixed>`

**Exemple :**
```php
$array = $enums->toAssociativeArray();
// ['level' => 'high', 'mode' => 'staging']
```

### `toFullArray(): array`

Convertit la collection en tableau avec toutes les données.

**Retourne :** `array<array<string, mixed>>`

**Exemple :**
```php
$full = $enums->toFullArray();
// [
//     [
//         'name' => 'level',
//         'value' => 'high',
//         'allowed_values' => ['low', 'medium', 'high'],
//         'default_value' => 'medium',
//         'value_state' => 'DEFAULTED'
//     ]
// ]
```

## Cas d'utilisation

### Cas 1 : Validation d'une valeur d'énumération

```php
$enums = new EnumCollection();
$enums->add(EnumRecord::from([
    'name' => 'level',
    'value' => 'high',
    'allowed_values' => ['low', 'medium', 'high'],
    'default_value' => 'medium',
    'value_state' => ValueState::DEFAULTED
]));

if (!$enums->isAllowed('level', 'expert')) {
    throw new \InvalidArgumentException('Valeur non autorisée');
}
```

### Cas 2 : Vérification de l'état d'une énumération

```php
if ($enums->isRequired('level')) {
    echo "Le niveau est requis";
} elseif ($enums->isOptional('level')) {
    echo "Le niveau est optionnel";
} elseif ($enums->hasDefault('level')) {
    echo "Valeur par défaut: " . $enums->getDefault('level');
}
```

---

# VariadicArgumentCollection

## Description

Collection typée pour les enregistrements `VariadicArgumentRecord`. Gère les arguments variadiques avec leurs valeurs multiples.

## Hiérarchie

```
TypedCollection
    └── VariadicArgumentCollection
```

## Rôle principal

Stocke et permet l'accès aux arguments variadiques avec leurs tableaux de valeurs et restrictions éventuelles.

## API

### `__construct()`

Initialise une nouvelle collection d'arguments variadiques.

```php
$collection = new VariadicArgumentCollection();
```

### `get(string $name): array`

Récupère les valeurs d'un argument variadique par son nom.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument variadique |

**Retourne :** `array<string>` - Liste des valeurs (tableau vide si non trouvé)

**Exemple :**
```php
$variadics = new VariadicArgumentCollection();
$variadics->add(VariadicArgumentRecord::from([
    'name' => 'files',
    'values' => ['file1.txt', 'file2.txt']
]));
$values = $variadics->get('files'); // ['file1.txt', 'file2.txt']
```

### `has(string $name): bool`

Vérifie si un argument variadique existe par son nom.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument variadique |

**Retourne :** `bool` - `true` s'il existe, `false` sinon

### `getNames(): array`

Récupère tous les noms des arguments variadiques.

**Retourne :** `array<string>` - Liste des noms

### `getAllValues(): array`

Récupère toutes les valeurs de tous les arguments variadiques (fusionnées).

**Retourne :** `array<string>` - Toutes les valeurs

**Exemple :**
```php
$all = $variadics->getAllValues();
// ['file1.txt', 'file2.txt', 'tag1', 'tag2']
```

### `toAssociativeArray(): array`

Convertit la collection en tableau associatif [nom => valeurs].

**Retourne :** `array<string, array<string>>`

**Exemple :**
```php
$array = $variadics->toAssociativeArray();
// ['files' => ['file1.txt', 'file2.txt']]
```

### `countAllValues(): int`

Compte le nombre total de valeurs dans tous les arguments variadiques.

**Retourne :** `int` - Nombre total de valeurs

**Exemple :**
```php
$total = $variadics->countAllValues();
// 4 (si 2 fichiers + 2 tags)
```

## Cas d'utilisation

### Cas 1 : Accès aux fichiers variadiques

```php
$variadics = new VariadicArgumentCollection();
$variadics->add(VariadicArgumentRecord::from([
    'name' => 'files',
    'values' => ['/var/www/file1.txt', '/var/www/file2.txt']
]));

$files = $variadics->get('files');
foreach ($files as $file) {
    echo "Traitement de $file\n";
}
```

### Cas 2 : Vérification de valeurs multiples

```php
if ($variadics->has('files')) {
    $count = count($variadics->get('files'));
    echo "$count fichiers fournis";
}
```

---

## Gestion des erreurs

Aucune exception spécifique n'est levée par ces collections. Les méthodes `get()` retournent des valeurs par défaut (null, false, ou tableau vide) lorsque l'élément n'existe pas.

| Situation | Comportement |
|-----------|--------------|
| Argument non trouvé dans `ArgumentCollection` | Retourne `null` |
| Flag non trouvé dans `FlagCollection` | Retourne `false` |
| Énumération non trouvée dans `EnumCollection` | Retourne `null` |
| Variadic non trouvé dans `VariadicArgumentCollection` | Retourne `[]` |

## Performance

- Toutes les collections ont une complexité O(n) pour les recherches par nom
- `toAssociativeArray()` et `toFullArray()` créent un nouveau tableau à chaque appel
- `countAllValues()` parcourt tous les éléments une seule fois

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\SignatureParser\Collections\ArgumentCollection;
use AndyDefer\SignatureParser\Collections\EnumCollection;
use AndyDefer\SignatureParser\Collections\FlagCollection;
use AndyDefer\SignatureParser\Collections\VariadicArgumentCollection;
use AndyDefer\SignatureParser\Records\ArgumentRecord;
use AndyDefer\SignatureParser\Records\EnumRecord;
use AndyDefer\SignatureParser\Records\FlagRecord;
use AndyDefer\SignatureParser\Records\VariadicArgumentRecord;
use AndyDefer\SignatureParser\Enums\ValueState;

// Collection d'arguments requis
$requireds = new ArgumentCollection();
$requireds->add(ArgumentRecord::from(['name' => 'source', 'value' => '/var/www']));
$requireds->add(ArgumentRecord::from(['name' => 'destination', 'value' => '/backup']));

// Collection d'arguments par défaut
$defaults = new ArgumentCollection();
$defaults->add(ArgumentRecord::from(['name' => 'format', 'value' => 'zip']));

// Collection d'énumérations
$enums = new EnumCollection();
$enums->add(EnumRecord::from([
    'name' => 'level',
    'value' => 'high',
    'allowed_values' => ['low', 'medium', 'high'],
    'default_value' => 'medium',
    'value_state' => ValueState::DEFAULTED
]));

// Collection de variadics
$variadics = new VariadicArgumentCollection();
$variadics->add(VariadicArgumentRecord::from([
    'name' => 'files',
    'values' => ['file1.txt', 'file2.txt']
]));

// Collection de flags
$flags = new FlagCollection();
$flags->add(FlagRecord::from(['name' => 'force', 'value' => true]));
$flags->add(FlagRecord::from(['name' => 'verbose', 'value' => false]));

// Accès aux données
echo "Source: " . $requireds->get('source') . "\n";
echo "Format: " . $defaults->get('format') . "\n";
echo "Niveau: " . $enums->get('level') . "\n";
echo "Fichiers: " . implode(', ', $variadics->get('files')) . "\n";
echo "Force: " . ($flags->isActive('force') ? 'Oui' : 'Non') . "\n";

// Vérifications
if ($enums->isAllowed('level', 'medium')) {
    echo "Niveau 'medium' autorisé\n";
}

if ($variadics->has('files')) {
    echo "Nombre de fichiers: " . count($variadics->get('files')) . "\n";
}
```

## Voir aussi

- `ArgumentRecord` - Enregistrement des arguments
- `EnumRecord` - Enregistrement des énumérations
- `FlagRecord` - Enregistrement des flags
- `VariadicArgumentRecord` - Enregistrement des variadics
- `ValueState` - États des énumérations