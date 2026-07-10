# QueryBuilder - Référence Technique

## Description

Constructeur de requêtes CLI qui permet de construire dynamiquement des chaînes de requête à partir d'une signature, avec validation automatique des types.

## Hiérarchie / Implémentations

```
QueryBuilder
    ├── SignatureStructureVO (structure)
    └── SignatureParser (validation)
```

## Rôle principal

`QueryBuilder` est un assistant pour la construction programmatique de requêtes CLI. Il permet de :

- Construire une requête à partir d'une signature
- Définir des arguments requis, par défaut, nullables et variadiques
- Accepter des tableaux ou des chaînes pour les arguments variadiques
- Activer/désactiver des flags
- Ajouter des tags personnalisés `<key="value">`
- Valider automatiquement la requête avant construction
- Parser une requête initiale pour la modifier

## Installation

```bash
composer require andydefer/php-signature-parser
```

### Dépendances

- `SignatureStructureVO` - Structure de la signature
- `SignatureParser` - Parser pour la validation
- `ValidationResultRecord` - Résultat de validation
- `StringTypedCollection` - Collection typée de chaînes
- PHP 8.1+

## API / Méthodes publiques

### `static init(string $signature, ?string $initialQuery = null): self`

Initialise un nouveau QueryBuilder avec une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature CLI (ex: `'greet {name} {--formal}'`) |
| `$initialQuery` | `string|null` | Requête initiale optionnelle |

**Retourne :** `self` - Instance du builder

**Exceptions :** `InvalidArgumentException` - Si la signature est invalide

**Exemple :**
```php
$builder = QueryBuilder::init('greet {name} {--formal}');
```

---

### `setArgument(string $name, ?string $value = null): self`

Définit un argument avec détection automatique du type.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |
| `$value` | `string|null` | Valeur (null = valeur par défaut) |

**Retourne :** `self` - Instance fluide

**Exceptions :** `InvalidArgumentException` - Si l'argument n'existe pas

**Exemple :**
```php
$builder->setArgument('name', 'John');
$builder->setArgument('age', null); // Utilise la valeur par défaut
```

---

### `setRequired(string $name, string $value): self`

Définit un argument requis.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |
| `$value` | `string` | Valeur |

**Retourne :** `self` - Instance fluide

**Exceptions :** `InvalidArgumentException` - Si l'argument n'est pas requis ou est vide

---

### `setDefault(string $name, ?string $value = null): self`

Définit un argument par défaut.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |
| `$value` | `string|null` | Valeur (null = valeur par défaut) |

**Retourne :** `self` - Instance fluide

**Exceptions :** `InvalidArgumentException` - Si l'argument n'est pas un argument par défaut

---

### `setVariadic(string $name, string|array $value): self`

Définit un argument variadique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |
| `$value` | `string|array<string>` | Valeurs (chaîne séparée par des virgules ou tableau) |

**Retourne :** `self` - Instance fluide

**Exceptions :** `InvalidArgumentException` - Si l'argument n'est pas variadique

**Exemple :**
```php
// Avec une chaîne
$builder->setVariadic('files', 'file1.txt, file2.txt, file3.txt');

// Avec un tableau
$builder->setVariadic('files', ['file1.txt', 'file2.txt', 'file3.txt']);
// Résultat: [file1.txt, file2.txt, file3.txt]
```

---

### `setFlag(string $name, bool $active = true): self`

Définit un flag.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag avec `--` (ex: `--verbose`) |
| `$active` | `bool` | État du flag |

**Retourne :** `self` - Instance fluide

**Exceptions :** `InvalidArgumentException` - Si le flag n'existe pas

---

### `toggleFlag(string $name): self`

Bascule l'état d'un flag.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag avec `--` |

**Retourne :** `self` - Instance fluide

---

### `hasFlag(string $name): bool`

Vérifie si un flag est actif.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag avec `--` |

**Retourne :** `bool` - `true` si le flag est actif

---

### `setCustom(string $key, string $value): self`

Définit un tag personnalisé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du tag |
| `$value` | `string` | Valeur du tag |

