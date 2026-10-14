<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\MemberRepository;
use App\Library\Application\{Page, Pagination};
final readonly class SearchMembersService
{
    public function __construct(private MemberRepository $members) {}
    public function execute(string $query, Pagination $pagination): Page { return $this->members->searchMembers($query, $pagination); }
}
