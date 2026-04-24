<?php

namespace App\Contracts;

interface UtilityScraperClientInterface
{
    /**
     * @return array<string, mixed>
     */
    public function scrapeEmbasa(): array;

    /**
     * @return array<string, mixed>
     */
    public function scrapeCoelba(): array;
}
