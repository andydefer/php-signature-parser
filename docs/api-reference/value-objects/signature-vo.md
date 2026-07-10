# SignatureVO - Référence Technique

## Description

Value Object représentant un couple signature/requête de commande CLI complet. Fournit un accès typé à tous les composants parsés et valide la requête contre la signature.

## Hiérarchie / Implémentations

```
AbstractValueObject
    └── SignatureVO
```

## Rôle principal

`SignatureVO` est la version complète de l'analyse des commandes CLI. Il combine l'analyse de la signature **et** de la requête pour fournir :

- Le nom de la commande (source)
- Les arguments requis avec leurs valeurs
- Les arguments par défaut avec leurs valeurs
- Les arguments variadiques avec leurs valeurs
- Les flags avec leur état (actif/inactif)
- Les tags personnalisés `<key="value">`
- La validation de la requête contre la signature
- Des erreurs et suggestions de correction

## Installation

```bash
composer require andydefer/php-signature-parser
```

### Dépendances

- `AbstractValueObject` - Classe de base des Value Objects
- `StringTypedCollection` - Collection typée de chaînes
- `StrictDataObject` - Structure de données immuable
- `SignatureParser` - Parser de signatures
- `ValidationResultRecord` - Enregistrement des résultats de validation
- PHP 8.1+

## API / Méthodes publiques

### `__construct(string $signature, string $query)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la commande |
| `$query` | `string` | Requête à analyser |

**Retourne :** `void`

**Exceptions :** `InvalidArgumentException` - Si la signature ou la requête est vide

**Exemple :**
```php
$vo = new SignatureVO(
    'send {recipient} {--verbose}',
    'send John --verbose <greeting="Hello World">'
);
```

---

### `getSource(): string`

Retourne le nom de la commande.

**Retourne :** `string` - Nom de la commande

---

### `getRequired(string $name): ?string`

Retourne la valeur d'un argument requis.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `string|null` - Valeur ou `null` si non trouvé

---

### `getRequireds(): array`

Retourne tous les arguments requis.

**Retourne :** `array<string, string>` - Tableau associatif [nom => valeur]

---

### `getDefault(string $name): ?string`

Retourne la valeur d'un argument par défaut.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `string|null` - Valeur ou `null` si non trouvé

---

### `getDefaults(): array`

Retourne tous les arguments par défaut.

**Retourne :** `array<string, string|null>` - Tableau associatif [nom => valeur]

---

### `getVariadic(string $name): array`

Retourne les valeurs d'un argument variadique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `array<string>` - Liste des valeurs

---

### `getVariadics(): array`

Retourne tous les arguments variadiques.

**Retourne :** `array<string, array<string>>` - Tableau associatif [nom => valeurs]

---

### `getFlag(string $name): bool`

Retourne la valeur d'un flag.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag (sans `--`) |

**Retourne :** `bool` - `true` si le flag est actif

---

### `getFlags(): array`

Retourne tous les flags.

**Retourne :** `array<string, bool>` - Tableau associatif [nom => état]

---

### `getCustom(string $key): ?string`

Retourne la valeur d'un tag personnalisé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du tag |

**Retourne :** `string|null` - Valeur ou `null` si non trouvé

---

### `getCustoms(): array`

Retourne tous les tags personnalisés.

**Retourne :** `array<string, string>` - Tableau associatif [clé => valeur]

---

### `hasCustom(string $key): bool`

Vérifie si un tag personnalisé existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du tag |

**Retourne :** `bool` - `true` si le tag existe

---

### `hasCustoms(): bool`

Vérifie si des tags personnalisés existent.

**Retourne :** `bool` - `true` si des tags existent

---

### `getParsed(): StrictDataObject`

Retourne la structure parsée.

**Retourne :** `StrictDataObject` - Structure complète

---

### `hasFlag(string $name): bool`

Vérifie si un flag est présent et actif.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag |

