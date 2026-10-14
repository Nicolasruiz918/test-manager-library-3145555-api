<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{Clock, LoanRepository, MemberRepository};
use App\Library\Domain\DomainError;
final readonly class GetMemberService
{
    public function __construct(private MemberRepository $members, private LoanRepository $loans, private Clock $clock) {}
    public function execute(string $id): array
    {
        $member = $this->members->findMember($id) ?? throw new DomainError('NOT_FOUND', 'No se encontró el socio.');
        return ['id' => $member->id, 'full_name' => $member->fullName, 'document_number' => $member->documentNumber, 'active_loans' => $this->loans->outstanding($id, $this->clock->now())['count']];
    }
}
