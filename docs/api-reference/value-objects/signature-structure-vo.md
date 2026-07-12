# SignatureStructureVO - Référence Technique

## Description

Le `SignatureStructureVO` est un Value Object qui analyse une signature de commande et extrait sa structure complète : nom de la commande, arguments requis, arguments par défaut, arguments nullables, énumérations, variadics et flags.

## Hiérarchie

```
AbstractValueObject
    └── SignatureStructureVO
```

## Rôle principal

Analyse une chaîne de signature pour en extraire tous les composants de manière structurée et typée. La validation est effectuée à la construction pour garantir la validité de la signature. Fournit également des méthodes de documentation pour générer une documentation lisible de la signature.

---

## API / Méthodes publiques

### `__construct(string $signature)`

Analyse et valide une signature de commande.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la commande (ex: `'backup {source} {--force}'`) |

**Exceptions :** `InvalidArgumentException` - Si la signature est vide

**Exemple :**
```php
$structure = new SignatureStructureVO('backup {source} {destination} {format=zip} {--force}');
```

---

### `getSource(): string`

Retourne le nom de la commande (source).

**Retourne :** `string` - Nom de la commande

**Exemple :**
```php
$source = $structure->getSource(); // 'backup'
```

---

### `getStructure(): StrictDataObject`

Retourne la représentation structurée de la signature.

**Retourne :** `StrictDataObject` - Objet contenant toutes les données

**Exemple :**
```php
$data = $structure->getStructure();
echo $data->source;      // 'backup'
print_r($data->requireds); // ['source', 'destination']
```

---

### `getRequireds(): array`

Retourne la liste des noms des arguments requis.

**Retourne :** `array<string>` - Noms des arguments requis

**Exemple :**
```php
$requireds = $structure->getRequireds(); // ['source', 'destination']
```

---

### `getDefaults(): array`

Retourne les arguments par défaut avec leurs valeurs. Les arguments nullables (`{name=?}`) sont représentés avec une valeur `null`.

**Retourne :** `array<string, string|null>` - Tableau [nom => valeur]

**Exemple :**
```php
$defaults = $structure->getDefaults(); // ['format' => 'zip', 'output' => null]
```

---

### `getVariadics(): array`

Retourne la liste des noms des arguments variadiques.

**Retourne :** `array<string>` - Noms des arguments variadiques

**Exemple :**
```php
$variadics = $structure->getVariadics(); // ['files']
```

---

### `getFlags(): array`

Retourne la liste des noms des flags.

**Retourne :** `array<string>` - Noms des flags (sans préfixe `--`)

**Exemple :**
```php
$flags = $structure->getFlags(); // ['force', 'verbose']
```

---

### `getEnums(): array`

Retourne les définitions des énumérations.

**Retourne :** `array<string, array{allowed_values: array<string>, default_value: string|null, is_required: bool, is_optional: bool}>`

**Exemple :**
```php
$enums = $structure->getEnums();
// [
//     'level' => [
//         'allowed_values' => ['low', 'medium', 'high'],
//         'default_value' => 'medium',
//         'is_required' => false,
//         'is_optional' => false
//     ]
// ]
```

---

### `getEnumAllowedValues(string $name): ?array`

Récupère les valeurs autorisées pour une énumération spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'énumération |

**Retourne :** `array<string>|null` - Valeurs autorisées ou `null` si l'énumération n'existe pas

**Exemple :**
```php
$allowed = $structure->getEnumAllowedValues('level'); // ['low', 'medium', 'high']
```

---

### `getEnumDefaultValue(string $name): ?string`

Récupère la valeur par défaut d'une énumération spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'énumération |

**Retourne :** `string|null` - Valeur par défaut ou `null`

**Exemple :**
```php
$default = $structure->getEnumDefaultValue('level'); // 'medium'
```

---

### `isEnumRequired(string $name): bool`

Vérifie si une énumération est requise.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'énumération |

**Retourne :** `bool` - `true` si requise, `false` sinon

**Exemple :**
```php
if ($structure->isEnumRequired('level')) {
    echo "Le niveau est requis";
}
```

---

### `isEnumOptional(string $name): bool`

Vérifie si une énumération est optionnelle (nullable).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'énumération |

**Retourne :** `bool` - `true` si optionnelle, `false` sinon