**Retourne :** `bool` - `true` si le flag est actif

---

### `hasRequired(string $name): bool`

Vérifie si un argument requis existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool` - `true` si l'argument existe

---

### `hasDefault(string $name): bool`

Vérifie si un argument par défaut existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool` - `true` si l'argument existe

---

### `hasVariadic(string $name): bool`

Vérifie si un argument variadique existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool` - `true` si l'argument existe

---

### `isValid(): bool`

Retourne si la requête est valide contre la signature.

**Retourne :** `bool` - `true` si la requête est valide

---

### `getValidationErrors(): StringTypedCollection`

Retourne les erreurs de validation.

**Retourne :** `StringTypedCollection` - Collection des messages d'erreur

---

### `getValidationSuggestions(): StringTypedCollection`

Retourne des suggestions pour corriger les erreurs.

**Retourne :** `StringTypedCollection` - Collection des suggestions

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

### Cas 1 : Analyse d'une commande avec tags personnalisés

```php
<?php

use AndyDefer\SignatureParser\ValueObjects\SignatureVO;

$vo = new SignatureVO(
    'send {recipient} {--verbose}',
    'send John --verbose <greeting="Hello World"> <later="goodby">'
);

echo "Source: " . $vo->getSource() . "\n";
echo "Destinataire: " . $vo->getRequired('recipient') . "\n";
echo "Verbose: " . ($vo->getFlag('verbose') ? 'Oui' : 'Non') . "\n";
echo "Greeting: " . $vo->getCustom('greeting') . "\n";
echo "Later: " . $vo->getCustom('later') . "\n";

// Source: send
// Destinataire: John
// Verbose: Oui
// Greeting: Hello World
// Later: goodby
```

### Cas 2 : Validation d'une commande

```php
<?php

$vo = new SignatureVO(
    'backup {source} {destination} {--force}',
    'backup /var/www --force'
);

if (!$vo->isValid()) {
    echo "❌ Commande invalide:\n";
    foreach ($vo->getValidationErrors() as $error) {
        echo "  - $error\n";
    }
} else {
    echo "✅ Commande valide\n";
}
// ❌ Commande invalide:
//   - Missing required argument: 'destination'
```

### Cas 3 : Commande complexe avec tous les composants

```php
<?php

$signature = 'deploy {environment} {version=?} {--force} {--verbose}';
$query = 'deploy staging --force <user="admin"> <timestamp="2026-07-10">';

$vo = new SignatureVO($signature, $query);

echo "=== Déploiement ===\n";
echo "Environnement: " . $vo->getRequired('environment') . "\n";
echo "Version: " . ($vo->getDefault('version') ?? 'non spécifiée') . "\n";
echo "Force: " . ($vo->getFlag('force') ? '✅' : '❌') . "\n";
echo "Verbose: " . ($vo->getFlag('verbose') ? '✅' : '❌') . "\n";
echo "User: " . $vo->getCustom('user') . "\n";
echo "Timestamp: " . $vo->getCustom('timestamp') . "\n";
```

---

## Flux d'exécution

```
new SignatureVO($signature, $query)
    ↓
Valider que signature et query ne sont pas vides
    ↓
SignatureParser::parse($signature, $query)
    ↓
Extraire les composants
    ├── Source
    ├── Required arguments
    ├── Default arguments
    ├── Variadic arguments
    ├── Flags
    └── Custom tags
    ↓
SignatureParser::validate($signature, $query)
    ↓
Retourner SignatureVO
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Signature vide | `InvalidArgumentException` | `Signature cannot be empty` |
| Query vide | `InvalidArgumentException` | `Query cannot be empty` |
| Argument requis manquant | Validation | `Missing required argument: '{name}'` |
| Flag inconnu | Validation | `Unknown flag: '{flag}'` |
| Tag invalide | Validation | `Invalid custom tag syntax: <{tag}>` |
| Tag non fermé | Validation | `Unclosed custom tag` |
| Variadique sans signature | Validation | `Variadic argument provided but not defined` |

