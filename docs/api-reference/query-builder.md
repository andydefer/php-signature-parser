# QueryBuilder - Référence Technique

## Description

Le `QueryBuilder` est un constructeur de requêtes CLI qui permet de construire des requêtes valides à partir d'une signature, avec validation automatique des arguments. Il gère tous les types d'arguments : requis, par défaut, nullables, variadics, enums, flags et tags personnalisés.

## Rôle principal

- Construire des requêtes CLI valides à partir d'une signature
- Valider automatiquement les valeurs par rapport à la signature
- Gérer les valeurs par défaut et les placeholders (`~`)
- Fournir une API fluide pour définir chaque type d'argument

---

## API / Méthodes publiques

### `init(string $signature, ?string $initialQuery = null): self`

Initialise un nouveau QueryBuilder avec une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la commande |
| `$initialQuery` | `string|null` | Requête initiale optionnelle pour peupler le builder |

**Retourne :** `self` - Instance du builder

**Exceptions :** `InvalidArgumentException` - Si la signature est invalide

**Exemple :**
```php
$builder = QueryBuilder::init('backup {source} {destination} {--force}');
$builder = QueryBuilder::init('backup {source}', 'backup /var/www');
```

---

### `setArgument(string $name, ?string $value = null): self`

Définit un argument avec détection automatique du type.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |
| `$value` | `string|null` | Valeur (null réinitialise à la valeur par défaut) |

**Retourne :** `self` - Instance du builder

**Exceptions :** `InvalidArgumentException` - Si l'argument n'existe pas

**Exemple :**
```php
$builder->setArgument('source', '/var/www');
```

---

### `setRequired(string $name, string $value): self`

Définit un argument requis.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |
| `$value` | `string` | Valeur |

**Retourne :** `self` - Instance du builder

**Exceptions :** `InvalidArgumentException` - Si l'argument n'est pas requis ou si la valeur est vide

**Exemple :**
```php
$builder->setRequired('source', '/var/www');
```

---

### `setDefault(string $name, ?string $value = null): self`

Définit un argument par défaut.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |
| `$value` | `string|null` | Valeur (null utilise la valeur par défaut) |

**Retourne :** `self` - Instance du builder

**Exceptions :** `InvalidArgumentException` - Si l'argument n'est pas un argument par défaut

**Exemple :**
```php
$builder->setDefault('format', 'zip');
$builder->setDefault('output', null); // Utilise la valeur par défaut
```

---

### `setVariadic(string $name, string|array $value): self`

Définit un argument variadique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |
| `$value` | `string|array<string>` | Valeur (chaîne ou tableau) |

**Retourne :** `self` - Instance du builder

**Exceptions :** `InvalidArgumentException` - Si l'argument n'est pas variadique

**Exemple :**
```php
$builder->setVariadic('files', 'file1.txt, file2.txt');
$builder->setVariadic('files', ['file1.txt', 'file2.txt']);
```

---

### `setEnum(string $name, ?string $value = null): self`

Définit une énumération.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'énumération |
| `$value` | `string|null` | Valeur (null utilise la valeur par défaut) |

**Retourne :** `self` - Instance du builder

**Exceptions :** `InvalidArgumentException` - Si l'énumération n'existe pas ou si la valeur n'est pas autorisée

**Exemple :**
```php
$builder->setEnum('level', 'high');
$builder->setEnum('level', null); // Utilise la valeur par défaut
```

---

### `setFlag(string $name, bool $active = true): self`

Définit un flag.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag avec '--' |
| `$active` | `bool` | État du flag |

**Retourne :** `self` - Instance du builder

**Exceptions :** `InvalidArgumentException` - Si le flag n'existe pas

**Exemple :**
```php
$builder->setFlag('--force', true);
$builder->setFlag('--verbose');
```

---

### `toggleFlag(string $name): self`

Inverse l'état d'un flag.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag avec '--' |

**Retourne :** `self` - Instance du builder

**Exceptions :** `InvalidArgumentException` - Si le flag n'existe pas

**Exemple :**
```php
$builder->toggleFlag('--verbose'); // Inverse l'état
```

---

### `hasFlag(string $name): bool`

Vérifie si un flag est actif.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag avec '--' |

**Retourne :** `bool` - `true` si actif, `false` sinon

**Exemple :**
```php
if ($builder->hasFlag('--force')) {
    echo "Mode force activé";
}
```

---

### `setCustom(string $key, string $value): self`

Définit un tag personnalisé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du tag |
| `$value` | `string` | Valeur du tag |

**Retourne :** `self` - Instance du builder

**Exemple :**
```php
$builder->setCustom('user', 'admin');
$builder->setCustom('greeting', 'Hello World');
```

---

### `setCustoms(array $tags): self`