---

### `hasEnum(string $name): bool`

Vérifie si une énumération existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'énumération |

**Retourne :** `bool` - `true` si elle existe, `false` sinon

---

### `hasEnums(): bool`

Vérifie si la signature contient au moins une énumération.

**Retourne :** `bool` - `true` si des énumérations existent, `false` sinon

---

### `hasRequired(string $name): bool`

Vérifie si un argument requis existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool` - `true` s'il existe, `false` sinon

---

### `hasDefault(string $name): bool`

Vérifie si un argument par défaut existe (incluant les nullables).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool` - `true` s'il existe, `false` sinon

---

### `hasVariadic(string $name): bool`

Vérifie si un argument variadique existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool` - `true` s'il existe, `false` sinon

---

### `hasFlag(string $name): bool`

Vérifie si un flag existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag (sans préfixe `--`) |

**Retourne :** `bool` - `true` s'il existe, `false` sinon

---

### `hasArgument(string $name): bool`

Vérifie si un argument existe (requis, par défaut ou variadique).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |

**Retourne :** `bool` - `true` s'il existe, `false` sinon

---

### `getRaw(): string`

Retourne la signature brute.

**Retourne :** `string` - Signature originale

**Exemple :**
```php
$raw = $structure->getRaw(); // 'backup {source} {--force}'
```

---

### `hasRequireds(): bool`

Vérifie si la signature contient au moins un argument requis.

**Retourne :** `bool`

---

### `hasDefaults(): bool`

Vérifie si la signature contient au moins un argument par défaut (incluant les nullables).

**Retourne :** `bool`

---

### `hasVariadics(): bool`

Vérifie si la signature contient au moins un argument variadique.

**Retourne :** `bool`

---

### `hasFlags(): bool`

Vérifie si la signature contient au moins un flag.

**Retourne :** `bool`

---

### `isValid(): bool`

Vérifie si la signature est valide.

**Retourne :** `bool` - `true` si valide, `false` sinon

**Exemple :**
```php
if ($structure->isValid()) {
    echo "Signature valide";
}
```

---

### `getValidationErrors(): array`

Retourne les erreurs de validation.

**Retourne :** `array<string>` - Liste des messages d'erreur

**Exemple :**
```php
$errors = $structure->getValidationErrors();
foreach ($errors as $error) {
    echo "Erreur: $error\n";
}
```

---

### `getValidationSuggestions(): array`

Retourne les suggestions pour corriger les erreurs.

**Retourne :** `array<string>` - Liste des suggestions

---

### `getValidationResult(): ValidationResultRecord`

Retourne le résultat complet de la validation.

**Retourne :** `ValidationResultRecord` - Résultat de la validation

---

### `document(string $format = 'markdown'): string|array`

Génère la documentation de la signature dans le format spécifié.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$format` | `string` | Format de sortie ('markdown', 'text', 'json', 'array') |

**Retourne :** `string|array` - Documentation générée

**Exceptions :** `InvalidArgumentException` - Si le format n'est pas supporté

**Exemple :**
```php
$markdown = $structure->document('markdown');
$json = $structure->document('json');
$array = $structure->document('array');
```

---

### `documentInMarkdown(): string`

Génère la documentation au format Markdown.

**Retourne :** `string` - Documentation Markdown

**Exemple :**
```php
$markdown = $structure->documentInMarkdown();
echo $markdown;
```

---

### `documentInText(): string`

Génère la documentation au format texte.

**Retourne :** `string` - Documentation texte

**Exemple :**
```php
$text = $structure->documentInText();
echo $text;
```

---

### `documentInJson(): string`

Génère la documentation au format JSON.

**Retourne :** `string` - Documentation JSON

**Exemple :**
```php
$json = $structure->documentInJson();
echo $json;
```

---

### `documentInArray(): array`

Génère la documentation sous forme de tableau.

**Retourne :** `array<string, mixed>` - Documentation en tableau

**Exemple :**
```php
$array = $structure->documentInArray();
print_r($array);
```

---

### `getValue(): StrictDataObject`

Méthode de `AbstractValueObject`. Retourne la structure complète.

**Retourne :** `StrictDataObject`

---

### `equals(AbstractValueObject $other): bool`

