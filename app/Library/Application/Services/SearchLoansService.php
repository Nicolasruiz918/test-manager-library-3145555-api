<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{Clock, LoanQueries};
use App\Library\Application\{LoanFilter, Page, Pagination};
final readonly class SearchLoansService
{
    public function __construct(private LoanQueries $queries, private Clock $clock) {}
    public function execute(LoanFilter $filter, Pagination $pagination): Page { return $this->queries->searchLoans($filter, $pagination, $this->clock->now()); }
}
