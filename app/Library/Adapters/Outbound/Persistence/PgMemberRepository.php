<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Persistence;
use App\Library\Application\Ports\MemberRepository;
use App\Library\Application\{Page, Pagination};
use App\Library\Domain\Member;
final class PgMemberRepository extends PgGateway implements MemberRepository
{
    private function hydrate(?object $row): ?Member { return $row ? new Member($row->id, $row->full_name, $row->document_number) : null; }
    public function findMember(string $id): ?Member { return $this->hydrate($this->db->table('library.member')->where('id', $id)->first()); }
    public function lockMember(string $id): ?Member
    {
        if ($this->db->transactionLevel() === 0) { throw new \LogicException('Member coordination requires a transaction.'); }
        return $this->hydrate($this->db->table('library.member')->where('id', $id)->lockForUpdate()->first());
    }
    public function addMember(Member $member): void
    {
        $this->insertUnique('library.member', ['id' => $member->id, 'full_name' => $member->fullName, 'document_number' => $member->documentNumber], 'member_document_number_key', 'DOCUMENT_TAKEN', 'El documento ya está registrado.');
    }
    public function searchMembers(string $query, Pagination $pagination): Page
    {
        $sql = $this->db->table('library.member');
        if ($query !== '') { $pattern = self::pattern($query); $sql->where(fn ($q) => $q->where('full_name', 'ilike', $pattern)->orWhere('document_number', 'ilike', $pattern)); }
        $total = (clone $sql)->count();
        $rows = $sql->orderBy('full_name')->orderBy('id')->offset($pagination->offset())->limit($pagination->size)->get();
        return new Page($rows->map(fn ($row) => $this->hydrate($row))->all(), $total, $pagination);
    }
}
