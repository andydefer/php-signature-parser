# Parseurs de Signatures - Référence Technique

## Description

Collection de parseurs spécialisés qui implémentent le pattern **Chaîne de responsabilité** (Chain of Responsibility) pour extraire les composants d'une commande CLI.

## Hiérarchie / Implémentations

```
ParserInterface
    ├── SourceParser
    ├── RequiredParser
    ├── DefaultParser
    ├── VariadicParser
    ├── FlagParser
    └── CustomTagParser
```

## Rôle principal

Chaque parseur est responsable de l'extraction d'un type spécifique de composant :

| Parser | Composant extrait | Syntaxe |
|--------|-------------------|---------|
| `SourceParser` | Nom de la commande | `command` |
| `RequiredParser` | Arguments requis | `{name}` |
| `DefaultParser` | Arguments par défaut | `{name=value}`, `{name=?}` |
| `VariadicParser` | Arguments variadiques | `{name*}` |
| `FlagParser` | Flags | `{--flag}` |
| `CustomTagParser` | Tags personnalisés | `<key="value">` |

---

## SourceParser

### Description

Extrait le nom de la commande (source) du premier élément de la signature et de la requête.

### API

#### `parse(array $signature, array $query): ParsedResultRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `array<int, string>` | Tokens de la signature |
| `$query` | `array<int, string>` | Tokens de la requête |

**Retourne :** `ParsedResultRecord` - Contient `source` dans `data`

**Exemple :**
```php
$parser = new SourceParser();
$result = $parser->parse(['greet', '{name}'], ['greet', 'John']);
// $result->data->source = 'greet'
// $result->signature = ['{name}']
// $result->query = ['John']
```

---

## RequiredParser

### Description

Extrait les arguments requis. Ce sont des tokens sans `=`, `*`, `?` ou `--`.

### API

#### `parse(array $signature, array $query): ParsedResultRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `array<int, string>` | Tokens de la signature |
| `$query` | `array<int, string>` | Tokens de la requête |

**Retourne :** `ParsedResultRecord` - Contient `required` dans `data`

**Exemple :**
```php
$parser = new RequiredParser();
$result = $parser->parse(['{source}', '{destination}'], ['/var/www', '/backup']);
// $result->data->required = ['source' => '/var/www', 'destination' => '/backup']
```

#### `validate(array $signature, array $query): ValidationResultRecord`

**Retourne :** `ValidationResultRecord` - Erreurs si des arguments requis sont manquants

---

## DefaultParser

### Description

Extrait les arguments avec valeurs par défaut `{name=value}` et les nullables `{name=?}`.

### API

#### `parse(array $signature, array $query): ParsedResultRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `array<int, string>` | Tokens de la signature |
| `$query` | `array<int, string>` | Tokens de la requête |

**Retourne :** `ParsedResultRecord` - Contient `default` dans `data`

**Exemple :**
```php
$parser = new DefaultParser();
$result = $parser->parse(['{format=zip}', '{env=?}'], ['tar.gz', 'staging']);
// $result->data->default = ['format' => 'tar.gz', 'env' => 'staging']
```

#### `validate(array $signature, array $query): ValidationResultRecord`

**Exceptions :** `InvalidArgumentException` - Si la syntaxe est invalide (`{name=}`)

---

## VariadicParser

### Description

Extrait les arguments variadiques `{name*}` qui capturent plusieurs valeurs.

### API

#### `parse(array $signature, array $query): ParsedResultRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `array<int, string>` | Tokens de la signature |
| `$query` | `array<int, string>` | Tokens de la requête |

**Retourne :** `ParsedResultRecord` - Contient `variadic` dans `data`

**Exemple :**
```php
$parser = new VariadicParser();
$result = $parser->parse(['{files*}'], ['[file1.txt, file2.txt]']);
// $result->data->variadic = ['files' => ['file1.txt', 'file2.txt']]
```

