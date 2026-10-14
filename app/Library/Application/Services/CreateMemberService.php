<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{IdentifierGenerator, MemberRepository};
use App\Library\Domain\Member;
final readonly class CreateMemberService
{
    public function __construct(private MemberRepository $members, private IdentifierGenerator $ids) {}
    public function execute(string $fullName, string $document): Member
    {
        $member = new Member($this->ids->next(), $fullName, $document);
        $this->members->addMember($member);
        return $member;
    }
}
