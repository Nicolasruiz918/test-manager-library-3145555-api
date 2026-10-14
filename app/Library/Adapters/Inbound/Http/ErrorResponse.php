<?php
declare(strict_types=1);
namespace App\Library\Adapters\Inbound\Http;
use App\Library\Domain\DomainError;
use App\Library\Adapters\Outbound\Persistence\BusyOperation;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
final class ErrorResponse
{
    public static function render(Throwable $error)
    {
        if ($error instanceof DomainError) {
            $status = match ($error->errorCode) {
                'INVALID_CREDENTIALS' => 401, 'FORBIDDEN_ROLE' => 403, 'NOT_FOUND' => 404,
                'USERNAME_TAKEN', 'ISBN_TAKEN', 'DOCUMENT_TAKEN', 'BOOK_HAS_OPEN_LOANS', 'BOOK_NOT_AVAILABLE', 'MEMBER_HAS_OVERDUE', 'ITEM_ALREADY_RETURNED' => 409,
                default => 422,
            };
            return response()->json(['code' => $error->errorCode, 'message' => $error->getMessage(), 'details' => $error->details], $status);
        }
        if ($error instanceof ValidationException) {
            $details = array_map(static fn ($field) => ['field' => $field, 'issue' => 'El valor no cumple las reglas del campo.'], array_keys($error->errors()));
            return response()->json(['code' => 'VALIDATION_ERROR', 'message' => 'Revisa los datos de la solicitud.', 'details' => $details], 422);
        }
        if ($error instanceof BusyOperation) {
            return response()->json(['code' => 'SERVICE_UNAVAILABLE', 'message' => 'La operación está ocupada. Inténtalo de nuevo.', 'details' => []], 503, ['Retry-After' => '1']);
        }
        if ($error instanceof HttpExceptionInterface) {
            $status = $error->getStatusCode();
            return response()->json(['code' => $status === 404 ? 'NOT_FOUND' : 'HTTP_ERROR', 'message' => $status === 404 ? 'No se encontró el recurso.' : 'La solicitud no se puede procesar.', 'details' => []], $status, $error->getHeaders());
        }
        return response()->json(['code' => 'INTERNAL_ERROR', 'message' => 'No se pudo completar la operación.', 'details' => []], 500);
    }
}
