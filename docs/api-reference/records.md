# Records - Référence Technique

## Description

Les records sont des objets de données immuables qui représentent les différentes entités extraites lors du parsing des signatures de commandes. Chaque record encapsule un ensemble spécifique de propriétés typées.

## Hiérarchie

```
AbstractRecord
    ├── ArgumentRecord
    ├── EnumRecord
    ├── FlagRecord
    ├── ParsedResultRecord
    ├── ParsedSignatureRecord
    ├── ValidationResultRecord
    └── VariadicArgumentRecord
```

## Rôle principal

Les records fournissent une représentation typée et immuable des données extraites, garantissant l'intégrité des informations à chaque étape du pipeline de parsing.

---

# ArgumentRecord

## Description

Record représentant un argument simple avec son nom, sa valeur et un commentaire optionnel.

## API

### `__construct(string $name, ?string $value = null, ?string $comment = null)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument |
| `$value` | `string|null` | Valeur de l'argument |
| `$comment` | `string|null` | Commentaire associé |

**Exemple :**
```php
$record = new ArgumentRecord('source', '/var/www', 'Le répertoire source');
// Utilisation avec from()
$record = ArgumentRecord::from(['name' => 'source', 'value' => '/var/www']);
```

## Cas d'utilisation

### Cas : Argument requis
```php
$record = new ArgumentRecord('name', 'John Doe');
echo $record->name;  // 'name'
echo $record->value; // 'John Doe'
```

### Cas : Argument avec commentaire
```php
$record = new ArgumentRecord('format', 'zip', 'Format d\'archive');
echo $record->comment; // 'Format d\'archive'
```

---

# EnumRecord

## Description

Record représentant une énumération avec ses valeurs autorisées, sa valeur par défaut et son état.

## API

### `__construct(string $name, mixed $value, StringTypedCollection $allowed_values, ?string $default_value = null, ValueState $value_state = ValueState::OPTIONAL, ?string $comment = null)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'énumération |
| `$value` | `mixed` | Valeur courante |
| `$allowed_values` | `StringTypedCollection` | Valeurs autorisées |
| `$default_value` | `string|null` | Valeur par défaut |
| `$value_state` | `ValueState` | État (REQUIRED, OPTIONAL, DEFAULTED) |
| `$comment` | `string|null` | Commentaire associé |

**Exemple :**
```php
use AndyDefer\SignatureParser\Enums\ValueState;

$record = new EnumRecord(
    name: 'level',
    value: 'high',
    allowed_values: StringTypedCollection::from(['low', 'medium', 'high']),
    default_value: 'medium',
    value_state: ValueState::DEFAULTED,
    comment: 'Niveau de priorité'
);
// Utilisation avec from()
$record = EnumRecord::from([
    'name' => 'level',
    'value' => 'high',
    'allowed_values' => ['low', 'medium', 'high'],
    'default_value' => 'medium',
    'value_state' => ValueState::DEFAULTED
]);
```

## Cas d'utilisation

### Cas 1 : Enum avec valeur par défaut
```php
$record = new EnumRecord(
    name: 'level',
    value: 'high',
    allowed_values: StringTypedCollection::from(['low', 'medium', 'high']),
    default_value: 'medium',
    value_state: ValueState::DEFAULTED
);
```

### Cas 2 : Enum requis
```php
$record = new EnumRecord(
    name: 'level',
    value: 'high',
    allowed_values: StringTypedCollection::from(['low', 'medium', 'high']),
    value_state: ValueState::REQUIRED
);
```

### Cas 3 : Enum optionnel
```php
$record = new EnumRecord(
    name: 'level',
    value: null,
    allowed_values: StringTypedCollection::from(['low', 'medium', 'high']),
    value_state: ValueState::OPTIONAL
);
```

---

# FlagRecord

## Description

Record représentant un flag booléen avec son nom, sa valeur et un commentaire optionnel.

## API

### `__construct(string $name, bool $value, ?string $comment = null)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom du flag (sans préfixe `--`) |
| `$value` | `bool` | État du flag |
| `$comment` | `string|null` | Commentaire associé |

**Exemple :**
```php
$record = new FlagRecord('force', true, 'Force l\'exécution');
// Utilisation avec from()
$record = FlagRecord::from(['name' => 'force', 'value' => true]);
```

## Cas d'utilisation

### Cas : Flag actif
```php
$record = new FlagRecord('verbose', true);
if ($record->value) {
    echo "Mode verbose activé";
}
```

