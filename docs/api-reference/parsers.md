# ParserInterface & Parsers - Référence Technique

## Description

Les parsers sont les composants responsables de l'extraction et de la validation des différents types d'arguments dans une signature de commande. Chaque parser implémente l'interface `ParserInterface` et suit le pattern **Chain of Responsibility**, où chaque parser traite un type spécifique d'argument dans un ordre défini.

## Hiérarchie / Implémentations

```
ParserInterface
    ├── SourceParser
    ├── RequiredParser
    ├── DefaultParser
    ├── EnumParser
    ├── VariadicParser
    ├── FlagParser
    └── Customs\CustomTagParser
```

## Rôle principal

Chaque parser est responsable d'un type d'argument spécifique :
- Extraction des données depuis la signature et la requête
- Validation de la syntaxe et des valeurs
- Nettoyage des tokens traités pour les parsers suivants

## Ordre d'exécution

```
SourceParser → RequiredParser → DefaultParser → EnumParser → VariadicParser → FlagParser → CustomTagParser
```

---

# ParserInterface

## Description

Interface définissant le contrat pour tous les parsers de la chaîne de responsabilité.

## API

### `parse(array $signature, array $query): ParsedResultRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `array<int, string>` | Tokens de la signature |
| `$query` | `array<int, string>` | Tokens de la requête |

**Retourne :** `ParsedResultRecord` - Contient les données extraites, la signature restante et la requête restante

### `validate(array $signature, array $query): ValidationResultRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `array<int, string>` | Tokens de la signature |
| `$query` | `array<int, string>` | Tokens de la requête |

**Retourne :** `ValidationResultRecord` - Contient les erreurs et suggestions

### `getTokenPattern(): string`

**Retourne :** `string` - Expression régulière pour valider les tokens

---

# SourceParser

## Description

Extrait le nom de la commande (source) depuis la signature et la requête. C'est le premier parser exécuté.

## Rôle principal

- Extrait le premier token de la signature comme nom de commande
- Retire ce token des tableaux pour les parsers suivants

## API

### `parse(array $signature, array $query): ParsedResultRecord`

**Exemple :**
```php
$parser = new SourceParser();
$result = $parser->parse(['backup', 'source'], ['backup', '/var/www']);
// $result->data->toArray() = ['source' => 'backup']
// $result->signature = ['source']
// $result->query = ['/var/www']
```

### `validate(array $signature, array $query): ValidationResultRecord`

| Situation | Message |
|-----------|---------|
| Signature vide | `Missing source (command name)` |
| Requête vide | `Missing query` |

## Cas d'utilisation

### Cas : Commande simple
```php
$sourceParser = new SourceParser();
$result = $sourceParser->parse(
    ['deploy', 'environment'],
    ['deploy', 'staging']
);
// $result->data->toArray() = ['source' => 'deploy']
```

---

# RequiredParser

## Description

Extrait les arguments requis de la signature. Un argument requis est un token simple (sans `=`, `*`, `?` ou `--`).

## Rôle principal

- Identifie les tokens simples dans la signature
- Associe chaque token à la valeur correspondante dans la requête
- Utilise `_` comme placeholder pour les valeurs manquantes

## API

### `parse(array $signature, array $query): ParsedResultRecord`

**Exemple :**
```php
$parser = new RequiredParser();
$result = $parser->parse(
    ['source', 'destination'],
    ['/var/www', '/backup']
);
// $result->data->toArray() = ['requireds' => ['source' => '/var/www', 'destination' => '/backup']]
```

## Cas d'utilisation

### Cas 1 : Arguments requis simples
```php
$parser = new RequiredParser();
$result = $parser->parse(['name', 'email'], ['John', 'john@example.com']);
// ['requireds' => ['name' => 'John', 'email' => 'john@example.com']]
```

### Cas 2 : Valeur manquante
```php
$result = $parser->parse(['name'], ['']);
// ['requireds' => ['name' => '']]
```

## Validation

| Situation | Message |
|-----------|---------|
| Argument requis manquant | `Missing required argument: '{$name}'` |

---

# DefaultParser

## Description

Extrait les arguments avec valeur par défaut et les arguments nullables.

## Rôle principal

- Traite les tokens avec `=` (valeur par défaut)
- Gère les nullables avec `{name=?}`
- Utilise `_` comme valeur nulle explicite

## API

### `parse(array $signature, array $query): ParsedResultRecord`

**Exemple :**
```php
$parser = new DefaultParser();
$result = $parser->parse(
    ['format=zip', 'output=?'],
    ['tar.gz']
);
// $result->data->toArray() = ['defaults' => ['format' => 'tar.gz', 'output' => null]]
```

## Cas d'utilisation

### Cas 1 : Valeur par défaut fournie
```php
$result = $parser->parse(['format=zip'], ['tar.gz']);
// ['defaults' => ['format' => 'tar.gz']]
```

### Cas 2 : Valeur par défaut utilisée
```php
$result = $parser->parse(['format=zip'], []);
// ['defaults' => ['format' => 'zip']]
```

