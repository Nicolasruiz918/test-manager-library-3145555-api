<?php
declare(strict_types=1);
namespace App\Library\Adapters\Inbound\Http;
use App\Library\Application\Ports\{CategoryCatalog, Clock};
use App\Library\Application\Services;
use App\Library\Application\{LoanFilter, Pagination};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
final readonly class LibraryController
{
    public function __construct(private Clock $clock) {}
    private function id(string $value): string { Validator::make(['id' => $value], ['id' => ['required', 'uuid:4']])->validate(); return $value; }
    private function pagination(Request $request): Pagination
    {
        $data = $request->validate(['page' => 'sometimes|required|integer|min:1', 'size' => 'sometimes|required|integer|min:1|max:50']);
        return new Pagination((int) ($data['page'] ?? 1), (int) ($data['size'] ?? 20));
    }
    public function login(Request $request, Services\LoginService $service)
    {
        $data = $request->validate(['username' => 'required|string', 'password' => 'required|string']);
        return response()->json($service->execute($data['username'], $data['password']));
    }
    public function createUser(Request $request, Services\CreateLibrarianService $service)
    {
        $data = $request->validate(['username' => 'required|string', 'password' => 'required|string', 'role' => 'required|string']);
        return response()->json($service->execute($data['username'], $data['password'], $data['role'])->jsonSerialize(), 201);
    }
    public function categories(CategoryCatalog $catalog) { return response()->json(['items' => $catalog->categories()]); }
    public function books(Request $request, Services\SearchBooksService $service)
    {
        $data = $request->validate(['q' => 'sometimes|string', 'category_id' => 'sometimes|required|uuid:4']);
        return response()->json(Presenter::page($service->execute($data['q'] ?? '', $data['category_id'] ?? null, $this->pagination($request)), Presenter::book(...)));
    }
    public function book(string $id, Services\GetBookService $service) { return response()->json(Presenter::book($service->execute($this->id($id)))); }
    private function bookInput(Request $request, bool $create): array
    {
        $rules = ['title' => 'required|string', 'author' => 'present|string', 'category_id' => 'required|uuid:4', 'total_copies' => 'required|integer|min:1'];
        if ($create) { $rules['isbn'] = 'required|string'; }
        return $request->validate($rules);
    }
    public function createBook(Request $request, Services\CreateBookService $service)
    {
        $data = $this->bookInput($request, true);
        return response()->json(Presenter::book($service->execute($data['title'], $data['author'], $data['isbn'], $data['category_id'], (int) $data['total_copies'])), 201);
    }
    public function updateBook(Request $request, string $id, Services\UpdateBookService $service)
    {
        $data = $this->bookInput($request, false);
        return response()->json(Presenter::book($service->execute($this->id($id), $data['title'], $data['author'], $data['category_id'], (int) $data['total_copies'])));
    }
    public function deleteBook(string $id, Services\DeleteBookService $service) { $service->execute($this->id($id)); return response()->noContent(); }
    public function putCover(Request $request, string $id, Services\PutCoverService $service)
    {
        $request->validate(['file' => 'required|file']);
        return response()->json(Presenter::book($service->execute($this->id($id), $request->file('file')->getRealPath())));
    }
    public function deleteCover(string $id, Services\DeleteCoverService $service) { $service->execute($this->id($id)); return response()->noContent(); }
    public function cover(string $id, Services\GetCoverService $service)
    {
        $file = $service->execute($this->id($id));
        return response($file['bytes'], 200, ['Content-Type' => $file['mime'], 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
    public function members(Request $request, Services\SearchMembersService $service)
    {
        $data = $request->validate(['q' => 'sometimes|string']);
        return response()->json(Presenter::page($service->execute($data['q'] ?? '', $this->pagination($request)), Presenter::member(...)));
    }
    public function member(string $id, Services\GetMemberService $service) { return response()->json($service->execute($this->id($id))); }
    public function createMember(Request $request, Services\CreateMemberService $service)
    {
        $data = $request->validate(['full_name' => 'required|string', 'document_number' => 'required|string']);
        return response()->json(Presenter::member($service->execute($data['full_name'], $data['document_number'])), 201);
    }
    public function createLoan(Request $request, Services\LendBooksService $service)
    {
        $data = $request->validate(['member_id' => 'required|uuid:4', 'items' => 'required|array|min:1|max:3', 'items.*.book_id' => 'required|uuid:4']);
        $loan = $service->execute($data['member_id'], array_column($data['items'], 'book_id'), $request->attributes->get('library_user')->id);
        return response()->json(Presenter::loan($loan, $this->clock->now()), 201);
    }
    public function loans(Request $request, Services\SearchLoansService $service)
    {
        $data = $request->validate(['from' => 'sometimes|required|string', 'to' => 'sometimes|required|string', 'status' => 'sometimes|required|string', 'member_id' => 'sometimes|required|uuid:4']);
        $filter = new LoanFilter($data['from'] ?? null, $data['to'] ?? null, $data['status'] ?? null, $data['member_id'] ?? null);
        $now = $this->clock->now();
        return response()->json(Presenter::page($service->execute($filter, $this->pagination($request)), static fn ($loan) => Presenter::loan($loan, $now)));
    }
    public function loan(string $id, Services\GetLoanService $service) { return response()->json(Presenter::loan($service->execute($this->id($id)), $this->clock->now())); }
    public function returnItem(string $loanId, string $itemId, Services\ReturnItemService $service)
    {
        return response()->json(Presenter::item($service->execute($this->id($loanId), $this->id($itemId))));
    }
    public function overdue(Request $request, Services\OverdueService $service)
    {
        return response()->json(Presenter::page($service->execute($this->pagination($request)), static fn ($row) => array_replace($row, ['due_at' => Presenter::date($row['due_at'])])));
    }
    public function report(Request $request, Services\MostBorrowedService $service)
    {
        $data = $request->validate(['from' => 'required|string', 'to' => 'required|string']);
        return response()->json($service->execute($data['from'], $data['to']));
    }
}
