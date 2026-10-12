<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImovelRequest;
use App\Http\Requests\PublicarImovelRequest;
use App\Http\Resources\ImovelResource;
use App\Models\Imovel;
use App\Models\User;
use App\Services\ImovelService;
use App\Support\Sessao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ImovelController extends Controller
{
    public function __construct(private readonly ImovelService $imoveis) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $utilizador = $this->gestor($request);
        $consulta = Imovel::query()->with('imagens');

        if ($utilizador === null) {
            $consulta->where('publicado', true);
        } elseif ($request->has('publicado')) {
            $consulta->where('publicado', $request->boolean('publicado'));
        }

        $this->filtrar($consulta, $request);

        if ($utilizador === null) {
            $consulta->orderBy('ordem')->orderByDesc('created_at');
        } else {
            $this->ordenar($consulta, $request, 'created_at', 'desc');
        }

        if ($request->boolean('paginar')) {
            return ImovelResource::collection(
                $consulta->paginate(min($request->integer('per_page', 15), 100))->withQueryString()
            );
        }

        return ImovelResource::collection($consulta->get());
    }

    public function destaque(Request $request): AnonymousResourceCollection
    {
        $consulta = Imovel::query()->with('imagens')->where('publicado', true)->where('destaque', true);
        $this->filtrar($consulta, $request);

        return ImovelResource::collection($consulta->orderBy('ordem')->orderByDesc('created_at')->get());
    }

    public function show(Request $request, Imovel $imovel): ImovelResource
    {
        $utilizador = $this->gestor($request);
        if ($utilizador === null && ! $imovel->publicado) {
            abort(404, 'O registo já não existe.');
        }

        return new ImovelResource($imovel->load('imagens'));
    }

    /**
     * Cria o imóvel. 201 com o imóvel gravado (já com as traduções EN/FR/ES).
     * Erros de validação saem como 422 { message, errors: { campo: [msg] } }.
     */
    public function store(ImovelRequest $request): JsonResponse
    {
        try {
            $imovel = $this->imoveis->criar($request->validated());

            return (new ImovelResource($imovel))->response()->setStatusCode(201);
        } catch (Throwable $e) {
            return $this->falha($e, 'Não foi possível registar o imóvel. Nada foi gravado; tente outra vez.');
        }
    }

    public function update(ImovelRequest $request, Imovel $imovel): JsonResponse
    {
        try {
            return (new ImovelResource($this->imoveis->atualizar($imovel, $request->validated())))->response();
        } catch (Throwable $e) {
            return $this->falha($e, 'Não foi possível guardar as alterações. O imóvel ficou como estava; tente outra vez.');
        }
    }

    public function publicar(PublicarImovelRequest $request, Imovel $imovel): JsonResponse
    {
        try {
            return (new ImovelResource($this->imoveis->publicar($imovel, $request->boolean('publicado'))))->response();
        } catch (Throwable $e) {
            return $this->falha($e, 'Não foi possível mudar a publicação. Tente outra vez.');
        }
    }

    public function destroy(Imovel $imovel): Response
    {
        try {
            $imovel->delete();

            return response()->noContent();
        } catch (Throwable $e) {
            return $this->falha($e, 'Não foi possível excluir o imóvel. Tente outra vez.');
        }
    }

    /**
     * Resposta para erros nas gravações.
     *
     * Validação (422) e erros HTTP já tratados (403, 404...) seguem para o
     * handler global (bootstrap/app.php), que os devolve no formato do painel.
     * O resto é um erro inesperado: fica no log com o detalhe técnico e o
     * utilizador recebe 500 com uma frase clara, sem expor o erro interno.
     */
    private function falha(Throwable $e, string $mensagem): JsonResponse
    {
        if ($e instanceof ValidationException || $e instanceof HttpExceptionInterface) {
            throw $e;
        }

        Log::error('Erro inesperado ao gravar imóvel.', [
            'mensagem' => $e->getMessage(),
            'ficheiro' => $e->getFile().':'.$e->getLine(),
        ]);

        return response()->json(['message' => $mensagem], 500);
    }

    private function gestor(Request $request): ?User
    {
        $utilizador = Sessao::opcional($request);
        if ($utilizador !== null && ! $utilizador->pode('imoveis')) {
            abort(403, 'Não tem permissão para esta ação.');
        }

        return $utilizador;
    }

    private function filtrar($consulta, Request $request): void
    {
        if ($q = trim($request->string('q')->toString())) {
            $consulta->where(function ($w) use ($q) {
                $w->where('titulo', 'like', "%{$q}%")
                    ->orWhere('referencia', 'like', "%{$q}%")
                    ->orWhere('zona', 'like', "%{$q}%")
                    ->orWhere('id', 'like', "%{$q}%");
            });
        }

        foreach (['estado', 'tipo', 'finalidade', 'zona'] as $campo) {
            if ($request->filled($campo)) {
                $consulta->where($campo, $request->string($campo)->toString());
            }
        }

        if ($request->filled('preco_min')) {
            $consulta->where('preco', '>=', $request->integer('preco_min'));
        }
        if ($request->filled('preco_max')) {
            $consulta->where('preco', '<=', $request->integer('preco_max'));
        }
    }

    private function ordenar($consulta, Request $request, string $defeito, string $direcao): void
    {
        $mapa = [
            'preco' => 'preco',
            'titulo' => 'titulo',
            'criado' => 'created_at',
            'ordem' => 'ordem',
        ];
        $pedido = $request->string('ordenar')->toString();
        $desc = str_starts_with($pedido, '-');
        $chave = ltrim($pedido, '-');

        if (! isset($mapa[$chave])) {
            $consulta->orderBy($defeito, $direcao);

            return;
        }

        $consulta->orderBy($mapa[$chave], $desc ? 'desc' : 'asc');
    }
}