### Cas 3 : Nullable avec `_`
```php
$result = $parser->parse(['output=?'], ['_']);
// ['defaults' => ['output' => null]]
```

## Validation

| Situation | Message |
|-----------|---------|
| Valeur par défaut vide | `Default argument '{$name}' has empty value` |
| Syntaxe nullable invalide | `Invalid syntax '{$name}?'` |

---

# EnumParser

## Description

Extrait les arguments de type énumération avec valeurs autorisées et états (requis, optionnel, défaut).

## Rôle principal

- Parse la syntaxe `::name->[value1,value2,value3]=state`
- Gère les trois états : `*` (requis), `?` (optionnel), `valeur` (défaut)
- Valide les valeurs autorisées et la valeur par défaut

## Syntaxe

| Syntaxe | État | Description |
|---------|------|-------------|
| `::name->[values]=*` | REQUIRED | Doit être fourni |
| `::name->[values]=?` | OPTIONAL | Peut être `_` |
| `::name->[values]=default` | DEFAULTED | Valeur par défaut |

## API

### `parse(array $signature, array $query): ParsedResultRecord`

**Exemple :**
```php
$parser = new EnumParser();
$result = $parser->parse(
    ['::level->[low,medium,high]=medium'],
    ['high']
);
// $result->data->toArray() = [
//     'enums' => [
//         'level' => [
//             'value' => 'high',
//             'allowed_values' => ['low', 'medium', 'high'],
//             'default_value' => 'medium',
//             'value_state' => ValueState::DEFAULTED
//         ]
//     ]
// ]
```

## Cas d'utilisation

### Cas 1 : Enum avec valeur par défaut
```php
$result = $parser->parse(
    ['::level->[low,medium,high]=medium'],
    ['high']
);
// value = 'high'
```

### Cas 2 : Enum requis
```php
$result = $parser->parse(
    ['::level->[low,medium,high]=*'],
    ['medium']
);
// value = 'medium', value_state = REQUIRED
```

### Cas 3 : Enum optionnel avec `_`
```php
$result = $parser->parse(
    ['::level->[low,medium,high]=?'],
    ['_']
);
// value = null, value_state = OPTIONAL
```

## Validation

| Situation | Message |
|-----------|---------|
| Aucune valeur autorisée | `Enum '{$name}' has no allowed values` |
| Valeur par défaut invalide | `Default value '{$value}' is not in allowed values` |
| Valeur invalide | `Invalid value '{$value}' for enum '{$name}'` |
| `_` sur non-optional | `Cannot use '_' for non-optional enum '{$name}'` |
| Requis manquant | `Missing required enum value for '{$name}'` |

---

# VariadicParser

## Description

Extrait les arguments variadiques, avec support des valeurs restreintes.

## Rôle principal

- Traite la syntaxe simple `{name*}` et restreinte `{name*>[values]}`
- Valide les valeurs autorisées pour les variadics restreints
- Gère les multiples valeurs entre crochets

## Syntaxe

| Syntaxe | Description |
|---------|-------------|
| `{name*}` | Variadic simple, toutes valeurs autorisées |
| `{name*>[val1,val2]}` | Variadic restreint, valeurs autorisées uniquement |

## API

### `parse(array $signature, array $query): ParsedResultRecord`

**Exemple :**
```php
$parser = new VariadicParser();
$result = $parser->parse(
    ['roles*>[admin,editor,viewer]'],
    ['[admin,editor]']
);
// $result->data->toArray() = ['variadics' => ['roles' => ['admin', 'editor']]]
```

## Cas d'utilisation

### Cas 1 : Variadic simple
```php
$result = $parser->parse(['files*'], ['[file1.txt, file2.txt]']);
// ['variadics' => ['files' => ['file1.txt', 'file2.txt']]]
```

### Cas 2 : Variadic restreint
```php
$result = $parser->parse(['roles*>[admin,editor]'], ['[admin,editor]']);
// ['variadics' => ['roles' => ['admin', 'editor']]]
```

### Cas 3 : Variadic vide
```php
$result = $parser->parse(['files*'], ['[]']);
// ['variadics' => ['files' => []]]
```

## Validation

| Situation | Message |
|-----------|---------|
| Valeur non autorisée | `Value '{$value}' not allowed for '{$name}'` |
| Aucune valeur autorisée | `Restricted variadic '{$name}' has no allowed values` |
| Valeur vide dans la liste | `Empty value in variadic argument` |
| Variadic fourni sans signature | `Variadic argument provided but not defined` |

---

# FlagParser

## Description

Extrait les flags booléens de la commande.

## Rôle principal

- Identifie les flags avec `--flag`
- Détermine si un flag est présent ou non
- Détecte les flags dupliqués

## API

### `parse(array $signature, array $query): ParsedResultRecord`

**Exemple :**
```php
$parser = new FlagParser();
$result = $parser->parse(
    ['--force', '--verbose'],
    ['--force']
);
// $result->data->toArray() = ['flags' => ['force' => true, 'verbose' => false]]
```

## Cas d'utilisation

### Cas 1 : Flag présent
```php
$result = $parser->parse(['--force'], ['--force']);
// ['flags' => ['force' => true]]
```

