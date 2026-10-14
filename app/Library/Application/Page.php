<?php
declare(strict_types=1);
namespace App\Library\Application;
final readonly class Page
{
    public function __construct(public array $items, public int $total, public Pagination $pagination) {}
}