---

## Intégration

### Avec SignatureParser

```php
$parser = new SignatureParser();
$result = $parser->parse('greet {name}', 'greet John');
$vo = new SignatureVO('greet {name}', 'greet John');
```

### Avec QueryBuilder

```php
$builder = QueryBuilder::init('greet {name} {--formal}');
$builder->setRequired('name', 'John');
$builder->setFlag('--formal', true);
$query = $builder->build();

$vo = new SignatureVO('greet {name} {--formal}', $query);
```

### Avec ValidationResultRecord

```php
$vo = new SignatureVO('greet {name}', 'greet');
$result = $vo->getValidationResult();

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
| `__construct()` | O(n) | n = nombre de tokens |
| `getSource()` | O(1) | Accès direct |
| `getRequired()` | O(1) | Accès tableau |
| `getRequireds()` | O(1) | Accès direct |
| `getCustom()` | O(1) | Accès tableau |
| `getCustoms()` | O(1) | Accès direct |
| `isValid()` | O(1) | Accès à la propriété |

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

use AndyDefer\SignatureParser\ValueObjects\SignatureVO;

// 1. Création
$signature = 'deploy {environment} {version=?} {files*} {--force} {--verbose}';
$query = 'deploy staging --force [config.yaml, secrets.json] <user="admin"> <timestamp="2026-07-10">';

$vo = new SignatureVO($signature, $query);

// 2. Validation
echo "=== Validation ===\n";
if ($vo->isValid()) {
    echo "✅ Commande valide\n\n";
} else {
    echo "❌ Commande invalide:\n";
    foreach ($vo->getValidationErrors() as $error) {
        echo "  - $error\n";
    }
    echo "\nSuggestions:\n";
    foreach ($vo->getValidationSuggestions() as $suggestion) {
        echo "  - $suggestion\n";
    }
    exit(1);
}

// 3. Affichage des composants
echo "=== Composants de la commande ===\n";
echo "Source: " . $vo->getSource() . "\n\n";

echo "Arguments requis:\n";
foreach ($vo->getRequireds() as $name => $value) {
    echo "  - $name: $value\n";
}
echo "\n";

echo "Arguments par défaut:\n";
foreach ($vo->getDefaults() as $name => $value) {
    $display = $value ?? 'null';
    echo "  - $name: $display\n";
}
echo "\n";

echo "Arguments variadiques:\n";
foreach ($vo->getVariadics() as $name => $values) {
    echo "  - $name: " . implode(', ', $values) . "\n";
}
echo "\n";

echo "Flags:\n";
foreach ($vo->getFlags() as $name => $active) {
    echo "  - --$name: " . ($active ? '✅' : '❌') . "\n";
}
echo "\n";

echo "Tags personnalisés:\n";
foreach ($vo->getCustoms() as $key => $value) {
    echo "  - $key: $value\n";
}
echo "\n";

// 4. Vérifications spécifiques
echo "=== Vérifications ===\n";
$checks = ['environment', 'version', 'files', 'force', 'user'];

foreach ($checks as $name) {
    if ($vo->hasCustom($name)) {
        echo "Tag '$name': " . $vo->getCustom($name) . "\n";
    } elseif ($vo->hasRequired($name)) {
        echo "Argument requis '$name': " . $vo->getRequired($name) . "\n";
    } elseif ($vo->hasFlag($name)) {
        echo "Flag '$name': " . ($vo->getFlag($name) ? 'actif' : 'inactif') . "\n";
    }
}
```

## Voir aussi

- `SignatureStructureVO` - Analyse de la structure des signatures
- `SignatureParser` - Parser principal
- `QueryBuilder` - Construction dynamique de requêtes
- `ValidationResultRecord` - Résultat de validation
- `StrictDataObject` - Structure de données immuable