### Cas : Flag inactif
```php
$record = new FlagRecord('force', false);
echo $record->value ? 'Actif' : 'Inactif'; // 'Inactif'
```

---

# VariadicArgumentRecord

## Description

Record représentant un argument variadique avec ses valeurs multiples, ses restrictions et un commentaire optionnel.

## API

### `__construct(string $name, StringTypedCollection $values, StringTypedCollection $restrictions = new StringTypedCollection, ?string $comment = null)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de l'argument variadique |
| `$values` | `StringTypedCollection` | Liste des valeurs |
| `$restrictions` | `StringTypedCollection` | Valeurs autorisées (restrictions) |
| `$comment` | `string|null` | Commentaire associé |

**Exemple :**
```php
$record = new VariadicArgumentRecord(
    name: 'files',
    values: StringTypedCollection::from(['file1.txt', 'file2.txt']),
    restrictions: StringTypedCollection::from(['txt', 'pdf', 'doc']),
    comment: 'Liste des fichiers'
);
// Utilisation avec from()
$record = VariadicArgumentRecord::from([
    'name' => 'files',
    'values' => ['file1.txt', 'file2.txt']
]);
```

## Cas d'utilisation

### Cas 1 : Variadic simple
```php
$record = new VariadicArgumentRecord(
    name: 'files',
    values: StringTypedCollection::from(['file1.txt', 'file2.txt'])
);
$count = $record->values->count(); // 2
```

### Cas 2 : Variadic restreint
```php
$record = new VariadicArgumentRecord(
    name: 'roles',
    values: StringTypedCollection::from(['admin', 'editor']),
    restrictions: StringTypedCollection::from(['admin', 'editor', 'viewer'])
);
if ($record->restrictions->contains('admin')) {
    echo "'admin' est autorisé";
}
```

---

# ParsedResultRecord

## Description

Record représentant le résultat d'une opération de parsing dans la chaîne de responsabilité.

## API

### `__construct(StrictAssociative $data, StringTypedCollection $signature, StringTypedCollection $query)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `StrictAssociative` | Données extraites par le parser |
| `$signature` | `StringTypedCollection` | Tokens de signature restants |
| `$query` | `StringTypedCollection` | Tokens de requête restants |

**Exemple :**
```php
use AndyDefer\DomainStructures\Utils\StrictAssociative;

$record = new ParsedResultRecord(
    data: new StrictAssociative(['source' => 'backup']),
    signature: StringTypedCollection::from(['destination']),
    query: StringTypedCollection::from(['/backup'])
);
// Utilisation avec from()
$record = ParsedResultRecord::from([
    'data' => ['source' => 'backup'],
    'signature' => ['destination'],
    'query' => ['/backup']
]);
```

## Cas d'utilisation

### Cas : Transmission entre parsers
```php
// Après SourceParser
$result = new ParsedResultRecord(
    data: new StrictAssociative(['source' => 'backup']),
    signature: StringTypedCollection::from(['destination']),
    query: StringTypedCollection::from(['/backup'])
);
// Les parsers suivants reçoivent la signature et la requête restantes
```

---

# ParsedSignatureRecord

## Description

Record représentant le résultat final du parsing complet d'une signature et d'une requête.

## API

### `__construct(string $source, ArgumentCollection $requireds, ArgumentCollection $defaults, VariadicArgumentCollection $variadics, FlagCollection $flags, EnumCollection $enums = new EnumCollection, StrictDataObject $custom_data = new StrictDataObject)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$source` | `string` | Nom de la commande |
| `$requireds` | `ArgumentCollection` | Arguments requis |
| `$defaults` | `ArgumentCollection` | Arguments par défaut |
| `$variadics` | `VariadicArgumentCollection` | Arguments variadiques |
| `$flags` | `FlagCollection` | Flags |
| `$enums` | `EnumCollection` | Énumérations |
| `$custom_data` | `StrictDataObject` | Données personnalisées |

**Exemple :**
```php
$record = new ParsedSignatureRecord(
    source: 'backup',
    requireds: new ArgumentCollection(),
    defaults: new ArgumentCollection(),
    variadics: new VariadicArgumentCollection(),
    flags: new FlagCollection(),
    enums: new EnumCollection(),
    custom_data: new StrictDataObject(['user' => 'admin'])
);
```

## Cas d'utilisation