**Retourne :** `self` - Instance fluide

**Exemple :**
```php
$builder->setCustom('greeting', 'Hello World');
// Résultat: <greeting="Hello World">
```

---

### `setCustoms(array $tags): self`

Définit plusieurs tags personnalisés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$tags` | `array<string, string>` | Tableau associatif clé => valeur |

**Retourne :** `self` - Instance fluide

---

### `removeCustom(string $key): self`

Supprime un tag personnalisé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du tag à supprimer |

**Retourne :** `self` - Instance fluide

---

### `getCustom(string $key): ?string`

Récupère la valeur d'un tag personnalisé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du tag |

**Retourne :** `string|null` - Valeur ou `null` si non trouvé

---

### `getCustoms(): array`

Récupère tous les tags personnalisés.

**Retourne :** `array<string, string>` - Tableau associatif clé => valeur

---

### `getArgument(string $name): ?string`

Récupère la valeur d'un argument.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `string|null` - Valeur ou `null` si non défini

---

### `getArguments(): array`

Récupère tous les arguments.

**Retourne :** `array<string, string>` - Tableau associatif nom => valeur

---

### `getFlags(): array`

Récupère tous les flags.

**Retourne :** `array<string, bool>` - Tableau associatif nom => état

---

### `reset(): self`

Réinitialise tous les arguments, flags et tags à leurs valeurs par défaut.

**Retourne :** `self` - Instance fluide

---

### `validate(): ValidationResultRecord`

Valide la requête actuelle contre la signature.

**Retourne :** `ValidationResultRecord` - Résultat de la validation

---

### `isValid(): bool`

Vérifie si la requête actuelle est valide.

**Retourne :** `bool` - `true` si la requête est valide

---

### `getErrors(): StringTypedCollection`

Récupère les erreurs de validation.

**Retourne :** `StringTypedCollection` - Collection des messages d'erreur

---

### `build(): string`

Construit la requête finale.

**Retourne :** `string` - La requête construite

**Exceptions :** `InvalidArgumentException` - Si la requête est invalide

---

### `getStructure(): SignatureStructureVO`

Récupère la structure de la signature.

**Retourne :** `SignatureStructureVO` - Structure de la signature

---

### `getSource(): string`

Récupère le nom de la commande (source).

**Retourne :** `string` - Nom de la commande

---

## Cas d'utilisation

### Cas 1 : Construction simple d'une requête

```php
<?php

use AndyDefer\SignatureParser\QueryBuilder;

$builder = QueryBuilder::init('greet {name} {--formal}');
$builder->setRequired('name', 'John');
$builder->setFlag('--formal', true);

$query = $builder->build();
echo $query; // 'greet John --formal'
```

### Cas 2 : Arguments variadiques avec tableau

```php
<?php

$builder = QueryBuilder::init('process {files*} {--verbose}');
$builder->setVariadic('files', ['file1.txt', 'file2.txt', 'file3.txt']);
$builder->setFlag('--verbose', true);

$query = $builder->build();
echo $query; // 'process [file1.txt, file2.txt, file3.txt] --verbose'
```

### Cas 3 : Tags personnalisés

```php
<?php

$builder = QueryBuilder::init('send {recipient} {--verbose}');
$builder->setRequired('recipient', 'John');
$builder->setFlag('--verbose', true);
$builder->setCustom('greeting', 'Hello World');
$builder->setCustom('later', 'goodby');

$query = $builder->build();
echo $query; // 'send John --verbose <greeting="Hello World"> <later="goodby">'
```

### Cas 4 : Parsing d'une requête initiale

```php
<?php

$builder = QueryBuilder::init(
    'send {recipient} {--verbose}',
    'send John --verbose <greeting="Hello">'
);

echo $builder->getRequired('recipient'); // 'John'
echo $builder->getCustom('greeting'); // 'Hello'

$builder->setCustom('greeting', 'Hello World');
$query = $builder->build();
echo $query; // 'send John --verbose <greeting="Hello World">'
```

---

## Flux d'exécution

```
QueryBuilder::init($signature, $initialQuery)
    ↓
