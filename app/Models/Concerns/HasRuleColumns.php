<?php

namespace App\Models\Concerns;

use App\Support\FieldRules;

// Afegeix als models de camp les columnes de regla (App\Support\FieldRules::COLUMNS) al fillable i als casts.
trait HasRuleColumns
{
    public function initializeHasRuleColumns(): void
    {
        $this->mergeFillable(FieldRules::COLUMNS);
        $this->mergeCasts(FieldRules::CASTS);
    }
}
