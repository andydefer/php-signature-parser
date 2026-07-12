# SignatureDocumentor - Référence Technique

## Description

Le `SignatureDocumentor` est un générateur de documentation automatique pour les signatures de commandes. Il analyse une signature et produit une documentation lisible dans différents formats : Markdown, texte brut, JSON ou tableau PHP.

## Rôle principal

- Analyser une signature et en extraire tous les composants
- Générer une documentation structurée avec les commentaires associés
- Produire la documentation dans plusieurs formats
- Fournir un exemple de commande complet

---

## API / Méthodes publiques

### `generate(string $signature, string $format = 'markdown'): string|array`

Génère la documentation complète d'une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la commande |
| `$format` | `string` | Format de sortie ('markdown', 'text', 'json', 'array') |

**Retourne :** `string|array` - Documentation générée

**Exceptions :** `InvalidArgumentException` - Si le format n'est pas supporté

**Exemple :**
```php
$markdown = SignatureDocumentor::generate(
    'backup {source}#"Source directory" {--force}#"Force overwrite"',
    'markdown'
);

$json = SignatureDocumentor::generate($signature, 'json');
$array = SignatureDocumentor::generate($signature, 'array');
```

---

## Formats de sortie

### Markdown (`markdown`)

Produit une documentation structurée en Markdown avec :
- Titre et description
- Tables pour chaque type d'argument
- Exemple complet de commande

**Exemple de sortie :**
```markdown
# Commande : backup

## Description

```bash
backup <source> <destination> [format=zip] [--force]
```

## Arguments requis

| Nom | Description |
|-----|-------------|
| `source` | Source directory |
| `destination` | — |

## Arguments par défaut

| Nom | Défaut | Description |
|-----|--------|-------------|
| `format` | `zip` | Archive format |

## Flags

| Nom | Description |
|-----|-------------|
| `--force` | Force overwrite |
```

---

### Texte (`text`)

Produit une documentation en texte brut avec alignement.

**Exemple de sortie :**
```
COMMANDE: backup
==================================================

ARGUMENTS REQUIS:
  source : Source directory
  destination : —

ARGUMENTS PAR DÉFAUT:
  format (défaut: zip) : Archive format

FLAGS:
  --force : Force overwrite

EXEMPLE:
  backup <source> <destination> [format=zip] [--force]
```

---

### JSON (`json`)

Produit une documentation structurée en JSON.

**Exemple de sortie :**
```json
{
    "source": "backup",
    "requireds": [
        {"name": "source", "comment": "Source directory"},
        {"name": "destination"}
    ],
    "defaults": [
        {"name": "format", "default": "zip", "comment": "Archive format"}
    ],
    "flags": [
        {"name": "force", "comment": "Force overwrite"}
    ],
    "enums": [],
    "variadics": []
}
```

---

### Array (`array`)

Produit une documentation sous forme de tableau PHP.

**Exemple de sortie :**
```php
[
    'source' => 'backup',
    'requireds' => [
        ['name' => 'source', 'comment' => 'Source directory'],
        ['name' => 'destination']
    ],
    'defaults' => [
        ['name' => 'format', 'default' => 'zip', 'comment' => 'Archive format']
    ],
    'flags' => [
        ['name' => 'force', 'comment' => 'Force overwrite']
    ],
    'enums' => [],
    'variadics' => []
]
```

---

## Structure des données

| Clé | Type | Description |
|-----|------|-------------|
| `source` | `string` | Nom de la commande |
| `requireds` | `array<array{name: string, comment?: string}>` | Arguments requis |
| `defaults` | `array<array{name: string, default: string|null, comment?: string}>` | Arguments par défaut |
| `enums` | `array<array{name: string, allowed_values: array, default_value: string|null, is_required: bool, is_optional: bool, comment?: string}>` | Énumérations |
| `variadics` | `array<array{name: string, restrictions: array|null, comment?: string}>` | Arguments variadiques |
| `flags` | `array<array{name: string, comment?: string}>` | Flags |

