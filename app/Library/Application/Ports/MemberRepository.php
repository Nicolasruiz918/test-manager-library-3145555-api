<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
use App\Library\Domain\Member;
use App\Library\Application\{Page, Pagination};
interface MemberRepository
{
    public function findMember(string $id): ?Member;
    /** Coordinate this member until the enclosing transaction finishes. */
    public function lockMember(string $id): ?Member;
    public function addMember(Member $member): void;
    public function searchMembers(string $query, Pagination $pagination): Page;
}
