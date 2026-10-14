<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{Clock, LoanQueries};
use App\Library\Application\{Page, Pagination};
final readonly class OverdueService
{
    public function __construct(private LoanQueries $queries, private Clock $clock) {}
    public function execute(Pagination $pagination): Page { return $this->queries->overdue($pagination, $this->clock->now()); }
}
