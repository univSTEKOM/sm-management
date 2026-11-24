<?php

namespace App\Support\Progress;

/** Transitions detected by ProgressRecalculator; used to dispatch events after commit. */
final class ProgressResult
{
    public function __construct(
        public readonly bool $menjadiMitra = false,
        public readonly bool $rangkaianSelesai = false,
        public readonly bool $sesiTerakhirDibatalkan = false,
    ) {}
}
