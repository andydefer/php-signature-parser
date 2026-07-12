# SignatureParser - Référence Technique Complète

## Description

Le `SignatureParser` est le cœur du package. Il analyse les signatures et requêtes de commandes CLI en utilisant une chaîne de responsabilité (Chain of Responsibility) de parsers spécialisés. Chaque parser extrait un type spécifique d'argument.

## Hiérarchie / Implémentations

```
ParserRegistryInterface
    └── SignatureParser

SignatureParserInterface
    └── SignatureParser
```

## Rôle principal

- Analyser une signature et une requête pour en extraire tous les composants
- Valider la syntaxe et l'ordre des arguments
- Gérer les commentaires inline via `CommentManager`
- Gérer les placeholders `~` pour les valeurs manquantes
- Produire un enregistrement structuré (`ParsedSignatureRecord`)

---

## Concepts fondamentaux

### Le placeholder `~` (tilde)

Le tilde `~` est un placeholder utilisé pour représenter une valeur manquante, nulle ou ignorée dans une requête. Il permet de respecter l'ordre des arguments sans avoir à fournir une valeur explicite.

**Pourquoi utiliser `~` ?**

| Situation | Utilisation de `~` | Exemple |
|-----------|-------------------|---------|
| Argument requis manquant | ❌ Non autorisé | Le parsing échoue |
| Argument nullable | ✅ Oui | `{output=?}` → `~` |
| Argument par défaut | ✅ Oui | `{format=zip}` → `~` |
| Enum optionnel | ✅ Oui | `::level->[low,high]=?` → `~` |
| Enum avec défaut | ✅ Oui | `::level->[low,high]=medium` → `~` |

**Exemple concret :**

```php
// Signature
$signature = 'backup {source} {destination} {format=zip} {output=?}';

// Requête avec ~ pour l'argument nullable
$query = 'backup /var/www /backup ~ ~';
// Résultat:
// source = '/var/www'
// destination = '/backup'
// format = 'zip' (valeur par défaut, car ~)
// output = null (nullable, car ~)
```

### Commentaires inline

Les commentaires permettent de documenter chaque argument directement dans la signature.

```php
$signature = 'backup {source}#"Source directory" {destination}#"Destination" {--force}#"Force overwrite"';
```

### Ordre des arguments

L'ordre est strict et doit être respecté :

1. **Source** : `backup`
2. **Required** : `{source}` `{destination}`
3. **Default** : `{format=zip}`
4. **Nullable** : `{output=?}`
5. **Enum** : `::level->[low,high]=medium`
6. **Variadic** : `{excludes*}`
7. **Flags** : `{--force}`

---

## API / Méthodes publiques

### `__construct()`

Initialise le parser avec la chaîne de parsers par défaut.

**Exemple :**
```php
$parser = new SignatureParser();
```

### `parse(string $signature, string $query): ParsedSignatureRecord`

Parse une signature et une requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la commande |
| `$query` | `string` | Requête de la commande |

**Retourne :** `ParsedSignatureRecord` - Résultat structuré du parsing

**Exceptions :** `InvalidArgumentException` - Si l'ordre de la signature est invalide

### `validate(string $signature, string $query): ValidationResultRecord`

Valide une requête par rapport à une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la commande |
| `$query` | `string` | Requête à valider |

**Retourne :** `ValidationResultRecord` - Résultat de la validation

### `validateSignature(string $signature): ValidationResultRecord`

Valide uniquement une signature (sans requête).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature à valider |

**Retourne :** `ValidationResultRecord` - Résultat de la validation

### `isValid(string $signature, string $query): bool`

Vérifie si une requête est valide.

**Exemple :**
```php
if ($parser->isValid('backup {source}', 'backup /var/www')) {
    echo "✅ Requête valide";
}
```

### `extractSignatureElements(string $signature): StringTypedCollection`

Extrait les tokens d'une signature.

**Exemple :**
```php
$tokens = $parser->extractSignatureElements('backup {source} {--force}');
// ['backup', 'source', '--force']
```

### `extractQueryElements(string $query): StringTypedCollection`

Extrait les tokens d'une requête.