#### `validate(array $signature, array $query): ValidationResultRecord`

---

## FlagParser

### Description

Extrait les flags booléens `{--flag}`.

### API

#### `parse(array $signature, array $query): ParsedResultRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `array<int, string>` | Tokens de la signature |
| `$query` | `array<int, string>` | Tokens de la requête |

**Retourne :** `ParsedResultRecord` - Contient `flags` dans `data`

**Exemple :**
```php
$parser = new FlagParser();
$result = $parser->parse(['{--force}', '{--verbose}'], ['--force']);
// $result->data->flags = ['force' => true, 'verbose' => false]
```

#### `validate(array $signature, array $query): ValidationResultRecord`

---

## CustomTagParser

### Description

Extrait les tags personnalisés au format `<key="value">`. Ces tags sont retirés de la requête pour les parseurs suivants.

### API

#### `parse(array $signature, array $query): ParsedResultRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `array<int, string>` | Tokens de la signature |
| `$query` | `array<int, string>` | Tokens de la requête |

**Retourne :** `ParsedResultRecord` - Contient les tags dans `data`

**Exemple :**
```php
$parser = new CustomTagParser();
$result = $parser->parse(
    ['send', '{recipient}'],
    ['send', 'John', '<greeting="Hello">']
);
// $result->data = ['greeting' => 'Hello']
// $result->query = ['send', 'John']
```

#### `validate(array $signature, array $query): ValidationResultRecord`

---

## Cas d'utilisation

### Cas 1 : Parsing complet d'une commande

```php
<?php

use AndyDefer\SignatureParser\Parsers\SourceParser;
use AndyDefer\SignatureParser\Parsers\RequiredParser;
use AndyDefer\SignatureParser\Parsers\DefaultParser;
use AndyDefer\SignatureParser\Parsers\VariadicParser;
use AndyDefer\SignatureParser\Parsers\FlagParser;

$signature = ['deploy', '{environment}', '{version}', '{--force}', '{--verbose}'];
$query = ['deploy', 'staging', '1.2.3', '--force'];

// Exécution en chaîne
$source = (new SourceParser())->parse($signature, $query);
$required = (new RequiredParser())->parse($source->signature, $source->query);
$default = (new DefaultParser())->parse($required->signature, $required->query);
$flag = (new FlagParser())->parse($default->signature, $default->query);

// Résultat
$data = $flag->data->toArray();
// ['source' => 'deploy', 'required' => ['environment' => 'staging', 'version' => '1.2.3'], 'flags' => ['force' => true, 'verbose' => false]]
```

### Cas 2 : Validation d'une requête

```php
<?php

$parser = new RequiredParser();
$result = $parser->validate(['{source}', '{destination}'], ['/var/www']);

if (!$result->isValid) {
    foreach ($result->errors as $error) {
        echo "❌ $error\n";
    }
}
// ❌ Missing required argument: 'destination'
```

---

## Flux d'exécution

```
SignatureParser::parse()
    ↓
SourceParser::parse()
    ├── Extrait le premier token comme source
    └── Retourne signature et query sans la source
    ↓
RequiredParser::parse()
    ├── Extrait les tokens sans '=', '*', '?', '--'
    └── Retourne signature et query sans les requis
    ↓
DefaultParser::parse()
    ├── Extrait les tokens avec '=' ou '?'
    └── Retourne signature et query sans les defaults
    ↓
VariadicParser::parse()
    ├── Extrait les tokens avec '*'
    └── Retourne signature et query sans les variadiques
    ↓
FlagParser::parse()
    ├── Extrait les tokens avec '--'
    └── Retourne signature et query sans les flags
    ↓
CustomTagParser::parse()
    ├── Extrait les tokens <key="value">
    └── Retourne signature et query sans les tags
```

---

## Gestion des erreurs