### Cas 2 : Flag absent
```php
$result = $parser->parse(['--force'], []);
// ['flags' => ['force' => false]]
```

## Validation

| Situation | Message |
|-----------|---------|
| Flag inconnu | `Unknown flag: '{$flag}'` |
| Flag dupliqué | `Duplicate flag: '{$flag}'` |

---

# CustomTagParser

## Description

Extrait les tags personnalisés de la requête.

## Rôle principal

- Parse la syntaxe `<key="value">` ou `<key='value'>`
- Extrait les paires clé-valeur
- Nettoie la requête des tags pour les parsers suivants

## API

### `parse(array $signature, array $query): ParsedResultRecord`

**Exemple :**
```php
$parser = new CustomTagParser();
$result = $parser->parse(
    [],
    ['<greeting="Hello World">', '<user="admin">']
);
// $result->data->toArray() = ['greeting' => 'Hello World', 'user' => 'admin']
// $result->query = []
```

## Cas d'utilisation

### Cas 1 : Tag simple
```php
$result = $parser->parse([], ['<format="json">']);
// ['format' => 'json']
```

### Cas 2 : Tag avec guillemets simples
```php
$result = $parser->parse([], ["<user='admin'>"]);
// ['user' => 'admin']
```

### Cas 3 : Tags multiples
```php
$result = $parser->parse([], ['<user="admin">', '<role="editor">']);
// ['user' => 'admin', 'role' => 'editor']
```

## Validation

| Situation | Message |
|-----------|---------|
| Tag invalide | `Invalid custom tag syntax: <{$content}>` |
| Tag non fermé | `Unclosed custom tag` |

---

## Flux d'exécution global

```
Signature brute + Query brute
        ↓
   SourceParser → Extrait le nom de la commande
        ↓
   RequiredParser → Extrait les arguments requis
        ↓
   DefaultParser → Extrait les arguments avec défaut/nullables
        ↓
   EnumParser → Extrait les énumérations
        ↓
   VariadicParser → Extrait les variadics
        ↓
   FlagParser → Extrait les flags
        ↓
   CustomTagParser → Extrait les tags personnalisés
        ↓
   ParsedSignatureRecord (résultat final)
```

## Gestion des erreurs communes

| Situation | Parser concerné | Message |
|-----------|-----------------|---------|
| Source manquante | SourceParser | `Missing source (command name)` |
| Requis manquant | RequiredParser | `Missing required argument: '{$name}'` |
| Valeur par défaut vide | DefaultParser | `Default argument '{$name}' has empty value` |
| Enum invalide | EnumParser | `Invalid value '{$value}' for enum '{$name}'` |
| Variadic restreint | VariadicParser | `Value '{$value}' not allowed for '{$name}'` |
| Flag inconnu | FlagParser | `Unknown flag: '{$flag}'` |
| Tag non fermé | CustomTagParser | `Unclosed custom tag` |

## Performance

- Chaque parser est O(n) où n est le nombre de tokens
- Les parsers sont exécutés en chaîne, chaque parser réduisant le nombre de tokens
- Pas de cache, car les signatures sont généralement uniques

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\SignatureParser\Parsers\Customs\CustomTagParser;
use AndyDefer\SignatureParser\Parsers\DefaultParser;
use AndyDefer\SignatureParser\Parsers\EnumParser;
use AndyDefer\SignatureParser\Parsers\FlagParser;
use AndyDefer\SignatureParser\Parsers\RequiredParser;
use AndyDefer\SignatureParser\Parsers\SourceParser;
use AndyDefer\SignatureParser\Parsers\VariadicParser;

// Signatures et requête
$signature = 'backup {source} {destination} {format=zip} ::level->[low,high]=medium {excludes*} {--force}';
$query = 'backup /var/www /backup tar.gz high [cache,logs] --force <user="admin">';

// Exécution des parsers en chaîne
$signatureTokens = explode(' ', $signature);
$queryTokens = explode(' ', $query);

$parsers = [
    new SourceParser(),
    new RequiredParser(),
    new DefaultParser(),
    new EnumParser(),
    new VariadicParser(),
    new FlagParser(),
    new CustomTagParser(),
];

$data = [];
foreach ($parsers as $parser) {
    $result = $parser->parse($signatureTokens, $queryTokens);
    $data = array_merge($data, $result->data->toArray());
    $signatureTokens = $result->signature->toArray();
    $queryTokens = $result->query->toArray();
}

// Résultat final
print_r($data);
/*
[
    'source' => 'backup',
    'requireds' => ['source' => '/var/www', 'destination' => '/backup'],
    'defaults' => ['format' => 'tar.gz'],
    'enums' => ['level' => ['value' => 'high', ...]],
    'variadics' => ['excludes' => ['cache', 'logs']],
    'flags' => ['force' => true],
    'user' => 'admin'
]
*/
```

## Voir aussi

- `SignatureParser` - Parseur principal orchestrant tous les parsers
- `ParsedResultRecord` - Structure de données du résultat
- `ValidationResultRecord` - Structure de données de validation