<?php
declare(strict_types=1);
namespace App\Library\Adapters\Inbound\Http;
use App\Library\Domain\DomainError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
final class JsonBody
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('POST') || $request->isMethod('PUT')) {
            $isCover = $request->isMethod('PUT') && str_ends_with($request->path(), '/cover');
            if (!$isCover && $request->getContent() !== '') {
                if (!$request->isJson()) { throw new HttpException(415); }
                try { $body = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR); }
                catch (\JsonException) { throw new DomainError('VALIDATION_ERROR', 'El cuerpo debe contener JSON válido.'); }
                if (!$body instanceof \stdClass && $body !== []) { throw new DomainError('VALIDATION_ERROR', 'El cuerpo JSON debe ser un objeto.'); }
            }
        }
        return $next($request);
    }
}
