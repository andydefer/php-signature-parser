# SignatureParser - Référence Technique

## Description

Parser principal des commandes CLI qui analyse les signatures et les requêtes pour en extraire une structure typée. Utilise le pattern **Chaîne de responsabilité** (Chain of Responsibility) avec des parseurs spécialisés.

## Hiérarchie / Implémentations

```
ParserRegistryInterface
    └── SignatureParser
SignatureParserInterface
    └── SignatureParser
```

**Interfaces implémentées :**
- `ParserRegistryInterface` - Gestion des parseurs
- `SignatureParserInterface` - Interface de parsing

## Rôle principal

`SignatureParser` est le point d'entrée central pour l'analyse des commandes CLI. Il permet de :

- Parser une signature et une requête en composants structurés
- Valider la syntaxe des signatures
- Valider la conformité des requêtes
- Extraire les éléments d'une signature ou d'une requête
- Gérer une chaîne de parseurs personnalisables
- Produire des enregistrements typés (Records)
- Supporter les tags personnalisés `<key="value">`

## Installation

```bash
composer require andydefer/php-signature-parser
```

### Dépendances

- `StringTypedCollection` - Collection typée de chaînes
- `StrictDataObject` - Structure de données immuable
- `ArgumentCollection` - Collection d'arguments
- `FlagCollection` - Collection de flags
- `VariadicArgumentCollection` - Collection d'arguments variadiques
- PHP 8.1+

## API / Méthodes publiques

### `__construct()`

Initialise le parser avec la chaîne de responsabilité par défaut.

**Retourne :** `void`

**Exemple :**
```php
$parser = new SignatureParser();
```