| Parser | Situation | Message |
|--------|-----------|---------|
| `RequiredParser` | Argument requis manquant | `Missing required argument: '{name}'` |
| `DefaultParser` | Syntaxe invalide | `Default argument '{name}' has empty value` |
| `DefaultParser` | Nullable invalide | `Invalid syntax '{element}'. Use '{name}=?'` |
| `VariadicParser` | Variadique sans signature | `Variadic argument provided but not defined` |
| `VariadicParser` | Valeur vide | `Empty value in variadic argument` |
| `FlagParser` | Flag inconnu | `Unknown flag: '{flag}'` |
| `FlagParser` | Flag dupliqué | `Duplicate flag: '{flag}'` |
| `CustomTagParser` | Tag non fermé | `Unclosed custom tag` |
| `CustomTagParser` | Syntaxe invalide | `Invalid custom tag syntax: <{tag}>` |

---

## Intégration

### Avec SignatureParser

```php
$parser = new SignatureParser();
// Les parseurs sont automatiquement ajoutés dans l'ordre
```

### Ajout de parseurs personnalisés

```php
$parser->addParser(new CustomTagParser());
```

### Suppression de parseurs

```php
$parser->removeParser(FlagParser::class);
```

---

## Performance

| Parser | Complexité | Détails |
|--------|------------|---------|
| `SourceParser` | O(1) | Extraction du premier token |
| `RequiredParser` | O(n) | Parcours des tokens |
| `DefaultParser` | O(n) | Parcours des tokens |
| `VariadicParser` | O(n) | Parcours des tokens |
| `FlagParser` | O(n) | Parcours des tokens |
| `CustomTagParser` | O(n) | Parcours des tokens |

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.4 | ✅ Complet |
| PHP 8.3 | ✅ Complet |
| PHP 8.2 | ✅ Complet |
| PHP 8.1 | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\SignatureParser\Parsers\SourceParser;
use AndyDefer\SignatureParser\Parsers\RequiredParser;
use AndyDefer\SignatureParser\Parsers\DefaultParser;
use AndyDefer\SignatureParser\Parsers\VariadicParser;
use AndyDefer\SignatureParser\Parsers\FlagParser;
use AndyDefer\SignatureParser\Parsers\Customs\CustomTagParser;

// 1. Configuration
$signature = ['deploy', '{environment}', '{version=latest}', '{files*}', '{--force}', '{--verbose}'];
$query = ['deploy', 'staging', '1.2.3', '[config.yaml, secrets.json]', '--force', '<user="admin">'];

// 2. Exécution en chaîne
$source = (new SourceParser())->parse($signature, $query);
$required = (new RequiredParser())->parse($source->signature, $source->query);
$default = (new DefaultParser())->parse($required->signature, $required->query);
$variadic = (new VariadicParser())->parse($default->signature, $default->query);
$flag = (new FlagParser())->parse($variadic->signature, $variadic->query);
$custom = (new CustomTagParser())->parse($flag->signature, $flag->query);

// 3. Résultat
$data = $custom->data->toArray();

echo "=== Résultat du parsing ===\n";
echo "Source: " . ($data['source'] ?? '') . "\n";
echo "Environnement: " . ($data['required']['environment'] ?? '') . "\n";
echo "Version: " . ($data['default']['version'] ?? '') . "\n";
echo "Fichiers: " . implode(', ', $data['variadic']['files'] ?? []) . "\n";
echo "Force: " . (($data['flags']['force'] ?? false) ? 'true' : 'false') . "\n";
echo "Verbose: " . (($data['flags']['verbose'] ?? false) ? 'true' : 'false') . "\n";
echo "User: " . ($data['user'] ?? '') . "\n";
```

## Voir aussi

- `SignatureParser` - Parser principal
- `SignatureParserInterface` - Interface du parser
- `ParserInterface` - Interface des parseurs
- `ParsedResultRecord` - Résultat intermédiaire
- `ValidationResultRecord` - Résultat de validation