### Cas : Résultat complet du parsing
```php
$record = $parser->parse('backup {source} {--force}', 'backup /var/www --force');
echo $record->source; // 'backup'
echo $record->requireds->get('source'); // '/var/www'
echo $record->flags->isActive('force'); // true
```

---

# ValidationResultRecord

## Description

Record représentant le résultat d'une validation de signature ou de requête.

## API

### `__construct(bool $isValid, StringTypedCollection $errors, StringTypedCollection $suggestions)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$isValid` | `bool` | État de la validation |
| `$errors` | `StringTypedCollection` | Liste des erreurs |
| `$suggestions` | `StringTypedCollection` | Suggestions de correction |

**Exemple :**
```php
$record = new ValidationResultRecord(
    isValid: false,
    errors: StringTypedCollection::from(['Missing required argument: source']),
    suggestions: StringTypedCollection::from(['Provide a value for source'])
);
// Utilisation avec from()
$record = ValidationResultRecord::from([
    'isValid' => false,
    'errors' => ['Missing required argument: source'],
    'suggestions' => ['Provide a value for source']
]);
```

## Cas d'utilisation

### Cas 1 : Validation réussie
```php
$result = $parser->validate('backup {source}', 'backup /var/www');
if ($result->isValid) {
    echo "La commande est valide";
}
```

### Cas 2 : Validation échouée
```php
$result = $parser->validate('backup {source}', 'backup');
if (!$result->isValid) {
    foreach ($result->errors as $error) {
        echo "Erreur: $error\n";
    }
}
```

---

## Intégration

Les records sont utilisés à différentes étapes du processus :

```
ValidationResultRecord ← SignatureParser::validate()
        ↓
ParsedResultRecord ← ParserInterface::parse()
        ↓
ParsedSignatureRecord ← SignatureParser::parse()
```

## Performance

- Tous les records sont immuables (`readonly` properties)
- Les collections utilisent `StringTypedCollection` pour une manipulation typée
- Les records héritent d'`AbstractRecord` qui fournit `from()` et `toArray()`

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\SignatureParser\Records\ArgumentRecord;
use AndyDefer\SignatureParser\Records\EnumRecord;
use AndyDefer\SignatureParser\Records\FlagRecord;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;
use AndyDefer\SignatureParser\Records\ParsedSignatureRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;
use AndyDefer\SignatureParser\Records\VariadicArgumentRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Collections\ArgumentCollection;
use AndyDefer\SignatureParser\Collections\EnumCollection;
use AndyDefer\SignatureParser\Collections\FlagCollection;
use AndyDefer\SignatureParser\Collections\VariadicArgumentCollection;
use AndyDefer\SignatureParser\Enums\ValueState;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

// Validation
$validation = new ValidationResultRecord(
    isValid: true,
    errors: StringTypedCollection::from([]),
    suggestions: StringTypedCollection::from([])
);

// Résultat de parsing intermédiaire
$parsedResult = new ParsedResultRecord(
    data: new StrictAssociative(['source' => 'backup']),
    signature: StringTypedCollection::from(['destination']),
    query: StringTypedCollection::from(['/backup'])
);

// Résultat final
$final = new ParsedSignatureRecord(
    source: 'backup',
    requireds: ArgumentCollection::from([
        ArgumentRecord::from(['name' => 'source', 'value' => '/var/www']),
        ArgumentRecord::from(['name' => 'destination', 'value' => '/backup'])
    ]),
    defaults: ArgumentCollection::from([
        ArgumentRecord::from(['name' => 'format', 'value' => 'zip'])
    ]),
    variadics: VariadicArgumentCollection::from([
        VariadicArgumentRecord::from([
            'name' => 'files',
            'values' => ['file1.txt', 'file2.txt']
        ])
    ]),
    flags: FlagCollection::from([
        FlagRecord::from(['name' => 'force', 'value' => true])
    ]),
    enums: EnumCollection::from([
        EnumRecord::from([
            'name' => 'level',
            'value' => 'high',
            'allowed_values' => ['low', 'medium', 'high'],
            'default_value' => 'medium',
            'value_state' => ValueState::DEFAULTED
        ])
    ]),
    custom_data: new StrictDataObject(['user' => 'admin'])
);

// Accès aux données
echo $final->source; // 'backup'
echo $final->requireds->get('source'); // '/var/www'
echo $final->flags->get('force'); // true
```

## Voir aussi

- `AbstractRecord` - Classe de base des records
- `ValueState` - États des énumérations
- `Collections` - Collections typées pour les records