**Exemple :**
```php
$tokens = $parser->extractQueryElements('backup /var/www --force');
// ['backup', '/var/www', '--force']
```

### `addParser(ParserInterface $parser): self`

Ajoute un parser à la chaîne.

**Exemple :**
```php
$parser->addParser(new CustomParser());
```

### `removeParser(string $parserClass): self`

Supprime un parser de la chaîne.

**Exemple :**
```php
$parser->removeParser(FlagParser::class);
```

### `getParsers(): array`

Récupère la liste des parsers.

---

## Types d'arguments

### 1. Source
```php
// Syntaxe
'backup'

// Exemple
$result = $parser->parse('backup {source}', 'backup /var/www');
echo $result->source; // 'backup'
```

### 2. Requis (Required)
```php
// Syntaxe
{name}

// Exemple
$result = $parser->parse('backup {source}', 'backup /var/www');
echo $result->requireds->get('source'); // '/var/www'
```

### 3. Par défaut (Default)
```php
// Syntaxe
{name=value}

// Comportement
// - Si la valeur est fournie → utilise la valeur fournie
// - Si '~' est fourni → utilise la valeur par défaut
// - Si rien n'est fourni → utilise la valeur par défaut

// Exemple
$result = $parser->parse(
    'backup {format=zip}',
    'backup tar.gz'  // format = 'tar.gz'
);

$result = $parser->parse(
    'backup {format=zip}',
    'backup ~'  // format = 'zip' (valeur par défaut)
);

$result = $parser->parse(
    'backup {format=zip}',
    'backup'  // format = 'zip' (valeur par défaut)
);
```

### 4. Nullable / Optionnel
```php
// Syntaxe
{name=?}

// Comportement
// - Si une valeur est fournie → utilise la valeur
// - Si '~' est fourni → valeur = null
// - Si rien n'est fourni → valeur = null

// Exemple
$result = $parser->parse(
    'backup {output=?}',
    'backup /tmp'  // output = '/tmp'
);

$result = $parser->parse(
    'backup {output=?}',
    'backup ~'  // output = null
);

$result = $parser->parse(
    'backup {output=?}',
    'backup'  // output = null
);
```

### 5. Énumérations (Enum)
```php
// Syntaxe
::name->[value1,value2,value3]=state

// États possibles
// - =*  → Requis (doit être fourni)
// - =?  → Optionnel (peut être '~')
// - =default → Valeur par défaut

// Exemple avec défaut
$result = $parser->parse(
    'set-level ::level->[low,medium,high]=medium',
    'set-level high'  // level = 'high'
);

$result = $parser->parse(
    'set-level ::level->[low,medium,high]=medium',
    'set-level ~'  // level = 'medium' (valeur par défaut)
);

// Exemple requis
$result = $parser->parse(
    'set-level ::level->[low,medium,high]=*',
    'set-level high'  // level = 'high'
);
// set-level seul échouerait

// Exemple optionnel
$result = $parser->parse(
    'set-level ::level->[low,medium,high]=?',
    'set-level ~'  // level = null
);
```

### 6. Variadiques
```php
// Syntaxe simple
{name*}

// Syntaxe restreinte
{name*>[value1,value2]}

// Exemple simple
$result = $parser->parse(
    'backup {files*}',
    'backup [file1.txt, file2.txt]'
);
echo implode(', ', $result->variadics->get('files')); // 'file1.txt, file2.txt'

// Exemple restreint
$result = $parser->parse(
    'command {roles*>[admin,editor,viewer]}',
    'command [admin,editor]'
);
echo implode(', ', $result->variadics->get('roles')); // 'admin, editor'
```

### 7. Flags
```php
// Syntaxe
{--flag}

// Exemple
$result = $parser->parse(
    'backup {--force}',
    'backup --force'
);
echo $result->flags->get('force'); // true

$result = $parser->parse(
    'backup {--force}',
    'backup'
);
echo $result->flags->get('force'); // false
```

### 8. Tags personnalisés
```php
// Syntaxe
<key="value">
<key='value'>

// Exemple
$result = $parser->parse(
    'send {recipient}',
    'send John <greeting="Hello World">'
);
$data = $result->custom_data->toArray();
echo $data['greeting']; // 'Hello World'
```