Définit plusieurs tags personnalisés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$tags` | `array<string, string>` | Tableau [clé => valeur] |

**Retourne :** `self` - Instance du builder

**Exemple :**
```php
$builder->setCustoms([
    'user' => 'admin',
    'greeting' => 'Hello World'
]);
```

---

### `removeCustom(string $key): self`

Supprime un tag personnalisé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du tag |

**Retourne :** `self` - Instance du builder

**Exemple :**
```php
$builder->removeCustom('greeting');
```

---

### `getCustom(string $key): ?string`

Récupère la valeur d'un tag personnalisé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du tag |

**Retourne :** `string|null` - La valeur ou `null`

---

### `getCustoms(): array`

Récupère tous les tags personnalisés.

**Retourne :** `array<string, string>` - Tableau [clé => valeur]

---

### `getArgument(string $name): ?string`

Récupère la valeur d'un argument.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `string|null` - La valeur ou `null`

---

### `getArguments(): array`

Récupère tous les arguments.

**Retourne :** `array<string, string>` - Tableau [nom => valeur]

---

### `getFlags(): array`

Récupère tous les flags.

**Retourne :** `array<string, bool>` - Tableau [nom => booléen]

---

### `getEnum(string $name): ?string`

Récupère la valeur d'une énumération.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'énumération |

**Retourne :** `string|null` - La valeur ou `null`

---

### `getEnums(): array`

Récupère toutes les énumérations.

**Retourne :** `array<string, string>` - Tableau [nom => valeur]

---

### `reset(): self`

Réinitialise tous les arguments à leurs valeurs par défaut.

**Retourne :** `self` - Instance du builder

**Exemple :**
```php
$builder->reset();
```

---

### `validate(): ValidationResultRecord`

Valide la requête actuelle par rapport à la signature.

**Retourne :** `ValidationResultRecord` - Résultat de la validation

**Exemple :**
```php
$result = $builder->validate();
if (!$result->isValid) {
    // Gérer les erreurs
}
```

---

### `isValid(): bool`

Vérifie si la requête actuelle est valide.

**Retourne :** `bool` - `true` si valide, `false` sinon

---

### `getErrors(): StringTypedCollection`

Récupère les erreurs de validation.

**Retourne :** `StringTypedCollection` - Collection des messages d'erreur

---

### `build(): string`

Construit la requête finale.

**Retourne :** `string` - La requête construite

**Exceptions :** `InvalidArgumentException` - Si la requête est invalide

**Exemple :**
```php
$query = $builder->build();
// 'backup /var/www /backup tar.gz --force'
```

---

### `getStructure(): SignatureStructureVO`

Récupère la structure de la signature.

**Retourne :** `SignatureStructureVO` - Structure de la signature

---

### `getSource(): string`

Récupère le nom de la commande.

**Retourne :** `string` - Nom de la commande

---

## Cas d'utilisation

### Cas 1 : Construction simple

```php
$builder = QueryBuilder::init('backup {source} {destination} {format=zip} {--force}');
$query = $builder->setRequired('source', '/var/www')
                 ->setRequired('destination', '/backup')
                 ->setDefault('format', 'tar.gz')
                 ->setFlag('--force', true)
                 ->build();
// Résultat: 'backup /var/www /backup tar.gz --force'
```

### Cas 2 : Avec énumérations

```php
$builder = QueryBuilder::init('set-level ::level->[beginner,middle,master]=middle {--verbose}');
$query = $builder->setEnum('level', 'master')
                 ->setFlag('--verbose', true)
                 ->build();
// Résultat: 'set-level master --verbose'
```

### Cas 3 : Avec tags personnalisés

```php
$builder = QueryBuilder::init('send {recipient} {--verbose}');
$query = $builder->setRequired('recipient', 'John')
                 ->setFlag('--verbose', true)
                 ->setCustom('greeting', 'Hello World')
                 ->setCustom('later', 'goodbye')
                 ->build();
// Résultat: 'send John --verbose <greeting="Hello World"> <later="goodbye">'
```

### Cas 4 : Validation avant construction

```php
$builder = QueryBuilder::init('backup {source} {destination} {--force}');
$builder->setRequired('source', '/var/www');

if (!$builder->isValid()) {
    $errors = $builder->getErrors();
    foreach ($errors as $error) {
        echo "Erreur: $error\n";
    }
}

$query = $builder->build();
```

---

## Flux d'exécution

```
Signature + Query initiale (optionnelle)
        ↓
SignatureStructureVO (validation)
        ↓
Initialisation des valeurs par défaut
        ↓
parseInitialQuery() (si fournie)
        ↓
┌─────────────────────────────────────────────────┐
│ API fluide : setArgument/setRequired/etc.     │
└─────────────────────────────────────────────────┘
        ↓
build() / validate()
        ↓
buildQueryString()
        ↓
Validation finale
        ↓
Query string
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Signature invalide | `InvalidArgumentException` | `Invalid signature: ...` |
| Argument inexistant | `InvalidArgumentException` | `Argument "{$name}" does not exist in signature` |
| Requis null ou vide | `InvalidArgumentException` | `Required argument "{$name}" cannot be null or empty` |
| Flag inexistant | `InvalidArgumentException` | `Flag "{$name}" does not exist in signature` |
| Enum inexistant | `InvalidArgumentException` | `Enum "{$name}" does not exist in signature` |
| Valeur d'enum invalide | `InvalidArgumentException` | `Invalid value "{$value}" for enum "{$name}"` |
| Requis enum null | `InvalidArgumentException` | `Required enum "{$name}" cannot be null` |
| Requête invalide | `InvalidArgumentException` | `Invalid query: ...` |

## Performance

- La validation est effectuée une seule fois (lazy)
- Les données sont stockées en mémoire dans des tableaux simples
- `build()` effectue une validation complète

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\SignatureParser\QueryBuilder;

$builder = QueryBuilder::init(
    'backup {source} {destination} {format=zip} {output=?} ::level->[low,medium,high]=medium {excludes*} {--force} {--verbose}'
);

$query = $builder
    ->setRequired('source', '/var/www')
    ->setRequired('destination', '/backup')
    ->setDefault('format', 'tar.gz')
    ->setDefault('output', null)
    ->setEnum('level', 'high')
    ->setVariadic('excludes', ['cache', 'logs', 'tmp'])
    ->setFlag('--force', true)
    ->setFlag('--verbose', true)
    ->setCustom('user', 'admin')
    ->build();

echo $query;
// backup /var/www /backup tar.gz ~ high [cache, logs, tmp] --force --verbose <user="admin">
```

## Voir aussi

- `SignatureStructureVO` - Structure de signature
- `SignatureParser` - Parser principal
- `ValidationResultRecord` - Résultat de validation