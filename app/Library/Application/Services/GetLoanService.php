<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\LoanRepository;
use App\Library\Domain\{DomainError, Loan};
final readonly class GetLoanService
{
    public function __construct(private LoanRepository $loans) {}
    public function execute(string $id): Loan { return $this->loans->findLoan($id) ?? throw new DomainError('NOT_FOUND', 'No se encontró el préstamo.'); }
}
