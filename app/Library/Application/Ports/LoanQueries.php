<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
use App\Library\Application\{LoanFilter, Page, Pagination};
use DateTimeImmutable;
interface LoanQueries
{
    public function searchLoans(LoanFilter $filter, Pagination $pagination, DateTimeImmutable $now): Page;
    public function overdue(Pagination $pagination, DateTimeImmutable $now): Page;
}
