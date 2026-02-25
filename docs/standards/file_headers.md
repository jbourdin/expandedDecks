# File Headers: Copyright & License

> **Audience:** Developer · **Scope:** Coding Standards

← Back to [Main Documentation](../docs.md) | [CLAUDE.md](../../CLAUDE.md)

## Rule

Every source file **MUST** include a copyright and license header as the first comment block. This project is licensed under [Apache License 2.0](../../LICENSE).

## Templates

### PHP

Place the header after `<?php` and `declare(strict_types=1);`, before any `namespace` declaration:

```php
<?php

declare(strict_types=1);

/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Service;
```

This header is **enforced automatically** by PHP-CS-Fixer via the `header_comment` rule. Running `make cs-fix` will add or correct it.

### TypeScript / JavaScript

Place the header at the very top of the file:

```typescript
/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React from 'react';
```

### Twig Templates

Optional but recommended for non-trivial templates:

```twig
{#
 # This file is part of the Expanded Decks project.
 #
 # (c) Expanded Decks contributors
 #
 # For the full copyright and license information, please view the LICENSE
 # file that was distributed with this source code.
 #}

{% extends 'base.html.twig' %}
```

### YAML / Configuration

No header required for configuration files (`config/*.yaml`, `docker-compose.yml`, etc.).

## Automation

- **PHP**: PHP-CS-Fixer adds/corrects the header automatically (`make cs-fix`)
- **JS/TS**: ESLint does not enforce this by default — add manually when creating new files
- **Twig**: Manual — add when creating non-trivial templates