---

## Cas d'utilisation

### Cas 1 : Documentation Markdown pour une commande

```php
$signature = 'backup {source}#"Source directory" {destination} {format=zip}#"Archive format" {excludes*}#"Files to exclude" {--force}#"Force overwrite"';
$markdown = SignatureDocumentor::generate($signature, 'markdown');
echo $markdown;
```

### Cas 2 : Export JSON pour intégration

```php
$signature = 'set-level ::level->[beginner,middle,master]=middle#"The user skill level"';
$json = SignatureDocumentor::generate($signature, 'json');

// Utilisation avec une API ou un outil de génération
file_put_contents('command-doc.json', $json);
```

### Cas 3 : Documentation en texte pour console

```php
$signature = 'deploy {environment} {version=latest} {--force} {--dry-run}';
$text = SignatureDocumentor::generate($signature, 'text');
echo $text;
```

### Cas 4 : Utilisation programmatique avec array

```php
$signature = 'command {roles*>[admin,editor,viewer]}#"The allowed roles"';
$data = SignatureDocumentor::generate($signature, 'array');

foreach ($data['variadics'] as $variadic) {
    echo "{$variadic['name']}: " . implode(', ', $variadic['restrictions']) . "\n";
}
```

---

## Flux d'exécution

```
Signature
        ↓
SignatureStructureVO
        ↓
CommentManager::extractComments()
        ↓
extractData()
        ↓
┌─────────────────────────────────────────────────┐
│ Analyse des éléments :                          │
│  • Requireds                                    │
│  • Defaults                                     │
│  • Enums                                        │
│  • Variadics                                    │
│  • Flags                                        │
└─────────────────────────────────────────────────┘
        ↓
Association des commentaires
        ↓
┌─────────────────────────────────────────────────┐
│ Formatage :                                     │
│  • markdown → toMarkdown()                      │
│  • text → toText()                              │
│  • json → toJson()                              │
│  • array → toArray()                            │
└─────────────────────────────────────────────────┘
        ↓
Documentation générée
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Format non supporté | `InvalidArgumentException` | `Unsupported format: {$format}` |
| Signature invalide | `InvalidArgumentException` | Erreur de `SignatureStructureVO` |

## Performance

- O(n) pour l'extraction des données, où n est le nombre d'éléments
- Utilisation de `preg_match` pour l'extraction des valeurs
- Appel à `CommentManager` pour les commentaires
- Pas de cache car la documentation est rarement générée plusieurs fois

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet  |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\SignatureParser\SignatureDocumentor;

$signature = 'backup {source}#"Source directory" {destination}#"Destination directory" {format=zip}#"Archive format" {output=?}#"Optional output" ::level->[low,medium,high]=medium#"Compression level" {excludes*}#"Files to exclude" {--force}#"Force overwrite" {--verbose}#"Show details"';

// Markdown
echo "📄 DOCUMENTATION MARKDOWN\n";
echo "=========================\n";
echo SignatureDocumentor::generate($signature, 'markdown');

// Texte
echo "\n📄 DOCUMENTATION TEXTE\n";
echo "=====================\n";
echo SignatureDocumentor::generate($signature, 'text');

// JSON
echo "\n📄 DOCUMENTATION JSON\n";
echo "===================\n";
echo SignatureDocumentor::generate($signature, 'json');

// Array
echo "\n📄 DOCUMENTATION ARRAY\n";
echo "====================\n";
print_r(SignatureDocumentor::generate($signature, 'array'));
```

## Intégration

Le `SignatureDocumentor` est utilisé par :
- `SignatureStructureVO` : via les méthodes `document()`, `documentInMarkdown()`, `documentInText()`, `documentInJson()`, `documentInArray()`
- `SignatureVO` : peut être étendu pour intégrer la documentation
- Outils de génération de documentation CLI

## Voir aussi

- `SignatureStructureVO` - Structure de signature avec méthodes de documentation
- `CommentManager` - Gestion des commentaires
- `SignatureParser` - Parser principal