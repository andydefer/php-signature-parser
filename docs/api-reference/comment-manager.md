# CommentManager - Référence Technique

## Description

Le `CommentManager` est un service qui gère l'extraction et la réattribution des commentaires dans les signatures de commandes. Il permet d'ajouter des commentaires documentaires directement dans la signature, puis de les extraire pour les associer aux arguments correspondants.

## Hiérarchie

```
CommentManager (service)
```

## Rôle principal

- Extrait les commentaires de la signature avant le parsing
- Stocke les commentaires avec des clés normalisées (nom de l'argument)
- Permet la réattribution des commentaires aux arguments après le parsing

---

## Syntaxe des commentaires

| Type | Syntaxe | Exemple |
|------|---------|---------|
| Arguments requis | `{name}#'comment'` | `{source}#"Source directory"` |
| Arguments par défaut | `{name=value}#'comment'` | `{format=zip}#"Archive format"` |
| Arguments nullables | `{name=?}#'comment'` | `{output=?}#"Optional output"` |
| Variadics simples | `{name*}#'comment'` | `{files*}#"Files to process"` |
| Variadics restreints | `{name*>[values]}#'comment'` | `{roles*>[admin,editor]}#"User roles"` |
| Énumérations | `::name->[values]#'comment'` | `::level->[low,high]#"Priority"` |
| Flags | `{--flag}#'comment'` | `{--force}#"Force operation"` |

---

## API / Méthodes publiques

### `extractComments(string $signature): string`

Extrait les commentaires de la signature et les stocke en mémoire.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature contenant des commentaires |

**Retourne :** `string` - Signature sans les commentaires

**Exemple :**
```php
$manager = new CommentManager();
$cleanSignature = $manager->extractComments(
    'backup {source}#"Source directory" {--force}#"Force overwrite"'
);
// $cleanSignature = 'backup {source} {--force}'
```

---

### `getComment(string $name): ?string`

Retourne le commentaire associé à un nom d'argument.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument (normalisé) |

**Retourne :** `string|null` - Le commentaire ou `null` s'il n'existe pas

**Exemple :**
```php
$comment = $manager->getComment('source'); // 'Source directory'
$flagComment = $manager->getComment('--force'); // 'Force overwrite'
```

---

### `getAllComments(): array`

Retourne tous les commentaires extraits.

**Retourne :** `array<string, string>` - Tableau [nom => commentaire]

**Exemple :**
```php
$comments = $manager->getAllComments();
// ['source' => 'Source directory', '--force' => 'Force overwrite']
```

---

### `hasComment(string $name): bool`

Vérifie si un argument a un commentaire.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool` - `true` si un commentaire existe, `false` sinon

**Exemple :**
```php
if ($manager->hasComment('source')) {
    echo $manager->getComment('source');
}
```

---

### `reset(): void`

Réinitialise le stockage des commentaires.

**Exemple :**
```php
$manager->reset();
// Tous les commentaires sont supprimés
```

---

## Normalisation des clés

Le `CommentManager` normalise les noms d'arguments pour faciliter la réattribution :

| Type original | Clé normalisée |
|---------------|----------------|
| `{source}` | `source` |
| `{format=zip}` | `format` |
| `{files*}` | `files` |
| `{roles*>[admin,editor]}` | `roles` |
| `::level->[low,high]` | `level` |
| `{--force}` | `--force` |

---

## Cas d'utilisation

### Cas 1 : Extraction de commentaires simples

```php
$manager = new CommentManager();
$signature = 'backup {source}#"Source directory" {destination}#"Destination" {--force}#"Force"';

$clean = $manager->extractComments($signature);
// $clean = 'backup {source} {destination} {--force}'

echo $manager->getComment('source'); // 'Source directory'
echo $manager->getComment('destination'); // 'Destination'
echo $manager->getComment('--force'); // 'Force'
```

### Cas 2 : Commentaires sur les variadics restreints

```php
$manager = new CommentManager();
$signature = 'command {roles*>[admin,editor,viewer]}#"The allowed roles"';

$clean = $manager->extractComments($signature);
// $clean = 'command {roles*>[admin,editor,viewer]}'

echo $manager->getComment('roles'); // 'The allowed roles'
```

### Cas 3 : Commentaires sur les énumérations

```php
$manager = new CommentManager();
$signature = 'set-level ::level->[beginner,middle,master]=middle#"The user skill level"';

$clean = $manager->extractComments($signature);
// $clean = 'set-level ::level->[beginner,middle,master]=middle'

echo $manager->getComment('level'); // 'The user skill level'
```

### Cas 4 : Commentaires avec guillemets simples

```php
$manager = new CommentManager();
$signature = "command {name}#'The user name' {--verbose}";

$clean = $manager->extractComments($signature);
echo $manager->getComment('name'); // 'The user name'
```

---

## Flux d'exécution

```
Signature avec commentaires
        ↓
extractComments()
        ↓
┌─────────────────────────────────────────────────┐
│ Pattern matching par type :                     │
│  • {name}#'comment' → {name}                   │
│  • ::name->[values]#'comment' → ::name->[values]│
│  • {--flag}#'comment' → {--flag}              │
└─────────────────────────────────────────────────┘
        ↓
Normalisation des clés
        ↓
Stockage dans $comments
        ↓
Signature nettoyée
```

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Commentaire mal formaté | Ignoré, le texte reste dans la signature |
| Guillemets non fermés | Ignoré, le texte reste dans la signature |
| Commentaire sans guillemets | Ignoré, le texte reste dans la signature |

## Performance

- O(n) pour l'extraction, où n est la longueur de la signature
- Utilisation de `preg_replace_callback` avec 3 patterns distincts
- Stockage en mémoire dans un tableau associatif
- Pas de cache, appelé une fois par parsing

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\SignatureParser\CommentManager;
use AndyDefer\SignatureParser\SignatureParser;

$signature = 'backup {source}#"Source directory" {destination}#"Destination" {format=zip}#"Archive format" {--force}#"Force overwrite"';
$query = 'backup /var/www /backup tar.gz --force';

// 1. Extraire les commentaires
$commentManager = new CommentManager();
$cleanSignature = $commentManager->extractComments($signature);

echo "Signature nettoyée: $cleanSignature\n";
// backup {source} {destination} {format=zip} {--force}

// 2. Parser la signature nettoyée
$parser = new SignatureParser();
$result = $parser->parse($cleanSignature, $query);

// 3. Réattribuer les commentaires
foreach ($result->requireds as $arg) {
    $comment = $commentManager->getComment($arg->name);
    if ($comment) {
        echo "Argument '{$arg->name}' = '{$arg->value}' (Commentaire: '$comment')\n";
    }
}

foreach ($result->defaults as $arg) {
    $comment = $commentManager->getComment($arg->name);
    if ($comment) {
        echo "Argument '{$arg->name}' = '{$arg->value}' (Commentaire: '$comment')\n";
    }
}

foreach ($result->flags as $flag) {
    $comment = $commentManager->getComment('--' . $flag->name);
    if ($comment) {
        echo "Flag '{$flag->name}' = " . ($flag->value ? 'true' : 'false') . " (Commentaire: '$comment')\n";
    }
}

// 4. Voir tous les commentaires
print_r($commentManager->getAllComments());
// [
//     'source' => 'Source directory',
//     'destination' => 'Destination',
//     'format' => 'Archive format',
//     '--force' => 'Force overwrite'
// ]
```

## Intégration

Le `CommentManager` est utilisé par :
- `SignatureParser` : pour extraire les commentaires avant le parsing
- `SignatureDocumentor` : pour récupérer les commentaires lors de la génération de documentation
- `SignatureStructureVO` : pour les méthodes de documentation

## Voir aussi

- `SignatureParser` - Parser principal utilisant CommentManager
- `SignatureDocumentor` - Générateur de documentation
- `ArgumentRecord` - Record avec propriété `comment`
- `EnumRecord` - Record avec propriété `comment`
- `FlagRecord` - Record avec propriété `comment`
- `VariadicArgumentRecord` - Record avec propriété `comment`