SignatureStructureVO($signature)
    ├── Validation de la signature
    └── Extraction des composants
    ↓
Initialisation des valeurs par défaut
    ├── Arguments par défaut
    └── Flags (inactifs)
    ↓
Si initialQuery ≠ null
    └── SignatureParser::parse()
        ├── Extraction des valeurs
        └── Remplissage du builder
    ↓
setRequired() / setDefault() / setVariadic() / setFlag() / setCustom()
    ├── Validation de l'existence
    ├── Mise à jour de la valeur
    └── Retour fluide
    ↓
build()
    ├── buildQueryString()
    │   ├── Source
    │   ├── Arguments requis
    │   ├── Arguments par défaut
    │   ├── Arguments variadiques ([value1, value2])
    │   ├── Flags actifs
    │   └── Tags personnalisés
    ├── SignatureParser::validate()
    └── Validation ou exception
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Signature invalide | `InvalidArgumentException` | `Invalid signature: {errors}` |
| Argument inexistant | `InvalidArgumentException` | `Argument "{name}" does not exist in signature` |
| Argument requis null/empty | `InvalidArgumentException` | `Required argument "{name}" cannot be null or empty` |
| Argument non requis (setRequired) | `InvalidArgumentException` | `Argument "{name}" is not a required argument` |
| Argument non défaut (setDefault) | `InvalidArgumentException` | `Argument "{name}" is not a default argument` |
| Argument non variadique | `InvalidArgumentException` | `Argument "{name}" is not a variadic argument` |
| Flag inexistant | `InvalidArgumentException` | `Flag "{name}" does not exist in signature` |
| Query invalide (build) | `InvalidArgumentException` | `Invalid query: {errors}` |

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `init()` | O(n) | n = tokens dans la signature |
| `setArgument()` | O(1) | Accès direct |
| `setVariadic()` | O(n) | n = nombre d'éléments dans le tableau |
| `setFlag()` | O(1) | Accès direct |
| `setCustom()` | O(1) | Accès direct |
| `build()` | O(n) | n = nombre d'éléments |
| `validate()` | O(n) | n = tokens dans la requête |

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

use AndyDefer\SignatureParser\QueryBuilder;

// 1. Initialisation
$builder = QueryBuilder::init(
    'deploy {environment} {version=?} {files*} {--force} {--verbose}'
);

// 2. Définition des valeurs avec différents types
$builder
    ->setRequired('environment', 'staging')
    ->setDefault('version', '1.2.3')
    ->setVariadic('files', ['config.yaml', 'secrets.json', 'deploy.sh'])
    ->setFlag('--force', true)
    ->setFlag('--verbose', false)
    ->setCustom('user', 'admin')
    ->setCustom('timestamp', '2026-07-10');

// 3. Validation
if (!$builder->isValid()) {
    echo "❌ Erreurs de validation:\n";
    foreach ($builder->getErrors() as $error) {
        echo "  - $error\n";
    }
    exit(1);
}

// 4. Construction
$query = $builder->build();
echo "Requête construite:\n";
echo $query . "\n";
// Result: 'deploy staging 1.2.3 [config.yaml, secrets.json, deploy.sh] --force <user="admin"> <timestamp="2026-07-10">'

// 5. Modification et reconstruction
$builder
    ->setRequired('environment', 'production')
    ->setVariadic('files', ['app.yaml', 'database.sql'])
    ->setFlag('--force', false);

$query = $builder->build();
echo "\nNouvelle requête:\n";
echo $query . "\n";
// 'deploy production 1.2.3 [app.yaml, database.sql] --verbose <user="admin"> <timestamp="2026-07-10">'

// 6. Reset
$builder->reset();
$query = $builder->build();
echo "\nRequête après reset:\n";
echo $query . "\n";
// 'deploy ~ ~ --verbose'
```

## Voir aussi

- `SignatureStructureVO` - Structure de la signature
- `SignatureParser` - Parser de signatures
- `SignatureVO` - Value Object signature/requête
- `ValidationResultRecord` - Résultat de validation
- `StringTypedCollection` - Collection typée de chaînes