Compare deux objets `SignatureStructureVO`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$other` | `AbstractValueObject` | Autre objet à comparer |

**Retourne :** `bool` - `true` s'ils sont égaux, `false` sinon

---

## Cas d'utilisation

### Cas 1 : Analyse d'une signature simple

```php
$structure = new SignatureStructureVO('backup {source} {destination} {--force}');

echo $structure->getSource();           // 'backup'
print_r($structure->getRequireds());    // ['source', 'destination']
print_r($structure->getFlags());        // ['force']
```

### Cas 2 : Vérification d'arguments

```php
$structure = new SignatureStructureVO('deploy {environment} {port=8080} {--verbose}');

if ($structure->hasRequired('environment')) {
    echo "L'argument environment est requis";
}

if ($structure->hasDefault('port')) {
    $default = $structure->getDefaults()['port'];
    echo "Port par défaut: $default";
}
```

### Cas 3 : Génération de documentation

```php
$structure = new SignatureStructureVO('backup {source}#"Source directory" {destination} {format=zip}#"Archive format" {--force}#"Force overwrite"');

// Documentation Markdown
echo $structure->documentInMarkdown();

// Documentation JSON
echo $structure->documentInJson();

// Documentation array
print_r($structure->documentInArray());
```

---

## Flux d'exécution

```
Signature brute
        ↓
SignatureParser::validateSignature()
        ↓
SignatureParser::extractSignatureElements()
        ↓
parseElements()
        ↓
├── Requireds
├── Defaults
├── Enums
├── Variadics
└── Flags
        ↓
StrictDataObject (structure)
        ↓
ValidationResultRecord (validation)
```

## Gestion des erreurs

| Situation | Exception/Erreur | Message |
|-----------|------------------|---------|
| Signature vide | `InvalidArgumentException` | `Signature cannot be empty` |
| Ordre invalide | Erreur de validation | `Required argument '{$name}' must appear before default, enum, variadic or flags` |
| Syntaxe invalide | Erreur de validation | `Invalid token syntax: '{$token}'` |
| Nom dupliqué | Erreur de validation | `Duplicate argument name: '{$name}'` |
| Source invalide | Erreur de validation | `Invalid source name: '{$name}'` |
| Enum sans valeurs | Erreur de validation | `Enum '{$name}' has no allowed values` |
| Format de documentation invalide | `InvalidArgumentException` | `Unsupported format: {$format}` |

## Performance

- O(n) pour l'extraction des éléments, où n est le nombre de tokens
- Validation effectuée une seule fois à la construction
- Structure immuable après construction
- Les méthodes de documentation délèguent à `SignatureDocumentor`

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;

// Création
$structure = new SignatureStructureVO(
    'backup {source} {destination} {format=zip} {output=?} ::level->[low,high]=medium {excludes*} {--force} {--verbose}'
);

// Accès aux données
echo "Commande: " . $structure->getSource() . "\n";
echo "Arguments requis: " . implode(', ', $structure->getRequireds()) . "\n";
echo "Arguments par défaut:\n";
foreach ($structure->getDefaults() as $name => $value) {
    echo "  $name: " . ($value ?? 'null') . "\n";
}
echo "Variadics: " . implode(', ', $structure->getVariadics()) . "\n";
echo "Flags: " . implode(', ', $structure->getFlags()) . "\n";

// Enums
foreach ($structure->getEnums() as $name => $data) {
    echo "Enum '$name':\n";
    echo "  Valeurs autorisées: " . implode(', ', $data['allowed_values']) . "\n";
    echo "  Défaut: " . ($data['default_value'] ?? 'aucun') . "\n";
    echo "  Requis: " . ($data['is_required'] ? 'Oui' : 'Non') . "\n";
}

// Validation
if ($structure->isValid()) {
    echo "\n✅ Signature valide\n";
} else {
    echo "\n❌ Signature invalide:\n";
    foreach ($structure->getValidationErrors() as $error) {
        echo "  - $error\n";
    }
}

// Documentation
echo "\n📄 Documentation Markdown:\n";
echo $structure->documentInMarkdown();
```

## Voir aussi

- `SignatureParser` - Parser principal
- `SignatureDocumentor` - Générateur de documentation
- `ValidationResultRecord` - Résultat de validation
- `SignatureVO` - Value Object pour signature complète avec requête