**Parseurs par défaut (dans l'ordre) :**
1. `SourceParser` - Nom de la commande
2. `RequiredParser` - Arguments requis
3. `DefaultParser` - Arguments par défaut
4. `VariadicParser` - Arguments variadiques
5. `FlagParser` - Flags
6. `CustomTagParser` - Tags personnalisés

---

### `addParser(ParserInterface $parser): self`

Ajoute un parseur personnalisé à la chaîne.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$parser` | `ParserInterface` | Parseur à ajouter |

**Retourne :** `self` - Instance fluide

**Exemple :**
```php
$parser->addParser(new CustomParser());
```

---

### `removeParser(string $parserClass): self`

Supprime un parseur de la chaîne.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$parserClass` | `string` | Nom de classe du parseur à supprimer |

**Retourne :** `self` - Instance fluide

**Exemple :**
```php
$parser->removeParser(FlagParser::class);
```

---

### `getParsers(): array`

Retourne tous les parseurs enregistrés.

**Retourne :** `array<ParserInterface>` - Liste des parseurs

---

### `parse(string $signature, string $query): ParsedSignatureRecord`

Parse une requête contre une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la commande |
| `$query` | `string` | Requête à parser |

**Retourne :** `ParsedSignatureRecord` - Résultat structuré du parsing

**Exceptions :** `InvalidArgumentException` - Si l'ordre de la signature est invalide

**Exemple :**
```php
$result = $parser->parse('greet {name} {--formal}', 'greet John --formal');

echo $result->source;                 // 'greet'
echo $result->required->first()->value; // 'John'
echo $result->flags->first()->value;   // true
```

---

### `validate(string $signature, string $query): ValidationResultRecord`

Valide une requête contre une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la commande |
| `$query` | `string` | Requête à valider |

**Retourne :** `ValidationResultRecord` - Résultat de la validation

**Exemple :**
```php
$result = $parser->validate('greet {name}', 'greet');

if (!$result->isValid) {
    foreach ($result->errors as $error) {
        echo "❌ $error\n";
    }
}
// ❌ Missing required argument: 'name'
```

---

### `validateSignature(string $signature): ValidationResultRecord`

Valide une signature seule (sans requête).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature à valider |

**Retourne :** `ValidationResultRecord` - Résultat de la validation

**Exemple :**
```php
$result = $parser->validateSignature('greet {name} {--formal}');
if ($result->isValid) {
    echo "✅ Signature valide\n";
}
```

---

### `isSignatureValid(string $signature): bool`

Vérifie si une signature est syntaxiquement valide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature à vérifier |

**Retourne :** `bool` - `true` si la signature est valide

---

### `isValid(string $signature, string $query): bool`

Vérifie si une requête est valide contre une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la commande |
| `$query` | `string` | Requête à vérifier |

**Retourne :** `bool` - `true` si la requête est valide

---

### `getValidationErrors(string $signature, string $query): StringTypedCollection`

Récupère les erreurs de validation pour une requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la commande |
| `$query` | `string` | Requête à valider |

**Retourne :** `StringTypedCollection` - Collection des messages d'erreur

---

### `extractSignatureElements(string $signature): StringTypedCollection`

Extrait les éléments individuels d'une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature à analyser |

**Retourne :** `StringTypedCollection` - Collection des éléments

**Exemple :**
```php
$elements = $parser->extractSignatureElements('greet {name} {--formal}');
// ['greet', 'name', '--formal']
```

---

### `extractQueryElements(string $query): StringTypedCollection`

Extrait les éléments individuels d'une requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Requête à analyser |

**Retourne :** `StringTypedCollection` - Collection des éléments

**Exemple :**
```php
$elements = $parser->extractQueryElements('greet John --formal');
// ['greet', 'John', '--formal']
```

---

## Cas d'utilisation

### Cas 1 : Parsing d'une commande simple

```php
<?php

use AndyDefer\SignatureParser\SignatureParser;

$parser = new SignatureParser();
$result = $parser->parse('greet {name} {--formal}', 'greet John --formal');

echo "Commande: " . $result->source . "\n";
echo "Nom: " . $result->required->first()->value . "\n";
echo "Formel: " . ($result->flags->first()->value ? 'Oui' : 'Non') . "\n";
```

### Cas 2 : Parsing avec tags personnalisés

```php
<?php

$result = $parser->parse(
    'send {recipient} {--verbose}',
    'send John --verbose <greeting="Hello World"> <later="goodby">'
);

echo "Destinataire: " . $result->required->first()->value . "\n";
echo "Verbose: " . ($result->flags->first()->value ? 'true' : 'false') . "\n";

$customData = $result->custom_data->toArray();
echo "Greeting: " . $customData['greeting'] . "\n";
echo "Later: " . $customData['later'] . "\n";
```

### Cas 3 : Validation avant exécution

```php
<?php

$parser = new SignatureParser();

$signature = 'backup {source} {destination} {format=zip} {--force}';

$queries = [
    'backup /var/www /backup tar.gz --force',  // ✅ Valide
    'backup /var/www /backup',                 // ✅ Valide (format par défaut)
    'backup /var/www',                         // ❌ Missing destination
];

foreach ($queries as $query) {
    $result = $parser->validate($signature, $query);
    echo ($result->isValid ? '✅' : '❌') . " $query\n";
}
```

### Cas 4 : Validation de signatures

```php
<?php

$parser = new SignatureParser();

$signatures = [
    'backup {source} {destination} {format=zip} {--force}',  // ✅ Valide
    'backup {format=zip} {source} {--force}',                // ❌ Ordre invalide
    'backup {source} {source} {--force}',                    // ❌ Doublon
];

foreach ($signatures as $signature) {
    $result = $parser->validateSignature($signature);
    echo ($result->isValid ? '✅' : '❌') . " $signature\n";
}
```

---

## Flux d'exécution

```
parse($signature, $query)
    ↓
extractSignatureElements($signature)
    ↓
extractQueryElements($query)
    ↓
validateSignatureOrder()
    ├── Valide l'ordre : source → required → default → variadic → flags
    └── Lance une exception si invalide
    ↓
Pour chaque parser dans la chaîne (dans l'ordre)
    ├── SourceParser → extrait le nom de la commande
    ├── RequiredParser → extrait les arguments requis
    ├── DefaultParser → extrait les arguments par défaut
    ├── VariadicParser → extrait les arguments variadiques
    ├── FlagParser → extrait les flags
    └── CustomTagParser → extrait les tags personnalisés
    ↓
buildRecord() → structure les résultats
    ↓
Retourner ParsedSignatureRecord
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Ordre invalide | `InvalidArgumentException` | `Invalid signature order: {error1}, {error2}` |
| Token invalide | Validation | `Invalid token syntax: '{token}'` |
| Doublon d'argument | Validation | `Duplicate argument name: '{name}'` |
| Argument requis manquant | Validation | `Missing required argument: '{name}'` |
| Flag inconnu | Validation | `Unknown flag: '{name}'` |
| Tag invalide | Validation | `Invalid custom tag syntax: <{tag}>` |
| Tag non fermé | Validation | `Unclosed custom tag` |

---

## Intégration

### Avec QueryBuilder

```php
$builder = QueryBuilder::init('greet {name} {--formal}');
$builder->setRequired('name', 'John');
$builder->setFlag('--formal', true);

$parser = new SignatureParser();
$result = $parser->parse('greet {name} {--formal}', $builder->build());
```

### Avec SignatureStructureVO

```php
$structure = new SignatureStructureVO('greet {name} {--formal}');
$parser = new SignatureParser();
$result = $parser->parse($structure->getRaw(), 'greet John --formal');
```

### Avec ValidationResultRecord

```php
$result = $parser->validate('greet {name}', 'greet');

if (!$result->isValid) {
    foreach ($result->errors as $error) {
        echo $error . "\n";
    }
    foreach ($result->suggestions as $suggestion) {
        echo $suggestion . "\n";
    }
}
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `parse()` | O(n × p) | n = tokens, p = nombre de parseurs |
| `validate()` | O(n × p) | n = tokens, p = nombre de parseurs |
| `validateSignature()` | O(n) | n = tokens dans la signature |
| `extractSignatureElements()` | O(n) | n = longueur de la signature |
| `extractQueryElements()` | O(n) | n = longueur de la requête |

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

use AndyDefer\SignatureParser\SignatureParser;

// 1. Initialisation
$parser = new SignatureParser();

// 2. Définition de la signature
$signature = 'deploy {environment} {version=?} {files*} {--force} {--verbose}';

// 3. Validation de la signature
$validation = $parser->validateSignature($signature);
if (!$validation->isValid) {
    echo "❌ Signature invalide:\n";
    foreach ($validation->errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}
echo "✅ Signature valide\n\n";

// 4. Parsing d'une requête avec tags personnalisés
$query = 'deploy staging --force [config.yaml, secrets.json] <user="admin"> <timestamp="2026-07-10">';

$result = $parser->parse($signature, $query);

echo "=== Résultat du parsing ===\n";
echo "Commande: " . $result->source . "\n";
echo "Environnement: " . $result->required->first()->value . "\n";
echo "Version: " . ($result->default->first()->value ?? 'non spécifiée') . "\n";

echo "Fichiers:\n";
foreach ($result->variadic->first()->values as $file) {
    echo "  - $file\n";
}

echo "Flags:\n";
echo "  Force: " . ($result->flags->first()->value ? '✅' : '❌') . "\n";
echo "  Verbose: " . ($result->flags->last()->value ? '✅' : '❌') . "\n";

$customData = $result->custom_data->toArray();
echo "Tags personnalisés:\n";
foreach ($customData as $key => $value) {
    echo "  - $key: $value\n";
}

// 5. Validation d'une requête invalide
$invalidQuery = 'deploy staging --force';
$validation = $parser->validate($signature, $invalidQuery);

if (!$validation->isValid) {
    echo "\n=== Validation de la requête invalide ===\n";
    echo "Requête: $invalidQuery\n";
    echo "Erreurs:\n";
    foreach ($validation->errors as $error) {
        echo "  - $error\n";
    }
}
```

## Voir aussi

- `SignatureStructureVO` - Analyse de la structure des signatures
- `QueryBuilder` - Construction dynamique de requêtes
- `ParsedSignatureRecord` - Résultat du parsing
- `ValidationResultRecord` - Résultat de la validation
- `ParserInterface` - Interface des parseurs personnalisés