---

## Gestion du placeholder `~` (détail)

Le tilde `~` est un mécanisme essentiel pour maintenir l'ordre des arguments sans fournir de valeur.

### Pourquoi est-ce important ?

```php
// Signature
$signature = 'backup {source} {destination} {format=zip} {output=?} {--force}';

// ❌ Sans ~, impossible de sauter un argument
// 'backup /var/www tar.gz --force' → destination manquant

// ✅ Avec ~
$query = 'backup /var/www /backup ~ ~ --force';
// Résultat: tout est correctement positionné
```

### Tableau des comportements

| Type d'argument | Sans `~` | Avec `~` |
|-----------------|----------|----------|
| Required | ❌ Erreur | ❌ Erreur (non autorisé) |
| Default | Utilise la valeur par défaut | Utilise la valeur par défaut |
| Nullable | `null` | `null` |
| Enum (default) | Utilise la valeur par défaut | Utilise la valeur par défaut |
| Enum (optional) | `null` | `null` |
| Variadic | `[]` | `[]` (s'il est vide) |

---

## Cas d'utilisation complets

### Cas 1 : Commande avec tous les types d'arguments

```php
$parser = new SignatureParser();

$signature = 'backup {source} {destination} {format=zip} {output=?} ::level->[low,medium,high]=medium {excludes*} {--force} {--verbose}';

// Tous les arguments fournis
$query1 = 'backup /var/www /backup tar.gz /tmp high [cache,logs] --force --verbose';
$result1 = $parser->parse($signature, $query1);

// Avec ~ pour les arguments optionnels
$query2 = 'backup /var/www /backup ~ ~ ~ [] --force';
$result2 = $parser->parse($signature, $query2);
// format = 'zip', output = null, level = 'medium', excludes = []
```

### Cas 2 : Validation avec erreurs

```php
$parser = new SignatureParser();

$signature = 'backup {source} {destination} {format=zip}';
$query = 'backup /var/www ~';

$result = $parser->validate($signature, $query);
if (!$result->isValid) {
    foreach ($result->errors as $error) {
        echo "❌ $error\n";
    }
    foreach ($result->suggestions as $suggestion) {
        echo "💡 $suggestion\n";
    }
}
// ❌ Missing required argument: 'destination'
// 💡 Provide a value for 'destination'
```

### Cas 3 : Commentaires inline

```php
$parser = new SignatureParser();

$signature = 'backup {source}#"Source directory" {destination}#"Destination" {format=zip}#"Archive format" {--force}#"Force overwrite"';
$query = 'backup /var/www /backup tar.gz --force';

$result = $parser->parse($signature, $query);

echo $result->requireds->first()->comment; // 'Source directory'
echo $result->defaults->first()->comment; // 'Archive format'
echo $result->flags->first()->comment; // 'Force overwrite'
```

---

## Flux d'exécution détaillé

```
1. Signature + Query
        ↓
2. CommentManager::extractComments()
   → Extrait les commentaires de la signature
   → Stocke les commentaires par nom d'argument
        ↓
3. extractSignatureElements()
   → Tokenise la signature nettoyée
   → ['backup', 'source', 'destination', 'format=zip', '--force']
        ↓
4. extractQueryElements()
   → Tokenise la requête
   → ['backup', '/var/www', '/backup', 'tar.gz', '--force']
        ↓
5. validateSignatureOrder()
   → Vérifie l'ordre des arguments
   → Source → Required → Default → Nullable → Enum → Variadic → Flags
        ↓
6. Chaîne de parsers (séquentielle)
   a. SourceParser → extrait 'backup'
   b. RequiredParser → extrait 'source' et 'destination'
   c. DefaultParser → extrait 'format=zip'
   d. EnumParser → extrait '::level->[...]'
   e. VariadicParser → extrait 'excludes*'
   f. FlagParser → extrait '--force'
   g. CustomTagParser → extrait '<key="value">'
        ↓
7. TextFormatter::format()
   → Remplace les '^' par des espaces dans les valeurs
   → 'John^Doe' → 'John Doe'
        ↓
8. buildRecord()
   → Associe les commentaires via CommentManager
   → Construit ParsedSignatureRecord
        ↓
9. ParsedSignatureRecord (résultat)
```

---

## Gestion des erreurs

| Situation | Exception/Erreur | Message |
|-----------|------------------|---------|
| Ordre invalide | `InvalidArgumentException` | `Invalid signature order: Required argument '{name}' must appear before default, enum, variadic or flags` |
| Source invalide | Erreur de validation | `Invalid source name: '{$name}'` |
| Token invalide | Erreur de validation | `Invalid token syntax: '{$token}'` |
| Nom dupliqué | Erreur de validation | `Duplicate argument name: '{$name}'` |
| Signature vide | Erreur de validation | `Signature cannot be empty` |
| Requis manquant | Erreur de validation | `Missing required argument: '{$name}'` |
| Enum invalide | Erreur de validation | `Invalid value '{$value}' for enum '{$name}'` |
| `~` sur non-optional | Erreur de validation | `Cannot use '~' for non-optional enum '{$name}'` |
| Tag non fermé | Erreur de validation | `Unclosed custom tag` |

---

## Performance

- O(n) pour le parsing, où n est le nombre de tokens
- Les parsers sont exécutés en séquence
- `TextFormatter` effectue le formatage des valeurs en une seule passe
- Les commentaires sont extraits avant le parsing
- Aucun cache, les signatures sont généralement uniques

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\SignatureParser\SignatureParser;

$parser = new SignatureParser();

$signature = 'backup {source}#"Source directory" {destination} {format=zip}#"Archive format" {output=?}#"Output directory" ::level->[low,medium,high]=medium#"Compression level" {excludes*}#"Files to exclude" {--force}#"Force overwrite" {--verbose}';

// Requête avec ~ pour les arguments optionnels
$query = 'backup /var/www /backup tar.gz ~ high [cache,logs,tmp] --force';

$result = $parser->parse($signature, $query);

// Affichage des résultats
echo "=== COMMANDE ===\n";
echo "Source: " . $result->source . "\n\n";

echo "=== ARGUMENTS ===\n";
echo "Source: " . $result->requireds->get('source') . "\n";
echo "Destination: " . $result->requireds->get('destination') . "\n";
echo "Format: " . $result->defaults->get('format') . "\n";
echo "Output: " . ($result->defaults->get('output') ?? 'null') . "\n";
echo "Level: " . $result->enums->get('level') . "\n";
echo "Excludes: " . implode(', ', $result->variadics->get('excludes')) . "\n";
echo "Force: " . ($result->flags->get('force') ? 'true' : 'false') . "\n";
echo "Verbose: " . ($result->flags->get('verbose') ? 'true' : 'false') . "\n\n";

echo "=== COMMENTAIRES ===\n";
echo "Source: " . ($result->requireds->first()->comment ?? '—') . "\n";
echo "Format: " . ($result->defaults->first()->comment ?? '—') . "\n";
echo "Output: " . ($result->defaults->last()->comment ?? '—') . "\n";
echo "Level: " . ($result->enums->first()->comment ?? '—') . "\n";
echo "Excludes: " . ($result->variadics->first()->comment ?? '—') . "\n";
echo "Force: " . ($result->flags->first()->comment ?? '—') . "\n";

// Validation
$validation = $parser->validate($signature, $query);
echo "\n=== VALIDATION ===\n";
echo "Statut: " . ($validation->isValid ? '✅ Valide' : '❌ Invalide') . "\n";

if (!$validation->isValid) {
    foreach ($validation->errors as $error) {
        echo "Erreur: $error\n";
    }
    foreach ($validation->suggestions as $suggestion) {
        echo "Suggestion: $suggestion\n";
    }
}
```

---

## Voir aussi

- `SignatureParserInterface` - Interface du parser
- `ParserInterface` - Interface des parsers individuels
- `ParsedSignatureRecord` - Résultat du parsing
- `ValidationResultRecord` - Résultat de validation
- `CommentManager` - Gestion des commentaires
- `Parsers` - Référence technique des parsers
- `QueryBuilder` - Constructeur de requêtes